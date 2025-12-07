<?php
$mysqli = require 'db.php';
$result = $mysqli->query("DESCRIBE WPISY");
echo "<h2>WPISY Columns:</h2><pre>";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";
?>
