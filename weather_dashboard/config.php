<?php
// ============================================
// KONFIGURASI KONEKSI DATABASE MYSQL
// Sesuaikan dengan environment lokal Anda (XAMPP/Laragon/dll)
// ============================================
$host     = 'localhost';
$dbname   = 'weather_station';
$dbuser   = 'root';
$dbpass   = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $dbuser,
        $dbpass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
