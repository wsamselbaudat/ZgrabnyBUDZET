<?php
session_start();
if (!isset($_SESSION['login'])) {
    http_response_code(401);
    exit;
}

$mysqli = require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['status'])) {
    http_response_code(400);
    exit;
}

$id = (int)$_POST['id'];
$statusRaw = trim($_POST['status']);

// Map form labels to actual ENUM values from database
$statusMap = [
    ''                 => '',
    'Szkic'            => 'Szkic',
    'Do zatwierdzenia' => 'Do zatwierdzenia',
    'Zatwierdzone'     => 'Zatwierdzone',
    'Odrzucone'        => 'Odrzucone',
    'Do poprawy'       => 'Do poprawy',
];

if (!array_key_exists($statusRaw, $statusMap)) {
    http_response_code(400);
    exit;
}

$status = $statusMap[$statusRaw];

// Check permission - user must be Admin or from same Komorka
if ($_SESSION['rola'] !== 'Admin') {
    $userKomorkaStmt = $mysqli->prepare('SELECT Nazwa FROM Komorki WHERE ID = ?');
    $userKomorkaStmt->bind_param('i', $_SESSION['dzial_id']);
    $userKomorkaStmt->execute();
    $userKomorkaResult = $userKomorkaStmt->get_result();
    $userKomorkaRow = $userKomorkaResult->fetch_assoc();
    $userKomorkaName = $userKomorkaRow ? $userKomorkaRow['Nazwa'] : '';
    
    // Get entry's Komorka
    $entryStmt = $mysqli->prepare('SELECT NazwaKomorki FROM WPISY WHERE ID = ?');
    $entryStmt->bind_param('i', $id);
    $entryStmt->execute();
    $entryResult = $entryStmt->get_result();
    $entryRow = $entryResult->fetch_assoc();
    
    if (!$entryRow || $entryRow['NazwaKomorki'] !== $userKomorkaName) {
        http_response_code(403);
        exit;
    }
}

// Update Status (NULL when empty)
if ($status === '') {
    $updateStmt = $mysqli->prepare('UPDATE WPISY SET Status = NULL WHERE ID = ?');
    if ($updateStmt) {
        $updateStmt->bind_param('i', $id);
    }
} else {
    $updateStmt = $mysqli->prepare('UPDATE WPISY SET Status = ? WHERE ID = ?');
    if ($updateStmt) {
        $updateStmt->bind_param('si', $status, $id);
    }
}

if ($updateStmt) {
    if ($updateStmt->execute()) {
        // Return JSON with updated status info
        header('Content-Type: application/json');
        $statusMap = [
            'Szkic' => 'Szkic',
            'Do zatwierdzenia' => 'Do zatwierdzenia',
            'Zatwierdzone' => 'Zatwierdzone',
            'Odrzucone' => 'Odrzucone',
            'Do poprawy' => 'Do poprawy'
        ];
        $statusClassMap = [
            'Szkic' => 'status-szkic',
            'Do zatwierdzenia' => 'status-do-zatwierdzenia',
            'Zatwierdzone' => 'status-zatwierdzone',
            'Odrzucone' => 'status-odrzucone',
            'Do poprawy' => 'status-do-poprawy'
        ];
        echo json_encode([
            'success' => true,
            'status' => $status,
            'label' => $statusMap[$status] ?? 'Brak statusu',
            'class' => $statusClassMap[$status] ?? 'status-brak'
        ]);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $updateStmt->error]);
    }
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $mysqli->error]);
}
?>
