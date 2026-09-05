<?php
// includes/db.php — single PDO connection, reused everywhere.
// Every query anywhere in the app must go through this connection using prepared statements.

require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // fail loudly, never silently
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements, not emulated
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never leak DB details to the browser
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Something went wrong. Please try again shortly.');
        }
    }
    return $pdo;
}
