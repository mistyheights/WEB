<?php
require 'auth_check.php';
require 'config.php';

$username = $_SESSION['username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Settings - Smart Weather Station</title>
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
  .topbar { margin-bottom: 20px; }
  .topbar h1 { font-size: 28px; font-weight: 800; color: #0f172a; }

  /* TAB SELECTOR UNTUK 3 GAME */
  .game-tabs {
    display: flex; gap: 12px; margin-bottom: 20px;
  }
  .tab-btn {
    background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(255, 255, 255, 0.9);
    padding: 12px 22px; border-radius: 16px; font-size: 14px; font-weight: 800;
    color: #475569; cursor: pointer; transition: all 0.25s ease; display: flex; align-items: center; gap: 8px;
    backdrop-filter: blur(10px);
  }
  .tab-btn:hover { background: rgba(255, 255, 255, 0.9); color: #2563eb; transform: translateY(-2px); }
  .tab-btn.active {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff; border-color: rgba(255, 255, 255, 0.9);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
  }

  /* GAME CONTAINER LIQUID GLASS */
  .game-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.35) 100%);
    backdrop-filter: blur(35px) saturate(220%);
    -webkit-backdrop-filter: blur(35px) saturate(220%);
    border-radius: 28px; padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05), inset 0 1.5px 2px rgba(255, 255, 255, 1);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    position: relative; overflow: hidden; min-height: 480px;
  }

  .game-header {
    width: 100%; display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 16px; padding: 0 10px;
  }
  .game-title { font-size: 18px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
  
  .game-stats {
    display: flex; gap: 14px; font-weight: 800; font-size: 14px; color: #334155;
  }
  .stat-badge {
    background: rgba(255, 255, 255, 0.85); padding: 6px 16px; border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.9); box-shadow: 0 4px 10px rgba(0,0,0,0.03);
  }

  #gameCanvas {
    background: linear-gradient(180deg, #e0f2fe 0%, #f1f5f9 60%, #cbd5e1 100%);
    border-radius: 22px; border: 2px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06); cursor: pointer;
    touch-action: manipulation;
  }

  /* OVERLAY START / GAME OVER */
  .game-overlay {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(10px);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    z-index: 10; border-radius: 28px; text-align: center; color: #ffffff; padding: 20px;
  }
  .game-overlay h2 { font-size: 32px; font-weight: 800; margin-bottom: 8px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
  .game-overlay p { font-size: 14px; font-weight: 600; opacity: 0.9; margin-bottom: 24px; max-width: 400px; line-height: 1.5; }

  .btn-play {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.8);
    padding: 14px 36px; border-radius: 18px; font-size: 16px; font-weight: 800;
    cursor: pointer; transition: all 0.3s ease; box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
  }
  .btn-play:hover { transform: scale(1.06); box-shadow: 0 14px 30px rgba(37, 99, 235, 0.5); }
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
      <li><a href="settings.php" class="active" title="Settings"><i class="fa-solid fa-gamepad"></i><span class="menu-text">Settings</span></a></li>
      <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user"></i><span class="menu-text">Profile</span></a></li>
      <li><a href="logout.php" style="color: #e11d48;" title="Logout"><i class="fa-solid fa-right-from-bracket"></i><span class="menu-text">Logout</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <main class="main">
    <div class="topbar">
      <h1>Settings Interaktif</h1>
    </div>

    <!-- TABS GAME SELECTOR -->
    <div class="game-tabs">
      <button class="tab-btn active" onclick="switchGame('catRunner')">
        <i class="fa-solid fa-cat"></i> 1. Cat Runner 🐱
      </button>
      <button class="tab-btn" onclick="switchGame('roadFighter')">
        <i class="fa-solid fa-car-side"></i> 2. Road Fighter 🏎️
      </button>
      <button class="tab-btn" onclick="switchGame('sensorCollector')">
        <i class="fa-solid fa-satellite-dish"></i> 3. Sensor Collector 🌤️
      </button>
    </div>

    <!-- GAME CARD CONTAINER -->
    <div class="game-card">

      <div class="game-header">
        <div class="game-title" id="gameTitle">
          <i class="fa-solid fa-cat" style="color: #2563eb;"></i> Cat Runner 🐱
        </div>
        <div class="game-stats">
          <div class="stat-badge" id="extraStatBadge" style="display:none;">MISSED: <span id="extraStatText" style="color: #dc2626;">0/5</span></div>
          <div class="stat-badge">HIGH SCORE: <span id="highScoreText" style="color: #d97706;">0</span></div>
          <div class="stat-badge">SCORE: <span id="scoreText" style="color: #2563eb;">0</span></div>
        </div>
      </div>

      <!-- CANVAS GAME -->
      <canvas id="gameCanvas" width="760" height="380"></canvas>

      <!-- OVERLAY START / GAME OVER -->
      <div class="game-overlay" id="gameOverlay">
        <h2 id="overlayTitle">Cat Runner 🐱</h2>
        <p id="overlaySub">Tekan <b>Spasi / Panah Atas / Klik</b> untuk melompat!<br>Lewati niki.jpg dan Awan Badai 🌩️.</p>
        <button class="btn-play" onclick="startGame()">Mulai Main!</button>
      </div>

    </div>

  </main>
</div>

<script>
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

// ==========================================
// PRELOAD CUSTOM OBSTACLE IMAGE (niki.jpg)
// ==========================================
const nikiImg = new Image();
nikiImg.src = 'niki.jpg';
let nikiLoaded = false;
nikiImg.onload = () => { nikiLoaded = true; };

// ==========================================
// GAME STATE MANAGEMENT
// ==========================================
const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

let currentGame = 'catRunner'; // 'catRunner' | 'roadFighter' | 'sensorCollector'
let gameRunning = false;
let animationId;
let score = 0;
let missedCount = 0;
let frameCount = 0;

function getHighScore(gameKey) {
  return localStorage.getItem('hs_' + gameKey) || 0;
}
function setHighScore(gameKey, val) {
  localStorage.setItem('hs_' + gameKey, val);
}

function updateStatsDisplay() {
  document.getElementById('scoreText').innerText = score;
  document.getElementById('highScoreText').innerText = getHighScore(currentGame);
}

function switchGame(gameKey) {
  gameRunning = false;
  cancelAnimationFrame(animationId);
  currentGame = gameKey;

  // Update Active Tab UI
  const buttons = document.querySelectorAll('.tab-btn');
  buttons.forEach((btn, idx) => {
    btn.classList.remove('active');
    if ((gameKey === 'catRunner' && idx === 0) ||
        (gameKey === 'roadFighter' && idx === 1) ||
        (gameKey === 'sensorCollector' && idx === 2)) {
      btn.classList.add('active');
    }
  });

  document.getElementById('extraStatBadge').style.display = 'none';

  // Config UI Text per Game
  if (gameKey === 'catRunner') {
    document.getElementById('gameTitle').innerHTML = '<i class="fa-solid fa-cat" style="color: #2563eb;"></i> Cat Runner 🐱';
    document.getElementById('overlayTitle').innerText = 'Cat Runner 🐱';
    document.getElementById('overlaySub').innerHTML = 'Lompat lari pakai Kucing 🐱 (sudah hadap kanan, gak moonwalk lagi!).<br>Rintangan: <b>niki.jpg</b> di tanah & 🌩️ <b>Awan Badai</b> di udara.';
  } else if (gameKey === 'roadFighter') {
    document.getElementById('gameTitle').innerHTML = '<i class="fa-solid fa-car-side" style="color: #dc2626;"></i> Road Fighter 🏎️';
    document.getElementById('overlayTitle').innerText = 'Road Fighter 🏎️';
    document.getElementById('overlaySub').innerHTML = 'Kendalikan Mobil Merah 🚗 pakai tombol <b>A / D / Panah Kiri-Kanan</b> atau Mouse!<br>Hindari mobil balap lalu lintas 🚖 🚙 di jalanan.';
  } else if (gameKey === 'sensorCollector') {
    document.getElementById('gameTitle').innerHTML = '<i class="fa-solid fa-satellite-dish" style="color: #0284c7;"></i> Sensor Collector 🌤️';
    document.getElementById('overlayTitle').innerText = 'Sensor Collector 🌤️';
    document.getElementById('overlaySub').innerHTML = 'Ambil data sensor 🌡️ 💧 🍃!<br>⚠️ <b>Aturan:</b> Jangan ambil <b>Api 🔥 / Petir ⚡</b> (Langsung Kalah) & Jangan biarkan data lolos lebih dari <b>5x</b>!';
    document.getElementById('extraStatBadge').style.display = 'inline-block';
    document.getElementById('extraStatText').innerText = '0/5';
  }

  score = 0;
  missedCount = 0;
  updateStatsDisplay();
  document.getElementById('gameOverlay').style.display = 'flex';
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}


// ==========================================
// GAME 1: CAT RUNNER (Fixed Moonwalk & niki.jpg)
// ==========================================
const groundY = 320;
let cat = { x: 70, y: groundY - 45, w: 44, h: 45, dy: 0, isJumping: false, speed: 6.5 };
let catObstacles = [];

function initCatRunner() {
  cat = { x: 70, y: groundY - 45, w: 44, h: 45, dy: 0, isJumping: false, speed: 6.5 };
  catObstacles = [];
}

function updateCatRunner() {
  score += 1;
  document.getElementById('scoreText').innerText = Math.floor(score / 5);

  cat.dy += 0.65; // Gravity
  cat.y += cat.dy;

  if (cat.y >= groundY - cat.h) {
    cat.y = groundY - cat.h;
    cat.dy = 0;
    cat.isJumping = false;
  }

  // Spawn Obstacles
  if (frameCount % 80 === 0) {
    const isCloud = Math.random() > 0.5;
    catObstacles.push({
      x: canvas.width + 30,
      y: isCloud ? groundY - 85 : groundY - 45,
      w: 45, h: 45,
      isCloud: isCloud
    });
  }

  // Move & Collision
  for (let i = catObstacles.length - 1; i >= 0; i--) {
    let obs = catObstacles[i];
    obs.x -= cat.speed;

    if (
      cat.x + 8 < obs.x + obs.w &&
      cat.x + cat.w - 8 > obs.x &&
      cat.y + 8 < obs.y + obs.h &&
      cat.y + cat.h > obs.y
    ) {
      endGame(Math.floor(score / 5));
      return;
    }

    if (obs.x < -50) catObstacles.splice(i, 1);
  }
}

function drawCatRunner() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // Ground Line
  ctx.beginPath();
  ctx.moveTo(0, groundY);
  ctx.lineTo(canvas.width, groundY);
  ctx.strokeStyle = '#0f172a';
  ctx.lineWidth = 3;
  ctx.stroke();

  // Draw Cat Character (MOONWALK FIX: Mirrored scaleX -1 so it faces RIGHT!)
  ctx.save();
  ctx.translate(cat.x + cat.w, cat.y);
  ctx.scale(-1, 1); // Flip horizontally!
  ctx.font = '38px sans-serif';
  ctx.fillText('🐱', 0, 36);
  ctx.restore();

  // Draw Obstacles (niki.jpg for ground, 🌩️ for air)
  catObstacles.forEach(obs => {
    if (obs.isCloud) {
      ctx.font = '34px sans-serif';
      ctx.fillText('🌩️', obs.x, obs.y + 35);
    } else {
      if (nikiLoaded) {
        ctx.drawImage(nikiImg, obs.x, obs.y, obs.w, obs.h);
      } else {
        ctx.font = '34px sans-serif';
        ctx.fillText('🗿', obs.x, obs.y + 35);
      }
    }
  });
}


// ==========================================
// GAME 2: ROAD FIGHTER (2D Car Dodge)
// ==========================================
const roadX = 230;
const roadW = 300;
let carPlayer = { x: roadX + roadW / 2 - 20, y: 300, w: 40, h: 60, speed: 7 };
let trafficCars = [];

function initRoadFighter() {
  carPlayer = { x: roadX + roadW / 2 - 20, y: 300, w: 40, h: 60, speed: 7 };
  trafficCars = [];
}

function updateRoadFighter() {
  score += 1;
  document.getElementById('scoreText').innerText = Math.floor(score / 3);

  // Spawn Traffic
  if (frameCount % 45 === 0) {
    const laneX = roadX + 20 + Math.random() * (roadW - 80);
    const emojis = ['🚖', '🚙', '🏎️', '🚚'];
    trafficCars.push({
      x: laneX,
      y: -70,
      w: 40, h: 60,
      speed: 4 + Math.random() * 3,
      emoji: emojis[Math.floor(Math.random() * emojis.length)]
    });
  }

  // Move & Collision
  for (let i = trafficCars.length - 1; i >= 0; i--) {
    let t = trafficCars[i];
    t.y += t.speed;

    if (
      carPlayer.x + 5 < t.x + t.w &&
      carPlayer.x + carPlayer.w - 5 > t.x &&
      carPlayer.y + 5 < t.y + t.h &&
      carPlayer.y + carPlayer.h - 5 > t.y
    ) {
      endGame(Math.floor(score / 3));
      return;
    }

    if (t.y > canvas.height + 80) trafficCars.splice(i, 1);
  }
}

function drawRoadFighter() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // Draw Asphalt Road
  ctx.fillStyle = '#334155';
  ctx.fillRect(roadX, 0, roadW, canvas.height);

  // Road Side Lines
  ctx.fillStyle = '#ef4444';
  ctx.fillRect(roadX - 10, 0, 10, canvas.height);
  ctx.fillRect(roadX + roadW, 0, 10, canvas.height);

  // Dashed Center Line
  ctx.strokeStyle = '#ffffff';
  ctx.lineWidth = 4;
  ctx.setLineDash([20, 20]);
  ctx.lineDashOffset = -frameCount * 8;
  ctx.beginPath();
  ctx.moveTo(roadX + roadW / 2, 0);
  ctx.lineTo(roadX + roadW / 2, canvas.height);
  ctx.stroke();
  ctx.setLineDash([]); // reset

  // Draw Player Red Car
  ctx.font = '45px sans-serif';
  ctx.fillText('🚗', carPlayer.x, carPlayer.y + 48);

  // Draw Traffic
  trafficCars.forEach(t => {
    ctx.fillText(t.emoji, t.x, t.y + 48);
  });
}


// ==========================================
// GAME 3: WEATHER SENSOR COLLECTOR (New Rules)
// ==========================================
let rxPlayer = { x: canvas.width / 2 - 40, y: canvas.height - 35, w: 80, h: 20 };
let sensorItems = [];
const goodTypes = ['🌡️', '💧', '🍃'];
const badTypes  = ['🔥', '⚡'];

function initSensorCollector() {
  rxPlayer = { x: canvas.width / 2 - 40, y: canvas.height - 35, w: 80, h: 20 };
  sensorItems = [];
  missedCount = 0;
  document.getElementById('extraStatText').innerText = '0/5';
}

function updateSensorCollector() {
  if (frameCount % 40 === 0) {
    const isBad = Math.random() < 0.3; // 30% chance for danger (fire/lightning)
    const icon = isBad 
      ? badTypes[Math.floor(Math.random() * badTypes.length)]
      : goodTypes[Math.floor(Math.random() * goodTypes.length)];

    sensorItems.push({
      x: Math.random() * (canvas.width - 40) + 20,
      y: -20,
      speed: 2.5 + Math.random() * 2.5,
      icon: icon,
      isBad: isBad
    });
  }

  for (let i = sensorItems.length - 1; i >= 0; i--) {
    let item = sensorItems[i];
    item.y += item.speed;

    // Catch Check
    if (
      item.y + 15 >= rxPlayer.y &&
      item.x >= rxPlayer.x - 15 &&
      item.x <= rxPlayer.x + rxPlayer.w + 15
    ) {
      if (item.isBad) {
        // RULE 1: ATURAN AMBIL API / PETIR -> LANGSUNG KALAH!
        endGame(score, "Kamu mengambil Api 🔥 / Petir ⚡! ESP32 milikmu terbakar!");
        return;
      } else {
        score += 10;
        document.getElementById('scoreText').innerText = score;
      }
      sensorItems.splice(i, 1);
      continue;
    }

    // Miss Check
    if (item.y > canvas.height) {
      if (!item.isBad) {
        missedCount++;
        document.getElementById('extraStatText').innerText = `${missedCount}/5`;
        
        // RULE 2: LEBIH DARI 5 DATA SENOR LOLOS -> LANGSUNG KALAH!
        if (missedCount > 5) {
          endGame(score, "Lebih dari 5 data sensor lolos (Missed > 5)! Sinyal terputus!");
          return;
        }
      }
      sensorItems.splice(i, 1);
    }
  }
}

function drawSensorCollector() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // Draw ESP32 RX Badge
  ctx.fillStyle = '#0284c7';
  ctx.beginPath();
  ctx.roundRect(rxPlayer.x, rxPlayer.y, rxPlayer.w, rxPlayer.h, 10);
  ctx.fill();

  ctx.fillStyle = '#ffffff';
  ctx.font = 'bold 11px sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText('ESP32 RX', rxPlayer.x + rxPlayer.w / 2, rxPlayer.y + 14);

  // Draw Sensors / Danger Items
  ctx.font = '24px sans-serif';
  sensorItems.forEach(item => {
    ctx.fillText(item.icon, item.x, item.y);
  });
}


// ==========================================
// MAIN CONTROLLER & INPUT HANDLER
// ==========================================
function startGame() {
  score = 0;
  missedCount = 0;
  frameCount = 0;
  gameRunning = true;

  if (currentGame === 'catRunner') initCatRunner();
  if (currentGame === 'roadFighter') initRoadFighter();
  if (currentGame === 'sensorCollector') initSensorCollector();

  updateStatsDisplay();
  document.getElementById('gameOverlay').style.display = 'none';

  cancelAnimationFrame(animationId);
  mainLoop();
}

function endGame(finalScore, customMsg = null) {
  gameRunning = false;
  const hs = getHighScore(currentGame);
  if (finalScore > hs) {
    setHighScore(currentGame, finalScore);
  }

  document.getElementById('overlayTitle').innerText = 'Game Over! 💥';
  document.getElementById('overlaySub').innerHTML = customMsg 
    ? customMsg + `<br>Skor Akhir: <b>${finalScore}</b>`
    : `Skor Akhir Kamu: <b>${finalScore}</b> poin!`;
  
  document.querySelector('.btn-play').innerText = 'Main Lagi 🔄';
  document.getElementById('gameOverlay').style.display = 'flex';
}

function mainLoop() {
  if (!gameRunning) return;
  frameCount++;

  if (currentGame === 'catRunner') {
    updateCatRunner();
    drawCatRunner();
  } else if (currentGame === 'roadFighter') {
    updateRoadFighter();
    drawRoadFighter();
  } else if (currentGame === 'sensorCollector') {
    updateSensorCollector();
    drawSensorCollector();
  }

  animationId = requestAnimationFrame(mainLoop);
}

// Global Inputs
window.addEventListener('keydown', (e) => {
  if (['ArrowLeft', 'KeyA'].includes(e.code)) {
    if (currentGame === 'roadFighter') carPlayer.x = Math.max(roadX, carPlayer.x - 25);
    if (currentGame === 'sensorCollector') rxPlayer.x = Math.max(0, rxPlayer.x - 25);
  }
  if (['ArrowRight', 'KeyD'].includes(e.code)) {
    if (currentGame === 'roadFighter') carPlayer.x = Math.min(roadX + roadW - carPlayer.w, carPlayer.x + 25);
    if (currentGame === 'sensorCollector') rxPlayer.x = Math.min(canvas.width - rxPlayer.w, rxPlayer.x + 25);
  }
  if (['Space', 'ArrowUp', 'KeyW'].includes(e.code)) {
    if (currentGame === 'catRunner' && !cat.isJumping) {
      cat.dy = -13.5;
      cat.isJumping = true;
    }
  }
});

canvas.addEventListener('mousemove', (e) => {
  const rect = canvas.getBoundingClientRect();
  const mouseX = e.clientX - rect.left;
  if (currentGame === 'roadFighter') {
    carPlayer.x = Math.max(roadX, Math.min(roadX + roadW - carPlayer.w, mouseX - carPlayer.w / 2));
  }
  if (currentGame === 'sensorCollector') {
    rxPlayer.x = Math.max(0, Math.min(canvas.width - rxPlayer.w, mouseX - rxPlayer.w / 2));
  }
});
</script>

</body>
</html>