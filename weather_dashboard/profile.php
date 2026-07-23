<?php
require 'auth_check.php';
require 'config.php';

$username_session = $_SESSION['username'] ?? 'admin';
$msg_success = '';
$msg_error = '';

// --------------------------------------------------------------------------
// 1. AMBIL DATA PROFILE USER DARIPADA DATABASE
// --------------------------------------------------------------------------
$userData = [
    'id'       => 0,
    'name'     => $username_session,
    'email'    => '-',
    'role'     => 'Superadmin'
];

try {
    // Query data user berdasarkan username session
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR name = ? LIMIT 1");
    $stmt->execute([$username_session, $username_session]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userRow) {
        $userData['id']    = $userRow['id'];
        $userData['name']  = $userRow['name'] ?? $userRow['username'] ?? $username_session;
        $userData['email'] = !empty($userRow['email']) ? $userRow['email'] : '-';
        $userData['role']  = $userRow['role'] ?? 'Superadmin';
    }
} catch (PDOException $e) {
    // Fallback jika terjadi error query
}

// --------------------------------------------------------------------------
// 2. PROSES UPDATE PASSWORD
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $msg_error = "Semua kolom password wajib diisi!";
    } elseif ($new_password !== $confirm_password) {
        $msg_error = "Konfirmasi password baru tidak cocok!";
    } else {
        try {
            // Ambil hash password lama dari DB
            $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmtPass->execute([$userData['id']]);
            $storedHash = $stmtPass->fetchColumn();

            // Verifikasi password lama (mendukung password_hash & plain text fallback)
            if ($storedHash && (password_verify($current_password, $storedHash) || $current_password === $storedHash)) {
                $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $userData['id']]);
                
                $msg_success = "Password berhasil diperbarui!";
            } else {
                $msg_error = "Password saat ini salah!";
            }
        } catch (PDOException $e) {
            $msg_error = "Gagal memperbarui password: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Profile - Smart Weather Station</title>
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

  /* PROFILE GRID 2 COLUMNS */
  .profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
  }

  /* CARD CONTAINER LIQUID GLASS */
  .glass-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px) saturate(220%);
    -webkit-backdrop-filter: blur(35px) saturate(220%);
    border-radius: 28px; padding: 32px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

  .card-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 28px; }

  /* ACCOUNT INFO STYLING */
  .info-group { margin-bottom: 24px; }
  .info-group:last-child { margin-bottom: 0; }
  .info-label { font-size: 13px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; }
  .info-value { font-size: 16px; font-weight: 800; color: #0f172a; }

  /* FORM INPUT STYLING WITH EYE TOGGLE */
  .form-group { margin-bottom: 20px; }
  .form-group label { display: block; font-size: 13px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; }
  
  .input-password-wrapper {
    position: relative;
    width: 100%;
  }

  .form-control {
    width: 100%; padding: 12px 42px 12px 16px; 
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.9); 
    border-radius: 14px; 
    font-size: 14px; font-weight: 600; color: #0f172a; 
    outline: none; transition: all 0.25s ease;
  }

  .form-control:focus { 
    background: #ffffff; 
    border-color: #0284c7; 
    box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12); 
  }

  .toggle-password-btn {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 15px;
    transition: color 0.2s ease;
  }

  .toggle-password-btn:hover { color: #0284c7; }

  /* BUTTON CHANGE PASSWORD */
  .btn-change-pass {
    background: linear-gradient(135deg, #1ca3b8 0%, #117a8b 100%);
    color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.8);
    padding: 12px 28px; border-radius: 14px; font-size: 14px; font-weight: 800;
    cursor: pointer; transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(28, 163, 184, 0.3); margin-top: 8px;
  }
  .btn-change-pass:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(28, 163, 184, 0.4); }

  /* ALERTS */
  .toast { padding: 12px 18px; border-radius: 16px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
  .toast-success { background: rgba(220, 252, 231, 0.9); color: #15803d; border: 1px solid rgba(187, 247, 208, 0.9); }
  .toast-error { background: rgba(254, 226, 226, 0.9); color: #b91c1c; border: 1px solid rgba(254, 202, 202, 0.9); }

  @media (max-width: 900px) {
    .profile-grid { grid-template-columns: 1fr; }
  }
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
      <li><a href="admin_management.php" title="Admin Management"><i class="fa-solid fa-users-gear"></i><span class="menu-text">Admin Management</span></a></li>
      <li><a href="client_management.php" title="Client Management"><i class="fa-solid fa-users"></i><span class="menu-text">Client Management</span></a></li>
      <li><a href="settings.php" title="Settings"><i class="fa-solid fa-gear"></i><span class="menu-text">Settings</span></a></li>
      <li><a href="profile.php" class="active" title="Profile"><i class="fa-solid fa-user"></i><span class="menu-text">Profile</span></a></li>
      <li><a href="logout.php" style="color: #e11d48;" title="Logout"><i class="fa-solid fa-right-from-bracket"></i><span class="menu-text">Logout</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">
    <div class="topbar">
      <h1>Profile</h1>
    </div>

    <?php if (!empty($msg_success)): ?>
      <div class="toast toast-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_success) ?></div>
    <?php endif; ?>

    <?php if (!empty($msg_error)): ?>
      <div class="toast toast-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($msg_error) ?></div>
    <?php endif; ?>

    <!-- PROFILE 2-COLUMN GRID -->
    <div class="profile-grid">

      <!-- CARD 1: ACCOUNT INFORMATION -->
      <div class="glass-card">
        <div class="card-title">Account Information</div>

        <div class="info-group">
          <div class="info-label">Name</div>
          <div class="info-value"><?= htmlspecialchars($userData['name']) ?></div>
        </div>

        <div class="info-group">
          <div class="info-label">Email</div>
          <div class="info-value"><?= htmlspecialchars($userData['email']) ?></div>
        </div>

        <div class="info-group">
          <div class="info-label">Role</div>
          <div class="info-value"><?= htmlspecialchars($userData['role']) ?></div>
        </div>
      </div>

      <!-- CARD 2: CHANGE PASSWORD -->
      <div class="glass-card">
        <div class="card-title">Change Password</div>

        <form method="POST">
          <input type="hidden" name="action" value="change_password">

          <div class="form-group">
            <label>Current Password</label>
            <div class="input-password-wrapper">
              <input type="password" name="current_password" id="currPass" class="form-control" required>
              <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('currPass', this)">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label>New Password</label>
            <div class="input-password-wrapper">
              <input type="password" name="new_password" id="newPass" class="form-control" required>
              <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('newPass', this)">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label>Confirm New Password</label>
            <div class="input-password-wrapper">
              <input type="password" name="confirm_password" id="confirmPass" class="form-control" required>
              <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirmPass', this)">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-change-pass">Change Password</button>
        </form>
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

// TOGGLE INTIP / SEMBUNYIKAN PASSWORD
function togglePasswordVisibility(inputId, btnIcon) {
  const input = document.getElementById(inputId);
  const icon = btnIcon.querySelector('i');

  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}
</script>

</body>
</html>