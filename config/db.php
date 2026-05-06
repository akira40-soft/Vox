<?php
/**
 * db.php - Vox Electoral Platform
 * Database connection (PDO, singleton pattern)
 */

if (!defined('DB_HOST'))    define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME'))    define('DB_NAME', 'vox_db');
if (!defined('DB_USER'))    define('DB_USER', 'root');
if (!defined('DB_PASS'))    define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            http_response_code(500);
            die("Erro de ligacao a base de dados. Por favor, tente mais tarde.");
        }
    }
    return $pdo;
}
