<?php
// ============================================
// ENDPOINT INI DIPANGGIL OLEH ESP8266/ESP32
// Contoh request dari alat (HTTP POST, application/x-www-form-urlencoded):
//   suhu=31.00&kelembaban=78.00
//
// Field "jarak" (ultrasonik) BELUM ada sensornya di lapangan,
// sehingga nilainya di-generate DUMMY secara acak di server ini.
// Kalau nanti sensor ultrasonik asli sudah terpasang, tinggal
// tambahkan field "jarak" di request dan hapus baris dummy di bawah.
// ============================================
header('Content-Type: application/json');
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed, gunakan POST']);
    exit;
}

$suhu       = isset($_POST['suhu']) ? filter_var($_POST['suhu'], FILTER_VALIDATE_FLOAT) : null;
$kelembaban = isset($_POST['kelembaban']) ? filter_var($_POST['kelembaban'], FILTER_VALIDATE_FLOAT) : null;

if ($suhu === null || $suhu === false || $kelembaban === null || $kelembaban === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter suhu dan kelembaban wajib diisi angka valid']);
    exit;
}

// --- DATA DUMMY ULTRASONIK (jarak dalam cm, 20.0 - 400.0 cm) ---
$jarak = round(mt_rand(200, 4000) / 10, 1);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO sensor_data (suhu, kelembaban, jarak) VALUES (:suhu, :kelembaban, :jarak)"
    );
    $stmt->execute([
        ':suhu' => $suhu,
        ':kelembaban' => $kelembaban,
        ':jarak' => $jarak,
    ]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Data tersimpan',
        'data'    => ['suhu' => $suhu, 'kelembaban' => $kelembaban, 'jarak' => $jarak]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
