<?php
// config/database.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'jamselinas_2026');
define('DB_USER', 'root');
define('DB_PASS', ''); // Sesuaikan dengan password MySQL Anda
define('DB_CHARSET', 'utf8mb4');

// BASE_URL otomatis menyesuaikan domain & folder tempat project dijalankan
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
// Jika file dipanggil dari subfolder /admin/, naik satu level
if (basename($scriptDir) === 'admin') {
    $scriptDir = dirname($scriptDir);
}
$basePath = rtrim($scriptDir, '/');
define('BASE_URL', $protocol . '://' . $host . $basePath);

define('UPLOAD_DIR', __DIR__ . '/../uploads/bukti/');

// Pastikan folder upload ada
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    return $pdo;
}