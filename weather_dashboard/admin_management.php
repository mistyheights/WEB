<?php
require 'auth_check.php';
require 'config.php';

$username = $_SESSION['username'] ?? 'admin';
$msg_success = '';
$msg_error = '';

// --------------------------------------------------------------------------
// PROSES CRUD (TAMBAH, EDIT, HAPUS ADMIN & ASSIGN DEVICES)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH ADMIN BARU
    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($name) && !empty($email) && !empty($password)) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // Menyesuaikan kolom 'username' atau 'name' pada tabel users
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hash]);
                $msg_success = "Admin '{$name}' berhasil ditambahkan!";
            } catch (PDOException $e) {
                // Fallback jika nama kolom di DB adalah 'name'
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $email, $hash]);
                    $msg_success = "Admin '{$name}' berhasil ditambahkan!";
                } catch (PDOException $ex) {
                    $msg_error = "Gagal menambah admin. Email mungkin sudah terdaftar.";
                }
            }
        } else {
            $msg_error = "Semua field (Nama, Email, Password) wajib diisi!";
        }
    }

    // 2. EDIT ADMIN
    elseif ($action === 'edit') {
        $id    = $_POST['id'] ?? 0;
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $id]);
            }
            $msg_success = "Data admin '{$name}' berhasil diperbarui!";
        } catch (PDOException $e) {
            // Fallback jika nama kolom adalah 'name'
            try {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $id]);
                }
                $msg_success = "Data admin '{$name}' berhasil diperbarui!";
            } catch (PDOException $ex) {
                $msg_error = "Gagal memperbarui data admin: " . $ex->getMessage();
            }
        }
    }

    // 3. ASSIGN DEVICES KE ADMIN
    elseif ($action === 'assign_devices') {
        $admin_id = $_POST['admin_id'] ?? 0;
        $devices  = $_POST['devices'] ?? []; // Array of device_code / device_id

        try {
            // Buat tabel relasi jika belum ada
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_devices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                device_code VARCHAR(100) NOT NULL
            )");

            // Hapus hak akses lama
            $stmtDel = $pdo->prepare("DELETE FROM admin_devices WHERE admin_id = ?");
            $stmtDel->execute([$admin_id]);

            // Insert hak akses baru
            if (!empty($devices)) {
                $stmtIns = $pdo->prepare("INSERT INTO admin_devices (admin_id, device_code) VALUES (?, ?)");
                foreach ($devices as $dev_code) {
                    $stmtIns->execute([$admin_id, $dev_code]);
                }
            }
            $msg_success = "Hak akses station perangkat berhasil diperbarui!";
        } catch (PDOException $e) {
            $msg_error = "Gagal menyimpan hak akses devices: " . $e->getMessage();
        }
    }

    // 4. HAPUS ADMIN
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $msg_success = "Admin berhasil dihapus!";
        } catch (PDOException $e) {
            $msg_error = "Gagal menghapus admin.";
        }
    }
}

// --------------------------------------------------------------------------
// AMBIL DATA ADMIN (USERS) & DEVICES
// --------------------------------------------------------------------------
$admins = [];
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$allDevices = [];
try {
    $stmtDev = $pdo->query("SELECT * FROM devices ORDER BY device_name ASC");
    $allDevices = $stmtDev->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Ambil Mapping Admin Devices
$adminDevicesMap = [];
try {
    $stmtMap = $pdo->query("SELECT admin_id, device_code FROM admin_devices");
    while ($row = $stmtMap->fetch(PDO::FETCH_ASSOC)) {
        $adminDevicesMap[$row['admin_id']][] = $row['device_code'];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Admin Management - Smart Weather Station</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- FONT AWESOME ICON 6.5.1 -->
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

  .card-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 20px; }

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

  .btn-add-admin {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.8);
    padding: 12px 22px; border-radius: 16px; font-size: 14px; font-weight: 800;
    cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 10px 20px rgba(2, 132, 199, 0.3);
  }
  .btn-add-admin:hover { transform: translateY(-2px); box-shadow: 0 14px 25px rgba(2, 132, 199, 0.4); }

  /* TABEL STYLING */
  table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
  th { padding: 12px 18px; text-align: left; color: #64748b; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
  td {
    background: rgba(255, 255, 255, 0.55); padding: 16px 18px;
    border-top: 1px solid rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.8);
    color: #0f172a; font-weight: 700; font-size: 14px; vertical-align: middle;
  }
  td:first-child { border-top-left-radius: 16px; border-bottom-left-radius: 16px; border-left: 1px solid rgba(255, 255, 255, 0.8); width: 60px; }
  td:last-child { border-top-right-radius: 16px; border-bottom-right-radius: 16px; border-right: 1px solid rgba(255, 255, 255, 0.8); }
  tr:hover td { background: rgba(255, 255, 255, 0.9); }

  /* ACTION BUTTONS & CPU ICON */
  .btn-cpu {
    width: 38px; height: 38px; border-radius: 12px; background: rgba(238, 242, 255, 0.9);
    color: #4f46e5; border: 1px solid rgba(199, 210, 254, 0.8); display: inline-flex;
    align-items: center; justify-content: center; font-size: 16px; cursor: pointer; transition: all 0.25s ease;
  }
  .btn-cpu:hover { background: #4f46e5; color: #ffffff; transform: scale(1.08); }

  .action-buttons { display: flex; align-items: center; gap: 10px; }
  .btn-icon {
    width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 14px; border: none; cursor: pointer; transition: all 0.2s ease;
  }
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
    backdrop-filter: blur(30px); border-radius: 28px; width: 100%; max-width: 500px;
    max-height: 90vh; overflow-y: auto; padding: 32px; border: 1px solid rgba(255, 255, 255, 1);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15); animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

  .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .modal-header h2 { font-size: 20px; font-weight: 800; color: #0f172a; }
  .modal-close-btn { background: none; border: none; font-size: 20px; color: #64748b; cursor: pointer; }

  .form-group { margin-bottom: 18px; }
  .form-group label { display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
  .form-control {
    width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.8);
    border: 1px solid #cbd5e1; border-radius: 14px; font-size: 14px; font-weight: 600; outline: none;
  }
  .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }

  /* ASSIGN DEVICES CHECKBOX CONTAINER */
  .device-select-box {
    background: rgba(248, 250, 252, 0.9); border: 1px solid #cbd5e1;
    border-radius: 16px; padding: 14px; max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;
  }
  .device-item {
    display: flex; align-items: center; gap: 10px; background: #ffffff; padding: 10px 14px;
    border-radius: 12px; border: 1px solid #e2e8f0; cursor: pointer; font-size: 13px; font-weight: 700; color: #334155;
    transition: all 0.2s ease;
  }
  .device-item:hover { border-color: #3b82f6; background: #f0f9ff; }
  .device-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer; }

  .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
  .btn-cancel { padding: 11px 22px; border-radius: 14px; border: 1px solid #cbd5e1; background: #fff; font-weight: 700; cursor: pointer; color: #475569; }
  .btn-save { padding: 11px 22px; border-radius: 14px; border: none; background: #0284c7; color: #fff; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(2, 132, 199, 0.3); }
  .btn-save-assign { background: #4f46e5; box-shadow: 0 8px 18px rgba(79, 70, 229, 0.3); }
  .btn-confirm-delete { background: #dc2626; box-shadow: 0 8px 18px rgba(220, 38, 38, 0.3); }
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
      <li><a href="log_data.php" title="Log Data"><i class="fa-solid fa-file-lines"></i><span class="menu-text">Log Data</span></a></li>
      <li><a href="admin_management.php" class="active" title="Admin Management"><i class="fa-solid fa-users-gear"></i><span class="menu-text">Admin Management</span></a></li>
      <li><a href="client_management.php" title="Client Management"><i class="fa-solid fa-users"></i><span class="menu-text">Client Management</span></a></li>
      <li><a href="settings.php" title="Settings"><i class="fa-solid fa-gear"></i><span class="menu-text">Settings</span></a></li>
      <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i><span class="menu-text">Profile</span></a></li>
      <li><a href="logout.php" style="color: #e11d48;" title="Logout"><i class="fa-solid fa-right-from-bracket"></i><span class="menu-text">Logout</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">
    <div class="topbar">
      <h1>Admin Management</h1>
    </div>

    <div class="content-card">
      
      <?php if (!empty($msg_success)): ?>
        <div class="toast toast-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_success) ?></div>
      <?php endif; ?>

      <?php if (!empty($msg_error)): ?>
        <div class="toast toast-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($msg_error) ?></div>
      <?php endif; ?>

      <!-- ACTION BAR: SEARCH & ADD ADMIN -->
      <div class="action-bar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput" class="search-input" placeholder="Search admins..." onkeyup="filterAdmins()">
        </div>
        <button class="btn-add-admin" onclick="openAddModal()">
          <i class="fa-solid fa-plus"></i> Add Admin
        </button>
      </div>

      <div class="card-title">Admins (<?= count($admins) ?>)</div>

      <!-- TABEL ADMIN -->
      <table id="adminTable">
        <thead>
          <tr>
            <th>#</th>
            <th>NAME</th>
            <th>EMAIL</th>
            <th style="text-align: center;">DEVICES</th>
            <th style="text-align: center;">ACTION</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($admins)): ?>
            <tr>
              <td colspan="5" style="text-align: center; color: #64748b; padding: 30px;">Belum ada admin terdaftar. Klik "+ Add Admin" untuk menambah.</td>
            </tr>
          <?php else: $no = 1; foreach ($admins as $adm): 
            $adm_id    = $adm['id'];
            $adm_name  = $adm['username'] ?? $adm['name'] ?? 'Admin';
            $adm_email = $adm['email'] ?? '-';
            $assigned  = $adminDevicesMap[$adm_id] ?? [];
          ?>
            <tr class="admin-row">
              <td style="color: #64748b;"><?= $no++ ?></td>
              <td class="admin-name" style="font-weight: 800; color: #0f172a;"><?= htmlspecialchars($adm_name) ?></td>
              <td class="admin-email" style="color: #64748b; font-weight: 600;"><?= htmlspecialchars($adm_email) ?></td>
              
              <!-- CPU ICON BUTTON (ASSIGN DEVICES) -->
              <td style="text-align: center;">
                <button class="btn-cpu" title="Assign Devices" onclick='openAssignModal(<?= $adm_id ?>, <?= json_encode($adm_name) ?>, <?= json_encode($assigned) ?>)'>
                  <i class="fa-solid fa-microchip"></i>
                </button>
              </td>

              <!-- ACTIONS (EDIT & DELETE) -->
              <td style="text-align: center;">
                <div class="action-buttons" style="justify-content: center;">
                  <!-- EDIT PENCIL ICON -->
                  <button class="btn-icon btn-edit" title="Edit Admin" onclick='openEditModal(<?= $adm_id ?>, <?= json_encode($adm_name) ?>, <?= json_encode($adm_email) ?>)'>
                    <i class="fa-solid fa-pencil"></i>
                  </button>
                  <!-- DELETE TRASH ICON -->
                  <button class="btn-icon btn-delete" title="Delete Admin" onclick='openDeleteModal(<?= $adm_id ?>, <?= json_encode($adm_name) ?>)'>
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
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
<!-- MODAL 1: ASSIGN DEVICES (IKON CPU) -->
<!-- ==================================================================== -->
<div class="modal-overlay" id="assignModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Assign Devices</h2>
      <button class="modal-close-btn" onclick="closeModal('assignModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <p style="font-size: 13px; font-weight: 700; color: #64748b; margin-bottom: 16px;">Admin: <span id="assignTargetAdmin" style="color: #0f172a; font-weight: 800;"></span></p>
    
    <form method="POST">
      <input type="hidden" name="action" value="assign_devices">
      <input type="hidden" name="admin_id" id="assignAdminId">

      <div class="device-select-box">
        <?php if (!empty($allDevices)): ?>
          <?php foreach ($allDevices as $dev): ?>
            <label class="device-item">
              <input type="checkbox" name="devices[]" value="<?= htmlspecialchars($dev['device_code']) ?>" class="assign-checkbox">
              <span><?= htmlspecialchars($dev['device_name']) ?> — <?= htmlspecialchars($dev['device_code']) ?> (<?= htmlspecialchars($dev['location_name'] ?: 'No Location') ?>)</span>
            </label>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="font-size: 12px; color: #94a3b8; text-align: center; padding: 10px;">Belum ada perangkat di database.</p>
        <?php endif; ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('assignModal')">Cancel</button>
        <button type="submit" class="btn-save btn-save-assign">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL 2: EDIT ADMIN (IKON PENSIL) -->
<!-- ==================================================================== -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Edit Admin</h2>
      <button class="modal-close-btn" onclick="closeModal('editModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editAdminId">
      
      <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" id="editName" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="editEmail" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Password <span style="color: #94a3b8; text-transform: none; font-weight: 600;">(leave blank to keep current)</span></label>
        <input type="password" name="password" class="form-control" placeholder="••••••••">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn-save">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL 3: ADD ADMIN (+ ADD ADMIN) -->
<!-- ==================================================================== -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add Admin</h2>
      <button class="modal-close-btn" onclick="closeModal('addModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      
      <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" class="form-control" placeholder="admin3" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="admin3@test.com" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn-save">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL 4: KONFIRMASI DELETE ADMIN -->
<!-- ==================================================================== -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width: 400px; text-align: center;">
    <div style="width: 52px; height: 52px; background: rgba(254, 226, 226, 0.9); color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 16px;">
      <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Hapus Admin?</h2>
    <p style="font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 24px;">
      Apakah Anda yakin ingin menghapus <span id="deleteTargetAdmin" style="color: #0f172a; font-weight: 800;"></span>? Tindakan ini tidak bisa dibatalkan xixixi.
    </p>

    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="deleteAdminId">

      <div style="display: flex; gap: 12px; justify-content: center;">
        <button type="button" class="btn-cancel" style="flex: 1;" onclick="closeModal('deleteModal')">Batal</button>
        <button type="submit" class="btn-save btn-confirm-delete" style="flex: 1;">Yakin, Hapus</button>
      </div>
    </form>
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

// SEARCH FILTER TABLE
function filterAdmins() {
  const input = document.getElementById('searchInput').value.toLowerCase();
  const rows = document.querySelectorAll('#adminTable tbody tr.admin-row');
  rows.forEach(row => {
    const name = row.querySelector('.admin-name').innerText.toLowerCase();
    const email = row.querySelector('.admin-email').innerText.toLowerCase();
    row.style.display = (name.includes(input) || email.includes(input)) ? '' : 'none';
  });
}

// MODAL CONTROLS
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function openAddModal() {
  document.getElementById('addModal').classList.add('active');
}

function openEditModal(id, name, email) {
  document.getElementById('editAdminId').value = id;
  document.getElementById('editName').value = name;
  document.getElementById('editEmail').value = email;
  document.getElementById('editModal').classList.add('active');
}

function openAssignModal(adminId, adminName, assignedDevices) {
  document.getElementById('assignAdminId').value = adminId;
  document.getElementById('assignTargetAdmin').innerText = adminName;

  const checkboxes = document.querySelectorAll('.assign-checkbox');
  checkboxes.forEach(cb => {
    cb.checked = assignedDevices.includes(cb.value);
  });

  document.getElementById('assignModal').classList.add('active');
}

function openDeleteModal(id, name) {
  document.getElementById('deleteAdminId').value = id;
  document.getElementById('deleteTargetAdmin').innerText = name;
  document.getElementById('deleteModal').classList.add('active');
}
</script>

</body>
</html>