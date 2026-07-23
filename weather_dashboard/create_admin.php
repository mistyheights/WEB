<?php
// ============================================
// JALANKAN FILE INI SEKALI SAJA LEWAT BROWSER
// untuk membuat akun admin awal, lalu HAPUS file ini.
// Default: username = admin | password = admin123
// ============================================
require 'config.php';

$username = 'admin';
$plainPassword = 'admin123';
$hashed = password_hash($plainPassword, PASSWORD_DEFAULT);

$check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$check->execute([$username]);

if ($check->fetch()) {
    echo "User 'admin' sudah ada. Tidak ada perubahan.";
} else {
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $hashed]);
    echo "Berhasil membuat user admin.<br>";
    echo "Username: <b>admin</b><br>";
    echo "Password: <b>admin123</b><br><br>";
    echo "<b style='color:red'>PENTING: Hapus file create_admin.php ini sekarang juga demi keamanan.</b>";
}
