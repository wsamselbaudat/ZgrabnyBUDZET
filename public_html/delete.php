<?php
session_start();
if (!isset($_SESSION['login']) || !isset($_SESSION['rola']) || $_SESSION['rola'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Brak uprawnień.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ids']) || !is_array($_POST['ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Brak wymaganych danych.']);
    exit;
}
$ids = array_filter(array_map('intval', $_POST['ids']), function($v) { return $v > 0; });
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Brak poprawnych ID.']);
    exit;
}
$mysqli = require 'db.php';
if (!($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Błąd połączenia z bazą.']);
    exit;
}
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $mysqli->prepare("DELETE FROM WPISY WHERE ID IN ($placeholders)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Błąd zapytania: ' . $mysqli->error]);
    exit;
}
$types = str_repeat('i', count($ids));
$stmt->bind_param($types, ...$ids);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Błąd usuwania: ' . $stmt->error]);
}
