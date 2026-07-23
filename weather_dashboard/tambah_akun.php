<?php
require 'auth_check.php';
require 'config.php';

$username_sess = $_SESSION['username'] ?? 'admin';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_username) || empty($new_password)) {
        $error = "Semua kolom wajib diisi!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // Cek apakah username sudah digunakan
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$new_username]);
        if ($stmt->fetch()) {
            $error = "Username sudah terdaftar, gunakan username lain.";
        } else {
            // Hash password dan simpan ke database
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            if ($stmt->execute([$new_username, $hashed_password])) {
                $success = "Akun baru '{$new_username}' berhasil ditambahkan!";
            } else {
                $error = "Gagal menambah akun baru.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Tambah Akun - Smart Weather Monitor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  * { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
  }

  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
    color: #0f172a;
    position: relative;
    overflow: hidden;
  }

  /* AURORA BLOBS */
  .aurora-blob {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    z-index: 0;
    pointer-events: none;
    opacity: 0.75;
    animation: floatAurora 12s infinite alternate ease-in-out;
  }
  .blob-blue { width: 450px; height: 450px; background: radial-gradient(circle, #3b82f6 0%, rgba(14, 165, 233, 0.25) 100%); top: -100px; left: -80px; }
  .blob-green { width: 480px; height: 480px; background: radial-gradient(circle, #10b981 0%, rgba(52, 211, 153, 0.25) 100%); bottom: -100px; right: -80px; }

  @keyframes floatAurora {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(35px, -30px) scale(1.1); }
  }

  /* GLASS CARD */
  .card-box {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px) saturate(220%);
    -webkit-backdrop-filter: blur(35px) saturate(220%);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 28px;
    padding: 40px 36px;
    width: 420px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.08), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    position: relative;
    z-index: 10;
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .header {
    text-align: center;
    margin-bottom: 28px;
  }
  .header-icon {
    width: 58px; height: 58px;
    background: rgba(37, 99, 235, 0.12);
    border: 1.5px solid rgba(37, 99, 235, 0.3);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
    font-size: 24px; color: #2563eb;
  }
  .header h2 { font-size: 22px; font-weight: 800; color: #0f172a; }
  .header p { font-size: 13px; color: #64748b; margin-top: 4px; font-weight: 600; }

  /* FORM STYLING */
  .form-group { margin-bottom: 18px; }
  label { display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
  
  .input-wrapper { position: relative; display: flex; align-items: center; }
  .input-wrapper i { position: absolute; left: 16px; color: #64748b; font-size: 15px; }
  
  input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 14px;
    font-size: 14px; font-weight: 600; color: #0f172a;
    outline: none; transition: all 0.3s ease;
  }
  input:focus {
    background: rgba(255, 255, 255, 0.95);
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
  }

  /* BUTTONS */
  .btn-submit {
    width: 100%;
    padding: 13px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 14px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff; font-weight: 800; font-size: 14px;
    cursor: pointer; transition: all 0.25s ease;
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-top: 22px;
  }
  .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(37, 99, 235, 0.45); }

  .btn-back {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    text-decoration: none; color: #475569; font-size: 13px; font-weight: 700;
    margin-top: 16px; transition: color 0.2s;
  }
  .btn-back:hover { color: #2563eb; }

  /* ALERTS */
  .alert {
    padding: 12px 16px; border-radius: 14px; font-size: 13px; font-weight: 700;
    margin-bottom: 20px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .alert-error { background: rgba(254, 226, 226, 0.85); color: #be123c; border: 1px solid rgba(239, 68, 68, 0.3); }
  .alert-success { background: rgba(209, 250, 229, 0.85); color: #047857; border: 1px solid rgba(16, 185, 129, 0.3); }
</style>
</head>
<body>

<div class="aurora-blob blob-blue"></div>
<div class="aurora-blob blob-green"></div>

<div class="card-box">
  <div class="header">
    <div class="header-icon"><i class="fa-solid fa-user-plus"></i></div>
    <h2>Tambah Akun Baru</h2>
    <p>Buat kredensial login untuk operator/admin baru</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Username Baru</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-user"></i>
        <input type="text" name="username" placeholder="Masukkan username..." required autocomplete="off">
      </div>
    </div>

    <div class="form-group">
      <label>Password</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" placeholder="Masukkan password..." required>
      </div>
    </div>

    <div class="form-group">
      <label>Konfirmasi Password</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-shield-halved"></i>
        <input type="password" name="confirm_password" placeholder="Ulangi password..." required>
      </div>
    </div>

    <button type="submit" class="btn-submit">
      <i class="fa-solid fa-floppy-disk"></i> Simpan Akun Baru
    </button>
  </form>

  <a href="dashboard.php" class="btn-back">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
  </a>
</div>

</body>
</html>