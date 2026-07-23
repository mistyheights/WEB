# SCADA Smart – Mini Weather Station (PHP + MySQL)

Dashboard monitoring **DHT11 (suhu & kelembaban nyata dari ESP8266/ESP32)** dan **ultrasonik (data dummy)**, disimpan lokal via PHP + MySQL.

## Struktur File
| File | Fungsi |
|---|---|
| `database.sql` | Skema database (import dulu) |
| `config.php` | Koneksi ke MySQL |
| `create_admin.php` | Buat 1x akun admin, lalu **hapus** |
| `login.php` / `logout.php` | Autentikasi dashboard |
| `auth_check.php` | Proteksi halaman (session guard) |
| `insert_data.php` | **Endpoint API** dipanggil ESP8266/ESP32 |
| `get_data.php` | Endpoint AJAX untuk dashboard (perlu login) |
| `dashboard.php` | Halaman utama monitoring |
| `cetak_laporan.php` | Laporan/print histori data |
| `simulate_esp.php` | Simulasi kirim data dummy tanpa hardware (untuk testing) |
| `esp_dht11.ino` | Kode Arduino untuk ESP8266/ESP32 + DHT11 |

## Langkah Instalasi

1. **Siapkan server lokal** (XAMPP / Laragon), aktifkan Apache + MySQL.
2. Copy folder `weather_dashboard` ke `htdocs/` (XAMPP) atau `www/` (Laragon).
3. **Import database**: buka phpMyAdmin → New → Import → pilih `database.sql`.
   Atau lewat terminal:
   ```
   mysql -u root -p < database.sql
   ```
4. Cek `config.php`, sesuaikan `$dbuser`/`$dbpass` jika MySQL Anda pakai password.
5. Buka `http://localhost/weather_dashboard/create_admin.php` di browser sekali saja
   untuk membuat akun **admin / admin123** — lalu **hapus file ini**.
6. Login di `http://localhost/weather_dashboard/login.php`.
7. Anda akan masuk ke `dashboard.php`.

## Mengirim Data dari ESP8266/ESP32 (DHT11)

- Upload `esp_dht11.ino` ke board Anda lewat Arduino IDE.
- Ganti `ssid`, `password`, dan `serverUrl` (isi dengan IP komputer server di jaringan lokal, contoh `http://192.168.1.100/weather_dashboard/insert_data.php`).
- Alat akan mengirim `suhu` & `kelembaban` tiap 5 detik ke `insert_data.php`.
- Server otomatis menambahkan nilai **dummy** untuk jarak ultrasonik (20–400 cm acak), karena sensor ultrasonik fisik belum terpasang.
- Jika nanti sensor ultrasonik asli sudah dipasang, cukup:
  1. Tambahkan pembacaan sensor HC-SR04 di kode `.ino`, kirim sebagai field `jarak`.
  2. Di `insert_data.php`, ganti baris dummy dengan membaca `$_POST['jarak']` seperti suhu/kelembaban.

## Testing Tanpa Hardware

Belum punya ESP8266/DHT11 di tangan? Jalankan `simulate_esp.php` berulang kali
(atau set cron job tiap 5 detik) untuk mengisi data dummy suhu & kelembaban juga,
supaya dashboard & grafik bisa langsung dicoba.

## Catatan Keamanan
- Segera hapus `create_admin.php` setelah akun admin dibuat.
- Untuk production, tambahkan HTTPS dan batasi akses `insert_data.php` (misal dengan token/API key) agar tidak bisa diisi sembarang orang dari luar jaringan lokal.
