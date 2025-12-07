<?php
session_start();
$conn = require 'db.php';
if (!($conn instanceof mysqli)) {
    die('Database config error.');
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = $_POST["login"];
    $haslo = $_POST["haslo"];

    $stmt = $conn->prepare("SELECT ID, Login, Haslo, Rola, IDDzialu FROM Uzytkownicy WHERE Login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Obsłuż zarówno hasła hashowane (password_hash), jak i stare plaintext
        $ok = password_verify($haslo, $row["Haslo"]) || $haslo === $row["Haslo"];
        if ($ok) {

            $_SESSION["user_id"] = $row["ID"];
            $_SESSION["login"] = $row["Login"];
            $_SESSION["rola"] = $row["Rola"];
            $_SESSION["dzial_id"] = $row["IDDzialu"];

            // Pobierz nazwę i skrót komórki na podstawie IDDzialu i zapisz w sesji
            $_SESSION["komorka_nazwa"] = '';
            $_SESSION["komorka_skrot"] = '';
            if (!empty($row["IDDzialu"])) {
                $komorkaStmt = $conn->prepare("SELECT Nazwa, Skrot FROM Komorki WHERE ID = ?");
                if ($komorkaStmt) {
                    $komorkaStmt->bind_param("i", $row["IDDzialu"]);
                    $komorkaStmt->execute();
                    $kres = $komorkaStmt->get_result();
                    if ($kres && $krow = $kres->fetch_assoc()) {
                        $_SESSION["komorka_nazwa"] = $krow["Nazwa"] ?? '';
                        $_SESSION["komorka_skrot"] = $krow["Skrot"] ?? '';
                    }
                }
            }

            header("Location: panel.php");
            exit;
        } else {
            $error = "Błędne hasło!";
        }
    } else {
        $error = "Nie znaleziono użytkownika!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Logowanie</title>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color:#fff; 
        font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        min-height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
    }
    .box {\n        width:380px; 
        padding:40px;\n        background:#ffffff; 
        border-radius:12px;\n        box-shadow: 0 10px 40px rgba(0,0,0,0.2);\n        color:#333;\n    }
    .box h2 { color:#667eea; margin-bottom:25px; text-align:center; font-size:24px; }
    input { 
        width:100%; 
        padding:12px 14px; 
        margin-top:14px; 
        border:2px solid #e0e0e0;\n        border-radius:6px;\n        font-size:14px;\n        transition:border-color 0.3s;\n    }
    input:focus { outline:none; border-color:#667eea; }
    button { 
        width:100%; 
        padding:12px; 
        margin-top:20px; 
        background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);\n        border:0; 
        color:white;\n        border-radius:6px;\n        font-weight:600;\n        cursor:pointer;\n        transition:opacity 0.2s;\n    }
    button:hover { opacity:0.9; }
    .error { color:#d32f2f; margin-top:12px; padding:10px; background:#ffebee; border-radius:4px; text-align:center; }
</style>
</head>
<body>

<div class="box">
    <h2>Logowanie</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="login" placeholder="Login" required>
        <input type="password" name="haslo" placeholder="Hasło" required>
        <button type="submit">Zaloguj się</button>
    </form>
</div>

</body>
</html>
