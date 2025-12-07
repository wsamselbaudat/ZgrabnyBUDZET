<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['rola'] !== 'Admin') {
    header('Location: index.php');
    exit;
}

$mysqli = require 'db.php';
$message = '';
$error = '';

// Dodawanie nowego użytkownika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $login = trim($_POST['login'] ?? '');
    $haslo = trim($_POST['haslo'] ?? '');
    $rola = trim($_POST['rola'] ?? 'User');
    $iddzialu = !empty($_POST['iddzialu']) ? (int)$_POST['iddzialu'] : NULL;
    
    if (empty($login) || empty($haslo)) {
        $error = 'Login i hasło są wymagane!';
    } else {
        // Zahaszuj hasło
        $hashed_password = password_hash($haslo, PASSWORD_BCRYPT);
        
        // Sprawdź czy login już istnieje
        $stmt = $mysqli->prepare('SELECT ID FROM Uzytkownicy WHERE Login = ?');
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Ten login już istnieje!';
        } else {
            // Dodaj nowego użytkownika
            $stmt = $mysqli->prepare('INSERT INTO Uzytkownicy (Login, Haslo, Rola, IDDzialu) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sssi', $login, $hashed_password, $rola, $iddzialu);
            
            if ($stmt->execute()) {
                $message = "Użytkownik '$login' został dodany pomyślnie!";
                $_POST = []; // Wyczyść formularz
            } else {
                $error = 'Błąd przy dodawaniu użytkownika: ' . $mysqli->error;
            }
        }
    }
}

// Usuwanie użytkownika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = (int)$_POST['user_id'];
    
    if ($user_id === $_SESSION['user_id']) {
        $error = 'Nie możesz usunąć własnego konta!';
    } else {
        $stmt = $mysqli->prepare('DELETE FROM Uzytkownicy WHERE ID = ?');
        $stmt->bind_param('i', $user_id);
        
        if ($stmt->execute()) {
            $message = 'Użytkownik został usunięty!';
        } else {
            $error = 'Błąd przy usuwaniu użytkownika!';
        }
    }
}

// Pobierz listę działów
$dzialy = $mysqli->query('SELECT ID, Nazwa FROM Dzialy ORDER BY Nazwa');

// Pobierz listę użytkowników
$uzytkownicy = $mysqli->query('SELECT ID, Login, Rola, IDDzialu FROM Uzytkownicy ORDER BY Login');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Zarządzanie użytkownikami</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial; margin: 0; padding: 0; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .main { flex: 1; padding: 24px; max-width: 1200px; margin: 0 auto; }
        h2 { color: #333; margin-top: 0; }
        .section { background: white; padding: 20px; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: bold; margin-bottom: 4px; color: #555; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .button-group { display: flex; gap: 12px; margin-top: 20px; }
        button { padding: 10px 20px; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .btn-add { background: #0097a7; color: white; }
        .btn-add:hover { background: #00838f; }
        .btn-delete { background: #d32f2f; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-delete:hover { background: #c62828; }
        .message { color: green; padding: 12px; background: #e8f5e9; border-radius: 4px; margin-bottom: 16px; }
        .error { color: red; padding: 12px; background: #ffebee; border-radius: 4px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; color: #555; }
        tr:hover { background: #f9f9f9; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .back-btn { display: inline-block; padding: 10px 20px; background: #ddd; color: #333; text-decoration: none; border-radius: 4px; margin-bottom: 16px; }
        .back-btn:hover { background: #ccc; }
    </style>
</head>
<body>
<div class="container">
    <div class="main">
        <a href="home.php" class="back-btn">← Powrót</a>
        
        <h2>Zarządzanie użytkownikami</h2>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- DODAWANIE NOWEGO UŻYTKOWNIKA -->
        <div class="section">
            <h3>Dodaj nowego użytkownika</h3>
            <form method="post">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="login">Login *</label>
                        <input type="text" name="login" id="login" required placeholder="np. john_doe">
                    </div>
                    <div class="form-group">
                        <label for="haslo">Hasło *</label>
                        <input type="password" name="haslo" id="haslo" required placeholder="Wylosuj silne hasło">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="rola">Rola *</label>
                        <select name="rola" id="rola" required>
                            <option value="User">User</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="iddzialu">Dział</label>
                        <select name="iddzialu" id="iddzialu">
                            <option value="">-- brak --</option>
                            <?php if ($dzialy) while ($r = $dzialy->fetch_assoc()): ?>
                                <option value="<?= (int)$r['ID'] ?>"><?= htmlspecialchars($r['Nazwa']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-add">✓ Dodaj użytkownika</button>
                </div>
            </form>
        </div>
        
        <!-- LISTA UŻYTKOWNIKÓW -->
        <div class="section">
            <h3>Lista użytkowników</h3>
            <table>
                <thead>
                    <tr>
                        <th>Login</th>
                        <th>Rola</th>
                        <th>Dział</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($uzytkownicy) while ($u = $uzytkownicy->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['Login']) ?></td>
                            <td><?= htmlspecialchars($u['Rola']) ?></td>
                            <td><?= $u['IDDzialu'] ? "ID: {$u['IDDzialu']}" : "brak" ?></td>
                            <td>
                                <?php if ($u['ID'] != $_SESSION['user_id']): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['ID'] ?>">
                                        <button type="submit" class="btn-delete" onclick="return confirm('Jesteś pewny?')">Usuń</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #999;">(To Ty)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'navbar.php'; ?>
</body>
</html>
