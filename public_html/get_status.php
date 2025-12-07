<?php
header('Content-Type: application/json');
$mysqli = require 'db.php';

$ids_param = isset($_GET['ids']) ? $_GET['ids'] : (isset($_GET['id']) ? $_GET['id'] : '');

if (!$ids_param) {
    echo json_encode(['success' => false, 'error' => 'Missing ID(s)']);
    exit;
}

// Parse IDs (handle both single ID and comma-separated list)
if (strpos($ids_param, ',') !== false) {
    $ids = array_map('intval', explode(',', $ids_param));
    $ids = array_filter($ids, function($v) { return $v > 0; });
} else {
    $ids = [intval($ids_param)];
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No valid IDs']);
    exit;
}

// Mapowanie statusów na etykiety i CSS klasy
$statusMap = [
    'Szkic' => ['label' => 'Szkic', 'class' => 'status-szkic'],
    'Do zatwierdzenia' => ['label' => 'Do zatwierdzenia', 'class' => 'status-do-zatwierdzenia'],
    'Zatwierdzone' => ['label' => 'Zatwierdzone', 'class' => 'status-zatwierdzone'],
    'Odrzucone' => ['label' => 'Odrzucone', 'class' => 'status-odrzucone'],
    'Do poprawy' => ['label' => 'Do poprawy', 'class' => 'status-do-poprawy'],
    'Brak' => ['label' => 'Brak', 'class' => 'status-brak']
];

$results = [];

// Get status for each ID
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT ID, Status FROM WPISY WHERE ID IN ($placeholders)";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare error: ' . $mysqli->error]);
    exit;
}

$types = str_repeat('i', count($ids));
$stmt->bind_param($types, ...$ids);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Execute error: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $status = $row['Status'] ?: 'Brak';
    $mapped = isset($statusMap[$status]) ? $statusMap[$status] : $statusMap['Brak'];
    
    $results[] = [
        'id' => (int)$row['ID'],
        'status' => $status,
        'label' => $mapped['label'],
        'class' => $mapped['class']
    ];
}

echo json_encode([
    'success' => true,
    'results' => $results
]);
?>
