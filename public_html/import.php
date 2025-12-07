<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Ignorujemy ostrzeżenie o podwójnym session_start
    if (strpos($errstr, 'session_start(): Ignoring session_start()') !== false) {
        return true;
    }

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => "PHP Error: $errstr"], JSON_UNESCAPED_UNICODE));
});

set_exception_handler(function($e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => "Exception: " . $e->getMessage()], JSON_UNESCAPED_UNICODE));
});

$hasSession = (session_status() === PHP_SESSION_ACTIVE);
if ($hasSession && !isset($_SESSION['login'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Brak autoryzacji'], JSON_UNESCAPED_UNICODE));
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Błąd uploadu'], JSON_UNESCAPED_UNICODE));
}

$format = $_POST['format'] ?? '';
$file = $_FILES['file']['tmp_name'];
$data = [];
$removeLeadingId = false;

function normalizeRowCells(array $row): array {
    $normalized = [];
    foreach ($row as $cell) {
        $value = str_replace(["\r", "\n", "\t"], ' ', (string)$cell);
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        $normalized[] = $value;
    }
    return $normalized;
}

function isNumberingRow(array $row): bool {
    $expected = 1;
    $hasNumbers = false;
    foreach ($row as $value) {
        $value = trim((string)$value);
        if ($value === '') {
            continue;
        }
        if (!ctype_digit($value) || (int)$value !== $expected) {
            return false;
        }
        $hasNumbers = true;
        $expected++;
    }
    return $hasNumbers;
}

function firstCellLooksLikeId(array $row): bool {
    if (empty($row)) {
        return false;
    }
    $value = trim((string)$row[0]);
    return $value !== '' && preg_match('/^\d{1,12}$/', $value);
}

if ($format === 'csv') {
    $content = @file_get_contents($file);
    if (!$content) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'error' => 'Nie można odczytać pliku'], JSON_UNESCAPED_UNICODE));
    }
    
    $content = str_replace("\xEF\xBB\xBF", '', $content);
    $lines = explode("\n", str_replace("\r\n", "\n", $content));
    
    $firstLine = $lines[0] ?? '';
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    $headerRowsToSkip = 1;
    if (isset($lines[1])) {
        $candidate = normalizeRowCells(str_getcsv($lines[1], $delimiter));
        if (isNumberingRow($candidate)) {
            $headerRowsToSkip = 2;
        }
    }

    $totalLines = count($lines);
    for ($i = $headerRowsToSkip; $i < $totalLines; $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        $row = normalizeRowCells(str_getcsv($line, $delimiter));
        if (!empty(array_filter($row))) {
            $data[] = $row;
        }
    }

    if (!empty($data) && firstCellLooksLikeId($data[0])) {
        $removeLeadingId = true;
    }
    
} elseif ($format === 'xlsx') {
    if (!file_exists('SimpleXLSX.php')) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'error' => 'Brak SimpleXLSX.php'], JSON_UNESCAPED_UNICODE));
    }
    
    require_once 'SimpleXLSX.php';
    
    if (!class_exists('SimpleXLSX')) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'error' => 'Nie można załadować SimpleXLSX'], JSON_UNESCAPED_UNICODE));
    }
    
    $xlsx = SimpleXLSX::parse($file);
    if (!$xlsx) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'error' => 'Nie można otworzyć Excel - sprawdź czy plik jest prawidłowy'], JSON_UNESCAPED_UNICODE));
    }
    
    $rows = $xlsx->rows();
    if (empty($rows)) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'error' => 'Plik Excel jest pusty'], JSON_UNESCAPED_UNICODE));
    }
    
    $headerRowsToSkip = 0;
    if (!empty($rows)) {
        $headerRowsToSkip = 1;
        $secondRow = $rows[1] ?? [];
        if (!empty($secondRow)) {
            $candidate = normalizeRowCells($secondRow);
            if (isNumberingRow($candidate)) {
                $headerRowsToSkip = 2;
            }
        }
    }

    foreach ($rows as $index => $row) {
        if ($index < $headerRowsToSkip) {
            continue;
        }

        $cleanRow = normalizeRowCells($row);
        if (!empty(array_filter($cleanRow))) {
            $data[] = $cleanRow;
        }
    }
} else {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Nieobsługiwany format'], JSON_UNESCAPED_UNICODE));
}

if (empty($data)) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Brak danych'], JSON_UNESCAPED_UNICODE));
}

$mysqli = @require 'db.php';
if (!($mysqli instanceof mysqli)) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Błąd bazy'], JSON_UNESCAPED_UNICODE));
}

$result = @$mysqli->query("SHOW COLUMNS FROM WPISY");
if (!$result) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Błąd struktury'], JSON_UNESCAPED_UNICODE));
}

$columns = [];
while ($col = $result->fetch_assoc()) {
    if ($col['Field'] !== 'ID') {  // Pomijamy ID - będzie auto-increment
        $columns[] = $col['Field'];
    }
}

$columnNames = '`' . implode('`,`', $columns) . '`';
$placeholders = implode(',', array_fill(0, count($columns), '?'));
$sql = "INSERT INTO WPISY ($columnNames) VALUES ($placeholders)";

$stmt = @$mysqli->prepare($sql);
if (!$stmt) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Błąd SQL'], JSON_UNESCAPED_UNICODE));
}

$imported = 0;

// Get column types from database
$result = @$mysqli->query("SHOW COLUMNS FROM WPISY");
$columnTypes = [];
while ($col = $result->fetch_assoc()) {
    $columnTypes[$col['Field']] = $col['Type'];
}

foreach ($data as $row) {
    if ($removeLeadingId) {
        array_shift($row);
    }
    
    $row = array_slice(array_pad($row, count($columns), ''), 0, count($columns));
    
    // Convert empty strings to NULL for numeric columns, set defaults for special columns
    for ($i = 0; $i < count($row); $i++) {
        $colName = $columns[$i];
        
        if ($row[$i] === '' || $row[$i] === null) {
            $colType = $columnTypes[$colName] ?? '';
            
            // Set default for Status column
            if ($colName === 'Status') {
                $row[$i] = 'szkic';
                continue;
            }
            
            // Check if column is numeric
            if (preg_match('/^(int|decimal|float|double|numeric)/i', $colType)) {
                $row[$i] = null;
            }
        } else {
            $colType = $columnTypes[$colName] ?? '';
            
            // For numeric columns, check if value is actually numeric
            if (preg_match('/^(int|decimal|float|double|numeric)/i', $colType)) {
                // If not numeric (like "planowane"), set to NULL
                if (!is_numeric($row[$i])) {
                    $row[$i] = null;
                }
            }
        }
    }
    
    // Build dynamic types string
    $types = '';
    $params = [];
    foreach ($row as $value) {
        if ($value === null) {
            $types .= 's'; // Still bind as string, but with NULL value
            $params[] = null;
        } else {
            $types .= 's';
            $params[] = $value;
        }
    }
    
    @$stmt->bind_param($types, ...$params);
    if (@$stmt->execute()) {
        $imported++;
    }
}

$stmt->close();
$mysqli->close();

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'count' => $imported], JSON_UNESCAPED_UNICODE);
