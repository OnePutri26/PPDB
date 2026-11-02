<?php
declare(strict_types=1);

$host = getenv('PPDB_DB_HOST') ?: '127.0.0.1';
$database = getenv('PPDB_DB_NAME') ?: 'db_ppdb';
$username = getenv('PPDB_DB_USER') ?: 'root';
$password = getenv('PPDB_DB_PASSWORD') ?: '';

try {
    return new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database belum siap. Periksa konfigurasi koneksi dan import database/db_ppdb.sql.');
}
