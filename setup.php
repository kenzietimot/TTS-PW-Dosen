<?php
/**
 * setup.php - Inisialisasi Database dan Data Contoh Inventaris Apotek
 * UTS Pemrograman Web - SehatMart
 */

// 1. Muat parser konfigurasi .env
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env.example';
}

$dbHost = 'localhost';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$dbName = 'db_apotik';
$dbTable = 'barang';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
                (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            if ($k === 'DB_HOST') $dbHost = $v;
            if ($k === 'DB_PORT') $dbPort = (int)$v;
            if ($k === 'DB_USERNAME') $dbUser = $v;
            if ($k === 'DB_PASSWORD') $dbPass = $v;
            if ($k === 'DB_DATABASE') $dbName = $v;
            if ($k === 'DB_TABLE') $dbTable = $v;
        }
    }
}

// 2. Koneksi ke server MySQL tanpa memilih database terlebih dahulu
mysqli_report(MYSQLI_REPORT_OFF);
$serverConn = @new mysqli($dbHost, $dbUser, $dbPass, '', $dbPort);

if ($serverConn->connect_error) {
    die("<div style='font-family: sans-serif; padding: 20px; color: #b91c1c;'>
        <h2>Koneksi ke MySQL Server Gagal!</h2>
        <p>Pesan error: " . htmlspecialchars($serverConn->connect_error) . "</p>
        <p>Pastikan server MySQL pada Laragon sudah dijalankan (klik <strong>Start All</strong> di Laragon).</p>
    </div>");
}

$serverConn->set_charset("utf8mb4");

// 3. Buat database jika belum ada
$sqlCreateDb = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$serverConn->query($sqlCreateDb)) {
    die("<div style='color: red;'>Gagal membuat database: " . $serverConn->error . "</div>");
}

$serverConn->select_db($dbName);
$conn = $serverConn;

// 4. Struktur Schema Tabel Inventaris Apotek
// Dibuat mendukung kolom standar UTS (nama_barang, jumlah, harga)
// sekaligus kompatibel dengan kolom template kelompok (nama_menu, stok).
$sqlTableSchema = " (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_barang VARCHAR(100) NOT NULL,
    nama_menu VARCHAR(100) NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    merek VARCHAR(100) NULL,
    satuan VARCHAR(30) NOT NULL DEFAULT 'pcs',
    harga_beli INT NOT NULL DEFAULT 0,
    harga INT NOT NULL DEFAULT 0,
    jumlah INT NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    stok_minimum INT NOT NULL DEFAULT 5,
    tanggal_kedaluwarsa DATE NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Buat tabel utama sesuai konfigurasi (misal 'barang')
$conn->query("CREATE TABLE IF NOT EXISTS `{$dbTable}` {$sqlTableSchema}");

// Jika konfigurasi DB_TABLE bukan 'menu', buat juga tabel 'menu' sebagai mirror/kompatibilitas
if ($dbTable !== 'menu') {
    $conn->query("CREATE TABLE IF NOT EXISTS `menu` {$sqlTableSchema}");
} else {
    $conn->query("CREATE TABLE IF NOT EXISTS `barang` {$sqlTableSchema}");
}

// Pastikan kolom-kolom penting ada jika tabel sudah dibuat sebelumnya dengan struktur lama
$targetTables = array_unique([$dbTable, 'barang', 'menu']);
foreach ($targetTables as $tbl) {
    $checkTbl = $conn->query("SHOW TABLES LIKE '{$tbl}'");
    if ($checkTbl && $checkTbl->num_rows > 0) {
        $kolomWajib = [
            'nama_barang' => "ALTER TABLE `{$tbl}` ADD COLUMN nama_barang VARCHAR(100) NOT NULL DEFAULT '' AFTER id",
            'nama_menu' => "ALTER TABLE `{$tbl}` ADD COLUMN nama_menu VARCHAR(100) NOT NULL DEFAULT '' AFTER nama_barang",
            'kategori' => "ALTER TABLE `{$tbl}` ADD COLUMN kategori VARCHAR(100) NOT NULL DEFAULT 'Obat Bebas' AFTER nama_menu",
            'merek' => "ALTER TABLE `{$tbl}` ADD COLUMN merek VARCHAR(100) NULL AFTER kategori",
            'satuan' => "ALTER TABLE `{$tbl}` ADD COLUMN satuan VARCHAR(30) NOT NULL DEFAULT 'pcs' AFTER merek",
            'harga_beli' => "ALTER TABLE `{$tbl}` ADD COLUMN harga_beli INT NOT NULL DEFAULT 0 AFTER satuan",
            'harga' => "ALTER TABLE `{$tbl}` ADD COLUMN harga INT NOT NULL DEFAULT 0 AFTER harga_beli",
            'jumlah' => "ALTER TABLE `{$tbl}` ADD COLUMN jumlah INT NOT NULL DEFAULT 0 AFTER harga",
            'stok' => "ALTER TABLE `{$tbl}` ADD COLUMN stok INT NOT NULL DEFAULT 0 AFTER jumlah",
            'stok_minimum' => "ALTER TABLE `{$tbl}` ADD COLUMN stok_minimum INT NOT NULL DEFAULT 5 AFTER stok",
            'tanggal_kedaluwarsa' => "ALTER TABLE `{$tbl}` ADD COLUMN tanggal_kedaluwarsa DATE NULL AFTER stok_minimum",
            'deskripsi' => "ALTER TABLE `{$tbl}` ADD COLUMN deskripsi TEXT NULL AFTER tanggal_kedaluwarsa"
        ];

        foreach ($kolomWajib as $namaKolom => $queryAlter) {
            $cekKolom = $conn->query("SHOW COLUMNS FROM `{$tbl}` LIKE '{$namaKolom}'");
            if ($cekKolom && $cekKolom->num_rows === 0) {
                $conn->query($queryAlter);
            }
        }

        // Sinkronisasi nama_barang & nama_menu, serta jumlah & stok jika ada yang kosong
        $conn->query("UPDATE `{$tbl}` SET nama_barang = nama_menu WHERE (nama_barang = '' OR nama_barang IS NULL) AND (nama_menu != '' AND nama_menu IS NOT NULL)");
        $conn->query("UPDATE `{$tbl}` SET nama_menu = nama_barang WHERE (nama_menu = '' OR nama_menu IS NULL) AND (nama_barang != '' AND nama_barang IS NOT NULL)");
        $conn->query("UPDATE `{$tbl}` SET jumlah = stok WHERE jumlah = 0 AND stok > 0");
        $conn->query("UPDATE `{$tbl}` SET stok = jumlah WHERE stok = 0 AND jumlah > 0");
    }
}

// 5. Masukkan data dummy farmasi jika tabel masih kosong
$cekData = $conn->query("SELECT COUNT(*) AS total FROM `{$dbTable}`");
$row = $cekData ? $cekData->fetch_assoc() : ['total' => 0];

if ((int)$row['total'] === 0) {
    $insertData = "INSERT INTO `{$dbTable}` 
        (nama_barang, nama_menu, kategori, merek, satuan, harga_beli, harga, jumlah, stok, stok_minimum, tanggal_kedaluwarsa, deskripsi) VALUES
        ('Paracetamol 500 mg', 'Paracetamol 500 mg', 'Obat Bebas', 'Kimia Farma', 'strip', 3500, 5000, 45, 45, 10, '2027-05-15', 'Analgesik dan antipiretik pereda demam dan nyeri ringan.'),
        ('Amoxicillin 500 mg', 'Amoxicillin 500 mg', 'Obat Keras', 'Sanbe', 'strip', 7000, 10000, 8, 8, 10, '2026-11-20', 'Antibiotik spektrum luas, wajib resep apoteker/dokter.'),
        ('Vitamin C 500 mg', 'Vitamin C 500 mg', 'Vitamin dan Suplemen', 'Enervon', 'botol', 28000, 35000, 24, 24, 8, '2027-08-10', 'Suplemen vitamin C daya tahan tubuh isi 30 tablet.'),
        ('Antasida DOEN', 'Antasida DOEN', 'Obat Bebas', 'Indofarma', 'strip', 4000, 6000, 4, 4, 10, '2026-12-01', 'Obat maag kunyah pereda asam lambung dan nyeri ulu hati.'),
        ('Betadine Antiseptik 30 ml', 'Betadine Antiseptik 30 ml', 'Antiseptik dan Disinfektan', 'Mundipharma', 'botol', 18000, 23000, 18, 18, 5, '2028-02-14', 'Povidone iodine 10% untuk pencegahan infeksi pada luka.'),
        ('Kasa Steril 16x16 cm', 'Kasa Steril 16x16 cm', 'Perawatan Luka', 'Onemed', 'box', 9000, 13000, 3, 3, 8, '2028-09-30', 'Kasa steril perban medis untuk penutup luka terbuka.'),
        ('Minyak Kayu Putih 60 ml', 'Minyak Kayu Putih 60 ml', 'Obat Tradisional', 'Cap Lang', 'botol', 16000, 21000, 0, 0, 5, '2027-10-05', 'Minyak oles pereda masuk angin, gatal serangga, dan perut kembung.')";
    
    $conn->query($insertData);

    // Salin ke tabel 'menu' jika ada dan berbeda
    if ($dbTable !== 'menu') {
        $conn->query("DELETE FROM `menu`");
        $conn->query("INSERT INTO `menu` SELECT * FROM `{$dbTable}`");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Database — SehatMart Inventaris</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; padding: 40px 15px; }
        .setup-card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 32px; }
        .badge-step { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #059669; color: #fff; font-weight: bold; font-size: 13px; margin-right: 8px; }
        .schema-table th { background: #f1f5f9; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="text-center mb-4">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 12px; background: #ecfdf5; color: #059669; font-size: 28px; margin-bottom: 12px;">
            <i class="bi bi-check2-circle"></i>
        </div>
        <h2 class="h4 fw-bold mb-1">Inisialisasi Database Berhasil</h2>
        <p class="text-muted small">Sistem Inventaris Apotek SehatMart siap digunakan untuk UTS Pemrograman Web</p>
    </div>

    <div class="list-group mb-4">
        <div class="list-group-item d-flex align-items-center">
            <span class="badge-step"><i class="bi bi-check"></i></span>
            <div class="flex-grow-1">
                <strong>Database:</strong> <code><?= htmlspecialchars($dbName); ?></code>
                <div class="small text-muted">Karakter set utf8mb4_unicode_ci</div>
            </div>
            <span class="badge bg-success">Tersedia</span>
        </div>
        <div class="list-group-item d-flex align-items-center">
            <span class="badge-step"><i class="bi bi-check"></i></span>
            <div class="flex-grow-1">
                <strong>Tabel Aktif:</strong> <code><?= htmlspecialchars($dbTable); ?></code> (dan alias <code>menu</code>)
                <div class="small text-muted">Mendukung nama_barang, jumlah, harga, kategori, kedaluwarsa, dll.</div>
            </div>
            <span class="badge bg-success">Siap</span>
        </div>
        <div class="list-group-item d-flex align-items-center">
            <span class="badge-step"><i class="bi bi-check"></i></span>
            <div class="flex-grow-1">
                <strong>Konfigurasi:</strong> Membaca parameter dari file <code>.env</code>
                <div class="small text-muted">Host: <?= htmlspecialchars($dbHost); ?>:<?= htmlspecialchars((string)$dbPort); ?> | User: <?= htmlspecialchars($dbUser); ?></div>
            </div>
            <span class="badge bg-success">Terkoneksi</span>
        </div>
    </div>

    <h5 class="h6 fw-bold mb-2">Spesifikasi Struktur Kolom Tabel:</h5>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered schema-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Field</th>
                    <th>Tipe Data</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1</td><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>Primary Key</td></tr>
                <tr><td>2</td><td><code>nama_barang</code> / <code>nama_menu</code></td><td>VARCHAR(100)</td><td>Nama produk / obat</td></tr>
                <tr><td>3</td><td><code>kategori</code></td><td>VARCHAR(100)</td><td>Kategori farmasi</td></tr>
                <tr><td>4</td><td><code>merek</code></td><td>VARCHAR(100) NULL</td><td>Pabrik / Principal</td></tr>
                <tr><td>5</td><td><code>satuan</code></td><td>VARCHAR(30)</td><td>Satuan unit (strip, botol, dll)</td></tr>
                <tr><td>6</td><td><code>harga_beli</code></td><td>INT</td><td>Harga pokok pembelian (HPP)</td></tr>
                <tr><td>7</td><td><code>harga</code></td><td>INT</td><td>Harga jual apotek</td></tr>
                <tr><td>8</td><td><code>jumlah</code> / <code>stok</code></td><td>INT</td><td>Jumlah fisik persediaan</td></tr>
                <tr><td>9</td><td><code>stok_minimum</code></td><td>INT</td><td>Ambang batas reorder point</td></tr>
                <tr><td>10</td><td><code>tanggal_kedaluwarsa</code></td><td>DATE NULL</td><td>Masa kedaluwarsa (Expired)</td></tr>
                <tr><td>11</td><td><code>deskripsi</code></td><td>TEXT NULL</td><td>Catatan penyimpanan & kegunaan</td></tr>
                <tr><td>12</td><td><code>created_at</code></td><td>TIMESTAMP</td><td>Waktu pendataan barang</td></tr>
            </tbody>
        </table>
    </div>

    <div class="text-center pt-2">
        <a href="index.php" class="btn btn-success px-4 py-2 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-1"></i> Buka Aplikasi Inventaris (index.php)
        </a>
    </div>
</div>
</body>
</html>
