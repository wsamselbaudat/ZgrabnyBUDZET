<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Panel admina</title>
<style>
body { background:#121212; color:white; padding:20px; font-family:Arial; }
.tile {
    padding:15px; border:1px solid #333; margin-bottom:10px; background:#1e1e1e; border-radius:8px;
}
</style>
</head>
<body>

<h1>Witaj, <?= $_SESSION["login"] ?>!</h1>
<p>Twoja rola: <b><?= $_SESSION["rola"] ?></b></p>

<?php if ($_SESSION["rola"] === "Admin"): ?>
<div class="tile">
    <h3>Dodaj wpis budżetowy</h3>
    <a href="add.php" style="color:#3a8bfd;">Przejdź</a>
</div>

<div class="tile">
    <h3>Lista wszystkich wpisów</h3>
    <a href="insert.php" style="color:#3a8bfd;">Przejdź</a>
</div>

<div class="tile">
    <h3>Zarządzanie użytkownikami</h3>
    <a href="users.php" style="color:#3a8bfd;">Przejdź</a>
</div>
<?php endif; ?>

<?php if ($_SESSION["rola"] === "Dzial"): ?>
<div class="tile">
    <h3>Wpisy przypisane do Twojego działu</h3>
    <a href="list.php?dzial=<?= $_SESSION["dzial"] ?>" style="color:#3a8bfd;">Przejdź</a>
</div>
<?php endif; ?>

<br>
<a href="logout.php" style="color:red;">Wyloguj</a>

</body>
</html>
