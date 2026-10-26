<?php
/**
 * koneksi.php - Manajemen Koneksi Database MySQL & Parser .env
 * UTS Pemrograman Web - Sistem Inventaris Apotek
 * Menggunakan PHP Native tanpa framework.
 */

// 1. Fungsi Parser File .env Sederhana (Native PHP)
if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $filePath): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Lewati komentar dan baris kosong
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Hilangkan kutip jika ada
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $variables[$key] = $value;
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }

        return $variables;
    }
}

// 2. Muat konfigurasi dari file .env (fallback ke .env.example jika .env belum dibuat)
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env.example';
}
loadEnvFile($envPath);

// 3. Ambil parameter koneksi dari environment atau gunakan nilai default
$host     = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$port     = (int) (getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306));
$user     = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
$pass     = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($_ENV['DB_PASSWORD'] ?? '');
$db       = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'db_apotik');
$cfgTable = getenv('DB_TABLE') ?: ($_ENV['DB_TABLE'] ?? 'barang');

// 4. Inisialisasi Koneksi MySQLi
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    // Pesan informatif jika database belum dibuat atau MySQL belum aktif
    $errMessage = "Koneksi database gagal: " . $conn->connect_error . "<br>";
    $errMessage .= "<small>Pastikan MySQL di Laragon sudah aktif dan database <strong>`{$db}`</strong> sudah dibuat. Jalankan <a href='setup.php'>setup.php</a> untuk inisialisasi awal.</small>";
    die($errMessage);
}

// Set karakter encoding ke UTF-8
$conn->set_charset("utf8mb4");

// 5. Tentukan nama tabel yang aktif (prioritaskan DB_TABLE, jika belum ada cek tabel menu atau barang)
$tableBarang = $cfgTable;
$checkTable = $conn->query("SHOW TABLES LIKE '{$cfgTable}'");
if (!$checkTable || $checkTable->num_rows === 0) {
    // Cek alternatif: jika 'barang' tidak ditemukan tapi 'menu' ada, gunakan 'menu'
    $checkMenu = $conn->query("SHOW TABLES LIKE 'menu'");
    if ($checkMenu && $checkMenu->num_rows > 0) {
        $tableBarang = 'menu';
    }
}
?>