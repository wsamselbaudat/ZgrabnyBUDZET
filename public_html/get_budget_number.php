<?php
// Endpoint: get_budget_number.php
// Pobiera numer budżetowy na podstawie kodu z 2f.txt

if (!isset($_GET['szczeg'])) {
    echo '';
    exit;
}

$szczeg = trim($_GET['szczeg']);

// Validate format: 2-4 parts (1.5, 1.6.1, 1.2.2.1, etc.)
if (!preg_match('/^[0-9]+(\.[0-9]+){1,3}$/', $szczeg)) {
    echo '';
    exit;
}

// Read 2f.txt file
$filePath = __DIR__ . '/2f.txt';
if (!file_exists($filePath)) {
    echo '';
    exit;
}

$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$result = '';

// Parse the file to find exact match
// Format can be: X.X (like 1.5), X.X.X (like 1.6.1), X.X.X.X (like 1.2.2.1), etc.
foreach ($lines as $line) {
    // Match lines that start with digits and dots
    // Pattern: captures any format like 1. 1.5. 1.6.1. 1.2.2.1. etc.
    if (preg_match('/^([0-9]+(?:\.[0-9]+)*)\.\s+(.+)/', $line, $matches)) {
        $lineCode = $matches[1];
        $desc = $matches[2];
        
        // Check if the code matches exactly what user entered
        if ($lineCode === $szczeg) {
            // Format: "kod. opis"
            $result = $lineCode . '. ' . $desc;
            break;
        }
    }
}

echo htmlspecialchars($result);
?>
