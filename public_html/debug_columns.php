<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: index.php');
    exit;
}

$mysqli = require 'db.php';

// Try to get one record and show all columns
$result = $mysqli->query("SELECT * FROM WPISY LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<h2>Kolumny w WPISY:</h2><pre>";
    foreach ($row as $key => $value) {
        echo htmlspecialchars($key) . "\n";
    }
    echo "</pre>";
} else {
    echo "Brak danych";
}
?>
