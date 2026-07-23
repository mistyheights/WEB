<?php
require 'auth_check.php';
require 'config.php';

// Ambil 100 data sensor terakhir
$stmt = $pdo->prepare("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 100");
$stmt->execute();
$data = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Global - Mini Weather Station</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; }
  body { padding: 24px; background: #fff; color: #0f172a; }

  /* KONTEN TOMBOL */
  .btn-container {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: 0.2s;
  }
  .btn-print {
    background: #2563eb;
    color: #fff;
  }
  .btn-print:hover {
    background: #1d4ed8;
  }
  .btn-back {
    background: #64748b;
    color: #fff;
  }
  .btn-back:hover {
    background: #475569;
  }

  /* JUDUL DAN TABEL */
  h2 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
  p.subtitle { color: #64748b; font-size: 13px; margin-bottom: 20px; }

  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: center; }
  th { background: #0f172a; color: #fff; font-weight: 700; }
  tr:nth-child(even) { background: #f8fafc; }

  /* Sembunyikan tombol saat mode cetak / simpan PDF */
  @media print {
    .btn-container { display: none !important; }
    body { padding: 0; }
  }
</style>
</head>
<body>

  <!-- KONTAN TOMBOL AKSI -->
  <div class="btn-container">
    <button onclick="window.print()" class="btn btn-print">
      <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
    </button>
    <a href="dashboard.php" class="btn btn-back">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
    </a>
  </div>

  <h2>Laporan Global - Mini Weather Station</h2>
  <p class="subtitle">Data Suhu & Kelembaban (DHT11) serta Jarak Ultrasonik (Dummy) &mdash; 100 data terakhir</p>

  <table>
    <thead>
      <tr>
        <th>No</th>
        <th>Waktu</th>
        <th>Suhu (°C)</th>
        <th>Kelembaban (%)</th>
        <th>Jarak (cm) - Dummy</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($data)): ?>
        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 16px;">Belum ada data.</td></tr>
      <?php else: $no = 1; foreach ($data as $row): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($row['waktu']) ?></td>
          <td><?= number_format($row['suhu'], 2) ?></td>
          <td><?= number_format($row['kelembaban'], 2) ?></td>
          <td><?= number_format($row['jarak'], 1) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

</body>
</html>