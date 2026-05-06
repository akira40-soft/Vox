<?php
/**
 * db.php - Vox Electoral Platform
 * Database connection (PDO, singleton pattern)
 * Enhanced for Render & PostgreSQL support
 */

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        // Render provides DATABASE_URL
        $dbUrl = getenv('DATABASE_URL');
        
        if ($dbUrl) {
            // Parse DATABASE_URL (postgresql://user:pass@host:port/dbname)
            $parsedUrl = parse_url($dbUrl);
            $dbHost = $parsedUrl['host'] ?? 'localhost';
            $dbPort = $parsedUrl['port'] ?? '5432';
            $dbUser = $parsedUrl['user'] ?? '';
            $dbPass = $parsedUrl['pass'] ?? '';
            $dbName = ltrim($parsedUrl['path'] ?? '', '/');
            
            // Build PostgreSQL DSN. 
            // We use sslmode=require for Render compatibility, but allow override if needed.
            $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;sslmode=require";
        } else {
            // Fallback to individual env vars or constants (Local Development)
            $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
            $dbName = getenv('DB_NAME') ?: 'vox_db';
            $dbUser = getenv('DB_USER') ?: 'root';
            $dbPass = getenv('DB_PASS') ?: '';
            $dbPort = getenv('DB_PORT') ?: '3306';
            $driver = getenv('DB_DRIVER') ?: 'mysql';

            if ($driver === 'pgsql') {
                $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
            } else {
                $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            }
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            
            // Set timezone for the connection if Postgres
            if (str_contains($dsn, 'pgsql')) {
                $pdo->exec("SET TIME ZONE 'Africa/Luanda'");
            }
        } catch (PDOException $e) {
            // If sslmode=require fails, try without it as a last resort
            if (str_contains($e->getMessage(), 'SSL')) {
                try {
                    $dsnNoSsl = str_replace('sslmode=require', 'sslmode=disable', $dsn);
                    $pdo = new PDO($dsnNoSsl, $dbUser, $dbPass, $options);
                } catch (PDOException $e2) {
                    error_log("DB Connection Error: " . $e2->getMessage());
                    die("Erro de ligação à base de dados.");
                }
            } else {
                error_log("DB Connection Error: " . $e->getMessage());
                die("Erro de ligação à base de dados.");
            }
        }
    }
    return $pdo;
}
