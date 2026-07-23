/*
  ESP8266/ESP32 + DHT11
  Mengirim data suhu & kelembaban ke server PHP setiap 5 detik.
  Field "jarak" (ultrasonik) TIDAK dikirim dari alat ini -
  server (insert_data.php) yang akan mengisi nilai dummy-nya.

  Library yang dibutuhkan (Library Manager Arduino IDE):
  - DHT sensor library (Adafruit)
  - ESP8266WiFi / WiFi.h (sudah bawaan board package)
*/

#include <ESP8266WiFi.h>       // ganti ke <WiFi.h> jika pakai ESP32
#include <ESP8266HTTPClient.h> // ganti ke <HTTPClient.h> jika pakai ESP32
#include <DHT.h>

#define DHTPIN   D4      // pin data DHT11
#define DHTTYPE  DHT11
DHT dht(DHTPIN, DHTTYPE);

const char* ssid     = "NAMA_WIFI_ANDA";
const char* password = "PASSWORD_WIFI_ANDA";

// Ganti dengan alamat IP komputer server PHP di jaringan lokal Anda
const char* serverUrl = "http://192.168.35.104/weather_dashboard/insert_data.php";

void setup() {
  Serial.begin(115200);
  dht.begin();

  WiFi.begin(ssid, password);
  Serial.print("Menghubungkan WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Terhubung, IP: " + WiFi.localIP().toString());
}

void loop() {
  float suhu = dht.readTemperature();
  float kelembaban = dht.readHumidity();

  if (isnan(suhu) || isnan(kelembaban)) {
    Serial.println("Gagal membaca sensor DHT11!");
    delay(5000);
    return;
  }

  if (WiFi.status() == WL_CONNECTED) {
    WiFiClient client;
    HTTPClient http;

    http.begin(client, serverUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String postData = "suhu=" + String(suhu, 2) + "&kelembaban=" + String(kelembaban, 2);
    int httpCode = http.POST(postData);

    Serial.println("Suhu: " + String(suhu) + " C, Kelembaban: " + String(kelembaban) + " %");
    Serial.println("Response code: " + String(httpCode));
    Serial.println(http.getString());

    http.end();
  } else {
    Serial.println("WiFi terputus.");
  }

  delay(5000); // kirim data tiap 5 detik
}
