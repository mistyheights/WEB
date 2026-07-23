<?php
require 'auth_check.php';
require 'config.php';
header('Content-Type: application/json');

$limit = 20;
$stmt = $pdo->prepare("SELECT suhu, kelembaban, jarak, waktu FROM sensor_data ORDER BY id DESC LIMIT :limit");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = array_reverse($stmt->fetchAll());

$latest = end($rows);
if ($latest === false) {
    $latest = ['suhu' => 0, 'kelembaban' => 0, 'jarak' => 0, 'waktu' => null];
    $connected = false;
} else {
    // dianggap "terkoneksi" kalau data terakhir masuk dalam 30 detik terakhir
    $connected = (time() - strtotime($latest['waktu'])) <= 30;
}

echo json_encode([
    'latest'    => $latest,
    'history'   => array_values($rows),
    'connected' => $connected,
]);
