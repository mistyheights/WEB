<?php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Username atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login - Smart Weather Monitor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  * { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", Roboto, sans-serif; 
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

  /* AURORA MULTI-TONE BACKGROUND BLOBS */
  .aurora-blob {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    z-index: 0;
    pointer-events: none;
    opacity: 0.75;
    animation: floatAurora 12s infinite alternate ease-in-out;
  }
  .blob-red {
    width: 450px; height: 450px;
    background: radial-gradient(circle, #ef4444 0%, rgba(244, 63, 94, 0.25) 100%);
    top: -100px; left: -80px;
  }
  .blob-blue {
    width: 480px; height: 480px;
    background: radial-gradient(circle, #3b82f6 0%, rgba(14, 165, 233, 0.25) 100%);
    bottom: -100px; right: -80px;
  }
  .blob-green {
    width: 380px; height: 380px;
    background: radial-gradient(circle, #10b981 0%, rgba(52, 211, 153, 0.25) 100%);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
  }

  @keyframes floatAurora {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(40px, -35px) scale(1.12); }
  }

  /* CONTAINER WRAPPER UNTUK POSISI FLOATING BADGES */
  .login-wrapper {
    position: relative;
    z-index: 10;
  }

  /* FLOATING DECORATIVE BADGES (PEMANIIS TAMPILAN) */
  .float-badge {
    position: absolute;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.4) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    padding: 10px 16px;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 12;
    animation: badgeFloat 6s infinite alternate ease-in-out;
  }

  .badge-1 { top: -25px; left: -40px; color: #ef4444; animation-delay: 0s; }
  .badge-2 { bottom: -20px; right: -35px; color: #2563eb; animation-delay: 1.5s; }
  .badge-3 { top: 40%; right: -60px; color: #10b981; animation-delay: 3s; }

  @keyframes badgeFloat {
    0% { transform: translateY(0px) rotate(0deg); }
    100% { transform: translateY(-12px) rotate(2deg); }
  }

  /* LIQUID GLASS LOGIN BOX */
  .login-box {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px) saturate(220%);
    -webkit-backdrop-filter: blur(35px) saturate(220%);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 28px;
    padding: 42px 38px;
    width: 380px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.08), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    position: relative;
    animation: glassEntrance 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @keyframes glassEntrance {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
  }

  .brand-header {
    text-align: center;
    margin-bottom: 28px;
  }

  .brand-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.05) 100%);
    border: 1.5px solid rgba(37, 99, 235, 0.3);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 14px;
    font-size: 26px;
    color: #2563eb;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.18);
  }

  .logo {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #0f172a;
  }
  .logo span { color: #2563eb; }

  .subtitle {
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    margin-top: 4px;
    text-transform: uppercase;
  }

  /* INPUT FORM STYLING WITH ICONS */
  .form-group {
    margin-bottom: 20px;
  }

  label {
    display: block;
    font-size: 12px;
    font-weight: 800;
    color: #334155;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }

  .input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
  }

  .input-wrapper i {
    position: absolute;
    left: 16px;
    color: #64748b;
    font-size: 15px;
    transition: color 0.3s;
  }

  input {
    width: 100%;
    padding: 13px 16px 13px 44px;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 16px;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    outline: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
  }

  input:focus {
    background: rgba(255, 255, 255, 0.95);
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
  }

  input:focus + i, input:focus ~ i {
    color: #2563eb;
  }

  /* BUTTON STYLING */
  button {
    width: 100%;
    padding: 14px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 16px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    font-weight: 800;
    font-size: 15px;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35), inset 0 1.5px 2px rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
  }

  button:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(37, 99, 235, 0.45);
  }

  button:active {
    transform: translateY(0);
  }

  /* ALERT ERROR */
  .error {
    background: rgba(254, 226, 226, 0.85);
    backdrop-filter: blur(10px);
    color: #be123c;
    border: 1px solid rgba(239, 68, 68, 0.3);
    padding: 12px 16px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 22px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    animation: shake 0.4s ease-in-out;
  }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-6px); }
    40%, 80% { transform: translateX(6px); }
  }

  .hint {
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    margin-top: 22px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.7);
    border-radius: 12px;
  }
</style>
</head>
<body>

<!-- AURORA LIQUID BLOBS -->
<div class="aurora-blob blob-red"></div>
<div class="aurora-blob blob-blue"></div>
<div class="aurora-blob blob-green"></div>

<div class="login-wrapper">

  <!-- FLOATING PEMANIS BADGES -->
  <div class="float-badge badge-1">
    <i class="fa-solid fa-temperature-half"></i> Suhu: 28.5 °C
  </div>
  <div class="float-badge badge-2">
    <i class="fa-solid fa-droplet"></i> Lembab: 65 %
  </div>
  <div class="float-badge badge-3">
    <i class="fa-solid fa-satellite-dish"></i> Node Active
  </div>

  <!-- LOGIN KOTAK KACA -->
  <div class="login-box">
    <div class="brand-header">
      <div class="brand-icon">
        <i class="fa-solid fa-satellite-dish"></i>
      </div>
      <div class="logo"><span>SMART</span> WEATHER</div>
      <div class="subtitle">Station Control Panel</div>
    </div>

    <?php if ($error): ?>
      <div class="error">
        <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <div class="input-wrapper">
          <i class="fa-solid fa-user"></i>
          <input type="text" name="username" placeholder="Masukkan username..." required autofocus autocomplete="off">
        </div>
      </div>

      <div class="form-group">
        <label>Password</label>
        <div class="input-wrapper">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" placeholder="Masukkan password..." required>
        </div>
      </div>

      <button type="submit">
        <span>Masuk Portal</span>
        <i class="fa-solid fa-right-to-bracket"></i>
      </button>
    </form>

    <div class="hint">
      <i class="fa-solid fa-circle-info" style="color: #2563eb;"></i> Default: <strong>admin</strong> / <strong>admin123</strong>
    </div>
  </div>

</div>

</body>
</html>