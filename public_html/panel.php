<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<?php require 'navbar.php'; ?>
<html>
<head>
<title>Panel</title>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { 
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        color:#333; 
        font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        padding:0; 
        min-height:100vh;
    }
    h1 { color:#1a73e8; margin:100px 20px 10px 20px; font-size:28px; }
    p { color:#666; margin:0 20px 20px 20px; font-size:15px; }
    .container { max-width:600px; margin:0 auto; padding:0 20px; }
    .tile {
        padding:20px; 
        background: linear-gradient(135deg, #ffffff 0%, #f9fbff 100%);
        border:none;
        border-left:4px solid #1a73e8;
        margin-bottom:15px; 
        margin-left: 0;
        margin-right: 0;
        border-radius:8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }
    .tile:hover {
        box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        transform: translateY(-2px);
    }
    .tile h3 { color:#1a73e8; font-size:16px; margin-bottom:12px; }
    a { color:#1a73e8; text-decoration:none; font-weight:600; transition:color 0.2s; }
    a:hover { color:#1557b0; }
    .logout-btn { color:#d32f2f; display:inline-block; margin-top:30px; margin-left:20px; }
    .error-box {
        max-width:600px; margin:20px auto; padding:12px 16px; background:#fff3cd; color:#856404; border-left:4px solid #ffc107; border-radius:4px;
    }
</style>
</head>
<body>

<h1>Witaj, <?= $_SESSION["login"] ?>!</h1>
<?php
$roleName = $_SESSION["rola"];
if ($roleName === "Dzial") {
    $komorkaSkrot = $_SESSION['komorka_skrot'] ?? '';
    $komorkaName = $_SESSION['komorka_nazwa'] ?? '';
    $dzialLabel = trim(($komorkaSkrot ? $komorkaSkrot . ' — ' : '') . $komorkaName);
    if ($dzialLabel === '') {
        $dzialLabel = 'Komórka ID ' . (isset($_SESSION['dzial_id']) ? $_SESSION['dzial_id'] : '');
    }
    $roleName = htmlspecialchars($dzialLabel);
}
?>
<p>Twój dział: <b><?= $roleName ?></b></p>

<?php if (!empty($_SESSION['komorka_error'])): ?>
    <div style="padding:10px; margin-bottom:10px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:4px; margin-left:20px; margin-right:20px; max-width:600px; margin-left:auto; margin-right:auto;">
        <?= htmlspecialchars($_SESSION['komorka_error']) ?>
    </div>
    <?php unset($_SESSION['komorka_error']); ?>
<?php endif; ?>

<div class="container">
<?php if ($_SESSION["rola"] === "Admin"): ?>

<div class="tile">
    <h3>Dodaj pozycję budżetową</h3>
    <a href="add.php">Przejdź</a>
</div>

<div class="tile">
    <h3>Lista wszystkich wpisów</h3>
    <a href="home.php">Przejdź</a>
</div>

<div class="tile">
    <h3>Zarządzanie użytkownikami</h3>
    <a href="users.php">Przejdź</a>
</div>

<?php endif; ?>


<?php if ($_SESSION["rola"] === "Ksiegowosc"): ?>

<div class="tile">
    <h3>Przegląd wszystkich wpisów</h3>
    <a href="home.php">Przejdź</a>
</div>

<?php endif; ?>


<?php if ($_SESSION["rola"] === "Dzial"): ?>

<div class="tile">
    <?php
    $panelKomorkaID = $_SESSION['dzial_id'] ?? '';
    $panelKomorkaName = $_SESSION['komorka_nazwa'] ?? '';
    $panelKomorkaSkrot = $_SESSION['komorka_skrot'] ?? '';
    $panelKomorkaLabel = trim(($panelKomorkaSkrot ? $panelKomorkaSkrot . ' — ' : '') . $panelKomorkaName);
    if ($panelKomorkaLabel === '') {
        $panelKomorkaLabel = 'Komórka ID ' . htmlspecialchars((string)$panelKomorkaID);
    }
    ?>
    <h3>Wpisy dla Twojej komórki: <?= htmlspecialchars($panelKomorkaLabel) ?><?= $panelKomorkaID ? ' (ID: ' . htmlspecialchars((string)$panelKomorkaID) . ')' : '' ?></h3>
    <a href="home_dzial.php">Przejdź</a>
</div>

<?php endif; ?>
</div>

<a href="logout.php" style="position:fixed; bottom:20px; right:20px; padding:10px 20px; background:#d32f2f; color:white; border-radius:4px; font-weight:600; transition:background 0.2s; text-decoration:none;">Wyloguj</a>

</body>
</html>
