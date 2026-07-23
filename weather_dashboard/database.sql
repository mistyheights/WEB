-- ============================================
-- Database: weather_station
-- Import file ini lewat phpMyAdmin atau:
--   mysql -u root -p < database.sql
-- ============================================

CREATE DATABASE IF NOT EXISTS weather_station
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE weather_station;

-- Tabel user untuk login admin dashboard
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel data sensor
-- suhu & kelembaban  -> dikirim NYATA dari DHT11 via ESP8266/ESP32
-- jarak              -> DATA DUMMY sensor ultrasonik (belum terpasang alat asli)
CREATE TABLE IF NOT EXISTS sensor_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  suhu FLOAT NOT NULL,
  kelembaban FLOAT NOT NULL,
  jarak FLOAT NOT NULL,
  waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
