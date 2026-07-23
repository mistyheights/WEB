<?php
require 'auth_check.php';
require 'config.php';

$username = $_SESSION['username'] ?? 'admin';
$device_code = $_GET['device'] ?? '';

// 1. AMBIL INFORMASI DEVICE BERDASARKAN DEVICE_CODE
$device = null;
if (!empty($device_code)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE device_code = ? LIMIT 1");
        $stmt->execute([$device_code]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Fallback jika device tidak ditemukan
if (!$device) {
    $device = [
        'device_name'   => 'Unknown Device',
        'device_code'   => $device_code ?: 'N/A',
        'location_name' => 'Lokasi Tidak Diketahui',
        'status'        => 'OFFLINE'
    ];
}

// 2. AMBIL 15 RIWAYAT LOG TERAKHIR UNTUK GRAFIK & TABEL
$logs = [];
if (!empty($device_code)) {
    try {
        $stmtLog = $pdo->prepare("SELECT * FROM sensor_logs WHERE device_code = ? ORDER BY id DESC LIMIT 15");
        $stmtLog->execute([$device_code]);
        $logs = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// 3. AMBIL DATA TERBARU (INDEX KE-0) UNTUK KARTU INDIKATOR
$latest = $logs[0] ?? [
    'temperature' => '--',
    'humidity'    => '--',
    'distance'    => '--',
    'created_at'  => '-'
];

// Logika sederhana penentuan "Kenyamanan Ruang"
$tempVal = floatval($latest['temperature']);
$humVal  = floatval($latest['humidity']);
$comfortStatus = "Ideal";
$comfortColor  = "#10b981"; // Hijau

if ($tempVal > 30 || $tempVal < 18 || $humVal > 80 || $humVal < 30) {
    $comfortStatus = "Perlu Perhatian";
    $comfortColor  = "#f59e0b"; // Kuning/Orange
}
if ($latest['temperature'] === '--') {
    $comfortStatus = "No Data";
    $comfortColor  = "#64748b";
}

// 4. SIAPKAN DATA GRAFIK (CHART.JS) - diurutkan dari yang terlama ke terbaru
$chartLabels = [];
$chartTemp   = [];
$chartHum    = [];
foreach (array_reverse($logs) as $l) {
    $chartLabels[] = date('H:i:s', strtotime($l['created_at'] ?? 'now'));
    $chartTemp[]   = floatval($l['temperature'] ?? 0);
    $chartHum[]    = floatval($l['humidity'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard <?= htmlspecialchars($device['device_name']) ?> - Smart Weather Station</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- FONT AWESOME & CHART.JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  * { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", Roboto, sans-serif; 
  }

  body { 
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
    color: #0f172a; 
    min-height: 100vh;
    overflow-x: hidden;
    position: relative;
  }

  /* AURORA LIQUID BLOBS */
  .aurora-blob {
    position: fixed; border-radius: 50%; filter: blur(80px); z-index: 0; pointer-events: none; opacity: 0.75;
    animation: floatAurora 12s infinite alternate ease-in-out;
  }
  .blob-blue { width: 480px; height: 480px; background: radial-gradient(circle, #3b82f6 0%, rgba(14, 165, 233, 0.2) 100%); top: -100px; left: 10%; }
  .blob-green { width: 450px; height: 450px; background: radial-gradient(circle, #10b981 0%, rgba(52, 211, 153, 0.2) 100%); bottom: -60px; right: 8%; }

  @keyframes floatAurora { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(35px, -30px) scale(1.1); } }

  .layout { display: flex; min-height: 100vh; position: relative; z-index: 1; }

  /* SIDEBAR COLLAPSIBLE */
  .sidebar {
    width: 260px; 
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.65) 0%, rgba(255, 255, 255, 0.3) 100%);
    backdrop-filter: blur(30px) saturate(220%);
    -webkit-backdrop-filter: blur(30px) saturate(220%);
    border-right: 1px solid rgba(255, 255, 255, 0.9);
    color: #0f172a; display: flex; flex-direction: column; padding: 20px 16px; flex-shrink: 0;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.04);
  }
  .sidebar.collapsed { width: 80px; padding: 20px 12px; }

  .sidebar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; padding: 0 4px; }
  .sidebar-toggle-btn {
    width: 36px; height: 36px; border-radius: 12px; background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.9); color: #2563eb; display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  }
  .sidebar-toggle-btn:hover { background: #ffffff; transform: scale(1.05); }
  .brand-title { font-size: 20px; font-weight: 800; color: #0f172a; white-space: nowrap; }
  .brand-title span { color: #2563eb; }
  .sidebar.collapsed .brand-title { display: none; }

  .menu { list-style: none; flex: 1; }
  .menu li { margin-bottom: 8px; }
  .menu a {
    display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 16px;
    color: #334155; text-decoration: none; font-size: 14px; font-weight: 700; transition: all 0.3s ease; border: 1px solid transparent; white-space: nowrap;
  }
  .menu a:hover { background: rgba(255, 255, 255, 0.7); color: #2563eb; border-color: rgba(255, 255, 255, 0.9); transform: translateX(3px); }
  .menu a.active { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.9); box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35); }
  .menu a i { width: 20px; text-align: center; font-size: 16px; flex-shrink: 0; }
  .sidebar.collapsed .menu-text { display: none; }
  .sidebar.collapsed .menu a { justify-content: center; padding: 12px; }

  /* MAIN CONTENT */
  .main { flex: 1; padding: 28px 36px; transition: all 0.35s ease; }
  
  .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
  .topbar-left { display: flex; align-items: center; gap: 16px; }
  .btn-back {
    width: 42px; height: 42px; border-radius: 14px; background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.9); color: #0f172a; display: flex; align-items: center;
    justify-content: center; text-decoration: none; font-size: 16px; transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
  }
  .btn-back:hover { background: #ffffff; color: #2563eb; transform: translateX(-3px); }
  
  .topbar h1 { font-size: 26px; font-weight: 800; color: #0f172a; }
  .topbar-sub { font-size: 13px; font-weight: 600; color: #64748b; margin-top: 2px; }

  /* CARDS 4 INDIKATOR SENSOR */
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
  }

  .sensor-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.45) 100%);
    backdrop-filter: blur(30px); border-radius: 22px; padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.04);
    display: flex; flex-direction: column; justify-content: space-between;
    animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
  }
  @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

  .card-temp { background: linear-gradient(135deg, rgba(254, 226, 226, 0.7) 0%, rgba(255, 255, 255, 0.4) 100%); border-color: rgba(254, 202, 202, 0.8); }
  .card-hum  { background: linear-gradient(135deg, rgba(224, 242, 254, 0.7) 0%, rgba(255, 255, 255, 0.4) 100%); border-color: rgba(186, 230, 253, 0.8); }
  .card-dist { background: linear-gradient(135deg, rgba(220, 252, 231, 0.7) 0%, rgba(255, 255, 255, 0.4) 100%); border-color: rgba(187, 247, 208, 0.8); }

  .sensor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
  .sensor-title { font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
  .sensor-icon { font-size: 16px; }

  .sensor-value { font-size: 30px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
  .sensor-unit { font-size: 16px; font-weight: 700; color: #64748b; margin-left: 2px; }
  .sensor-sub { font-size: 11px; font-weight: 600; color: #64748b; }

  /* CHART GLASS BOX */
  .chart-box {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px); border-radius: 24px; padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05); margin-bottom: 24px;
  }
  .box-title { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

  /* TABLE GLASS BOX */
  .table-box {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px); border-radius: 24px; padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
  }
  table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
  th { text-align: left; padding: 12px 16px; color: #64748b; font-size: 12px; font-weight: 800; text-transform: uppercase; }
  td { background: rgba(255, 255, 255, 0.6); padding: 14px 16px; font-weight: 700; font-size: 13px; color: #0f172a; }
  td:first-child { border-radius: 14px 0 0 14px; }
  td:last-child { border-radius: 0 14px 14px 0; }
  tr:hover td { background: rgba(255, 255, 255, 0.9); }
</style>
</head>
<body>

<div class="aurora-blob blob-blue"></div>
<div class="aurora-blob blob-green"></div>

<div class="layout">

  <!-- SIDEBAR COLLAPSIBLE -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <button class="sidebar-toggle-btn" onclick="toggleSidebar()" id="toggleBtn">
        <i class="fa-solid fa-chevron-left" id="toggleIcon"></i>
      </button>
      <div class="brand-title"><span>Applify</span></div>
    </div>

    <ul class="menu">
      <li><a href="dashboard.php" title="Dashboard"><i class="fa-solid fa-border-all"></i><span class="menu-text">Dashboard</span></a></li>
      <li><a href="device_management.php" class="active" title="Device Management"><i class="fa-solid fa-microchip"></i><span class="menu-text">Device Management</span></a></li>
      <li><a href="log_data.php" title="Log Data"><i class="fa-solid fa-file-lines"></i><span class="menu-text">Log Data</span></a></li>
      <li><a href="admin_management.php" title="Admin Management"><i class="fa-solid fa-users-gear"></i><span class="menu-text">Admin Management</span></a></li>
      <li><a href="client_management.php" title="Client Management"><i class="fa-solid fa-users"></i><span class="menu-text">Client Management</span></a></li>
      <li><a href="settings.php" title="Settings"><i class="fa-solid fa-gear"></i><span class="menu-text">Settings</span></a></li>
      <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i><span class="menu-text">Profile</span></a></li>
      <li><a href="logout.php" style="color: #e11d48;" title="Logout"><i class="fa-solid fa-right-from-bracket"></i><span class="menu-text">Logout</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">
    
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="topbar-left">
        <a href="device_management.php" class="btn-back" title="Kembali ke Devices">
          <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
          <h1>Dashboard <?= htmlspecialchars($device['device_name']) ?></h1>
          <div class="topbar-sub">Ringkasan data cuaca & lingkungan dari seluruh sensor terhubung (Kode: <b><?= htmlspecialchars($device['device_code']) ?></b>)</div>
        </div>
      </div>
    </div>

    <!-- 4 KARTU INDIKATOR SENSOR -->
    <div class="cards-grid">
      <!-- 1. SUHU -->
      <div class="sensor-card card-temp">
        <div class="sensor-header">
          <span class="sensor-title">SUHU UDARA</span>
          <i class="fa-solid fa-temperature-half sensor-icon" style="color: #ef4444;"></i>
        </div>
        <div>
          <div class="sensor-value" style="color: #ef4444;"><?= htmlspecialchars($latest['temperature']) ?><span class="sensor-unit">°C</span></div>
          <div class="sensor-sub">Update: <?= htmlspecialchars(date('H:i:s', strtotime($latest['created_at'] ?? 'now'))) ?></div>
        </div>
      </div>

      <!-- 2. KELEMBABAN -->
      <div class="sensor-card card-hum">
        <div class="sensor-header">
          <span class="sensor-title">KELEMBABAN</span>
          <i class="fa-solid fa-droplet sensor-icon" style="color: #2563eb;"></i>
        </div>
        <div>
          <div class="sensor-value" style="color: #2563eb;"><?= htmlspecialchars($latest['humidity']) ?><span class="sensor-unit">%</span></div>
          <div class="sensor-sub">Update: <?= htmlspecialchars(date('H:i:s', strtotime($latest['created_at'] ?? 'now'))) ?></div>
        </div>
      </div>

      <!-- 3. JARAK ULTRASONIK -->
      <div class="sensor-card card-dist">
        <div class="sensor-header">
          <span class="sensor-title">JARAK ULTRASONIK</span>
          <i class="fa-solid fa-ruler-horizontal sensor-icon" style="color: #10b981;"></i>
        </div>
        <div>
          <div class="sensor-value" style="color: #10b981;"><?= htmlspecialchars($latest['distance'] ?? '--') ?><span class="sensor-unit">cm</span></div>
          <div class="sensor-sub">Status: Active (Testing)</div>
        </div>
      </div>

      <!-- 4. KENYAMANAN RUANG -->
      <div class="sensor-card">
        <div class="sensor-header">
          <span class="sensor-title">KENYAMANAN RUANG</span>
          <i class="fa-solid fa-face-smile sensor-icon" style="color: <?= $comfortColor ?>;"></i>
        </div>
        <div>
          <div class="sensor-value" style="color: <?= $comfortColor ?>; font-size: 26px;"><?= $comfortStatus ?></div>
          <div class="sensor-sub">Berdasarkan Sensor Terpadu</div>
        </div>
      </div>
    </div>

    <!-- GRAFIK TREN SUHU & KELEMBABAN -->
    <div class="chart-box">
      <div class="box-title"><i class="fa-solid fa-chart-line" style="color: #2563eb;"></i> Grafik Tren Suhu & Kelembaban</div>
      <canvas id="trendChart" height="85"></canvas>
    </div>

    <!-- TABEL RIWAYAT DATA TERINTEGRASI -->
    <div class="table-box">
      <div class="box-title"><i class="fa-solid fa-table-list" style="color: #10b981;"></i> Riwayat Data Terintegrasi</div>
      <table>
        <thead>
          <tr>
            <th>WAKTU</th>
            <th>SUHU (°C)</th>
            <th>KELEMBABAN (%)</th>
            <th>JARAK (CM)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 24px;">Belum ada riwayat data untuk perangkat ini.</td></tr>
          <?php else: foreach ($logs as $l): ?>
            <tr>
              <td><?= htmlspecialchars($l['created_at']) ?></td>
              <td style="color: #ef4444; font-weight: 800;"><?= htmlspecialchars($l['temperature'] ?? '--') ?> °C</td>
              <td style="color: #2563eb; font-weight: 800;"><?= htmlspecialchars($l['humidity'] ?? '--') ?> %</td>
              <td style="color: #10b981; font-weight: 800;"><?= htmlspecialchars($l['distance'] ?? '--') ?> cm</td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
// SIDEBAR TOGGLE
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const toggleIcon = document.getElementById('toggleIcon');
  sidebar.classList.toggle('collapsed');
  if (sidebar.classList.contains('collapsed')) {
    toggleIcon.classList.remove('fa-chevron-left'); toggleIcon.classList.add('fa-chevron-right');
  } else {
    toggleIcon.classList.remove('fa-chevron-right'); toggleIcon.classList.add('fa-chevron-left');
  }
}

// RENDER GRAFIK CHART.JS
const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [
      {
        label: 'Suhu (°C)',
        data: <?= json_encode($chartTemp) ?>,
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239, 68, 68, 0.1)',
        borderWidth: 3,
        tension: 0.4,
        fill: false,
        pointBackgroundColor: '#ef4444',
        pointRadius: 4
      },
      {
        label: 'Kelembaban (%)',
        data: <?= json_encode($chartHum) ?>,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37, 99, 235, 0.1)',
        borderWidth: 3,
        tension: 0.4,
        fill: false,
        pointBackgroundColor: '#2563eb',
        pointRadius: 4
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top', labels: { font: { weight: 'bold', family: '-apple-system' } } }
    },
    scales: {
      x: { grid: { color: 'rgba(255, 255, 255, 0.6)' } },
      y: { grid: { color: 'rgba(255, 255, 255, 0.6)' } }
    }
  }
});
</script>

</body>
</html>