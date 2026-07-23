<?php
require 'auth_check.php';
require 'config.php';

$username = $_SESSION['username'] ?? 'admin';

// --------------------------------------------------------------------------
// QUERY STATISTIK & LOKASI DEVICE DARI DATABASE
// --------------------------------------------------------------------------
$totalDevice = 0;
$onlineDevice = 0;
$offlineDevice = 0;
$devicesList = [];
$lastUpdate = 'No Data';

try {
    // Anggap device OFFLINE jika tidak ada aktivitas dalam 5 menit terakhir
    $pdo->exec("UPDATE devices SET status = 'offline' WHERE last_seen < NOW() - INTERVAL 5 MINUTE");
    
    // Total Perangkat
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM devices");
    $totalDevice = $stmtTotal->fetchColumn() ?: 0;

    // Device Online & Offline
    $stmtOnline = $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'online'");
    $onlineDevice = $stmtOnline->fetchColumn() ?: 0;
    $offlineDevice = $totalDevice - $onlineDevice;

    // Ambil Semua List Device untuk Pin di Peta
    $stmtDevices = $pdo->query("SELECT device_code, device_name, location_name, latitude, longitude, status, last_seen FROM devices");
    $devicesList = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Waktu Last Update
    $stmtLast = $pdo->query("SELECT waktu FROM sensor_data ORDER BY id DESC LIMIT 1");
    $lastRow = $stmtLast->fetch();
    if ($lastRow && !empty($lastRow['waktu'])) {
        $lastUpdate = date('H:i:s', strtotime($lastRow['waktu'])) . ' WIB';
    }
} catch (PDOException $e) {
    // Handling jika tabel belum siap
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard - Smart Weather Station</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- LEAFLET MAP CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- FONT AWESOME ICON -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    z-index: 0;
    pointer-events: none;
    opacity: 0.75;
    animation: floatAurora 12s infinite alternate ease-in-out;
  }
  .blob-red { width: 480px; height: 480px; background: radial-gradient(circle, #ef4444 0%, rgba(244, 63, 94, 0.2) 100%); top: -100px; left: 10%; }
  .blob-blue { width: 450px; height: 450px; background: radial-gradient(circle, #3b82f6 0%, rgba(14, 165, 233, 0.2) 100%); bottom: -60px; right: 8%; }
  .blob-green { width: 380px; height: 380px; background: radial-gradient(circle, #10b981 0%, rgba(52, 211, 153, 0.2) 100%); top: 35%; right: 35%; }

  @keyframes floatAurora {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(35px, -30px) scale(1.1); }
  }

  .layout { display: flex; min-height: 100vh; position: relative; z-index: 1; }

  /* SIDEBAR LIQUID GLASS & COLLAPSIBLE */
  .sidebar {
    width: 260px; 
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.65) 0%, rgba(255, 255, 255, 0.3) 100%);
    backdrop-filter: blur(30px) saturate(220%);
    -webkit-backdrop-filter: blur(30px) saturate(220%);
    border-right: 1px solid rgba(255, 255, 255, 0.9);
    color: #0f172a; 
    display: flex; 
    flex-direction: column;
    padding: 20px 16px; 
    flex-shrink: 0;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.04);
    position: relative;
  }

  .sidebar.collapsed {
    width: 80px;
    padding: 20px 12px;
  }

  .sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
    padding: 0 4px;
  }

  .sidebar-toggle-btn {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.9);
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  }
  .sidebar-toggle-btn:hover { background: #ffffff; transform: scale(1.05); }

  .brand-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    transition: opacity 0.25s;
    white-space: nowrap;
  }
  .brand-title span { color: #2563eb; }

  .sidebar.collapsed .brand-title { display: none; }

  .menu { list-style: none; flex: 1; }
  .menu li { margin-bottom: 8px; }
  .menu a {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    border-radius: 16px;
    color: #334155;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    transition: all 0.3s ease;
    border: 1px solid transparent;
    white-space: nowrap;
  }
  .menu a:hover { 
    background: rgba(255, 255, 255, 0.7); 
    color: #2563eb; 
    border-color: rgba(255, 255, 255, 0.9);
    transform: translateX(3px);
  }
  .menu a.active { 
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); 
    color: #ffffff; 
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
  }
  .menu a i { width: 20px; text-align: center; font-size: 16px; flex-shrink: 0; }

  .menu-text { transition: opacity 0.25s; }
  .sidebar.collapsed .menu-text { display: none; }
  .sidebar.collapsed .menu a { justify-content: center; padding: 12px; }

  /* MAIN AREA */
  .main { flex: 1; padding: 28px 36px; transition: all 0.35s ease; }
  .topbar { margin-bottom: 24px; }
  .topbar h1 { font-size: 28px; font-weight: 800; color: #0f172a; }

  /* BARIS ATAU: 4 CARDS HORIZONTAL */
  .stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
  }

  .stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(25px) saturate(220%);
    -webkit-backdrop-filter: blur(25px) saturate(220%);
    border-radius: 22px;
    padding: 20px 24px;
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.04), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    display: flex; align-items: center; justify-content: space-between;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  .stat-card:nth-child(1) { animation-delay: 0.1s; }
  .stat-card:nth-child(2) { animation-delay: 0.2s; }
  .stat-card:nth-child(3) { animation-delay: 0.3s; }
  .stat-card:nth-child(4) { animation-delay: 0.4s; }

  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .stat-card:hover {
    transform: translateY(-4px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.5) 100%);
    box-shadow: 0 18px 35px rgba(0, 0, 0, 0.08);
  }

  .stat-info .stat-label {
    font-size: 11px; font-weight: 800; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px;
  }
  .stat-info .stat-value { font-size: 28px; font-weight: 800; color: #0f172a; }
  .stat-info .stat-value.val-blue { color: #0284c7; }
  .stat-info .stat-value.val-green { color: #16a34a; }
  .stat-info .stat-value.val-red { color: #dc2626; }
  .stat-info .stat-value.val-amber { color: #d97706; font-size: 18px; }

  .stat-icon-badge {
    width: 52px; height: 52px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 22px;
  }
  .badge-blue { background: rgba(224, 242, 254, 0.9); color: #0284c7; border: 1px solid rgba(186, 230, 253, 0.8); }
  .badge-green { background: rgba(220, 252, 231, 0.9); color: #16a34a; border: 1px solid rgba(187, 247, 208, 0.8); }
  .badge-red { background: rgba(254, 226, 226, 0.9); color: #dc2626; border: 1px solid rgba(254, 202, 202, 0.8); }
  .badge-amber { background: rgba(254, 243, 199, 0.9); color: #d97706; border: 1px solid rgba(253, 230, 138, 0.8); }

  /* BARIS BAWAH: MAP CONTAINER LEBAR FULL */
  .map-card {
    width: 100%;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px) saturate(220%);
    -webkit-backdrop-filter: blur(35px) saturate(220%);
    border-radius: 28px; padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 0.3s;
  }

  .map-card h3 { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 18px; }

  #deviceMap {
    width: 100%; height: 480px; border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
    z-index: 1;
  }

  @media (max-width: 1024px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 640px) {
    .stats-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- AURORA LIQUID BLOBS -->
<div class="aurora-blob blob-red"></div>
<div class="aurora-blob blob-blue"></div>
<div class="aurora-blob blob-green"></div>

<div class="layout">

  <!-- COLLAPSIBLE SIDEBAR -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <button class="sidebar-toggle-btn" onclick="toggleSidebar()" id="toggleBtn">
        <i class="fa-solid fa-chevron-left" id="toggleIcon"></i>
      </button>
      <div class="brand-title"><span>Applify</span></div>
    </div>

    <ul class="menu">
      <li>
        <a href="dashboard.php" class="active" title="Dashboard">
          <i class="fa-solid fa-border-all"></i>
          <span class="menu-text">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="device_management.php" title="Device Management">
          <i class="fa-solid fa-microchip"></i>
          <span class="menu-text">Device Management</span>
        </a>
      </li>
      <li>
        <a href="log_data.php" title="Log Data">
          <i class="fa-solid fa-file-lines"></i>
          <span class="menu-text">Log Data</span>
        </a>
      </li>
      <li>
        <a href="admin_management.php" title="Admin Management">
          <i class="fa-solid fa-users-gear"></i>
          <span class="menu-text">Admin Management</span>
        </a>
      </li>
      <li>
        <a href="client_management.php" title="Client Management">
          <i class="fa-solid fa-users"></i>
          <span class="menu-text">Client Management</span>
        </a>
      </li>
      <li>
        <a href="settings.php" title="Settings">
          <i class="fa-solid fa-gear"></i>
          <span class="menu-text">Settings</span>
        </a>
      </li>
      <li>
        <a href="profile.php" title="Profile">
          <i class="fa-solid fa-user"></i>
          <span class="menu-text">Profile</span>
        </a>
      </li>
      <li>
        <a href="logout.php" style="color: #e11d48;" title="Logout">
          <i class="fa-solid fa-right-from-bracket"></i>
          <span class="menu-text">Logout</span>
        </a>
      </li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">
    
    <div class="topbar">
      <h1>Dashboard</h1>
    </div>

    <!-- BARIS 1: 4 STATS CARDS SEJAJAR HORIZONTAL -->
    <div class="stats-row">
      
      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">TOTAL DEVICE</div>
          <div class="stat-value val-blue"><?= $totalDevice ?></div>
        </div>
        <div class="stat-icon-badge badge-blue"><i class="fa-solid fa-microchip"></i></div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">ONLINE DEVICE</div>
          <div class="stat-value val-green"><?= $onlineDevice ?></div>
        </div>
        <div class="stat-icon-badge badge-green"><i class="fa-solid fa-wifi"></i></div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">OFFLINE DEVICE</div>
          <div class="stat-value val-red"><?= $offlineDevice ?></div>
        </div>
        <div class="stat-icon-badge badge-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">LAST UPDATE</div>
          <div class="stat-value val-amber"><?= htmlspecialchars($lastUpdate) ?></div>
        </div>
        <div class="stat-icon-badge badge-amber"><i class="fa-solid fa-clock"></i></div>
      </div>

    </div>

    <!-- BARIS 2: MAP FULL-WIDTH LEBAR -->
    <div class="map-card">
      <h3>Device Locations</h3>
      <div id="deviceMap"></div>
    </div>

  </div>
</div>

<script>
// SIDEBAR COLLAPSE / EXPAND TOGGLE
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const toggleIcon = document.getElementById('toggleIcon');
  sidebar.classList.toggle('collapsed');

  if (sidebar.classList.contains('collapsed')) {
    toggleIcon.classList.remove('fa-chevron-left');
    toggleIcon.classList.add('fa-chevron-right');
  } else {
    toggleIcon.classList.remove('fa-chevron-right');
    toggleIcon.classList.add('fa-chevron-left');
  }

  // Auto resize map saat sidebar dipencet/di-toggle
  setTimeout(() => {
    map.invalidateSize();
  }, 350);
}

// INITIALIZE LEAFLET MAP
const map = L.map('deviceMap').setView([-2.5489, 118.0149], 5);

L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
  maxZoom: 18,
  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

// AMBIL LIST DEVICE DINAMIS DARI DATABASE
const devices = <?= json_encode($devicesList) ?>;

if (devices.length > 0) {
  devices.forEach(dev => {
    const statusColor = dev.status === 'online' ? '#16a34a' : '#dc2626';
    const marker = L.marker([parseFloat(dev.latitude), parseFloat(dev.longitude)]).addTo(map);
    
    marker.bindPopup(`
      <div style="font-family: sans-serif; font-size: 13px;">
        <strong style="color: #0f172a;">${dev.device_name}</strong> (${dev.device_code})<br>
        Lokasi: ${dev.location_name || '-'}<br>
        Status: <span style="color: ${statusColor}; font-weight: bold;">${dev.status.toUpperCase()}</span><br>
        <small style="color: #64748b;">Last Seen: ${dev.last_seen || '-'}</small>
      </div>
    `);
  });
}
</script>

</body>
</html>