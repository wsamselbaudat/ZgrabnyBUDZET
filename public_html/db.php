<?php
// Load environment variables from .env file
require_once __DIR__ . '/../.env.php';

// Database connection configuration from environment variables
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'database';

// Create connection
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
	error_log('MySQL connection error: ' . $mysqli->connect_error);
	die('Database connection error.');
}

// Try to set a supported charset. Prefer utf8mb4, fall back to utf8.
try {
	if (!@$mysqli->set_charset('utf8mb4')) {
		if (!@$mysqli->set_charset('utf8')) {
			error_log('Could not set MySQL client charset to utf8mb4 or utf8: ' . $mysqli->error);
		}
	}
} catch (\Throwable $e) {
	// If mysqli is configured to throw exceptions, catch them and log instead of letting script die.
	error_log('Charset exception: ' . $e->getMessage());
}

// Export $mysqli for use by other scripts that `require` this file
return $mysqli;

?>
