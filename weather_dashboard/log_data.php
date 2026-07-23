<?php
require 'auth_check.php';
require 'config.php';

$username = $_SESSION['username'] ?? 'admin';
$msg_success = '';
$msg_error = '';

// --------------------------------------------------------------------------
// 1. OPSI EXPORT TO EXCEL / CSV
// --------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $device_filter = $_GET['device_code'] ?? '';
    $start_date    = $_GET['start_date'] ?? '';
    $end_date      = $_GET['end_date'] ?? '';

    $where = ["1=1"];
    $params = [];

    if (!empty($device_filter)) {
        $where[] = "device_code = ?";
        $params[] = $device_filter;
    }
    if (!empty($start_date)) {
        $where[] = "DATE(waktu) >= ?";
        $params[] = $start_date;
    }
    if (!empty($end_date)) {
        $where[] = "DATE(waktu) <= ?";
        $params[] = $end_date;
    }

    $sql = "SELECT waktu, suhu, kelembaban, rain, wind_speed, wind_direction, jarak FROM sensor_data WHERE " . implode(' AND ', $where) . " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Sensor_Logs_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['TIMESTAMP', 'TEMP (°C)', 'HUM (%)', 'RAIN (mm)', 'WIND SPEED (m/s)', 'WIND DIRECTION (°)', 'LEVEL (cm)']);

    foreach ($logs as $row) {
        fputcsv($output, [
            $row['waktu'],
            $row['suhu'],
            $row['kelembaban'],
            $row['rain'] ?? 0,
            $row['wind_speed'] ?? 0,
            $row['wind_direction'] ?? 0,
            $row['jarak']
        ]);
    }
    fclose($output);
    exit;
}

// --------------------------------------------------------------------------
// 2. PROSES DELETE LOGS
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_logs') {
    try {
        $pdo->exec("DELETE FROM sensor_data");
        $msg_success = "Seluruh riwayat log data berhasil dihapus!";
    } catch (PDOException $e) {
        $msg_error = "Gagal menghapus log data: " . $e->getMessage();
    }
}

// --------------------------------------------------------------------------
// 3. QUERY DATA PERANGKAT (UNTUK DROPDOWN FILTER)
// --------------------------------------------------------------------------
$deviceList = [];
try {
    $stmtDev = $pdo->query("SELECT device_code, device_name FROM devices ORDER BY device_name ASC");
    $deviceList = $stmtDev->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// --------------------------------------------------------------------------
// 4. FILTER & PAGINATION QUERY
// --------------------------------------------------------------------------
$selected_device = $_GET['device_code'] ?? '';
$start_date      = $_GET['start_date'] ?? '';
$end_date        = $_GET['end_date'] ?? '';
$page            = max(1, intval($_GET['page'] ?? 1));
$limit           = 10;
$offset          = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if (!empty($selected_device)) {
    $where[] = "device_code = ?";
    $params[] = $selected_device;
}
if (!empty($start_date)) {
    $where[] = "DATE(waktu) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $where[] = "DATE(waktu) <= ?";
    $params[] = $end_date;
}

$where_sql = implode(' AND ', $where);

// Hitung total data
$totalRows = 0;
try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM sensor_data WHERE {$where_sql}");
    $stmtCount->execute($params);
    $totalRows = $stmtCount->fetchColumn() ?: 0;
} catch (PDOException $e) {}

$totalPages = max(1, ceil($totalRows / $limit));

// Ambil data sesuai limit pagination
$logsData = [];
try {
    $sqlData = "SELECT * FROM sensor_data WHERE {$where_sql} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
    $stmtData = $pdo->prepare($sqlData);
    $stmtData->execute($params);
    $logsData = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Perhitungan range item untuk teks "Showing X - Y of Z"
$startItem = $totalRows > 0 ? ($offset + 1) : 0;
$endItem   = min($offset + $limit, $totalRows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Log Data - Smart Weather Station</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

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
  .main { flex: 1; padding: 28px 36px; transition: all 0.35s ease; width: calc(100% - 260px); }
  .topbar { margin-bottom: 24px; }
  .topbar h1 { font-size: 28px; font-weight: 800; color: #0f172a; }

  /* GLASS CARD CONTAINERS */
  .glass-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px) saturate(220%);
    -webkit-backdrop-filter: blur(35px) saturate(220%);
    border-radius: 24px; padding: 24px 28px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    margin-bottom: 24px;
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

  .card-title { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 18px; }

  /* FILTER FORM LAYOUT */
  .filter-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr auto;
    gap: 16px;
    align-items: center;
  }

  .form-control-custom {
    width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.9); border-radius: 14px; font-size: 14px; font-weight: 600;
    color: #0f172a; outline: none; transition: all 0.25s ease;
  }
  .form-control-custom:focus { background: #ffffff; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); }

  .btn-show-data {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.8);
    padding: 12px 28px; border-radius: 14px; font-size: 14px; font-weight: 800;
    cursor: pointer; transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(2, 132, 199, 0.3); white-space: nowrap;
  }
  .btn-show-data:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(2, 132, 199, 0.4); }

  /* HEADER AKSI SENSOR LOGS */
  .logs-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .logs-actions { display: flex; align-items: center; gap: 12px; }

  .btn-delete-logs {
    background: #e11d48; color: #ffffff; border: none;
    padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 800;
    cursor: pointer; transition: all 0.25s ease; box-shadow: 0 6px 18px rgba(225, 29, 72, 0.25);
  }
  .btn-delete-logs:hover { background: #be123c; transform: translateY(-2px); }

  .btn-export-excel {
    background: #16a34a; color: #ffffff; text-decoration: none;
    padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 8px; transition: all 0.25s ease;
    box-shadow: 0 6px 18px rgba(22, 163, 74, 0.25);
  }
  .btn-export-excel:hover { background: #15803d; transform: translateY(-2px); }

  /* REVISI TABEL: KONFIGURASI LEBAR TERATUR & JARAK RAPAT */
  .table-responsive {
    width: 100%;
    overflow-x: auto;
  }

  table { 
    width: 100%; 
    border-collapse: separate; 
    border-spacing: 0 6px; 
    table-layout: auto;
  }
  
  th { 
    padding: 10px 12px; 
    text-align: left; 
    color: #64748b; 
    font-size: 11px; 
    font-weight: 800; 
    text-transform: uppercase; 
    letter-spacing: 0.5px;
    white-space: nowrap;
  }

  td {
    background: rgba(255, 255, 255, 0.55); 
    padding: 12px 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.8); 
    border-bottom: 1px solid rgba(255, 255, 255, 0.8);
    color: #0f172a; 
    font-weight: 700; 
    font-size: 13px; 
    vertical-align: middle;
    white-space: nowrap;
  }
  
  td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; border-left: 1px solid rgba(255, 255, 255, 0.8); }
  td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; border-right: 1px solid rgba(255, 255, 255, 0.8); }
  tr:hover td { background: rgba(255, 255, 255, 0.9); }

  /* REVISI PAGINATION DESAIN PERSIS ACUAN GAMBAR */
  .pagination-container { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-top: 22px; 
    padding-top: 8px; 
  }

  .showing-text { 
    font-size: 13px; 
    font-weight: 600; 
    color: #64748b; 
  }

  .pagination-nav { 
    display: flex; 
    align-items: center; 
    gap: 6px; 
  }

  .pag-btn {
    padding: 8px 16px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
  }

  .pag-btn.active {
    background: #e2e8f0;
    color: #1e293b;
    border-color: #cbd5e1;
    font-weight: 800;
  }

  .pag-btn.disabled {
    background: rgba(255, 255, 255, 0.4);
    color: #cbd5e1;
    border-color: #f1f5f9;
    pointer-events: none;
    box-shadow: none;
  }

  .pag-btn:hover:not(.disabled):not(.active) {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
  }

  /* ALERTS */
  .toast { padding: 12px 18px; border-radius: 16px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
  .toast-success { background: rgba(220, 252, 231, 0.9); color: #15803d; border: 1px solid rgba(187, 247, 208, 0.9); }
  .toast-error { background: rgba(254, 226, 226, 0.9); color: #b91c1c; border: 1px solid rgba(254, 202, 202, 0.9); }
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
      <li><a href="device_management.php" title="Device Management"><i class="fa-solid fa-microchip"></i><span class="menu-text">Device Management</span></a></li>
      <li><a href="log_data.php" class="active" title="Log Data"><i class="fa-solid fa-file-lines"></i><span class="menu-text">Log Data</span></a></li>
      <li><a href="admin_management.php" title="Admin Management"><i class="fa-solid fa-users-gear"></i><span class="menu-text">Admin Management</span></a></li>
      <li><a href="client_management.php" title="Client Management"><i class="fa-solid fa-users"></i><span class="menu-text">Client Management</span></a></li>
      <li><a href="settings.php" title="Settings"><i class="fa-solid fa-gear"></i><span class="menu-text">Settings</span></a></li>
      <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i><span class="menu-text">Profile</span></a></li>
      <li><a href="logout.php" style="color: #e11d48;" title="Logout"><i class="fa-solid fa-right-from-bracket"></i><span class="menu-text">Logout</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">
    <div class="topbar">
      <h1>Log Data</h1>
    </div>

    <?php if (!empty($msg_success)): ?>
      <div class="toast toast-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_success) ?></div>
    <?php endif; ?>

    <?php if (!empty($msg_error)): ?>
      <div class="toast toast-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($msg_error) ?></div>
    <?php endif; ?>

    <!-- CARD 1: FILTER HISTORY DATA -->
    <div class="glass-card">
      <div class="card-title">Filter History Data</div>
      <form method="GET" class="filter-grid">
        <select name="device_code" class="form-control-custom">
          <option value="">Select Device</option>
          <?php foreach ($deviceList as $dev): ?>
            <option value="<?= htmlspecialchars($dev['device_code']) ?>" <?= $selected_device === $dev['device_code'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($dev['device_name']) ?> (<?= htmlspecialchars($dev['device_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <input type="date" name="start_date" class="form-control-custom" value="<?= htmlspecialchars($start_date) ?>">
        <input type="date" name="end_date" class="form-control-custom" value="<?= htmlspecialchars($end_date) ?>">

        <button type="submit" class="btn-show-data">Show Data</button>
      </form>
    </div>

    <!-- CARD 2: SENSOR LOGS TABLE -->
    <div class="glass-card">
      <div class="logs-header">
        <div class="card-title" style="margin-bottom:0;">Sensor Logs</div>
        <div class="logs-actions">
          <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus SELURUH log data sensor?');">
            <input type="hidden" name="action" value="delete_logs">
            <button type="submit" class="btn-delete-logs">Delete Logs</button>
          </form>

          <a href="log_data.php?export=excel&device_code=<?= urlencode($selected_device) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn-export-excel">
            Export Excel
          </a>
        </div>
      </div>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>TIMESTAMP</th>
              <th>TEMP</th>
              <th>HUM</th>
              <th>RAIN</th>
              <th>WIND SPEED</th>
              <th>WIND DIRECTION</th>
              <th>LEVEL</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logsData)): ?>
              <tr>
                <td colspan="7" style="text-align: center; color: #64748b; padding: 30px;">Tidak ada data log yang ditemukan.</td>
              </tr>
            <?php else: foreach ($logsData as $row): ?>
              <tr>
                <td style="color: #475569; font-size: 12px;"><?= htmlspecialchars($row['waktu']) ?></td>
                <td><?= number_format($row['suhu'], 1) ?> °C</td>
                <td><?= number_format($row['kelembaban'], 1) ?> %</td>
                <td><?= number_format($row['rain'] ?? 0, 1) ?> mm</td>
                <td><?= number_format($row['wind_speed'] ?? 0, 1) ?> m/s</td>
                <td><?= number_format($row['wind_direction'] ?? 0, 0) ?>°</td>
                <td><?= number_format($row['jarak'], 1) ?> cm</td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION FOOTER (SESUAI GAMBAR ACUAN) -->
      <div class="pagination-container">
        <div class="showing-text">
          Showing <?= $startItem ?> - <?= $endItem ?> of <?= $totalRows ?>
        </div>
        
        <div class="pagination-nav">
          <?php 
            $queryString = "device_code=" . urlencode($selected_device) . "&start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date);
            
            // Logika Halaman Terbatas (Hanya tampilkan 2-3 tombol angka di sekitar halaman aktif)
            $startPage = max(1, $page);
            $endPage   = min($totalPages, $startPage + 1);
            if ($endPage - $startPage < 1 && $startPage > 1) {
              $startPage = $startPage - 1;
            }
          ?>

          <!-- Tombol Prev -->
          <a href="log_data.php?<?= $queryString ?>&page=<?= max(1, $page - 1) ?>" class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">Prev</a>
          
          <!-- Tombol Angka Halaman -->
          <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="log_data.php?<?= $queryString ?>&page=<?= $i ?>" class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>

          <!-- Tombol Next -->
          <a href="log_data.php?<?= $queryString ?>&page=<?= min($totalPages, $page + 1) ?>" class="pag-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next</a>

          <!-- Tombol Last -->
          <a href="log_data.php?<?= $queryString ?>&page=<?= $totalPages ?>" class="pag-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">last</a>
        </div>
      </div>

    </div>

  </div>
</div>

<script>
// SIDEBAR COLLAPSE TOGGLE
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
</script>

</body>
</html>