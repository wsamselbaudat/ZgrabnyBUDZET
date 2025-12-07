<?php
session_start();
$mysqli = require 'db.php';
if (!($mysqli instanceof mysqli)) {
    die('Database config error.');
}

// Check if user is admin
if (!isset($_SESSION['rola']) || $_SESSION['rola'] !== 'Admin') {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle purge of all non-admin users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purge_non_admin') {
    $stmt = $mysqli->prepare("DELETE FROM Uzytkownicy WHERE Rola <> 'Admin'");
    if ($stmt && $stmt->execute()) {
        $success = 'Usunięto wszystkich użytkowników bez rangi Admin.';
    } else {
        $error = 'Błąd przy czyszczeniu użytkowników nie-admin.';
    }
}

// Handle seeding random Dzial users into Komorki (multiple users can share the same Komorka)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seed_dummy') {
    $dept_res = $mysqli->query('SELECT ID, Skrot, Nazwa FROM Komorki ORDER BY ID');
    $departments_seed = [];
    while ($dept_res && $row = $dept_res->fetch_assoc()) {
        $departments_seed[] = $row;
    }
    if (empty($departments_seed)) {
        $error = 'Brak komórek w tabeli Komorki – nie można dodać użytkowników.';
    } else {
        $dummyUsers = [
            ['Anna', 'Kowalska'],
            ['Jan', 'Nowak'],
            ['Maria', 'Wiśniewska'],
            ['Piotr', 'Wójcik'],
            ['Krzysztof', 'Krawczyk'],
            ['Magdalena', 'Zielińska'],
            ['Tomasz', 'Lewandowski'],
            ['Agnieszka', 'Szymańska'],
            ['Paweł', 'Dąbrowski'],
            ['Katarzyna', 'Jankowska']
        ];
        $added = 0;
        $insertStmt = $mysqli->prepare('INSERT INTO Uzytkownicy (Login, Haslo, Rola, IDDzialu) VALUES (?, ?, ?, ?)');
        $checkStmt  = $mysqli->prepare('SELECT ID FROM Uzytkownicy WHERE Login = ?');
        if (!$insertStmt || !$checkStmt) {
            $error = 'Błąd przygotowania zapytań do dodawania użytkowników.';
        } else {
            $role = 'Dzial';
            $pwdTemplate = 'Haslo123!';
            $deptCount = count($departments_seed);
            foreach ($dummyUsers as $idx => [$imie, $nazwisko]) {
                $baseLogin = strtolower($imie . '.' . $nazwisko);
                $login = $baseLogin;
                $suffix = 1;
                // Ensure unique login
                while (true) {
                    $checkStmt->bind_param('s', $login);
                    $checkStmt->execute();
                    $resCheck = $checkStmt->get_result();
                    if ($resCheck && $resCheck->num_rows > 0) {
                        $login = $baseLogin . $suffix;
                        $suffix++;
                        if ($suffix > 50) { break; }
                    } else {
                        break;
                    }
                }
                if ($suffix > 50) { continue; }
                $hashedPwd = password_hash($pwdTemplate, PASSWORD_DEFAULT);
                $dept = $departments_seed[$idx % $deptCount]; // allow many users per same dept via round-robin
                $deptId = (int)($dept['ID'] ?? 0);
                $insertStmt->bind_param('sssi', $login, $hashedPwd, $role, $deptId);
                if ($insertStmt->execute()) {
                    $added++;
                }
            }
            if ($added > 0) {
                $success = 'Dodano ' . $added . ' przykładowych użytkowników (rola Dzial, hasło: ' . $pwdTemplate . ').';
            } else {
                $error = 'Nie dodano żadnego użytkownika (być może loginy już istnieją).';
            }
        }
    }
}

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['user_id'];
    $stmt = $mysqli->prepare('DELETE FROM Uzytkownicy WHERE ID = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $success = 'Użytkownik usunięty!';
    } else {
        $error = 'Błąd przy usuwaniu użytkownika.';
    }
}

// Handle UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)$_POST['user_id'];
    $login = $_POST['login'];
    $haslo = $_POST['haslo'];
    $rola = $_POST['rola'];
    $id_dzialu = (int)$_POST['id_dzialu'];

    if (empty($login)) {
        $error = 'Login nie może być pusty!';
    } else {
        if (!empty($haslo)) {
            $stmt = $mysqli->prepare('UPDATE Uzytkownicy SET Login = ?, Haslo = ?, Rola = ?, IDDzialu = ? WHERE ID = ?');
            $stmt->bind_param('sssii', $login, $haslo, $rola, $id_dzialu, $id);
        } else {
            $stmt = $mysqli->prepare('UPDATE Uzytkownicy SET Login = ?, Rola = ?, IDDzialu = ? WHERE ID = ?');
            $stmt->bind_param('ssii', $login, $rola, $id_dzialu, $id);
        }
        if ($stmt->execute()) {
            $success = 'Użytkownik zaktualizowany!';
        } else {
            $error = 'Błąd przy aktualizacji użytkownika.';
        }
    }
}

// Handle INSERT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $login = $_POST['new_login'];
    $haslo = $_POST['new_haslo'];
    $rola = $_POST['new_rola'];
    $id_dzialu = (int)$_POST['new_id_dzialu'];

    if (empty($login) || empty($haslo)) {
        $error = 'Login i hasło nie mogą być puste!';
    } else {
        $stmt = $mysqli->prepare('INSERT INTO Uzytkownicy (Login, Haslo, Rola, IDDzialu) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('sssi', $login, $haslo, $rola, $id_dzialu);
        if ($stmt->execute()) {
            $success = 'Nowy użytkownik dodany!';
        } else {
            $error = 'Błąd przy dodawaniu użytkownika.';
        }
    }
}

// Fetch all users
$res = $mysqli->query('SELECT ID, Login, Haslo, Rola, IDDzialu FROM Uzytkownicy ORDER BY ID DESC');
$users = [];
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}

// Fetch from Komorki (Skrot and Nazwa)
$dept_res = $mysqli->query('SELECT ID, Skrot, Nazwa FROM Komorki ORDER BY Nazwa');
$departments = [];
while ($row = $dept_res->fetch_assoc()) {
    $departments[] = $row;
}
?>
<!DOCTYPE html>
<?php require 'navbar.php'; ?>
<html>
<head>
    <meta charset="utf-8">
    <title>Zarządzanie użytkownikami</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; background:#f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        .notification { padding: 12px; margin-bottom: 20px; border-radius: 4px; }
        .error { background: #ffebee; color: #c62828; border: 1px solid #ef5350; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #66bb6a; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-delete { background: #dc3545; }
        .btn-delete:hover { background: #c82333; }
        .btn-edit { background: #28a745; }
        .btn-edit:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f9f9f9; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        .form-section { background: white; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-full { grid-column: 1 / -1; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; }
        .modal-close { float: right; font-size: 24px; cursor: pointer; }
    </style>
    <script>
        function openEditModal(userId) {
            var modal = document.getElementById('edit-modal-' + userId);
            if (modal) modal.classList.add('active');
        }
        function closeEditModal(userId) {
            var modal = document.getElementById('edit-modal-' + userId);
            if (modal) modal.classList.remove('active');
        }
        function openAddModal() {
            var modal = document.getElementById('add-modal');
            if (modal) modal.classList.add('active');
        }
        function closeAddModal() {
            var modal = document.getElementById('add-modal');
            if (modal) modal.classList.remove('active');
        }
        function openDeleteModal(userId) {
            var modal = document.getElementById('delete-modal-' + userId);
            if (modal) modal.classList.add('active');
        }
        function closeDeleteModal(userId) {
            var modal = document.getElementById('delete-modal-' + userId);
            if (modal) modal.classList.remove('active');
        }
    </script>
</head>
<body>

<div class="container">
    <div class="back-link">
        <a href="panel.php">&larr; Powrót do panelu</a>
    </div>

    <h1>Zarządzanie użytkownikami</h1>

    <?php if ($error): ?>
        <div class="notification error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="notification success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Actions -->
    <div style="margin-bottom: 20px; display:flex; gap:12px; flex-wrap:wrap;">
        <button class="btn" onclick="openAddModal()">+ Dodaj nowego użytkownika</button>
        <form method="POST" onsubmit="return confirm('Na pewno usunąć wszystkich użytkowników, którzy nie są Admin?');">
            <input type="hidden" name="action" value="purge_non_admin">
            <button type="submit" class="btn btn-delete">Usuń wszystkich nie-Admin</button>
        </form>
        <form method="POST" onsubmit="return confirm('Dodać przykładowych użytkowników Dział (hasło: Haslo123!)?');">
            <input type="hidden" name="action" value="seed_dummy">
            <button type="submit" class="btn">Dodaj przykładowych użytkowników (Dział)</button>
        </form>
    </div>

    <!-- Users Table -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Login</th>
                <th>Rola</th>
                <th>Komórka</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int)$user['ID'] ?></td>
                    <td><?= htmlspecialchars($user['Login']) ?></td>
                    <td><?= htmlspecialchars($user['Rola']) ?></td>
                    <td>
                        <?php
                        $dept_id = (int)$user['IDDzialu'];
                        $dept_name = 'Brak';
                        foreach ($departments as $d) {
                            if ($d['ID'] == $dept_id) {
                                $label = trim(($d['Skrot'] ?? '') . ' — ' . ($d['Nazwa'] ?? ''));
                                $dept_name = htmlspecialchars($label);
                                break;
                            }
                        }
                        echo $dept_name;
                        ?>
                    </td>
                    <td>
                        <button class="btn btn-edit" onclick="openEditModal(<?= (int)$user['ID'] ?>)">Edytuj</button>
                        <button class="btn btn-delete" onclick="openDeleteModal(<?= (int)$user['ID'] ?>)">Usuń</button>
                    </td>
                </tr>

                <!-- Edit Modal -->
                <div id="edit-modal-<?= (int)$user['ID'] ?>" class="modal">
                    <div class="modal-content">
                        <span class="modal-close" onclick="closeEditModal(<?= (int)$user['ID'] ?>)">&times;</span>
                        <h2>Edytuj użytkownika</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="user_id" value="<?= (int)$user['ID'] ?>">
                            <div class="form-group">
                                <label>Login</label>
                                <input type="text" name="login" value="<?= htmlspecialchars($user['Login']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Hasło (pozostaw puste aby nie zmieniać)</label>
                                <input type="password" name="haslo">
                            </div>
                            <div class="form-group">
                                <label>Rola</label>
                                <select name="rola">
                                    <option value="Admin" <?= $user['Rola'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="Ksiegowosc" <?= $user['Rola'] === 'Ksiegowosc' ? 'selected' : '' ?>>Księgowość</option>
                                    <option value="Dzial" <?= $user['Rola'] === 'Dzial' ? 'selected' : '' ?>>Dział</option>
                                    <option value="User" <?= $user['Rola'] === 'User' ? 'selected' : '' ?>>User</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Komórka</label>
                                <select name="id_dzialu">
                                    <option value="0">Brak</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int)$d['ID'] ?>" <?= (int)$user['IDDzialu'] === (int)$d['ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim(($d['Skrot'] ?? '') . ' — ' . ($d['Nazwa'] ?? ''))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn">Zapisz zmiany</button>
                        </form>
                    </div>
                </div>

                <!-- Delete Modal -->
                <div id="delete-modal-<?= (int)$user['ID'] ?>" class="modal">
                    <div class="modal-content">
                        <span class="modal-close" onclick="closeDeleteModal(<?= (int)$user['ID'] ?>)">&times;</span>
                        <h2>Potwierdzenie usunięcia</h2>
                        <p>Czy na pewno chcesz usunąć użytkownika <strong><?= htmlspecialchars($user['Login']) ?></strong>?</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int)$user['ID'] ?>">
                            <button type="submit" class="btn btn-delete">Tak, usuń</button>
                            <button type="button" class="btn" onclick="closeDeleteModal(<?= (int)$user['ID'] ?>)">Anuluj</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add User Modal -->
<div id="add-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeAddModal()">&times;</span>
        <h2>Dodaj nowego użytkownika</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Login</label>
                <input type="text" name="new_login" required>
            </div>
            <div class="form-group">
                <label>Hasło</label>
                <input type="password" name="new_haslo" required>
            </div>
            <div class="form-group">
                <label>Rola</label>
                <select name="new_rola">
                    <option value="Dzial">Dział</option>
                    <option value="Ksiegowosc">Księgowość</option>
                    <option value="Admin">Admin</option>
                    <option value="User">User</option>
                </select>
            </div>
            <div class="form-group">
                <label>Komórka</label>
                <select name="new_id_dzialu">
                    <option value="0">Brak</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['ID'] ?>">
                            <?= htmlspecialchars(trim(($d['Skrot'] ?? '') . ' — ' . ($d['Nazwa'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Dodaj użytkownika</button>
        </form>
    </div>
</div>

</body>
</html>
