<?php
session_start();
$mysqli = require 'db.php';
if (!($mysqli instanceof mysqli)) { die('DB error'); }

$data = $_POST;

$cols = [
    'CzęśćBudżetowa','Dział','Rozdział','Paragraf','ŹródłoFinansowania','GrupaWydatków',
    'BudżetZadaniowySzczeg','BudżetZadaniowyNr','NazwaProgramu','NazwaKomorki',
    'PlanWI','DysponentŚrodków','Budżet','NazwaZadania','SzczegUzasadnienie',
    'PrzeznaczenieWydatków',
    'Potrzeby2026','Limit2026','Braki2026','KwotaUmowy2026','NrUmowy2026',
    'Potrzeby2027','Limit2027','Braki2027','KwotaUmowy2027','NrUmowy2027',
    'Potrzeby2028','Limit2028','Braki2028','KwotaUmowy2028','NrUmowy2028',
    'Potrzeby2029','Limit2029','Braki2029','KwotaUmowy2029','NrUmowy2029',
    'DotacjaZKim','PodstawaPrawna','Uwagi'
];

$placeholders = implode(',', array_fill(0, count($cols), '?'));
$columnsSQL = implode(',', array_map(fn($c)=>"`$c`", $cols));

$sql = "INSERT INTO WPISY ($columnsSQL) VALUES ($placeholders)";
$stmt = $mysqli->prepare($sql);

$values = [];
$values = [];
foreach ($cols as $c) {
    $v = $data[$c] ?? null;

    // Jeśli pole jest puste, ustawiamy NULL zamiast pustego stringa
    if ($v === '' || $v === null) {
        $v = null;
    }

    // Decimal mogą przyjmować liczby, ale jeśli użytkownik wpisze coś dziwnego → null
    if (in_array($c, [
        'Potrzeby2026','Limit2026','Braki2026','KwotaUmowy2026',
        'Potrzeby2027','Limit2027','Braki2027','KwotaUmowy2027',
        'Potrzeby2028','Limit2028','Braki2028','KwotaUmowy2028',
        'Potrzeby2029','Limit2029','Braki2029','KwotaUmowy2029'
    ])) {
        if ($v === null || $v === '') {
            $v = null;
        } else {
            $v = floatval($v); // bezpieczna konwersja
        }
    }

    $values[] = $v;
}

function znajdzGrupeWydatkow($mysqli, $kod3) {
    $res = $mysqli->query("SELECT ID, Nazwa, Zakres FROM GrupaWydatkow");
    if (!$res) return null;

    while ($row = $res->fetch_assoc()) {
        $zakres = $row['Zakres'];
        $czesci = array_map('trim', explode(',', $zakres));

        foreach ($czesci as $fragment) {
            
            // zakres typu 302-305
            if (strpos($fragment, '-') !== false) {
                list($od, $do) = array_map('intval', explode('-', $fragment));
                if ($kod3 >= $od && $kod3 <= $do) {
                    return $row['Nazwa']; // ZWROT NAZWY GRUPY
                }
            }

            // pojedynczy paragraf, np. "307"
            else {
                if (intval($fragment) === intval($kod3)) {
                    return $row['Nazwa'];
                }
            }
        }
    }

    return null; // brak dopasowania
}

$types = str_repeat('s', count($cols));

$stmt->bind_param($types, ...$values);
if (! $stmt->execute()) {
    http_response_code(500);
    echo 'Błąd zapisu: ' . htmlspecialchars($stmt->error);
    exit;
}

$newID = $mysqli->insert_id;

// Wyświetl podsumowanie zapisanego wpisu wraz z ID
?>
<!DOCTYPE html>
<?php require 'navbar.php'; ?>
<html>
<head>
    <meta charset="utf-8">
    <title>Podsumowanie zapisu</title>
    <style>body{font-family:Arial,Helvetica,sans-serif} table{border-collapse:collapse} td,th{border:1px solid #ccc;padding:6px}</style>
</head>
<body>
    <h2>✔ Zapisano nowy wpis</h2>
    <p>Przydzielone ID: <strong><?= htmlspecialchars((string)$newID) ?></strong></p>
    <h3>Podsumowanie pól</h3>
    <table>
        <tr><th>Pole</th><th>Wartość</th></tr>
        <?php foreach ($cols as $i => $col):
            $val = $values[$i] ?? null;
            if ($val === null) $display = '<em>(puste)</em>';
            else $display = htmlspecialchars((string)$val);
        ?>
        <tr>
            <td><?= htmlspecialchars($col) ?></td>
            <td><?= $display ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="index.php">← Powrót do listy</a> | <a href="add.php">Dodaj kolejny</a></p>
</body>
</html>
<?php
exit;

