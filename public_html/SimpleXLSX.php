<?php
// Włączenie buforowania wyjścia, aby można było kontrolować nagłówki JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

// Ustawienie limitów dla dużego importu
set_time_limit(300); // 5 minut
ini_set('memory_limit', '512M'); // 512 MB

// --------------------------- WALIDACJA SESJI (NAPRAWA BŁĘDU session_start) ---------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Zwraca błąd jako czysty JSON i przerywa skrypt.
 */
function return_json_error(string $msg, int $http_code = 500) {
    // Wyczyść bufor wyjścia z potencjalnych śmieci
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8', true, $http_code);
    die(json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE));
}

// --------------------------- OBSŁUGA BŁĘDÓW PHP (KRYTYCZNE DLA JSON) ---------------------------

// Przechwytywanie Warnings/Notices
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    return_json_error("Wewnętrzny błąd PHP (Warning/Notice): $errstr w pliku $errfile linia $errline");
}, E_ALL);

// Przechwytywanie Fatal Errors/Exceptions
set_exception_handler(function($e) {
    return_json_error("Wystąpił wyjątek krytyczny: " . $e->getMessage());
});

// --------------------------- WALIDACJA WEJŚCIOWA I ODCZYT PLIKU ---------------------------

if (!isset($_SESSION['login'])) {
    return_json_error('Brak autoryzacji');
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    return_json_error('Błąd uploadu: Nie przesłano pliku lub błąd serwera.');
}

$format = $_POST['format'] ?? '';
$file = $_FILES['file']['tmp_name'];
$data = [];

// Nagłówek to pierwszy wiersz, pomijamy tylko jeden wiersz
$header_rows_to_skip = 1; 

// --------------------------- CZYTANIE PLIKU CSV ---------------------------
if ($format === 'csv') {
    $content = @file_get_contents($file);
    if (!$content) {
        return_json_error('Nie można odczytać pliku CSV.');
    }
    
    $content = str_replace("\xEF\xBB\xBF", '', $content);
    $lines = explode("\n", str_replace("\r\n", "\n", $content));
    
    $firstLine = $lines[0] ?? '';
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    
    // Zaczynamy od wiersza za nagłówkami (wiersz $i = $header_rows_to_skip)
    for ($i = $header_rows_to_skip; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        $row = str_getcsv($line, $delimiter);
        
        // **NIE USUWANIE KOLUMN**
        
        if (!empty(array_filter($row))) {
            $data[] = $row;
        }
    }
    
// --------------------------- CZYTANIE PLIKU XLSX ---------------------------
} elseif ($format === 'xlsx') {
    if (!file_exists('SimpleXLSX.php')) {
        return_json_error('Brak biblioteki SimpleXLSX.php. Zainstaluj ją.');
    }
    
    require_once 'SimpleXLSX.php';
    
    if (!class_exists('SimpleXLSX')) {
        return_json_error('Nie można załadować SimpleXLSX.');
    }
    
    $xlsx = SimpleXLSX::parse($file);
    if (!$xlsx) {
        return_json_error('Nie można otworzyć Excel - sprawdź, czy plik jest prawidłowy.');
    }
    
    $rows = $xlsx->rows();
    if (empty($rows)) {
        return_json_error('Plik Excel jest pusty.');
    }
    
    $current_row_index = 0;
    foreach ($rows as $row) {
        // Pomijamy wiersze nagłówka
        if ($current_row_index < $header_rows_to_skip) { 
            $current_row_index++; 
            continue; 
        }
        $current_row_index++;
        
        // **NIE USUWANIE KOLUMN**

        if (!empty(array_filter($row))) {
            $data[] = $row;
        }
    }
    
} else {
    return_json_error('Nieobsługiwany format pliku.');
}

if (empty($data)) {
    return_json_error('Brak danych do importu po odfiltrowaniu nagłówków.');
}

// --------------------------- KONFIGURACJA BAZY DANYCH ---------------------------

$mysqli = @require 'db.php';
if (!($mysqli instanceof mysqli)) {
    return_json_error('Błąd połączenia z bazą. Sprawdź plik db.php.');
}

// 1. Pobranie nazw i typów kolumn Z BAZY (Musi idealnie pasować do pliku!)
$result = @$mysqli->query("SHOW COLUMNS FROM WPISY");
if (!$result) {
    $db_error = $mysqli->error;
    $mysqli->close();
    return_json_error('Błąd struktury: Błąd podczas pobierania kolumn z tabeli WPISY: ' . $db_error);
}

$columns = [];
$columnTypes = [];
// POBIERAMY WSZYSTKIE KOLUMNY W KOLEJNOŚCI Z BAZY
while ($col = $result->fetch_assoc()) { 
    $columns[] = $col['Field'];
    $columnTypes[$col['Field']] = $col['Type'];
}

if (empty($columns)) {
    $mysqli->close();
    return_json_error('Tabela WPISY jest pusta.');
}

// 2. Przygotowanie zapytania SQL
$columnNames = '`' . implode('`,`', $columns) . '`';
$placeholders = implode(',', array_fill(0, count($columns), '?'));
$sql = "INSERT INTO WPISY ($columnNames) VALUES ($placeholders)";

$stmt = @$mysqli->prepare($sql);
if (!$stmt) {
    $db_error = $mysqli->error;
    $mysqli->close();
    return_json_error('Błąd przygotowania SQL: ' . $db_error);
}

// --------------------------- IMPORT DANYCH ---------------------------

$imported = 0;
$failed_rows = [];
$row_counter = 0;

foreach ($data as $row) {
    $row_counter++;
    
    // Upewnienie się, że wiersz ma poprawną liczbę kolumn
    if (count($row) !== count($columns)) {
        $failed_rows[] = "Wiersz danych nr " . ($row_counter + $header_rows_to_skip) . " (Błąd Wiersza): Liczba kolumn w pliku (" . count($row) . ") nie zgadza się z tabelą bazy (" . count($columns) . ").";
        continue;
    }
    
    $types = '';
    $params = [];
    
    // Walidacja i konwersja wartości
    for ($i = 0; $i < count($row); $i++) {
        $colName = $columns[$i];
        $colType = $columnTypes[$colName] ?? '';
        $value = $row[$i];

        // 1. Czyszczenie pustych wartości
        if ($value === '' || $value === null) {
            // Wstawienie domyślnego statusu, jeśli kolumna jest np. 'Status'
            if ($colName === 'Status') {
                $value = 'szkic';
            } elseif (preg_match('/^(int|decimal|float|double|numeric)/i', $colType)) {
                // Ustawienie NULL dla pustych wartości w kolumnach numerycznych
                $value = null;
            }
        } 
        // 2. Czyszczenie wartości nienumerycznych w kolumnach numerycznych
        elseif (preg_match('/^(int|decimal|float|double|numeric)/i', $colType)) {
            // Obsługa polskiego separatora dziesiętnego (przecinka)
            $value = str_replace(',', '.', $value);
            
            if (!is_numeric($value)) {
                $value = null; // Ustaw NULL, jeśli jest tekst
            }
        }

        // Wiązanie jako string, aby móc obsłużyć zarówno stringi, jak i NULL
        $types .= 's'; 
        $params[] = $value;
    }
    
    // Dynamiczne wiązanie parametrów (wymaga referencji)
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    
    if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
        $failed_rows[] = "Wiersz danych nr " . ($row_counter + $header_rows_to_skip) . " (Błąd bind_param): Nieprawidłowe wiązanie typów/danych.";
        continue;
    }
    
    if (@$stmt->execute()) {
        $imported++;
    } else {
        $failed_rows[] = "Wiersz danych nr " . ($row_counter + $header_rows_to_skip) . " (Błąd execute): " . $stmt->error;
    }
}

$stmt->close();
$mysqli->close();

// --------------------------- WYNIK KOŃCOWY ---------------------------

if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => true, 
    'count' => $imported,
    'total_rows_read' => $row_counter
];

if (!empty($failed_rows)) {
    $response['success'] = ($imported > 0);
    $response['warning'] = 'Import zakończony, ale wystąpiły błędy w niektórych wierszach. Sprawdź szczegóły.';
    $response['failed_rows_count'] = count($failed_rows);
    $response['failed_rows_sample'] = array_slice($failed_rows, 0, 10);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);