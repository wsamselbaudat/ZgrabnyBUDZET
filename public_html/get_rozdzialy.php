<?php
// Zwraca <option> dla rozdziałów pasujących do wybranego Działu (AJAX)
if (!isset($_GET['dzial'])) {
    http_response_code(400);
    exit('Brak parametru dzial');
}
$dzial = preg_replace('/[^0-9]/', '', $_GET['dzial']);
$mysqli = require 'db.php';
$res = $mysqli->prepare("SELECT Kod, Nazwa FROM KlasyfikacjaBudzetowa WHERE Typ='Rozdział' AND Kod LIKE CONCAT(?, '%') ORDER BY Kod");
$res->bind_param('s', $dzial);
$res->execute();
$res->bind_result($kod, $nazwa);
$options = '';
while ($res->fetch()) {
    $label = htmlspecialchars($kod . ' ' . $nazwa);
    $options .= "<option value=\"$kod\">$label</option>\n";
}
echo $options;
