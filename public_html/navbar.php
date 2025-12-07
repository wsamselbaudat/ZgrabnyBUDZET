<?php
// Navbar component - include this at the top of your page (after session_start)
if (!isset($_SESSION['login'])) {
    header('Location: index.php');
    exit;
}
?>
<style>
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 56px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        padding: 0 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 999;
    }
    .navbar a, .navbar button {
        color: white;
        text-decoration: none;
        padding: 8px 16px;
        margin: 0 8px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        border-radius: 4px;
    }
    .navbar a:hover, .navbar button:hover {
        background: rgba(255,255,255,0.15);
    }
    .navbar-spacer {
        flex: 1;
    }
    .navbar-user {
        font-size: 13px;
        opacity: 0.9;
        margin-right: 16px;
    }
    body {
        padding-top: 56px;
    }
</style>

<div class="navbar">
    <a href="home.php" style="font-weight:600;">HOME</a>
    <a href="panel.php">PANEL</a>
    <div class="navbar-spacer"></div>
    <span class="navbar-user"><?= htmlspecialchars($_SESSION['login']) ?></span>
    <a href="logout.php" style="background:rgba(255,255,255,0.2);">Wyloguj</a>
</div>
