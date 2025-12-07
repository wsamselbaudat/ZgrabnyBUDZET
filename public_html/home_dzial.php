<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: index.php');
    exit;
}

// Only Dzial or Admin can view
if (!in_array($_SESSION['rola'], ['Dzial', 'Admin'], true)) {
    header('Location: panel.php');
    exit;
}

$mysqli = require 'db.php';
if (!($mysqli instanceof mysqli)) {
    die('Database config error.');
}

$dzialId = isset($_SESSION['dzial_id']) ? $_SESSION['dzial_id'] : null;
$komorkaName = isset($_SESSION['komorka_nazwa']) ? $_SESSION['komorka_nazwa'] : '';

// If admin, allow optional komorka filter via GET
$filterKomorka = '';
if ($_SESSION['rola'] === 'Admin' && isset($_GET['komorka'])) {
    $filterKomorka = trim($_GET['komorka']);
}

$where = [];
$params = [];
$types = '';

if ($filterKomorka !== '') {
    $where[] = 'WPISY.NazwaKomorki = ?';
    $params[] = $filterKomorka;
    $types .= 's';
} else {
    // default: current user's komorka
    if ($komorkaName !== '') {
        $where[] = 'WPISY.NazwaKomorki = ?';
        $params[] = $komorkaName;
        $types .= 's';
    } else if ($dzialId) {
        $where[] = 'WPISY.NazwaKomorki = CONCAT("Komorka ID ", ?)';
        $params[] = $dzialId;
        $types .= 'i';
    }
}

$sql = "SELECT WPISY.ID, WPISY.NazwaZadania, WPISY.Status, WPISY.NazwaKomorki, Komorki.Skrot FROM WPISY LEFT JOIN Komorki ON WPISY.NazwaKomorki = Komorki.Nazwa";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY WPISY.ID DESC';

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$statusClassMap = [
    'Szkic' => 'status-szkic',
    'Do zatwierdzenia' => 'status-do-zatwierdzenia',
    'Zatwierdzone' => 'status-zatwierdzone',
    'Odrzucone' => 'status-odrzucone',
    'Do poprawy' => 'status-do-poprawy'
];
$statusLabelMap = [
    'Szkic' => 'Szkic',
    'Do zatwierdzenia' => 'Do zatwierdzenia',
    'Zatwierdzone' => 'Zatwierdzone',
    'Odrzucone' => 'Odrzucone',
    'Do poprawy' => 'Do poprawy'
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Twoje wnioski</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin:0; background:#f5f7fa; }
        .container { max-width:1100px; margin:20px auto; padding:0 16px; }
        h2 { margin:12px 0 8px 0; color:#1a73e8; }
        table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        th, td { padding:10px; border:1px solid #e0e0e0; text-align:left; }
        th { background:#e9eef7; }
        .status-badge { display:inline-block; padding:3px 8px; border-radius:3px; font-size:12px; font-weight:600; color:#fff; }
        .status-szkic { background:#95a5a6; }
        .status-do-zatwierdzenia { background:#f39c12; }
        .status-zatwierdzone { background:#27ae60; }
        .status-odrzucone { background:#e74c3c; }
        .status-do-poprawy { background:#3498db; }
        .status-brak { background:#bdc3c7; }
        .top-bar { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; gap:20px; }
        .top-bar > div { flex:1; }
        .back { color:#1a73e8; text-decoration:none; font-weight:600; }
        .view-link { color:#1a73e8; text-decoration:none; font-weight:600; cursor:pointer; }
        .view-link:hover { text-decoration:underline; }
    </style>
</head>
<body>
<?php require 'navbar.php'; ?>
<div class="container">
    <div class="top-bar">
        <div>
            <h2>Twoje wnioski</h2>
            <a href="add.php" style="color:#27ae60; margin-top:5px; display:inline-block;">+ Dodaj nowy wniosek</a>
        </div>
        <a class="back" href="panel.php"><- Panel</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Komorka</th>
                <th>Nazwa zadania</th>
                <th>Status</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $res->fetch_assoc()): ?>
                <?php
                    $status = isset($row['Status']) ? $row['Status'] : '';
                    $statusClass = isset($statusClassMap[$status]) ? $statusClassMap[$status] : 'status-brak';
                    $statusLabel = isset($statusLabelMap[$status]) ? $statusLabelMap[$status] : 'Brak statusu';
                    $komorkaLabel = (!empty($row['Skrot']) ? $row['Skrot'] . ' - ' : '') . (isset($row['NazwaKomorki']) ? $row['NazwaKomorki'] : '');
                ?>
                <tr>
                    <td><?= (int)$row['ID'] ?></td>
                    <td><?= htmlspecialchars($komorkaLabel) ?></td>
                    <td><?= htmlspecialchars((string)$row['NazwaZadania']) ?></td>
                    <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                    <td><a href="edit.php?id=<?= (int)$row['ID'] ?>" class="view-link">Szczegóły</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
