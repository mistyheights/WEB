<?php
// ============================================
// OPSIONAL - untuk TESTING saat belum ada hardware.
// Buka file ini di browser / jalankan via cron untuk
// mengirim satu data dummy DHT11 (seolah dari ESP8266).
// Setelah alat asli terpasang, file ini tidak perlu dipakai lagi.
// ============================================
$suhu = round(mt_rand(280, 340) / 10, 1);        // 28.0 - 34.0 °C
$kelembaban = round(mt_rand(600, 850) / 10, 1);  // 60.0 - 85.0 %

$url = 'http://localhost/weather_dashboard/insert_data.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'suhu' => $suhu,
    'kelembaban' => $kelembaban,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: application/json');
echo $response;
