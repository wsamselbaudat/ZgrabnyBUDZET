<?php
// export.php
// POST: ids[] (integers), format = csv|xlsx
// Simple exporter: CSV with UTF-8 BOM. For 'xlsx' we return same CSV data but with .xlsx filename

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$ids = isset($_POST['ids']) ? $_POST['ids'] : [];
$format = isset($_POST['format']) ? strtolower((string)$_POST['format']) : 'csv';

if (!is_array($ids) || count($ids) === 0) {
    http_response_code(400);
    echo 'No ids provided';
    exit;
}

// sanitize and cast to int, remove duplicates
$ids = array_values(array_unique(array_map('intval', $ids)));
// safety limit
if (count($ids) > 2000) {
    http_response_code(400);
    echo 'Too many ids';
    exit;
}

// Connect DB
$mysqli = require __DIR__ . '/db.php';
if (!($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo 'DB connection error';
    exit;
}

// Prepare query: select all columns so export contains full record (all DB fields)
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT * FROM WPISY WHERE ID IN ($placeholders) ORDER BY ID DESC";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo 'DB prepare error: ' . $mysqli->error;
    exit;
}

// bind params (all ints)
$types = str_repeat('i', count($ids));
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    echo 'DB query error';
    exit;
}

// Build rows: fetch associative arrays and use DB column order as CSV header
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

if (empty($rows)) {
    // no data found for ids
    http_response_code(404);
    echo 'No records found for provided ids';
    exit;
}

// Header uses keys from first row (DB column order)
$header = array_keys($rows[0]);

// Prepare filename
$now = date('Ymd_His');
$filename = "wnioski_{$now}";

if ($format === 'xlsx') {
    $outName = $filename . '.xlsx';
    $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
} else {
    $outName = $filename . '.csv';
    $contentType = 'text/csv; charset=UTF-8';
}

// Stream CSV
// We'll generate UTF-8 CSV with BOM so Excel opens correctly
header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $outName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$fh = fopen('php://output', 'w');
if ($fh === false) {
    http_response_code(500);
    echo 'Unable to open output';
    exit;
}

// BOM
fwrite($fh, "\xEF\xBB\xBF");

// write header
fputcsv($fh, $header);

foreach ($rows as $r) {
    // ensure strings
    $line = array_map(function($v){ return is_null($v) ? '' : (string)$v; }, $r);
    fputcsv($fh, $line);
}

fclose($fh);
exit;
?>