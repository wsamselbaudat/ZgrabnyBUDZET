<?php
session_start();
// Check session first (navbar will be included at end)
if (!isset($_SESSION['login'])) {
    header('Location: index.php');
    exit;
}

// Dostęp tylko dla Admin i Księgowej
$rola = $_SESSION['rola'] ?? '';
if (!in_array($rola, ['Admin', 'Ksiegowosc'], true)) {
    header('Location: panel.php');
    exit;
}

// Handle delete action for admin
$deleteSuccess = null;
$deleteError = null;
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    if (!isset($_SESSION['rola']) || $_SESSION['rola'] !== 'Admin') {
        $deleteError = 'Brak uprawnień do usuwania.';
    } else {
        $mysqli = require 'db.php';
        if (!($mysqli instanceof mysqli)) {
            $deleteError = 'Błąd połączenia z bazą.';
        } else {
            $id = (int)$_POST['id'];
            error_log('[home.php] Delete attempt id=' . $id . ' user=' . ($_SESSION['login'] ?? 'unknown'));
            $stmt = $mysqli->prepare('DELETE FROM WPISY WHERE ID = ?');
            if (!$stmt) {
                $deleteError = 'Błąd przygotowania zapytania: ' . $mysqli->error;
            } else {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $deleteSuccess = 'Wpis został usunięty.';
                    } else {
                        $deleteError = 'Nie znaleziono rekordu do usunięcia (ID=' . $id . ').';
                        error_log('[home.php] Delete affected_rows=0 id=' . $id);
                    }
                } else {
                    $deleteError = 'Błąd usuwania: ' . $stmt->error;
                    error_log('[home.php] Delete failed id=' . $id . ' err=' . $stmt->error);
                }
            }
        }
    }
}

// Handle delete selected action for admin
if (isset($_POST['action']) && $_POST['action'] === 'delete_selected' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    if (!isset($_SESSION['rola']) || $_SESSION['rola'] !== 'Admin') {
        $deleteError = 'Brak uprawnień do usuwania.';
    } else {
        $mysqli = require 'db.php';
        if (!($mysqli instanceof mysqli)) {
            $deleteError = 'Błąd połączenia z bazą.';
        } else {
            $ids = array_map('intval', $_POST['ids']);
            $ids = array_filter($ids, function($v) { return $v > 0; });
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $mysqli->prepare("DELETE FROM WPISY WHERE ID IN ($in)");
                if ($stmt) {
                    $types = str_repeat('i', count($ids));
                    $stmt->bind_param($types, ...$ids);
                    if ($stmt->execute()) {
                        $deleteSuccess = 'Usunięto ' . $stmt->affected_rows . ' wniosków.';
                    } else {
                        $deleteError = 'Błąd usuwania: ' . $stmt->error;
                    }
                } else {
                    $deleteError = 'Błąd przygotowania zapytania: ' . $mysqli->error;
                }
            }
        }
    }
}

if (isset($_GET['details_id'])) {
    // AJAX details response: return only the details table (styled)
    require 'db.php';
    if (!($mysqli instanceof mysqli)) {
        http_response_code(500);
        echo 'Błąd połączenia z bazą.';
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $id = (int)$_GET['details_id'];
    $det = $mysqli->query('SELECT * FROM WPISY WHERE ID=' . $id);
    if ($det && $det->num_rows) {
        $d = $det->fetch_assoc();
        // pretty label generator: split camelcase and underscores, then title-case
        $pretty_label = function($s) {
            $s = str_replace('_', ' ', $s);
            // insert space before uppercase letters (UTF-8 aware)
            $s = preg_replace('/(?<!^)(?=[A-ZĄĆĘŁŃÓŚŻŹ])/u', ' ', $s);
            $s = preg_replace('/\s+/u', ' ', trim($s));
            // Title case using mb
            if (function_exists('mb_convert_case')) {
                return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
            }
            return ucwords(strtolower($s));
        };
        // optional manual overrides
        $displayNames = [
            'ID' => 'ID'
        ];
        echo '<style>table.details-table{border-collapse:separate;border-spacing:0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;}';
        echo 'table.details-table th, table.details-table td{padding:6px 8px;border-left:1px solid #ddd;max-width:200px;white-space:normal;word-wrap:break-word;overflow:hidden;text-overflow:ellipsis;}';
        echo 'table.details-table th:nth-child(even), table.details-table td:nth-child(even){background:#f7f7f7;}';
        echo 'table.details-table th{background:#efefef;border-top:0;font-weight:600;}';
        echo '</style>';
        // build colgroup to make 'Paragraf' column wider
        $cols = [];
        foreach ($d as $k=>$v) {
            if ($k === 'Paragraf') {
                $cols[] = 'width:320px';
            } else {
                $cols[] = 'width:180px';
            }
        }
        echo '<table class="details-table" style="min-width:800px"><colgroup>';
        foreach ($cols as $c) {
            echo '<col style="' . $c . '">';
        }
        echo '</colgroup><tr>';
        foreach ($d as $k=>$v) {
            $label = $displayNames[$k] ?? $pretty_label($k);
            echo '<th>'.htmlspecialchars((string)$label).'</th>';
        }
        echo '</tr><tr>';
        foreach ($d as $k=>$v) {
            echo '<td style="text-align:center">'.htmlspecialchars((string)$v).'</td>';
        }
        echo '</tr></table>';
    } else {
        echo 'Brak danych dla wybranego wniosku.';
    }
    exit;
}
$mysqli = require 'db.php';
if (!($mysqli instanceof mysqli)) {
    die('Database config error.');
}
// Pobierz filtry z GET (dla checkboxów - mogą być wielokrotne)
$filters = [
    'komorki' => isset($_GET['komorki']) ? (is_array($_GET['komorki']) ? $_GET['komorki'] : [$_GET['komorki']]) : [],
    'dzial' => isset($_GET['dzial']) ? (is_array($_GET['dzial']) ? $_GET['dzial'] : [$_GET['dzial']]) : [],
    'rozdzial' => isset($_GET['rozdzial']) ? (is_array($_GET['rozdzial']) ? $_GET['rozdzial'] : [$_GET['rozdzial']]) : [],
    'paragraf' => isset($_GET['paragraf']) ? (is_array($_GET['paragraf']) ? $_GET['paragraf'] : [$_GET['paragraf']]) : [],
    'zrodlo' => isset($_GET['zrodlo']) ? (is_array($_GET['zrodlo']) ? $_GET['zrodlo'] : [$_GET['zrodlo']]) : [],
    'grupa' => isset($_GET['grupa']) ? (is_array($_GET['grupa']) ? $_GET['grupa'] : [$_GET['grupa']]) : [],
];

// Usuń puste wartości z filtrów
foreach ($filters as $key => $val) {
    $filters[$key] = array_filter($val, function($v) { return $v !== ''; });
}

    // Brak ograniczenia dla home.php (widok globalny tylko dla Admin/Księgowa)

// Buduj warunki SQL
$where = [];
$params = [];
if (!empty($filters['dzial'])) {
    $placeholders = implode(',', array_fill(0, count($filters['dzial']), '?'));
    $where[] = "Dział IN ($placeholders)";
    $params = array_merge($params, $filters['dzial']);
}
if (!empty($filters['rozdzial'])) {
    $placeholders = implode(',', array_fill(0, count($filters['rozdzial']), '?'));
    $where[] = "Rozdział IN ($placeholders)";
    $params = array_merge($params, $filters['rozdzial']);
}
if (!empty($filters['paragraf'])) {
    $placeholders = implode(',', array_fill(0, count($filters['paragraf']), '?'));
    $where[] = "Paragraf IN ($placeholders)";
    $params = array_merge($params, $filters['paragraf']);
}
if (!empty($filters['zrodlo'])) {
    $placeholders = implode(',', array_fill(0, count($filters['zrodlo']), '?'));
    $where[] = "ŹródłoFinansowania IN ($placeholders)";
    $params = array_merge($params, $filters['zrodlo']);
}
if (!empty($filters['grupa'])) {
    $placeholders = implode(',', array_fill(0, count($filters['grupa']), '?'));
    $where[] = "GrupaWydatków IN ($placeholders)";
    $params = array_merge($params, $filters['grupa']);
}
if (!empty($filters['komorki'])) {
    $placeholders = implode(',', array_fill(0, count($filters['komorki']), '?'));
    $where[] = "NazwaKomorki IN ($placeholders)";
    $params = array_merge($params, $filters['komorki']);
}

// Widok globalny (bez ograniczeń) z dopasowaniem także rekordów zapisanych jako "Komórka ID X"
$sql = "SELECT WPISY.ID, WPISY.Dział, WPISY.Rozdział, WPISY.Paragraf, WPISY.NazwaZadania, WPISY.NazwaKomorki, WPISY.Status, Komorki.Skrot, Komorki.Nazwa AS KomorkaNazwa, Komorki.ID AS KomorkaID, Dzialy.Nazwa as DzialNazwa FROM WPISY LEFT JOIN Komorki ON (WPISY.NazwaKomorki = Komorki.Nazwa OR WPISY.NazwaKomorki = CONCAT('Komórka ID ', Komorki.ID)) LEFT JOIN Dzialy ON WPISY.Dział = Dzialy.Kod";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY WPISY.ID DESC';
$stmt = $mysqli->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

// Podsumowania budżetowe (tylko do wyświetlenia w modalu Budżet)
$budgetTotals = [
    'budzet' => 0,
    'p2026' => 0, 'p2027' => 0, 'p2028' => 0, 'p2029' => 0,
    'l2026' => 0, 'l2027' => 0, 'l2028' => 0, 'l2029' => 0,
    'b2026' => 0, 'b2027' => 0, 'b2028' => 0, 'b2029' => 0,
    'ku2026' => 0, 'ku2027' => 0, 'ku2028' => 0, 'ku2029' => 0,
];
$aggSql = "SELECT
    SUM(IFNULL(`Budżet`,0)) AS budzet,
    SUM(IFNULL(`Potrzeby2026`,0)) AS p2026,
    SUM(IFNULL(`Potrzeby2027`,0)) AS p2027,
    SUM(IFNULL(`Potrzeby2028`,0)) AS p2028,
    SUM(IFNULL(`Potrzeby2029`,0)) AS p2029,
    SUM(IFNULL(`Limit2026`,0)) AS l2026,
    SUM(IFNULL(`Limit2027`,0)) AS l2027,
    SUM(IFNULL(`Limit2028`,0)) AS l2028,
    SUM(IFNULL(`Limit2029`,0)) AS l2029,
    SUM(IFNULL(`Braki2026`,0)) AS b2026,
    SUM(IFNULL(`Braki2027`,0)) AS b2027,
    SUM(IFNULL(`Braki2028`,0)) AS b2028,
    SUM(IFNULL(`Braki2029`,0)) AS b2029,
    SUM(IFNULL(`KwotaUmowy2026`,0)) AS ku2026,
    SUM(IFNULL(`KwotaUmowy2027`,0)) AS ku2027,
    SUM(IFNULL(`KwotaUmowy2028`,0)) AS ku2028,
    SUM(IFNULL(`KwotaUmowy2029`,0)) AS ku2029
FROM WPISY";
if ($aggRes = $mysqli->query($aggSql)) {
    $row = $aggRes->fetch_assoc();
    if ($row) {
        foreach ($budgetTotals as $k => $v) {
            if (isset($row[$k])) {
                $budgetTotals[$k] = (float)$row[$k];
            }
        }
    }
}
$fmtMoney = function($v) { return number_format((float)$v, 2, ',', ' '); };
?>
<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <title>Wnioski - Strona główna</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin:0; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #f4f4f4; padding: 24px 16px 16px 16px; border-right: 1px solid #ddd; }
        .main { flex: 1; padding: 24px; }
        .filter-group { margin-bottom: 12px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
        .filter-header { 
            background: #e0e0e0; 
            padding: 10px 12px; 
            cursor: pointer; 
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }
        .filter-header:hover { background: #d5d5d5; }
        .filter-header .arrow { font-size: 12px; transition: transform 0.2s; }
        .filter-header.active .arrow { transform: rotate(180deg); }
        .filter-content { 
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: white;
        }
        .filter-content.open { max-height: 500px; overflow-y: auto; }
        .filter-options { padding: 8px 12px; }
        .checkbox-label { display: flex; align-items: center; margin-bottom: 6px; cursor: pointer; }
        .checkbox-label input[type="checkbox"] { margin-right: 8px; }
        .checkbox-label label { margin: 0; font-weight: normal; font-size: 13px; }
        .results-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .results-table th, .results-table td { border: 1px solid #ccc; padding: 6px 8px; }
        .results-table th { background: #e9e9e9; }
        .results-table thead tr { cursor: default; }
        .results-table tbody tr { 
            cursor: pointer; 
            transition: all 0.2s ease;
            position: relative;
        }
        .results-table tbody tr:hover { 
            background: #f0f8ff; 
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .row-tooltip {
            position: fixed;
            background: #d3d3d3;
            color: #333;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            pointer-events: none;
            white-space: nowrap;
            z-index: 101;
            display: none;
        }
        .row-tooltip.visible {
            display: block;
        }
        .results-table tbody tr:hover td { position: relative; }
        .preview-btn { background: #e0f7fa; border: 1px solid #0097a7; color: #006064; padding: 4px 10px; cursor:pointer; border-radius:4px; }
        .add-btn {
            background: #27ae60;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            display: inline-block;
        }
        .add-btn:hover { background: #219150; }
        .export-btn {
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .export-btn[disabled] { opacity: 0.5; cursor: not-allowed; }
        .budget-btn {
            background: #006064;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .budget-btn:hover { background: #004d52; }
        .export-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            border-radius: 4px;
            padding: 6px;
            z-index: 200;
            min-width: 140px;
        }
        .export-menu button { display:block; width:100%; text-align:left; padding:8px 10px; border:none; background:transparent; cursor:pointer; }
        .export-menu button:hover { background:#f5f5f5; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; cursor: pointer; position: relative; }
        .status-badge.admin { cursor: pointer; }
        .status-menu { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            background: #fff; 
            border: 1px solid #ccc; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
            border-radius: 4px; 
            min-width: 150px; 
            z-index: 200; 
            display: none; 
        }
        .status-menu button { 
            display: block; 
            width: 100%; 
            text-align: left; 
            padding: 8px 10px; 
            border: none; 
            background: transparent; 
            cursor: pointer; 
            font-size: 12px; 
        }
        .status-menu button:hover { background: #f5f5f5; }
        .status-menu.open { display: block; }
        .status-szkic { background: #95a5a6; color: #fff; }
        .status-do-zatwierdzenia { background: #f39c12; color: #fff; }
        .status-zatwierdzone { background: #27ae60; color: #fff; }
        .status-odrzucone { background: #e74c3c; color: #fff; }
        .status-do-poprawy { background: #3498db; color: #fff; }
        .status-brak { background: #bdc3c7; color: #fff; }
        .details-scroll {
            box-sizing: border-box;
            position: fixed;
            top: 12vh;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            box-shadow: 0 2px 16px rgba(0,0,0,0.2);
            border: 1px solid #ccc;
            z-index: 1000;
            padding: 40px 16px 5px 10px;
            overflow: auto;
            white-space: normal;
            max-width: 96vw;
            display: none;
            max-height: 70vh;
        }
        .details-scroll.active {
            display: block;
        }
        .details-close-btn {
            position: absolute;
            top: 8px;
            right: 16px;
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: 4px 12px;
            cursor: pointer;
            font-size: 16px;
            border-radius: 4px;
        }
        .details-drag-handle {
            position: absolute;
            top: 8px;
            left: 12px;
            width: 20px;
            height: 20px;
            background-color: transparent;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%230097a7' d='M13 3h-2v4h2V3zm0 14h-2v4h2v-4zM3 11v2h4v-2H3zm14 0v2h4v-2h-4zM6.22 6.22l-1.42 1.42L8.59 11l-3.79 3.36 1.42 1.42L10 12.41 6.22 6.22zM17.78 17.78l1.42-1.42L15.41 13l3.79-3.36-1.42-1.42L14 11.59l3.78 6.19z'/></svg>");
            background-repeat: no-repeat;
            background-position: center;
            background-size: 16px 16px;
            cursor: move;
            z-index: 1010;
            display: inline-block;
        }
        .td {
            text-align: center;
        }
        .colgroup{
            text-align: center;
        }
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            margin: 0 4px;
            padding: 0;
            color: #666;
            transition: color 0.2s;
        }
        .action-btn:hover {
            color: #000;
        }
        .action-btn.delete:hover {
            color: #e74c3c;
        }
        .action-btn.edit:hover {
            color: #27ae60;
        }
        .alert {
            margin: 10px auto;
            max-width: 1200px;
            padding: 10px 14px;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .alert.success {
            background: #e6f7e8;
            color: #1e7a34;
            border-color: #b7e1c3;
        }
        .alert.error {
            background: #ffecec;
            color: #b31b1b;
            border-color: #f3c2c2;
        }
        .budget-modal {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 420px;
            max-width: 90vw;
            background: #ffffff;
            border: 1px solid #ccc;
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            border-radius: 8px;
            padding: 16px;
            z-index: 1200;
            display: none;
        }
        .budget-modal.open { display: block; }
        .budget-modal h3 { margin: 0 0 10px 0; }
        .budget-table { width:100%; border-collapse: collapse; font-size:13px; margin-top:6px; }
        .budget-table th, .budget-table td { border:1px solid #e0e0e0; padding:6px; }
        .budget-table th { background:#f5f5f5; text-align:center; }
        .budget-table td:first-child { font-weight:700; text-align:left; }
        .budget-table td { text-align:right; }
        .budget-close { position:absolute; top:8px; right:10px; background:#e74c3c; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; }
    </style>
    <script>
    let tooltip = null;
    let statusTooltip = null;
    
    function toggleBudget() {
        var modal = document.getElementById('budget-modal');
        if (!modal) return;
        modal.classList.toggle('open');
    }

    function handleRowClick(event, id) {
        // Don't open details if clicking on checkbox, button, form, or status badge
        if (event.target.closest('input[type="checkbox"]') || 
            event.target.closest('button') || 
            event.target.closest('form') ||
            event.target.closest('.status-badge') ||
            event.target.closest('.status-menu') ||
            event.target.closest('label')) {
            return;
        }
        toggleDetails(id);
    }
    
    function setupRowHover(row) {
        row.addEventListener('mouseenter', function() {
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'row-tooltip';
                tooltip.textContent = 'ℹ️ Kliknij, aby zobaczyć szczegóły';
                document.body.appendChild(tooltip);
            }
        });
        
        row.addEventListener('mousemove', function(e) {
            if (!tooltip) return;
            // Hide row tooltip if hovering over status badge
            if (e.target.closest('.status-badge')) {
                tooltip.classList.remove('visible');
                return;
            }
            tooltip.classList.add('visible');
            tooltip.style.left = (e.clientX + 15) + 'px';
            tooltip.style.top = (e.clientY + 10) + 'px';
        });
        
        row.addEventListener('mouseleave', function() {
            if (tooltip) tooltip.classList.remove('visible');
        });
    }
    
    function setupStatusHover(badge) {
        // Status badge is now read-only, no tooltip needed
    }

    
    function toggleDetails(id) {
        var detailsDiv = document.getElementById('floating-details');
        if (!detailsDiv) return;
        if (detailsDiv.classList.contains('active')) {
            detailsDiv.classList.remove('active');
            detailsDiv.innerHTML = '';
            return;
        }
        // AJAX fetch details
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'home.php?details_id=' + id, true);
        xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        detailsDiv.innerHTML = '<div class="details-drag-handle" onmousedown="startDrag(event)" ontouchstart="startDrag(event)"></div>' +                     
                            '<div style="position:absolute; top:8px; right:12px; display:flex; align-items:center; gap:8px;">' +                                
                                '<div style="position:relative; display:inline-block;">' +
                                    '<button id="detailsExportBtn" class="export-btn" title="Eksportuj" style="padding:6px 10px;font-size:14px;border-radius:4px;">Eksportuj <span style="font-size:12px;">▾</span></button>' +
                                    '<div id="detailsExportMenu" class="export-menu" style="display:none;">' +
                                        '<button type="button" class="export-option" data-format="xlsx">do .xlsx</button>' +
                                        '<button type="button" class="export-option" data-format="csv">do .csv</button>' +
                                        '<button type="button" class="export-option" data-format="docx">do .docx</button>' +
                                    '</div>' +
                                '</div>' +
                                '<button type="button" style="background:#e74c3c;color:#fff;border:none;padding:6px 10px;border-radius:4px;font-size:14px;cursor:pointer;" onclick="toggleDetails(' + id + ')">Zamknij</button>' +
                            '</div>' +
                            '<div style="overflow-x:auto;padding-bottom:18px;max-width:92vw;box-sizing:border-box;margin-top:48px;">' + xhr.responseText + '</div>';
                        detailsDiv.classList.add('active');

                        // attach local handlers for details export (export current id)
                        (function() {
                            var dExportBtn = detailsDiv.querySelector('#detailsExportBtn');
                            var dExportMenu = detailsDiv.querySelector('#detailsExportMenu');
                            if (!dExportBtn || !dExportMenu) return;

                            dExportBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                dExportMenu.style.display = (dExportMenu.style.display === 'block') ? 'none' : 'block';
                            });

                            // click on an export option -> submit form for this id
                            Array.from(dExportMenu.querySelectorAll('.export-option')).forEach(function(btn) {
                                btn.addEventListener('click', function(ev) {
                                    var format = this.getAttribute('data-format');
                                    var form = document.createElement('form');
                                    form.method = 'POST';
                                    form.action = 'export.php';
                                    form.style.display = 'none';
                                    var fmt = document.createElement('input'); fmt.name = 'format'; fmt.value = format; form.appendChild(fmt);
                                    var inp = document.createElement('input'); inp.name = 'ids[]'; inp.value = id; form.appendChild(inp);
                                    document.body.appendChild(form);
                                    form.submit();
                                });
                            });

                            // close menu when clicking outside details menu
                            detailsDiv.addEventListener('click', function(ev) {
                                if (dExportMenu.style.display === 'block' && !dExportMenu.contains(ev.target) && ev.target !== dExportBtn) {
                                    dExportMenu.style.display = 'none';
                                }
                            });
                        })();
                    }
        };
        xhr.send();
    }
    
        var dragState = { active: false, startX: 0, startY: 0, origLeft: 0, origTop: 0 };
        function startDrag(e) {
            e = e || window.event;
            e.preventDefault();
            var detailsDiv = document.getElementById('floating-details');
            if (!detailsDiv) return;
            dragState.active = true;
            var rect = detailsDiv.getBoundingClientRect();
            dragState.origLeft = rect.left + window.scrollX;
            dragState.origTop = rect.top + window.scrollY;
            if (e.touches && e.touches[0]) {
                dragState.startX = e.touches[0].clientX;
                dragState.startY = e.touches[0].clientY;
            } else {
                dragState.startX = e.clientX;
                dragState.startY = e.clientY;
            }
            document.addEventListener('mousemove', onDrag);
            document.addEventListener('mouseup', endDrag);
            document.addEventListener('touchmove', onDrag, { passive: false });
            document.addEventListener('touchend', endDrag);
        }

        function onDrag(e) {
            if (!dragState.active) return;
            e.preventDefault();
            var clientX = e.touches && e.touches[0] ? e.touches[0].clientX : e.clientX;
            var clientY = e.touches && e.touches[0] ? e.touches[0].clientY : e.clientY;
            var dx = clientX - dragState.startX;
            var dy = clientY - dragState.startY;
            var detailsDiv = document.getElementById('floating-details');
            if (!detailsDiv) return;
            var newLeft = dragState.origLeft + dx;
            var newTop = dragState.origTop + dy;
            // constrain to viewport
            var maxLeft = window.innerWidth - detailsDiv.offsetWidth - 8;
            var maxTop = window.innerHeight - detailsDiv.offsetHeight - 8;
            newLeft = Math.max(8, Math.min(newLeft, maxLeft));
            newTop = Math.max(8, Math.min(newTop, maxTop));
            detailsDiv.style.left = (newLeft + window.scrollX) + 'px';
            detailsDiv.style.top = (newTop + window.scrollY) + 'px';
            detailsDiv.style.transform = 'translateX(0)';
        }

        function endDrag() {
            if (!dragState.active) return;
            dragState.active = false;
            document.removeEventListener('mousemove', onDrag);
            document.removeEventListener('mouseup', endDrag);
            document.removeEventListener('touchmove', onDrag);
            document.removeEventListener('touchend', endDrag);
        }

        // Usuwanie tylko przez prosty formularz w wierszu (bez JS)
        
        // Funkcja do refresh statusów
        function refreshStatuses() {
            var rows = document.querySelectorAll('.results-table tbody tr');
            if (rows.length === 0) return;

            var ids = [];
            rows.forEach(function(row) {
                var id = row.id.replace('row-', '');
                if (id) ids.push(id);
            });

            if (ids.length === 0) return;

            // Chunk requests to avoid very long URLs
            var chunkSize = 50;
            for (var i = 0; i < ids.length; i += chunkSize) {
                var chunk = ids.slice(i, i + chunkSize);
                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'get_status.php?ids=' + chunk.join(','), true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.results && Array.isArray(data.results)) {
                                data.results.forEach(function(item) {
                                    var row = document.getElementById('row-' + item.id);
                                    if (row) {
                                        var badge = row.querySelector('.status-badge');
                                        if (badge && item.label && item.class) {
                                            badge.textContent = item.label;
                                            // Preserve admin class if it exists
                                            var hasAdmin = badge.classList.contains('admin');
                                            badge.className = 'status-badge ' + item.class + (hasAdmin ? ' admin' : '');
                                        }
                                    }
                                });
                            }
                        } catch(e) {
                            console.log('Parse error:', e);
                        }
                    } else {
                        console.log('XHR status error:', xhr.status);
                    }
                };
                xhr.onerror = function() {
                    console.log('XHR network error');
                };
                xhr.send();
            }
        }
        
        // Refresh NATYCHMIAST gdy strona się załaduje
        document.addEventListener('DOMContentLoaded', refreshStatuses);
        
        // Potem co 3 sekundy
        setInterval(refreshStatuses, 3000);

        // Odśwież po powrocie na kartę
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshStatuses();
            }
        });

        // Odśwież gdy wracamy przez przycisk Wstecz (bfcache)
        window.addEventListener('pageshow', function() {
            refreshStatuses();
        });

        // Gdy inna karta ustawi statusChanged_* w localStorage – odśwież jeden wiersz
        window.addEventListener('storage', function(e) {
            if (!e.key || !e.key.startsWith('statusChanged_')) return;
            var id = e.key.replace('statusChanged_', '');
            if (!id) return;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_status.php?ids=' + encodeURIComponent(id), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.results && data.results.length) {
                            data.results.forEach(function(item) {
                                var row = document.getElementById('row-' + item.id);
                                if (!row) return;
                                var badge = row.querySelector('.status-badge');
                                if (badge && item.label && item.class) {
                                    var hasAdmin = badge.classList.contains('admin');
                                    badge.textContent = item.label;
                                    badge.className = 'status-badge ' + item.class + (hasAdmin ? ' admin' : '');
                                }
                            });
                        }
                    } catch(err) {}
                }
            };
            xhr.send();
        });
    </script>
</head>
<body>
        <?php if ($deleteSuccess): ?>
            <div class="alert success"><?= htmlspecialchars($deleteSuccess) ?></div>
        <?php elseif ($deleteError): ?>
            <div class="alert error"><?= htmlspecialchars($deleteError) ?></div>
        <?php endif; ?>
<div id="floating-details" class="details-scroll"></div>
<div class="container">
    <div class="sidebar">
        <h3>Filtry</h3>
        <form method="get" action="home.php" id="filterForm">

            <!-- Komórka -->
            <div class="filter-group">
                <div class="filter-header" onclick="toggleFilter(this)">
                    <span>Komórka</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="filter-content">
                    <div class="filter-options">
                        <?php $q = $mysqli->query('SELECT DISTINCT NazwaKomorki FROM WPISY ORDER BY NazwaKomorki');
                        while ($r = $q->fetch_assoc()): ?>
                            <div class="checkbox-label">
                                <input type="checkbox" name="komorki[]" value="<?= htmlspecialchars((string)$r['NazwaKomorki']) ?>" 
                                    <?= in_array($r['NazwaKomorki'], $filters['komorki']) ? 'checked' : '' ?> />
                                <label><?= htmlspecialchars((string)$r['NazwaKomorki']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

        
            <!-- Dział -->
            <div class="filter-group">
                <div class="filter-header" onclick="toggleFilter(this)">
                    <span>Dział</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="filter-content">
                    <div class="filter-options">
                        <?php $q = $mysqli->query('SELECT DISTINCT Dział FROM WPISY ORDER BY Dział');
                        while ($r = $q->fetch_assoc()): ?>
                            <div class="checkbox-label">
                                <input type="checkbox" name="dzial[]" value="<?= htmlspecialchars((string)$r['Dział']) ?>" 
                                    <?= in_array($r['Dział'], $filters['dzial']) ? 'checked' : '' ?> />
                                <label><?= htmlspecialchars((string)$r['Dział']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Rozdział -->
            <div class="filter-group">
                <div class="filter-header" onclick="toggleFilter(this)">
                    <span>Rozdział</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="filter-content">
                    <div class="filter-options">
                        <?php $q = $mysqli->query('SELECT DISTINCT Rozdział FROM WPISY ORDER BY Rozdział');
                        while ($r = $q->fetch_assoc()): ?>
                            <div class="checkbox-label">
                                <input type="checkbox" name="rozdzial[]" value="<?= htmlspecialchars((string)$r['Rozdział']) ?>" 
                                    <?= in_array($r['Rozdział'], $filters['rozdzial']) ? 'checked' : '' ?> />
                                <label><?= htmlspecialchars((string)$r['Rozdział']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Paragraf -->
            <div class="filter-group">
                <div class="filter-header" onclick="toggleFilter(this)">
                    <span>Paragraf</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="filter-content">
                    <div class="filter-options">
                        <?php $q = $mysqli->query('SELECT DISTINCT Paragraf FROM WPISY ORDER BY Paragraf');
                        while ($r = $q->fetch_assoc()): ?>
                            <div class="checkbox-label">
                                <input type="checkbox" name="paragraf[]" value="<?= htmlspecialchars((string)$r['Paragraf']) ?>" 
                                    <?= in_array($r['Paragraf'], $filters['paragraf']) ? 'checked' : '' ?> />
                                <label><?= htmlspecialchars((string)$r['Paragraf']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Źródło finansowania -->
            <div class="filter-group">
                <div class="filter-header" onclick="toggleFilter(this)">
                    <span>Źródło finansowania</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="filter-content">
                    <div class="filter-options">
                        <?php $q = $mysqli->query('SELECT DISTINCT ŹródłoFinansowania FROM WPISY ORDER BY ŹródłoFinansowania');
                        while ($r = $q->fetch_assoc()): ?>
                            <div class="checkbox-label">
                                <input type="checkbox" name="zrodlo[]" value="<?= htmlspecialchars((string)$r['ŹródłoFinansowania']) ?>" 
                                    <?= in_array($r['ŹródłoFinansowania'], $filters['zrodlo']) ? 'checked' : '' ?> />
                                <label><?= htmlspecialchars((string)$r['ŹródłoFinansowania']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Grupa wydatków -->
            <div class="filter-group">
                <div class="filter-header" onclick="toggleFilter(this)">
                    <span>Grupa wydatków</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="filter-content">
                    <div class="filter-options">
                        <?php $q = $mysqli->query('SELECT DISTINCT GrupaWydatków FROM WPISY ORDER BY GrupaWydatków');
                        while ($r = $q->fetch_assoc()): ?>
                            <div class="checkbox-label">
                                <input type="checkbox" name="grupa[]" value="<?= htmlspecialchars((string)$r['GrupaWydatków']) ?>" 
                                    <?= in_array($r['GrupaWydatków'], $filters['grupa']) ? 'checked' : '' ?> />
                                <label><?= htmlspecialchars((string)$r['GrupaWydatków']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 16px; display: flex; gap: 8px; flex-direction: column;">
                <button type="submit" style="padding: 10px; background: #0097a7; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">🔍 Filtruj</button>
                <a href="home.php" style="color:#0097a7; text-decoration:none; font-weight:bold; text-align: center; padding: 8px;">↻ Wyczyść filtry</a>
            </div>
        </form>
        
        <script>
        function toggleFilter(header) {
            const content = header.nextElementSibling;
            const isOpen = content.classList.contains('open');
            
            // Zamknij wszystkie inne
            document.querySelectorAll('.filter-content').forEach(c => c.classList.remove('open'));
            document.querySelectorAll('.filter-header').forEach(h => h.classList.remove('active'));
            
            // Toggle obecnego
            if (!isOpen) {
                content.classList.add('open');
                header.classList.add('active');
            }
        }
        
        // Auto-otwórz sekcję z zaznaczonymi checkboxami
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.filter-group').forEach(group => {
                const hasChecked = group.querySelector('input[type=\"checkbox\"]:checked');
                if (hasChecked) {
                    const header = group.querySelector('.filter-header');
                    const content = group.querySelector('.filter-content');
                    content.classList.add('open');
                    header.classList.add('active');
                }
            });
            
            // Setup hover tooltips for all table rows
            document.querySelectorAll('.results-table tbody tr').forEach(row => {
                setupRowHover(row);
            });
            
            // Setup status hover tooltips for admin
            document.querySelectorAll('.status-badge.admin').forEach(badge => {
                setupStatusHover(badge);
            });
            
            // Export / selection logic
            const selectAll = document.getElementById('selectAll');
            const exportBtn = document.getElementById('exportBtn');
            const exportMenu = document.getElementById('exportMenu');

            function updateExportState() {
                const any = document.querySelectorAll('.row-select:checked').length > 0;
                if (exportBtn) exportBtn.disabled = !any;
                var deleteBtn = document.getElementById('deleteSelectedBtn');
                if (deleteBtn) deleteBtn.disabled = !any;
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const checked = !!this.checked;
                    document.querySelectorAll('.row-select').forEach(cb => cb.checked = checked);
                    updateExportState();
                });
            }
            document.querySelectorAll('.row-select').forEach(cb => cb.addEventListener('change', function() {
                const all = document.querySelectorAll('.row-select').length;
                const checked = document.querySelectorAll('.row-select:checked').length;
                if (selectAll) selectAll.checked = (checked === all && all > 0);
                updateExportState();
            }));

            if (exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    if (this.disabled) return;
                    exportMenu.style.display = (exportMenu.style.display === 'block') ? 'none' : 'block';
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!exportMenu) return;
                const within = exportMenu.contains(e.target) || (exportBtn && exportBtn.contains(e.target));
                if (!within) exportMenu.style.display = 'none';
            });

            // Import button handlers
            var importBtn = document.getElementById('importBtn');
            var importMenu = document.getElementById('importMenu');
            var importFileInput = document.getElementById('importFileInput');
            var selectedImportFormat = null;

            if (importBtn && importMenu) {
                importBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    importMenu.style.display = (importMenu.style.display === 'block') ? 'none' : 'block';
                });

                document.addEventListener('click', function(e) {
                    const within = importMenu.contains(e.target) || importBtn.contains(e.target);
                    if (!within) importMenu.style.display = 'none';
                });

                document.querySelectorAll('.import-option').forEach(btn => btn.addEventListener('click', function() {
                    selectedImportFormat = this.getAttribute('data-format');
                    importFileInput.accept = selectedImportFormat === 'csv' ? '.csv' : '.xlsx,.xls';
                    importFileInput.click();
                    importMenu.style.display = 'none';
                }));

                importFileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        const formData = new FormData();
                        formData.append('file', this.files[0]);
                        formData.append('format', selectedImportFormat);
                        
                        fetch('import.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Zaimportowano ' + data.count + ' rekordów');
                                location.reload();
                            } else {
                                alert('Błąd importu: ' + (data.error || 'Nieznany błąd'));
                            }
                        })
                        .catch(error => {
                            alert('Błąd połączenia: ' + error);
                        });
                        
                        this.value = '';
                    }
                });
            }

            document.querySelectorAll('.export-option').forEach(btn => btn.addEventListener('click', function() {
                const format = this.getAttribute('data-format');
                const ids = Array.from(document.querySelectorAll('.row-select:checked')).map(cb => cb.value);
                if (!ids.length) return;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'export.php';
                form.style.display = 'none';
                const fmt = document.createElement('input'); fmt.name = 'format'; fmt.value = format; form.appendChild(fmt);
                ids.forEach(id => {
                    const inp = document.createElement('input'); inp.name = 'ids[]'; inp.value = id; form.appendChild(inp);
                });
                document.body.appendChild(form);
                form.submit();
            }));

            var deleteBtn = document.getElementById('deleteSelectedBtn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    if (this.disabled) return;
                    if (!confirm('Usunąć zaznaczone wnioski?')) return;
                    const ids = Array.from(document.querySelectorAll('.row-select:checked')).map(cb => cb.value);
                    if (!ids.length) return;
                    deleteBtn.disabled = true;
                    deleteBtn.textContent = 'Usuwanie...';
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'home.php';
                    form.style.display = 'none';
                    const actionInput = document.createElement('input');
                    actionInput.name = 'action';
                    actionInput.value = 'delete_selected';
                    form.appendChild(actionInput);
                    ids.forEach(id => {
                        const inp = document.createElement('input');
                        inp.name = 'ids[]';
                        inp.value = id;
                        form.appendChild(inp);
                    });
                    document.body.appendChild(form);
                    form.submit();
                });
            }

            updateExportState();
        });
        </script>
    </div>
    <div class="main">
        <div style="display:flex;align-items:center;justify-content:flex-start;gap:10px;margin-bottom:8px;">
            <h2 style="margin:0;display:inline-block;">Lista wniosków</h2>
            <?php if (isset($_SESSION['rola']) && $_SESSION['rola'] === 'Admin'): ?>
                <a href="add.php" class="add-btn" title="Dodaj wniosek">Dodaj</a>
            <?php endif; ?>
            <div style="position:relative; display:inline-flex; gap:8px; align-items:center;">
                <button id="exportBtn" class="export-btn" disabled title="Eksportuj">Eksportuj <span style="font-size:12px;">▾</span></button>
                <div id="exportMenu" class="export-menu" style="display:none;">
                    <button type="button" class="export-option" data-format="xlsx">do .xlsx</button>
                    <button type="button" class="export-option" data-format="csv">do .csv</button>
                    <button type="button" class="export-option" data-format="docx">do .docx</button>
                </div>
                <button id="importBtn" class="export-btn" style="background:#3498db;border:1px solid #3498db;" title="Importuj">Importuj <span style="font-size:12px;">▾</span></button>
                <div id="importMenu" class="export-menu" style="display:none;">
                    <button type="button" class="import-option" data-format="xlsx">z .xlsx</button>
                    <button type="button" class="import-option" data-format="csv">z .csv</button>
                </div>
                <input type="file" id="importFileInput" style="display:none;" accept=".xlsx,.xls,.csv">
                <?php if (isset($_SESSION['rola']) && $_SESSION['rola'] === 'Admin'): ?>
                <button id="deleteSelectedBtn" class="export-btn" style="background:#e74c3c;color:#fff;border:1px solid #e74c3c;" disabled title="Usuń zaznaczone">Usuń zaznaczone</button>
                <button type="button" class="budget-btn" title="Podsumowanie budżetu" onclick="toggleBudget()">Budżet</button>
                <?php endif; ?>
            </div>
        </div>
        <table class="results-table">
            <thead>
            <tr>
                <th style="width:120px"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" id="selectAll" title="Zaznacz wszystko"><span>ID</span></label></th>
                <th>Komórka</th>
                <th>Dział</th>
                <th>Rozdział</th>
                <th>Paragraf</th>
                <th>Nazwa zadania</th>
                <th>Status</th>
                <?php if (isset($_SESSION['rola']) && $_SESSION['rola'] === 'Admin'): ?>
                <th>Akcje</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php while($row = $res->fetch_assoc()): ?>
            <tr id="row-<?= (int)$row['ID'] ?>" onclick="handleRowClick(event, <?= (int)$row['ID'] ?>)">
                <td>
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="row-select" name="select_ids[]" value="<?= (int)$row['ID'] ?>" onclick="event.stopPropagation();">
                        <span><?= htmlspecialchars((string)$row['ID']) ?></span>
                    </label>
                </td>
                    <td><?= htmlspecialchars((string)($row['Skrot'] ?? $row['KomorkaNazwa'] ?? $row['NazwaKomorki'] ?? '')) ?><?= isset($row['KomorkaNazwa']) && $row['KomorkaNazwa'] ? ' — ' . htmlspecialchars((string)$row['KomorkaNazwa']) : '' ?></td>
                <td><?= htmlspecialchars((string)($row['DzialNazwa'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)preg_replace('/^([0-9]+).*/', '$1', $row['Rozdział'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)preg_replace('/^([0-9]+).*/', '$1', $row['Paragraf'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)$row['NazwaZadania']) ?></td>
                <td style="text-align:center;">
                    <?php
                    $statusValue = $row['Status'] ?? '';
                    // Map database ENUM values to display labels
                    $statusMap = [
                        'Szkic' => 'Szkic',
                        'Do zatwierdzenia' => 'Do zatwierdzenia',
                        'Zatwierdzone' => 'Zatwierdzone',
                        'Odrzucone' => 'Odrzucone',
                        'Do poprawy' => 'Do poprawy'
                    ];
                    $statusLabel = $statusMap[$statusValue] ?? 'Brak statusu';
                    // Map to CSS class
                    $statusClassMap = [
                        'Szkic' => 'status-szkic',
                        'Do zatwierdzenia' => 'status-do-zatwierdzenia',
                        'Zatwierdzone' => 'status-zatwierdzone',
                        'Odrzucone' => 'status-odrzucone',
                        'Do poprawy' => 'status-do-poprawy'
                    ];
                    $statusClass = $statusClassMap[$statusValue] ?? 'status-brak';
                    ?>
                    <span class="status-badge <?= $statusClass ?> <?= ($_SESSION['rola'] === 'Admin' ? 'admin' : '') ?>"><?= $statusLabel ?></span>
                </td>
                <?php if (isset($_SESSION['rola']) && in_array($_SESSION['rola'], ['Admin', 'Ksiegowosc'], true)): ?>
                <td style="text-align: center;" onclick="event.stopPropagation();">
                    <button class="action-btn edit" onclick="event.stopPropagation(); window.location.href='edit.php?id=<?= (int)$row['ID'] ?>'" title="Edytuj">
                        ✎
                    </button>
                    <!-- Jedyny przycisk usuń: prosty POST do PHP bez JS -->
                    <form method="post" action="home.php" style="display:inline;" onsubmit="event.stopPropagation(); return confirm('Usunąć ten wniosek?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$row['ID'] ?>">
                        <button type="submit" class="action-btn delete" title="Usuń" onclick="event.stopPropagation();">🗑</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
    <?php if (isset($_SESSION['rola']) && $_SESSION['rola'] === 'Admin'): ?>
    <div id="budget-modal" class="budget-modal">
        <button class="budget-close" onclick="toggleBudget()">×</button>
        <h3>Podsumowanie budżetu</h3>
        <div style="margin-bottom:8px; font-size:13px;"><strong>Budżet łączny: (w tys)</strong> <?= $fmtMoney($budgetTotals['budzet'] ?? 0) ?></div>
        <table class="budget-table">
            <thead>
                <tr>
                    <th></th>
                    <th>2026</th>
                    <th>2027</th>
                    <th>2028</th>
                    <th>2029</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Potrzeby</td>
                    <td><?= $fmtMoney($budgetTotals['p2026'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['p2027'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['p2028'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['p2029'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Limity</td>
                    <td><?= $fmtMoney($budgetTotals['l2026'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['l2027'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['l2028'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['l2029'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Braki</td>
                    <td><?= $fmtMoney($budgetTotals['b2026'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['b2027'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['b2028'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['b2029'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Kwoty umów</td>
                    <td><?= $fmtMoney($budgetTotals['ku2026'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['ku2027'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['ku2028'] ?? 0) ?></td>
                    <td><?= $fmtMoney($budgetTotals['ku2029'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php require 'navbar.php'; ?>
</body>
</html>
