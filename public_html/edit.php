<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: index.php');
    exit;
}

$mysqli = require 'db.php';

// Get entry ID from URL
if (!isset($_GET['id'])) {
    header('Location: home.php');
    exit;
}

$id = (int)$_GET['id'];

// Fetch entry data
$result = $mysqli->query('SELECT * FROM WPISY WHERE ID=' . $id);
if (!$result || $result->num_rows === 0) {
    header('Location: home.php');
    exit;
}

$entry = $result->fetch_assoc();

// Check if user has permission to edit (must be Admin, Ksiegowosc, or from same Komorka)
if (!in_array($_SESSION['rola'], ['Admin', 'Ksiegowosc'], true)) {
    // Get user's Komorka name
    $userKomorkaStmt = $mysqli->prepare('SELECT Nazwa FROM Komorki WHERE ID = ?');
    $userKomorkaStmt->bind_param('i', $_SESSION['dzial_id']);
    $userKomorkaStmt->execute();
    $userKomorkaResult = $userKomorkaStmt->get_result();
    $userKomorkaRow = $userKomorkaResult->fetch_assoc();
    $userKomorkaName = $userKomorkaRow ? $userKomorkaRow['Nazwa'] : '';
    
    // If entry belongs to different Komorka, deny access
    if ($entry['NazwaKomorki'] !== $userKomorkaName) {
        header('Location: home.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['CzęśćBudżetowa', 'Dział', 'Rozdział', 'Paragraf', 'ŹródłoFinansowania', 'GrupaWydatków',
               'BudżetZadaniowySzczeg', 'BudżetZadaniowyNr', 'NazwaProgramu', 'NazwaKomorki', 'PlanWI',
               'DysponentŚrodków', 'Budżet', 'NazwaZadania', 'SzczegUzasadnienie', 'PrzeznaczenieWydatków',
               'DotacjaZKim', 'PodstawaPrawna', 'Uwagi', 'Status'];
    
    // Add year fields
    foreach ([2026, 2027, 2028, 2029] as $year) {
        $fields[] = "Potrzeby{$year}";
        $fields[] = "Limit{$year}";
        $fields[] = "Braki{$year}";
        $fields[] = "KwotaUmowy{$year}";
        $fields[] = "NrUmowy{$year}";
    }
    
    $updates = [];
    $params = [];
    $types = '';
    
    foreach ($fields as $f) {
        // Skip Status field - it causes database truncation errors
        if ($f === 'Status') continue;
        
        // Skip NazwaKomorki for Admin (preserve original value)
        if ($f === 'NazwaKomorki' && $_SESSION['rola'] === 'Admin') continue;
        
        if (isset($_POST[$f])) {
            $value = $_POST[$f];
            if ($value === '') {
                $updates[] = "$f = NULL";
            } else {
                $updates[] = "$f = ?";
                $params[] = $value;
                $types .= 's';
            }
        }
    }
    
    if (!empty($updates)) {
        $params[] = $id;
        $types .= 'i';
        
        $sql = 'UPDATE WPISY SET ' . implode(', ', $updates) . ' WHERE ID = ?';
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $success = 'Zmiany zapisane!';
                // Re-fetch updated data
                $result = $mysqli->query('SELECT * FROM WPISY WHERE ID=' . $id);
                $entry = $result->fetch_assoc();
            } else {
                $error = 'Błąd aktualizacji: ' . $stmt->error;
            }
        } else {
            $error = 'Błąd przygotowania zapytania: ' . $mysqli->error;
        }
    }
}

// Fetch dropdown options from reference tables
$czesciBudzetowe = $mysqli->query('SELECT Kod, Nazwa FROM CzesciBudzetowe ORDER BY Kod');
$dzial = $mysqli->query('SELECT Kod, Nazwa FROM Dzialy ORDER BY Kod');
$paragraf = $mysqli->query('SELECT Kod3, Nazwa FROM Paragrafy3Cyfry ORDER BY Kod3');
// Źródło finansowania to ostatnia cyfra Kodu z ZrodlaIGrupy
$zrodlo = $mysqli->query('SELECT DISTINCT RIGHT(Kod, 1) as Zrodlo FROM ZrodlaIGrupy ORDER BY Zrodlo');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edytuj wpis</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial; margin: 0; padding: 0; }
        .container { display: flex; min-height: 100vh; }
        .main { flex: 1; padding: 24px; max-width: 1200px; margin: 0 auto; }
        h2 { margin-top: 0; color: #333; }
        .section { background: #f9f9f9; padding: 20px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #ddd; }
        .section h3 { margin-top: 0; color: #0097a7; border-bottom: 2px solid #0097a7; padding-bottom: 8px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: bold; margin-bottom: 4px; color: #555; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { 
            padding: 8px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            font-size: 14px;
            font-family: Arial;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .full-width { grid-column: 1 / -1; }
        
        .year-toggle { margin-bottom: 20px; }
        .year-toggle label { 
            display: inline-flex; 
            align-items: center; 
            margin-right: 20px; 
            cursor: pointer;
            padding: 8px 16px;
            background: #e0f7fa;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .year-toggle label:hover { background: #b2ebf2; }
        .year-toggle input[type="checkbox"] { margin-right: 8px; width: auto; }
        
        .year-section { 
            display: none; 
            background: white; 
            padding: 16px; 
            border-radius: 4px; 
            border: 2px solid #0097a7;
            margin-top: 12px;
        }
        .year-section.active { display: block; }
        .year-section h4 { margin-top: 0; color: #0097a7; }
        
        .button-group { display: flex; gap: 12px; margin-top: 24px; }
        .button-group button, .button-group a { 
            padding: 12px 24px; 
            border: none; 
            cursor: pointer; 
            border-radius: 4px; 
            text-decoration: none; 
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
        }
        .button-group button { background: #0097a7; color: white; }
        .button-group button:hover { background: #00838f; }
        .button-group a { background: #ddd; color: #333; }
        .button-group a:hover { background: #ccc; }
        
        .success { color: green; padding: 12px; background: #e8f5e9; border-radius: 4px; margin-bottom: 16px; }
        .error { color: red; padding: 12px; background: #ffe6e6; border-radius: 4px; margin-bottom: 16px; }
    </style>
    <script>
        function toggleYear(checkbox, year) {
            const section = document.getElementById('year-' + year);
            if (checkbox.checked) {
                section.classList.add('active');
            } else {
                section.classList.remove('active');
            }
        }
        
        function updateRozdzialy(dzial) {
            var rozdzial = document.getElementById('Rozdział');
            var currentValue = rozdzial.value; // Preserve current selection
            rozdzial.innerHTML = '<option value="">-- ładowanie... --</option>';
            if (!dzial) {
                rozdzial.innerHTML = '<option value="">-- najpierw wybierz Dział --</option>';
                return;
            }
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_rozdzialy.php?dzial=' + encodeURIComponent(dzial), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    rozdzial.innerHTML = '<option value="">-- wybierz --</option>' + xhr.responseText;
                    // Restore previously selected value if it exists
                    if (currentValue) {
                        rozdzial.value = currentValue;
                    }
                } else {
                    rozdzial.innerHTML = '<option value="">-- błąd ładowania --</option>';
                }
            };
            xhr.onerror = function() {
                rozdzial.innerHTML = '<option value="">-- błąd połączenia --</option>';
            };
            xhr.send();
        }
        
        function updateGrupaWydatkow() {
            var paragraf = document.getElementById('Paragraf').value;
            var grupaInput = document.getElementById('GrupaWydatków');
            if (!paragraf) {
                grupaInput.value = '';
                return;
            }
            grupaInput.value = 'ładowanie...';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_grupa_wydatkow.php?paragraf=' + encodeURIComponent(paragraf), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    grupaInput.value = xhr.responseText;
                } else {
                    grupaInput.value = 'błąd';
                }
            };
            xhr.onerror = function() {
                grupaInput.value = 'błąd połączenia';
            };
            xhr.send();
        }
        
        function updateBudgetNumber(szczeg) {
            var budgetInput = document.getElementById('BudżetZadaniowyNr');
            if (!szczeg) {
                budgetInput.value = '';
                return;
            }
            if (!/^[0-9]+(\.[0-9]+){1,3}$/.test(szczeg)) {
                budgetInput.value = '';
                return;
            }
            budgetInput.value = 'ładowanie...';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_budget_number.php?szczeg=' + encodeURIComponent(szczeg), true);
            xhr.onload = function() {
                if (xhr.status === 200 && xhr.responseText) {
                    budgetInput.value = xhr.responseText;
                } else {
                    budgetInput.value = '';
                }
            };
            xhr.onerror = function() {
                budgetInput.value = '';
            };
            xhr.send();
        }
        
        function updateNazwaProgramu(zrodlo) {
            var nazwaInput = document.getElementById('NazwaProgramu');
            if (zrodlo === '0') {
                nazwaInput.value = 'NIE DOTYCZY';
                nazwaInput.readOnly = true;
                nazwaInput.style.background = '#f0f0f0';
                nazwaInput.style.cursor = 'not-allowed';
            } else {
                nazwaInput.readOnly = false;
                nazwaInput.style.background = 'white';
                nazwaInput.style.cursor = 'auto';
            }
        }
        
        // Handle Status field - update via AJAX and notify home.php
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('Status');
            if (!statusSelect) return;

            statusSelect.addEventListener('change', function() {
                const newStatus = this.value;
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_status.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        localStorage.setItem('statusChanged_<?= $id ?>', Date.now());
                    }
                };
                xhr.send('id=<?= $id ?>&status=' + encodeURIComponent(newStatus));
            });
        });
    </script>
</head>
<body>
<div class="container">
    <div class="main">
        <h2>Edytuj wpis #<?= (int)$id ?></h2>
        
        <script>
            // Załaduj Rozdziały dla bieżącego Działu
            document.addEventListener('DOMContentLoaded', function() {
                const dzialSelect = document.getElementById('Dział');
                if (dzialSelect.value) {
                    updateRozdzialy(dzialSelect.value);
                }
            });
        </script>
        
        <?php if (isset($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <!-- PODSTAWOWE KLASYFIKACJE -->
            <div class="section">
                <h3>Podstawowe klasyfikacje budżetowe</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="CzęśćBudżetowa">Część budżetowa</label>
                        <select name="CzęśćBudżetowa" id="CzęśćBudżetowa" required>
                            <option value="">-- wybierz --</option>
                            <?php if ($czesciBudzetowe) while ($r = $czesciBudzetowe->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Kod']) ?>" <?= $entry['CzęśćBudżetowa'] == $r['Kod'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$r['Kod']) ?> - <?= htmlspecialchars((string)$r['Nazwa']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="Dział">Dział *</label>
                        <select name="Dział" id="Dział" onchange="updateRozdzialy(this.value)" required>
                            <option value="">-- wybierz --</option>
                            <?php if ($dzial) while ($r = $dzial->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Kod']) ?>" <?= $entry['Dział'] == $r['Kod'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$r['Kod']) ?> - <?= htmlspecialchars((string)$r['Nazwa']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="Rozdział">Rozdział *</label>
                        <select name="Rozdział" id="Rozdział" required>
                            <option value="">-- najpierw wybierz Dział --</option>
                            <option value="<?= htmlspecialchars((string)$entry['Rozdział']) ?>" selected><?= htmlspecialchars((string)$entry['Rozdział']) ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="Paragraf">Paragraf (3 cyfry) *</label>
                        <select name="Paragraf" id="Paragraf" onchange="updateGrupaWydatkow()" required>
                            <option value="">-- wybierz --</option>
                            <?php if ($paragraf) while ($r = $paragraf->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Kod3']) ?>" <?= $entry['Paragraf'] == $r['Kod3'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$r['Kod3']) ?> - <?= htmlspecialchars((string)$r['Nazwa']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ŹródłoFinansowania">Źródło finansowania (1 cyfra) *</label>
                        <select name="ŹródłoFinansowania" id="ŹródłoFinansowania" onchange="updateNazwaProgramu(this.value)" required>
                            <option value="">-- wybierz --</option>
                            <?php if ($zrodlo) while ($r = $zrodlo->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Zrodlo']) ?>" <?= $entry['ŹródłoFinansowania'] == $r['Zrodlo'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$r['Zrodlo']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="GrupaWydatków">Grupa wydatków (auto) *</label>
                        <input type="text" name="GrupaWydatków" id="GrupaWydatków" readonly style="background:#f0f0f0; cursor:not-allowed;" value="<?= htmlspecialchars((string)$entry['GrupaWydatków']) ?>" required>
                    </div>
                </div>
            </div>
            
            <!-- DANE ZADANIA -->
            <div class="section">
                <h3>Dane zadania</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="BudżetZadaniowySzczeg">Budżet zadaniowy w pełnej szczegółowości</label>
                        <input type="text" name="BudżetZadaniowySzczeg" id="BudżetZadaniowySzczeg" pattern="[0-9]+(\.[0-9]+){1,3}" maxlength="20" placeholder="np. 1.5, 1.6.1, 22.01.01.01" title="Format: liczby oddzielone kropkami (np. 1.5, 1.6.1, 1.2.2.1, 22.01.01.01)" onchange="updateBudgetNumber(this.value)" value="<?= htmlspecialchars((string)$entry['BudżetZadaniowySzczeg']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="BudżetZadaniowyNr">Budżet zadaniowy nr funkcji, nr zadania</label>
                        <input type="text" name="BudżetZadaniowyNr" id="BudżetZadaniowyNr" readonly style="background:#f0f0f0; cursor:not-allowed;" placeholder="auto-wypełniane" value="<?= htmlspecialchars((string)$entry['BudżetZadaniowyNr']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="NazwaProgramu">Nazwa programu/projektu</label>
                        <input type="text" name="NazwaProgramu" id="NazwaProgramu" style="background: white; cursor: auto;" value="<?= htmlspecialchars((string)$entry['NazwaProgramu']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="NazwaKomorki">Nazwa komórki organizacyjnej</label>
                        <input type="text" name="NazwaKomorki_display" id="NazwaKomorki" readonly style="background:#f0f0f0; cursor:not-allowed;" value="<?= htmlspecialchars((string)$entry['NazwaKomorki']) ?>" required>
                        <input type="hidden" name="NazwaKomorki" value="<?= htmlspecialchars((string)$entry['NazwaKomorki']) ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="PlanWI">Plan WI <span style="font-weight: normal; color: #999; font-size: 12px;">(opcjonalne)</span></label>
                        <input type="text" name="PlanWI" id="PlanWI" value="<?= htmlspecialchars((string)$entry['PlanWI']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="DysponentŚrodków">Dysponent Środków <span style="font-weight: normal; color: #999; font-size: 12px;">(opcjonalne)</span></label>
                        <input type="text" name="DysponentŚrodków" id="DysponentŚrodków" value="<?= htmlspecialchars((string)$entry['DysponentŚrodków']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="Budżet">Budżet <span style="font-weight: normal; color: #999; font-size: 12px;">(opcjonalne)</span></label>
                        <input type="text" name="Budżet" id="Budżet" value="<?= htmlspecialchars((string)$entry['Budżet']) ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="NazwaZadania">Nazwa zadania *</label>
                        <input type="text" name="NazwaZadania" id="NazwaZadania" required value="<?= htmlspecialchars((string)$entry['NazwaZadania']) ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="SzczegUzasadnienie">Szczegółowe uzasadnienie realizacji zadania <span style="font-weight: normal; color: #999; font-size: 12px;">(szczegółowe uzasadnienie do informacji w kolumnie 14)</span></label>
                        <textarea name="SzczegUzasadnienie" id="SzczegUzasadnienie"><?= htmlspecialchars((string)$entry['SzczegUzasadnienie']) ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="PrzeznaczenieWydatków">Przeznaczenie wydatków wg obszaru działalności</label>
                        <textarea name="PrzeznaczenieWydatków" id="PrzeznaczenieWydatków"><?= htmlspecialchars((string)$entry['PrzeznaczenieWydatków']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="DotacjaZKim">Dotacja z kim</label>
                        <input type="text" name="DotacjaZKim" id="DotacjaZKim" value="<?= htmlspecialchars((string)$entry['DotacjaZKim']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="PodstawaPrawna">Podstawa prawna udzielenia dotacji</label>
                        <input type="text" name="PodstawaPrawna" id="PodstawaPrawna" value="<?= htmlspecialchars((string)$entry['PodstawaPrawna']) ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="Uwagi">Uwagi</label>
                        <textarea name="Uwagi" id="Uwagi"><?= htmlspecialchars((string)$entry['Uwagi']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="Status">Status</label>
                        <select name="Status" id="Status">
                            <option value="" <?= empty($entry['Status']) ? 'selected' : '' ?>>-- brak --</option>
                            <option value="Szkic" <?= $entry['Status'] === 'Szkic' ? 'selected' : '' ?>>Szkic</option>
                            <option value="Do zatwierdzenia" <?= $entry['Status'] === 'Do zatwierdzenia' ? 'selected' : '' ?>>Do zatwierdzenia</option>
                            <option value="Zatwierdzone" <?= $entry['Status'] === 'Zatwierdzone' ? 'selected' : '' ?>>Zatwierdzone</option>
                            <option value="Odrzucone" <?= $entry['Status'] === 'Odrzucone' ? 'selected' : '' ?>>Odrzucone</option>
                            <option value="Do poprawy" <?= $entry['Status'] === 'Do poprawy' ? 'selected' : '' ?>>Do poprawy</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- DANE BUDŻETOWE DLA LAT -->
            <div class="section">
                <h3>Dane budżetowe dla lat</h3>
                <p style="color: #666; font-size: 13px; margin-bottom: 16px;">Zaznacz lata, dla których chcesz wprowadzić dane budżetowe:</p>
                
                <div class="year-toggle">
                    <label><input type="checkbox" onchange="toggleYear(this, 2026)" <?= !empty($entry['Potrzeby2026']) || !empty($entry['Limit2026']) ? 'checked' : '' ?>> <strong>2026</strong></label>
                    <label><input type="checkbox" onchange="toggleYear(this, 2027)" <?= !empty($entry['Potrzeby2027']) || !empty($entry['Limit2027']) ? 'checked' : '' ?>> <strong>2027</strong></label>
                    <label><input type="checkbox" onchange="toggleYear(this, 2028)" <?= !empty($entry['Potrzeby2028']) || !empty($entry['Limit2028']) ? 'checked' : '' ?>> <strong>2028</strong></label>
                    <label><input type="checkbox" onchange="toggleYear(this, 2029)" <?= !empty($entry['Potrzeby2029']) || !empty($entry['Limit2029']) ? 'checked' : '' ?>> <strong>2029</strong></label>
                </div>
                
                <!-- ROK 2026 -->
                <div id="year-2026" class="year-section" <?= !empty($entry['Potrzeby2026']) || !empty($entry['Limit2026']) ? 'style="display:block"' : '' ?>>
                    <h4>Rok 2026</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2026">Potrzeby finansowe na 2026 (kwota)</label>
                            <input type="number" name="Potrzeby2026" id="Potrzeby2026" step="0.01" value="<?= htmlspecialchars((string)$entry['Potrzeby2026']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Limit2026">Limit wydatków na rok 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2026" id="Limit2026" step="0.01" value="<?= htmlspecialchars((string)$entry['Limit2026']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Braki2026">Kwota na realizację zadań w 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2026" id="Braki2026" step="0.01" value="<?= htmlspecialchars((string)$entry['Braki2026']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2026">Kwota zawartej umowy 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2026" id="KwotaUmowy2026" step="0.01" value="<?= htmlspecialchars((string)$entry['KwotaUmowy2026']) ?>">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2026">Nr wniosku o udzielenie zamówienia publicznego 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2026" id="NrUmowy2026" value="<?= htmlspecialchars((string)$entry['NrUmowy2026']) ?>">
                        </div>
                    </div>
                </div>
                
                <!-- ROK 2027 -->
                <div id="year-2027" class="year-section" <?= !empty($entry['Potrzeby2027']) || !empty($entry['Limit2027']) ? 'style="display:block"' : '' ?>>
                    <h4>Rok 2027</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2027">Potrzeby finansowe na 2027 (kwota)</label>
                            <input type="number" name="Potrzeby2027" id="Potrzeby2027" step="0.01" value="<?= htmlspecialchars((string)$entry['Potrzeby2027']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Limit2027">Limit wydatków na rok 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2027" id="Limit2027" step="0.01" value="<?= htmlspecialchars((string)$entry['Limit2027']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Braki2027">Kwota na realizację zadań w 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2027" id="Braki2027" step="0.01" value="<?= htmlspecialchars((string)$entry['Braki2027']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2027">Kwota zawartej umowy 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2027" id="KwotaUmowy2027" step="0.01" value="<?= htmlspecialchars((string)$entry['KwotaUmowy2027']) ?>">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2027">Nr wniosku o udzielenie zamówienia publicznego 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2027" id="NrUmowy2027" value="<?= htmlspecialchars((string)$entry['NrUmowy2027']) ?>">
                        </div>
                    </div>
                </div>
                
                <!-- ROK 2028 -->
                <div id="year-2028" class="year-section" <?= !empty($entry['Potrzeby2028']) || !empty($entry['Limit2028']) ? 'style="display:block"' : '' ?>>
                    <h4>Rok 2028</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2028">Potrzeby finansowe na 2028 (kwota)</label>
                            <input type="number" name="Potrzeby2028" id="Potrzeby2028" step="0.01" value="<?= htmlspecialchars((string)$entry['Potrzeby2028']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Limit2028">Limit wydatków na rok 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2028" id="Limit2028" step="0.01" value="<?= htmlspecialchars((string)$entry['Limit2028']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Braki2028">Kwota na realizację zadań w 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2028" id="Braki2028" step="0.01" value="<?= htmlspecialchars((string)$entry['Braki2028']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2028">Kwota zawartej umowy 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2028" id="KwotaUmowy2028" step="0.01" value="<?= htmlspecialchars((string)$entry['KwotaUmowy2028']) ?>">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2028">Nr wniosku o udzielenie zamówienia publicznego 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2028" id="NrUmowy2028" value="<?= htmlspecialchars((string)$entry['NrUmowy2028']) ?>">
                        </div>
                    </div>
                </div>
                
                <!-- ROK 2029 -->
                <div id="year-2029" class="year-section" <?= !empty($entry['Potrzeby2029']) || !empty($entry['Limit2029']) ? 'style="display:block"' : '' ?>>
                    <h4>Rok 2029</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2029">Potrzeby finansowe na 2029 (kwota)</label>
                            <input type="number" name="Potrzeby2029" id="Potrzeby2029" step="0.01" value="<?= htmlspecialchars((string)$entry['Potrzeby2029']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Limit2029">Limit wydatków na rok 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2029" id="Limit2029" step="0.01" value="<?= htmlspecialchars((string)$entry['Limit2029']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="Braki2029">Kwota na realizację zadań w 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2029" id="Braki2029" step="0.01" value="<?= htmlspecialchars((string)$entry['Braki2029']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2029">Kwota zawartej umowy 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2029" id="KwotaUmowy2029" step="0.01" value="<?= htmlspecialchars((string)$entry['KwotaUmowy2029']) ?>">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2029">Nr wniosku o udzielenie zamówienia publicznego 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2029" id="NrUmowy2029" value="<?= htmlspecialchars((string)$entry['NrUmowy2029']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="button-group">
                <button type="submit">✓ Zapisz zmiany</button>
                <a href="home.php">✗ Anuluj</a>
            </div>
        </form>
    </div>
</div>

<?php require 'navbar.php'; ?>
</body>
</html>
