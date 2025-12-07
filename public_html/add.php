<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: index.php');
    exit;
}

$mysqli = require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($error)) {
    $fields = ['CzęśćBudżetowa', 'Dział', 'Rozdział', 'Paragraf', 'ŹródłoFinansowania', 'GrupaWydatków',
               'BudżetZadaniowySzczeg', 'BudżetZadaniowyNr', 'NazwaProgramu', 'NazwaKomorki', 'PlanWI',
               'DysponentŚrodków', 'Budżet', 'NazwaZadania', 'SzczegUzasadnienie', 'PrzeznaczenieWydatków',
               'DotacjaZKim', 'PodstawaPrawna', 'Uwagi'];
    
    // Add year fields
    foreach ([2026, 2027, 2028, 2029] as $year) {
        $fields[] = "Potrzeby{$year}";
        $fields[] = "Limit{$year}";
        $fields[] = "Braki{$year}";
        $fields[] = "KwotaUmowy{$year}";
        $fields[] = "NrUmowy{$year}";
    }
    
    $cols = [];
    $vals = [];
    $params = [];
    $types = '';
    
    foreach ($fields as $f) {
        if (isset($_POST[$f]) && $_POST[$f] !== '') {
            $cols[] = $f;
            $vals[] = '?';
            $params[] = $_POST[$f];
            $types .= 's';
        }
    }
    
    if ($cols) {
        $sql = 'INSERT INTO WPISY (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        // Po dodaniu kieruj na widok zależnie od roli
        if (isset($_SESSION['rola']) && $_SESSION['rola'] === 'Dzial') {
            header('Location: home_dzial.php');
        } else {
            header('Location: home.php');
        }
        exit;
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
    <title>Dodaj wpis</title>
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
        
        .error { color: red; padding: 12px; background: #ffe6e6; border-radius: 4px; margin-bottom: 16px; }
    </style>
    <script>
        function toggleYear(checkbox, year) {
            const section = document.getElementById('year-' + year);
            if (checkbox.checked) {
                section.classList.add('active');
            } else {
                section.classList.remove('active');
                // Clear all inputs in this section
                section.querySelectorAll('input').forEach(inp => inp.value = '');
            }
        }
        
        function updateRozdzialy(dzial) {
            var rozdzial = document.getElementById('Rozdział');
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
            // Validate format: 2-4 parts (1.5, 1.6.1, 1.2.2.1, 22.01.01.01, etc.)
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
                nazwaInput.value = '';
                nazwaInput.readOnly = false;
                nazwaInput.style.background = 'white';
                nazwaInput.style.cursor = 'auto';
            }
        }
    </script>
</head>
<body>
<div class="container">
    <div class="main">
        <h2>Dodaj nowy wpis</h2>
        
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
                        <select name="CzęśćBudżetowa" id="CzęśćBudżetowa">
                            <option value="">-- wybierz --</option>
                            <?php if ($czesciBudzetowe) while ($r = $czesciBudzetowe->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Kod']) ?>"><?= htmlspecialchars((string)$r['Kod']) ?> - <?= htmlspecialchars((string)$r['Nazwa']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="Dział">Dział *</label>
                        <select name="Dział" id="Dział" onchange="updateRozdzialy(this.value)" required>
                            <option value="">-- wybierz --</option>
                            <?php if ($dzial) while ($r = $dzial->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Kod']) ?>"><?= htmlspecialchars((string)$r['Kod']) ?> - <?= htmlspecialchars((string)$r['Nazwa']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="Rozdział">Rozdział *</label>
                        <select name="Rozdział" id="Rozdział" required>
                            <option value="">-- najpierw wybierz Dział --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="Paragraf">Paragraf (3 cyfry) *</label>
                        <select name="Paragraf" id="Paragraf" onchange="updateGrupaWydatkow()" required>
                            <option value="">-- wybierz --</option>
                            <?php if ($paragraf) while ($r = $paragraf->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Kod3']) ?>"><?= htmlspecialchars((string)$r['Kod3']) ?> - <?= htmlspecialchars((string)$r['Nazwa']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ŹródłoFinansowania">Źródło finansowania (1 cyfra) *</label>
                        <select name="ŹródłoFinansowania" id="ŹródłoFinansowania" onchange="updateNazwaProgramu(this.value)" required>
                            <option value="">-- wybierz --</option>
                            <?php if ($zrodlo) while ($r = $zrodlo->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars((string)$r['Zrodlo']) ?>"><?= htmlspecialchars((string)$r['Zrodlo']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="GrupaWydatków">Grupa wydatków (auto) *</label>
                        <input type="text" name="GrupaWydatków" id="GrupaWydatków" readonly style="background:#f0f0f0; cursor:not-allowed;" placeholder="wybierz Paragraf i Źródło" required>
                    </div>
                </div>
            </div>
            
            <!-- DANE ZADANIA -->
            <div class="section">
                <h3>Dane zadania</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="BudżetZadaniowySzczeg">Budżet zadaniowy w pełnej szczegółowości</label>
                        <input type="text" name="BudżetZadaniowySzczeg" id="BudżetZadaniowySzczeg" pattern="[0-9]+(\.[0-9]+){1,3}" maxlength="20" placeholder="np. 1.5, 1.6.1, 22.01.01.01" title="Format: liczby oddzielone kropkami (np. 1.5, 1.6.1, 1.2.2.1, 22.01.01.01)" onchange="updateBudgetNumber(this.value)">
                    </div>
                    
                    <div class="form-group">
                        <label for="BudżetZadaniowyNr">Budżet zadaniowy nr funkcji, nr zadania</label>
                        <input type="text" name="BudżetZadaniowyNr" id="BudżetZadaniowyNr" readonly style="background:#f0f0f0; cursor:not-allowed;" placeholder="auto-wypełniane">
                    </div>
                    
                    <div class="form-group">
                        <label for="NazwaProgramu">Nazwa programu/projektu</label>
                        <input type="text" name="NazwaProgramu" id="NazwaProgramu" style="background: white; cursor: auto;">
                    </div>
                    
                    <?php
                        $addKomorkaID = $_SESSION['dzial_id'] ?? null;
                        $addKomorkaName = '';
                        if ($addKomorkaID) {
                            $komorkaStmt = $mysqli->prepare('SELECT Nazwa FROM Komorki WHERE ID = ?');
                            if ($komorkaStmt) {
                                $komorkaStmt->bind_param('i', $addKomorkaID);
                                $komorkaStmt->execute();
                                $komorkaResult = $komorkaStmt->get_result();
                                if ($komorkaRow = $komorkaResult->fetch_assoc()) {
                                    $addKomorkaName = $komorkaRow['Nazwa'] ?? '';
                                }
                            }
                        }
                        if ($addKomorkaName === '' && $addKomorkaID) {
                            $addKomorkaName = 'Komórka ID ' . $addKomorkaID; // fallback
                        }
                    ?>
                    <div class="form-group">
                        <label for="NazwaKomorki">Nazwa komórki organizacyjnej</label>
                        <input type="text" name="NazwaKomorki_display" id="NazwaKomorki_display" readonly style="background:#f0f0f0; cursor:not-allowed;" value="<?= htmlspecialchars((string)$addKomorkaName) ?>" required>
                        <input type="hidden" name="NazwaKomorki" value="<?= htmlspecialchars((string)$addKomorkaName) ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="PlanWI">Plan WI <span style="font-weight: normal; color: #999; font-size: 12px;">(opcjonalne)</span></label>
                        <input type="text" name="PlanWI" id="PlanWI">
                    </div>
                    <div class="form-group">
                        <label for="DysponentŚrodków">Dysponent Środków <span style="font-weight: normal; color: #999; font-size: 12px;">(opcjonalne)</span></label>
                        <input type="text" name="DysponentŚrodków" id="DysponentŚrodków">
                    </div>
                    <div class="form-group">
                        <label for="Budżet">Budżet <span style="font-weight: normal; color: #999; font-size: 12px;">(opcjonalne)</span></label>
                        <input type="text" name="Budżet" id="Budżet">
                    </div>
                    <div class="form-group full-width">
                        <label for="NazwaZadania">Nazwa zadania *</label>
                        <input type="text" name="NazwaZadania" id="NazwaZadania" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="SzczegUzasadnienie">Szczegółowe uzasadnienie realizacji zadania <span style="font-weight: normal; color: #999; font-size: 12px;"></span></label>
                        <textarea name="SzczegUzasadnienie" id="SzczegUzasadnienie"></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="PrzeznaczenieWydatków">Przeznaczenie wydatków wg obszaru działalności</label>
                        <textarea name="PrzeznaczenieWydatków" id="PrzeznaczenieWydatków"></textarea>
                    </div>
                    <!-- Potrzeby finansowe na 2026 pojawią się po zaznaczeniu checkboxa poniżej (sekcja lat) -->
                    <div class="form-group">
                        <label for="DotacjaZKim">Dotacja z kim</label>
                        <input type="text" name="DotacjaZKim" id="DotacjaZKim">
                    </div>
                    <div class="form-group">
                        <label for="PodstawaPrawna">Podstawa prawna udzielenia dotacji</label>
                        <input type="text" name="PodstawaPrawna" id="PodstawaPrawna">
                    </div>
                    <div class="form-group full-width">
                        <label for="Uwagi">Uwagi</label>
                        <textarea name="Uwagi" id="Uwagi"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- DANE BUDŻETOWE DLA LAT -->
            <div class="section">
                <h3>Dane budżetowe dla lat</h3>
                <p style="color: #666; font-size: 13px; margin-bottom: 16px;">Zaznacz lata, dla których chcesz wprowadzić dane budżetowe:</p>
                
                <div class="year-toggle">
                    <label><input type="checkbox" onchange="toggleYear(this, 2026)"> <strong>2026</strong></label>
                    <label><input type="checkbox" onchange="toggleYear(this, 2027)"> <strong>2027</strong></label>
                    <label><input type="checkbox" onchange="toggleYear(this, 2028)"> <strong>2028</strong></label>
                    <label><input type="checkbox" onchange="toggleYear(this, 2029)"> <strong>2029</strong></label>
                </div>
                
                <!-- ROK 2026 -->
                <div id="year-2026" class="year-section">
                    <h4>Rok 2026</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2026">Potrzeby finansowe na 2026 (kwota)</label>
                            <input type="number" name="Potrzeby2026" id="Potrzeby2026" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Limit2026">Limit wydatków na rok 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2026" id="Limit2026" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Braki2026">Kwota na realizację zadań w 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2026" id="Braki2026" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2026">Kwota zawartej umowy 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2026" id="KwotaUmowy2026" step="0.01">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2026">Nr wniosku o udzielenie zamówienia publicznego 2026 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2026" id="NrUmowy2026">
                        </div>
                    </div>
                </div>
                
                <!-- ROK 2027 -->
                <div id="year-2027" class="year-section">
                    <h4>Rok 2027</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2027">Potrzeby finansowe na 2027 (kwota)</label>
                            <input type="number" name="Potrzeby2027" id="Potrzeby2027" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Limit2027">Limit wydatków na rok 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2027" id="Limit2027" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Braki2027">Kwota na realizację zadań w 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2027" id="Braki2027" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2027">Kwota zawartej umowy 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2027" id="KwotaUmowy2027" step="0.01">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2027">Nr wniosku o udzielenie zamówienia publicznego 2027 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2027" id="NrUmowy2027">
                        </div>
                    </div>
                </div>
                
                <!-- ROK 2028 -->
                <div id="year-2028" class="year-section">
                    <h4>Rok 2028</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2028">Potrzeby finansowe na 2028 (kwota)</label>
                            <input type="number" name="Potrzeby2028" id="Potrzeby2028" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Limit2028">Limit wydatków na rok 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2028" id="Limit2028" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Braki2028">Kwota na realizację zadań w 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2028" id="Braki2028" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2028">Kwota zawartej umowy 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2028" id="KwotaUmowy2028" step="0.01">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2028">Nr wniosku o udzielenie zamówienia publicznego 2028 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2028" id="NrUmowy2028">
                        </div>
                    </div>
                </div>
                
                <!-- ROK 2029 -->
                <div id="year-2029" class="year-section">
                    <h4>Rok 2029</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Potrzeby2029">Potrzeby finansowe na 2029 (kwota)</label>
                            <input type="number" name="Potrzeby2029" id="Potrzeby2029" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Limit2029">Limit wydatków na rok 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(ustalany przez komórkę)</span></label>
                            <input type="number" name="Limit2029" id="Limit2029" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="Braki2029">Kwota na realizację zadań w 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(różnica między potrzebami a limitem)</span></label>
                            <input type="number" name="Braki2029" id="Braki2029" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="KwotaUmowy2029">Kwota zawartej umowy 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(jeśli pusta - uzupełnia komórka zbierająca; kwota > limit wywoła alert "Brak środków")</span></label>
                            <input type="number" name="KwotaUmowy2029" id="KwotaUmowy2029" step="0.01">
                        </div>
                        <div class="form-group full-width">
                            <label for="NrUmowy2029">Nr wniosku o udzielenie zamówienia publicznego 2029 <span style="font-weight: normal; color: #999; font-size: 12px;">(obowiązkowe jeśli wpisana kwota umowy)</span></label>
                            <input type="text" name="NrUmowy2029" id="NrUmowy2029">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="button-group">
                <button type="submit">✓ Dodaj wpis</button>
                <a href="home.php">✗ Anuluj</a>
            </div>
        </form>
    </div>
</div>

<?php require 'navbar.php'; ?>
</body>
</html>
