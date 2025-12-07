<?php
// Zwraca GrupaWydatków na podstawie Paragrafu z tabeli GrupaWydatkow
// Tabela: ID, Nazwa, Zakres (np. "200-278, 280-299" lub "307")
if (!isset($_GET['paragraf'])) {
    http_response_code(400);
    exit('Brak parametru paragraf');
}
$paragraf = (int)preg_replace('/[^0-9]/', '', $_GET['paragraf']);

$mysqli = require 'db.php';
// Pobierz wszystkie zakresy i sprawdź, w którym mieści się paragraf
$result = $mysqli->query("SELECT Nazwa, Zakres FROM GrupaWydatkow");
while ($row = $result->fetch_assoc()) {
    $zakres = $row['Zakres'];
    // Zakres może zawierać wiele fragmentów oddzielonych przecinkami
    $czesci = array_map('trim', explode(',', $zakres));
    
    foreach ($czesci as $fragment) {
        // zakres typu "302-305"
        if (strpos($fragment, '-') !== false) {
            list($od, $do) = array_map('intval', explode('-', $fragment));
            if ($paragraf >= $od && $paragraf <= $do) {
                echo htmlspecialchars($row['Nazwa']);
                exit;
            }
        }
        // pojedynczy paragraf, np. "307"
        else {
            if (intval($fragment) === $paragraf) {
                echo htmlspecialchars($row['Nazwa']);
                exit;
            }
        }
    }
}
echo 'brak';
