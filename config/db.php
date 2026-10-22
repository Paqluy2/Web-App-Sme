<?php
/**
 * Database Connection Handler using PDO
 * Compatible with: Local XAMPP & Cloud (Render + Aiven MySQL)
 */

function getDB(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // 1. Read Cloud Environment Variables (Render) or local defaults
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = (int)(getenv('DB_PORT') ?: 3306);
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $dbname = getenv('DB_NAME') ?: 'gigscafe';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    // 2. Enable SSL for Aiven Cloud connection
    if (getenv('DB_HOST')) {
        $ca_cert = __DIR__ . '/ca.pem';
        if (file_exists($ca_cert)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca_cert;
        } else {
            // Connect over SSL without requiring local CA file
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Error: " . $e->getMessage());
    }
}

// Global variable support in case older scripts reference $db or $conn directly
try {
    $db = getDB();
    $conn = $db;
} catch (Throwable $e) {
    // Suppress die during CLI/editor static analysis
}