<?php
// Check if Status column exists in WPISY table
$mysqli = require 'db.php';
if (!($mysqli instanceof mysqli)) {
    die('Database connection error.');
}

$result = $mysqli->query("SHOW COLUMNS FROM WPISY LIKE 'Status'");
if ($result->num_rows > 0) {
    echo "✓ Kolumna 'Status' istnieje w tabeli WPISY<br>";
    $col = $result->fetch_assoc();
    echo "Typ: " . $col['Type'] . "<br>";
    echo "Domyślna wartość: " . ($col['Default'] ?? 'NULL') . "<br>";
} else {
    echo "✗ Kolumna 'Status' NIE istnieje w tabeli WPISY<br>";
    echo "<br>Aby dodać kolumnę Status, wykonaj:<br>";
    echo "<pre>ALTER TABLE WPISY ADD COLUMN Status VARCHAR(50) DEFAULT 'do-zatwierdzenia';</pre>";
}

// Show first few records
echo "<br><br>Pierwsze 3 rekordy z tabeli WPISY:<br>";
$res = $mysqli->query("SELECT ID, NazwaZadania, Status FROM WPISY LIMIT 3");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo "ID: " . $row['ID'] . " | Zadanie: " . $row['NazwaZadania'] . " | Status: " . ($row['Status'] ?? 'BRAK') . "<br>";
    }
}
