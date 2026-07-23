<?php
require 'auth_check.php';
require 'config.php';

$username = $_SESSION['username'] ?? 'admin';
$msg_success = '';
$msg_error = '';

// --------------------------------------------------------------------------
// PROSES CRUD (TAMBAH, EDIT, HAPUS, THRESHOLD)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH DEVICE BARU
    if ($action === 'add') {
        $device_code   = trim($_POST['device_code'] ?? '');
        $device_name   = trim($_POST['device_name'] ?? '');
        $location_name = trim($_POST['location_name'] ?? '');
        $latitude      = $_POST['latitude'] ?? -7.2575;
        $longitude     = $_POST['longitude'] ?? 112.7521;

        if (!empty($device_code) && !empty($device_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO devices (device_code, device_name, location_name, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, 'offline')");
                $stmt->execute([$device_code, $device_name, $location_name, $latitude, $longitude]);
                $msg_success = "Perangkat '{$device_name}' berhasil ditambahkan!";
            } catch (PDOException $e) {
                $msg_error = "Gagal menambah perangkat. ID Perangkat mungkin sudah ada.";
            }
        } else {
            $msg_error = "Nama dan ID Perangkat wajib diisi!";
        }
    }

    // 2. EDIT DEVICE
    elseif ($action === 'edit') {
        $id            = $_POST['id'] ?? 0;
        $device_name   = trim($_POST['device_name'] ?? '');
        $location_name = trim($_POST['location_name'] ?? '');
        $latitude      = $_POST['latitude'] ?? -7.2575;
        $longitude     = $_POST['longitude'] ?? 112.7521;

        try {
            $stmt = $pdo->prepare("UPDATE devices SET device_name = ?, location_name = ?, latitude = ?, longitude = ? WHERE id = ?");
            $stmt->execute([$device_name, $location_name, $latitude, $longitude, $id]);
            $msg_success = "Perangkat berhasil diperbarui!";
        } catch (PDOException $e) {
            $msg_error = "Gagal memperbarui perangkat: " . $e->getMessage();
        }
    }

    // 3. HAPUS DEVICE
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            $stmt->execute([$id]);
            $msg_success = "Perangkat berhasil dihapus!";
        } catch (PDOException $e) {
            $msg_error = "Gagal menghapus perangkat.";
        }
    }

    // 4. UPDATE RANGE THRESHOLD
    elseif ($action === 'threshold') {
        $id        = $_POST['id'] ?? 0;
        $temp_min  = $_POST['temp_min'] ?? 0;  $temp_max  = $_POST['temp_max'] ?? 50;
        $hum_min   = $_POST['hum_min'] ?? 0;   $hum_max   = $_POST['hum_max'] ?? 100;
        $rain_min  = $_POST['rain_min'] ?? 0;  $rain_max  = $_POST['rain_max'] ?? 100;
        $wind_min  = $_POST['wind_min'] ?? 0;  $wind_max  = $_POST['wind_max'] ?? 50;
        $water_min = $_POST['water_min'] ?? 0; $water_max = $_POST['water_max'] ?? 200;

        try {
            $stmt = $pdo->prepare("UPDATE devices SET temp_min=?, temp_max=?, hum_min=?, hum_max=?, rain_min=?, rain_max=?, wind_min=?, wind_max=?, water_min=?, water_max=? WHERE id=?");
            $stmt->execute([$temp_min, $temp_max, $hum_min, $hum_max, $rain_min, $rain_max, $wind_min, $wind_max, $water_min, $water_max, $id]);
            $msg_success = "Threshold batas sinyal berhasil disimpan!";
        } catch (PDOException $e) {
            $msg_error = "Gagal menyimpan threshold.";
        }
    }
}

// AMBIL SELURUH DATA DEVICE
$devices = [];
try {
    $stmt = $pdo->query("SELECT * FROM devices ORDER BY id DESC");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handling error jika query gagal
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Device Management - Smart Weather Station</title>
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
  .topbar { margin-bottom: 24px; }
  .topbar h1 { font-size: 28px; font-weight: 800; color: #0f172a; }

  /* CARD CONTAINER LIQUID GLASS */
  .content-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px) saturate(220%);
    -webkit-backdrop-filter: blur(35px) saturate(220%);
    border-radius: 28px; padding: 28px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

  /* HEADER AKSI & PENCARIAN */
  .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; }
  .search-box { position: relative; width: 320px; }
  .search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; }
  .search-input {
    width: 100%; padding: 12px 16px 12px 42px; background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.9); border-radius: 16px; font-size: 14px; font-weight: 600;
    color: #0f172a; outline: none; transition: all 0.25s ease;
  }
  .search-input:focus { background: rgba(255, 255, 255, 0.95); border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); }

  .btn-add-device {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.8);
    padding: 12px 22px; border-radius: 16px; font-size: 14px; font-weight: 800;
    cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 10px 20px rgba(2, 132, 199, 0.3);
  }
  .btn-add-device:hover { transform: translateY(-2px); box-shadow: 0 14px 25px rgba(2, 132, 199, 0.4); }

  /* TABEL STYLING */
  table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
  th { padding: 12px 18px; text-align: left; color: #64748b; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
  td {
    background: rgba(255, 255, 255, 0.55); padding: 16px 18px;
    border-top: 1px solid rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.8);
    color: #0f172a; font-weight: 700; font-size: 14px; vertical-align: middle;
  }
  td:first-child { border-top-left-radius: 16px; border-bottom-left-radius: 16px; border-left: 1px solid rgba(255, 255, 255, 0.8); }
  td:last-child { border-top-right-radius: 16px; border-bottom-right-radius: 16px; border-right: 1px solid rgba(255, 255, 255, 0.8); }
  tr:hover td { background: rgba(255, 255, 255, 0.9); }

  .device-name-cell { display: flex; align-items: center; gap: 12px; }
  .device-icon-box {
    width: 38px; height: 38px; border-radius: 12px; background: rgba(224, 242, 254, 0.8);
    color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 16px;
  }

  .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 800; }
  .status-offline { background: rgba(254, 226, 226, 0.85); color: #dc2626; border: 1px solid rgba(254, 202, 202, 0.8); }
  .status-online { background: rgba(220, 252, 231, 0.85); color: #16a34a; border: 1px solid rgba(187, 247, 208, 0.8); }
  .status-dot { width: 7px; height: 7px; border-radius: 50%; }
  .dot-red { background: #dc2626; }
  .dot-green { background: #16a34a; }

  /* ACTION BUTTONS (4 ICON) */
  .action-buttons { display: flex; align-items: center; gap: 12px; }
  .btn-icon {
    width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 14px; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease;
  }
  .btn-view { background: rgba(224, 242, 254, 0.8); color: #0284c7; }
  .btn-view:hover { background: #0284c7; color: #ffffff; }
  .btn-threshold { background: rgba(220, 252, 231, 0.8); color: #16a34a; }
  .btn-threshold:hover { background: #16a34a; color: #ffffff; }
  .btn-edit { background: rgba(254, 243, 199, 0.8); color: #d97706; }
  .btn-edit:hover { background: #d97706; color: #ffffff; }
  .btn-delete { background: rgba(254, 226, 226, 0.8); color: #dc2626; }
  .btn-delete:hover { background: #dc2626; color: #ffffff; }

  /* ALERTS */
  .toast { padding: 12px 18px; border-radius: 16px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
  .toast-success { background: rgba(220, 252, 231, 0.9); color: #15803d; border: 1px solid rgba(187, 247, 208, 0.9); }
  .toast-error { background: rgba(254, 226, 226, 0.9); color: #b91c1c; border: 1px solid rgba(254, 202, 202, 0.9); }

  /* MODAL GLASS STYLING */
  .modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px);
    z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;
  }
  .modal-overlay.active { display: flex; }

  .modal-box {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
    backdrop-filter: blur(30px); border-radius: 28px; width: 100%; max-width: 580px;
    max-height: 90vh; overflow-y: auto; padding: 32px; border: 1px solid rgba(255, 255, 255, 1);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15); animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

  .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
  .modal-header h2 { font-size: 20px; font-weight: 800; color: #0f172a; }
  .modal-close-btn { background: none; border: none; font-size: 20px; color: #64748b; cursor: pointer; }

  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
  .form-control {
    width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.8);
    border: 1px solid #cbd5e1; border-radius: 14px; font-size: 14px; font-weight: 600; outline: none;
  }
  .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }

  .modal-map { width: 100%; height: 200px; border-radius: 16px; margin-top: 8px; border: 1px solid #cbd5e1; }

  .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
  .btn-cancel { padding: 11px 22px; border-radius: 14px; border: 1px solid #cbd5e1; background: #fff; font-weight: 700; cursor: pointer; color: #475569; }
  .btn-save { padding: 11px 22px; border-radius: 14px; border: none; background: #0284c7; color: #fff; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(2, 132, 199, 0.3); }

  /* THRESHOLD BOXES IN MODAL */
  .threshold-card { background: rgba(248, 250, 252, 0.8); border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; margin-bottom: 14px; }
  .threshold-card h4 { font-size: 13px; font-weight: 800; color: #1e293b; margin-bottom: 10px; }
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
    <div class="topbar">
      <h1>Devices</h1>
    </div>

    <div class="content-card">
      
      <?php if (!empty($msg_success)): ?>
        <div class="toast toast-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_success) ?></div>
      <?php endif; ?>

      <?php if (!empty($msg_error)): ?>
        <div class="toast toast-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($msg_error) ?></div>
      <?php endif; ?>

      <!-- ACTION BAR: SEARCH & ADD DEVICE -->
      <div class="action-bar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput" class="search-input" placeholder="Search device..." onkeyup="filterTable()">
        </div>
        <button class="btn-add-device" onclick="openAddModal()">
          <i class="fa-solid fa-plus"></i> Add Device
        </button>
      </div>

      <!-- TABEL DEVICE -->
      <table id="deviceTable">
        <thead>
          <tr>
            <th>NAME <i class="fa-solid fa-arrow-down-up-across-line" style="font-size: 10px;"></i></th>
            <th>STATUS</th>
            <th>LAST UPDATE</th>
            <th style="text-align: right; padding-right: 28px;">ACTION</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($devices)): ?>
            <tr>
              <td colspan="4" style="text-align: center; color: #64748b; padding: 30px;">Belum ada perangkat terdaftar. Klik "+ Add Device" untuk menambah.</td>
            </tr>
          <?php else: foreach ($devices as $dev): ?>
            <tr>
              <td>
                <div class="device-name-cell">
                  <div class="device-icon-box"><i class="fa-solid fa-microchip"></i></div>
                  <div>
                    <div><?= htmlspecialchars($dev['device_name']) ?></div>
                    <small style="color: #64748b; font-weight: 600; font-size: 11px;"><?= htmlspecialchars($dev['device_code']) ?> &bull; <?= htmlspecialchars($dev['location_name'] ?: 'No Location') ?></small>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($dev['status'] === 'online'): ?>
                  <span class="status-badge status-online"><span class="status-dot dot-green"></span> ONLINE</span>
                <?php else: ?>
                  <span class="status-badge status-offline"><span class="status-dot dot-red"></span> OFFLINE</span>
                <?php endif; ?>
              </td>
              <td style="color: #64748b; font-weight: 600; font-size: 13px;">
                <?= $dev['last_seen'] ? date('d/m/Y, H.i.s', strtotime($dev['last_seen'])) : 'No data' ?>
              </td>
              <td>
                <div class="action-buttons" style="justify-content: flex-end;">
                  <!-- 1. VIEW DETAIL (MATA) -->
                   <!-- TOMBOL VIEW DETAIL DINAMIS -->
                  <a href="device_detail.php?device=<?= htmlspecialchars($dev['device_code']) ?>" class="btn-icon btn-view" title="View Detail Device">
                    <i class="fa-solid fa-eye"></i>
                  </a>
                  <!-- <a href="sensor_suhu.php?device=<?= urlencode($dev['device_code']) ?>" class="btn-icon btn-view" title="View Detail Station">
                    <i class="fa-solid fa-eye"></i>
                  </a> -->
                  <!-- 2. RANGE THRESHOLD (SLIDERS) -->
                  <button class="btn-icon btn-threshold" title="Range Threshold" onclick='openThresholdModal(<?= json_encode($dev) ?>)'>
                    <i class="fa-solid fa-sliders"></i>
                  </button>
                  <!-- 3. EDIT (PENSIL) -->
                  <button class="btn-icon btn-edit" title="Edit Device" onclick='openEditModal(<?= json_encode($dev) ?>)'>
                    <i class="fa-solid fa-pencil"></i>
                  </button>
                  <!-- 4. DELETE (SAMPAH) -->
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus perangkat ini?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $dev['id'] ?>">
                    <button type="submit" class="btn-icon btn-delete" title="Delete Device">
                      <i class="fa-solid fa-trash-can"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

    </div>
  </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL 1: ADD NEW DEVICE -->
<!-- ==================================================================== -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add New Device</h2>
      <button class="modal-close-btn" onclick="closeModal('addModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group">
          <label>Device Name</label>
          <input type="text" name="device_name" class="form-control" placeholder="Station 1" required>
        </div>
        <div class="form-group">
          <label>Device ID</label>
          <input type="text" name="device_code" id="addDeviceCode" class="form-control" required readonly style="background: #f1f5f9; color: #2563eb; font-weight: 800;">
        </div>
      </div>
      <div class="form-group">
        <label>Location Name</label>
        <input type="text" name="location_name" class="form-control" placeholder="Surabaya" required>
      </div>
      <div class="form-group">
        <label>Pick Location from Map</label>
        <div id="addMap" class="modal-map"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Latitude</label>
          <input type="text" name="latitude" id="addLat" class="form-control" value="-7.2575" readonly>
        </div>
        <div class="form-group">
          <label>Longitude</label>
          <input type="text" name="longitude" id="addLng" class="form-control" value="112.7521" readonly>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn-save">Add Device</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL 2: EDIT DEVICE -->
<!-- ==================================================================== -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Edit Device</h2>
      <button class="modal-close-btn" onclick="closeModal('editModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="form-row">
        <div class="form-group">
          <label>Device Name</label>
          <input type="text" name="device_name" id="editName" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Device ID</label>
          <input type="text" id="editCode" class="form-control" readonly style="background: #f1f5f9; color: #2563eb; font-weight: 800;">
        </div>
      </div>
      <div class="form-group">
        <label>Location Name</label>
        <input type="text" name="location_name" id="editLocation" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Pick Location from Map</label>
        <div id="editMap" class="modal-map"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Latitude</label>
          <input type="text" name="latitude" id="editLat" class="form-control" readonly>
        </div>
        <div class="form-group">
          <label>Longitude</label>
          <input type="text" name="longitude" id="editLng" class="form-control" readonly>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn-save">Save Device</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL 3: RANGE THRESHOLD -->
<!-- ==================================================================== -->
<div class="modal-overlay" id="thresholdModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="thresholdTitle">Range Threshold</h2>
      <button class="modal-close-btn" onclick="closeModal('thresholdModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="threshold">
      <input type="hidden" name="id" id="threshId">

      <div class="threshold-card">
        <h4>Temperature (°C)</h4>
        <div class="form-row" style="margin:0;">
          <div><label>Min</label><input type="number" step="any" name="temp_min" id="tTempMin" class="form-control"></div>
          <div><label>Max</label><input type="number" step="any" name="temp_max" id="tTempMax" class="form-control"></div>
        </div>
      </div>

      <div class="threshold-card">
        <h4>Humidity (%)</h4>
        <div class="form-row" style="margin:0;">
          <div><label>Min</label><input type="number" step="any" name="hum_min" id="tHumMin" class="form-control"></div>
          <div><label>Max</label><input type="number" step="any" name="hum_max" id="tHumMax" class="form-control"></div>
        </div>
      </div>

      <div class="threshold-card">
        <h4>Rainfall (mm)</h4>
        <div class="form-row" style="margin:0;">
          <div><label>Min</label><input type="number" step="any" name="rain_min" id="tRainMin" class="form-control"></div>
          <div><label>Max</label><input type="number" step="any" name="rain_max" id="tRainMax" class="form-control"></div>
        </div>
      </div>

      <div class="threshold-card">
        <h4>Wind Speed (m/s)</h4>
        <div class="form-row" style="margin:0;">
          <div><label>Min</label><input type="number" step="any" name="wind_min" id="tWindMin" class="form-control"></div>
          <div><label>Max</label><input type="number" step="any" name="wind_max" id="tWindMax" class="form-control"></div>
        </div>
      </div>

      <div class="threshold-card">
        <h4>Water Level (cm)</h4>
        <div class="form-row" style="margin:0;">
          <div><label>Min</label><input type="number" step="any" name="water_min" id="tWaterMin" class="form-control"></div>
          <div><label>Max</label><input type="number" step="any" name="water_max" id="tWaterMax" class="form-control"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('thresholdModal')">Cancel</button>
        <button type="submit" class="btn-save">Save Threshold</button>
      </div>
    </form>
  </div>
</div>

<script>
// SIDEBAR COLLAPSE
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

// SEARCH FILTER TABLE
function filterTable() {
  const input = document.getElementById('searchInput').value.toLowerCase();
  const rows = document.querySelectorAll('#deviceTable tbody tr');
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(input) ? '' : 'none';
  });
}

// MODAL CONTROL
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

let addMapObj = null, addMarker = null;
let editMapObj = null, editMarker = null;

function openAddModal() {
  // Generate Random Device ID e.g., HP54I7NX7K
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let randId = 'ST';
  for (let i = 0; i < 8; i++) randId += chars.charAt(Math.floor(Math.random() * chars.length));
  document.getElementById('addDeviceCode').value = randId;

  document.getElementById('addModal').classList.add('active');

  setTimeout(() => {
    if (!addMapObj) {
      addMapObj = L.map('addMap').setView([-7.2575, 112.7521], 11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(addMapObj);
      addMarker = L.marker([-7.2575, 112.7521], {draggable: true}).addTo(addMapObj);

      addMapObj.on('click', function(e) {
        addMarker.setLatLng(e.latlng);
        document.getElementById('addLat').value = e.latlng.lat.toFixed(8);
        document.getElementById('addLng').value = e.latlng.lng.toFixed(8);
      });
    } else {
      addMapObj.invalidateSize();
    }
  }, 200);
}

function openEditModal(dev) {
  document.getElementById('editId').value = dev.id;
  document.getElementById('editName').value = dev.device_name;
  document.getElementById('editCode').value = dev.device_code;
  document.getElementById('editLocation').value = dev.location_name || '';
  document.getElementById('editLat').value = dev.latitude;
  document.getElementById('editLng').value = dev.longitude;

  document.getElementById('editModal').classList.add('active');

  setTimeout(() => {
    const lat = parseFloat(dev.latitude) || -7.2575;
    const lng = parseFloat(dev.longitude) || 112.7521;
    if (!editMapObj) {
      editMapObj = L.map('editMap').setView([lat, lng], 11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(editMapObj);
      editMarker = L.marker([lat, lng], {draggable: true}).addTo(editMapObj);

      editMapObj.on('click', function(e) {
        editMarker.setLatLng(e.latlng);
        document.getElementById('editLat').value = e.latlng.lat.toFixed(8);
        document.getElementById('editLng').value = e.latlng.lng.toFixed(8);
      });
    } else {
      editMapObj.setView([lat, lng], 11);
      editMarker.setLatLng([lat, lng]);
      editMapObj.invalidateSize();
    }
  }, 200);
}

function openThresholdModal(dev) {
  document.getElementById('threshId').value = dev.id;
  document.getElementById('thresholdTitle').innerText = 'Range Threshold - ' + dev.device_name;
  document.getElementById('tTempMin').value = dev.temp_min ?? 0;
  document.getElementById('tTempMax').value = dev.temp_max ?? 50;
  document.getElementById('tHumMin').value  = dev.hum_min ?? 0;
  document.getElementById('tHumMax').value  = dev.hum_max ?? 100;
  document.getElementById('tRainMin').value = dev.rain_min ?? 0;
  document.getElementById('tRainMax').value = dev.rain_max ?? 100;
  document.getElementById('tWindMin').value = dev.wind_min ?? 0;
  document.getElementById('tWindMax').value = dev.wind_max ?? 50;
  document.getElementById('tWaterMin').value= dev.water_min ?? 0;
  document.getElementById('tWaterMax').value= dev.water_max ?? 200;

  document.getElementById('thresholdModal').classList.add('active');
}
</script>

</body>
</html>