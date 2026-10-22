<?php
require_once 'koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(100) NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    merek VARCHAR(100) NULL,
    satuan VARCHAR(30) NOT NULL DEFAULT 'pcs',
    harga_beli INT NOT NULL DEFAULT 0,
    harga INT NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    stok_minimum INT NOT NULL DEFAULT 5,
    tanggal_kedaluwarsa DATE NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "<h3 style='color: green;'>1. Tabel menu inventaris berhasil dibuat / sudah tersedia.</h3>";
} else {
    echo "<h3 style='color: red;'>Gagal membuat tabel: " . $conn->error . "</h3>";
}

// Pastikan semua kolom inventaris ada pada database yang sudah ada sebelumnya
$kolomWajib = [
    'merek' => "ALTER TABLE menu ADD COLUMN merek VARCHAR(100) NULL AFTER kategori",
    'satuan' => "ALTER TABLE menu ADD COLUMN satuan VARCHAR(30) NOT NULL DEFAULT 'pcs' AFTER merek",
    'harga_beli' => "ALTER TABLE menu ADD COLUMN harga_beli INT NOT NULL DEFAULT 0 AFTER satuan",
    'stok_minimum' => "ALTER TABLE menu ADD COLUMN stok_minimum INT NOT NULL DEFAULT 5 AFTER stok",
    'tanggal_kedaluwarsa' => "ALTER TABLE menu ADD COLUMN tanggal_kedaluwarsa DATE NULL AFTER stok_minimum",
    'deskripsi' => "ALTER TABLE menu ADD COLUMN deskripsi TEXT NULL AFTER tanggal_kedaluwarsa"
];

foreach ($kolomWajib as $namaKolom => $queryAlter) {
    $cekKolom = $conn->query("SHOW COLUMNS FROM menu LIKE '{$namaKolom}'");
    if ($cekKolom && $cekKolom->num_rows === 0) {
        $conn->query($queryAlter);
    }
}

$cek = $conn->query("SELECT * FROM menu");

if ($cek && $cek->num_rows == 0) {
    $data = "INSERT INTO menu (nama_menu, kategori, merek, satuan, harga_beli, harga, stok, stok_minimum, tanggal_kedaluwarsa, deskripsi) VALUES
        ('Paracetamol 500 mg', 'Obat Bebas', 'Kimia Farma', 'strip', 3500, 5000, 45, 10, '2027-05-15', 'Analgesik dan antipiretik pereda demam dan nyeri ringan.'),
        ('Amoxicillin 500 mg', 'Obat Keras', 'Sanbe', 'strip', 7000, 10000, 8, 10, '2026-11-20', 'Antibiotik spektrum luas, wajib resep apoteker/dokter.'),
        ('Vitamin C 500 mg', 'Vitamin dan Suplemen', 'Enervon', 'botol', 28000, 35000, 24, 8, '2027-08-10', 'Suplemen vitamin C daya tahan tubuh isi 30 tablet.'),
        ('Antasida DOEN', 'Obat Bebas', 'Indofarma', 'strip', 4000, 6000, 4, 10, '2026-12-01', 'Obat maag kunyah pereda asam lambung dan nyeri ulu hati.'),
        ('Betadine Antiseptik 30 ml', 'Antiseptik dan Disinfektan', 'Mundipharma', 'botol', 18000, 23000, 18, 5, '2028-02-14', 'Povidone iodine 10% untuk pencegahan infeksi pada luka.'),
        ('Kasa Steril 16x16 cm', 'Perawatan Luka', 'Onemed', 'box', 9000, 13000, 3, 8, '2028-09-30', 'Kasa steril perban medis untuk penutup luka terbuka.'),
        ('Minyak Kayu Putih 60 ml', 'Obat Tradisional', 'Cap Lang', 'botol', 16000, 21000, 0, 5, '2027-10-05', 'Minyak oles pereda masuk angin, gatal serangga, dan perut kembung.')";

    if ($conn->query($data)) {
        echo "<h3 style='color: green;'>2. Data contoh inventaris apotek berhasil ditambahkan.</h3>";
    } else {
        echo "<h3 style='color: red;'>Gagal menambahkan data contoh: " . $conn->error . "</h3>";
    }
} else {
    $jumlah = $cek ? $cek->num_rows : 0;
    echo "<h3 style='color: blue;'>2. Data produk inventaris sudah ada ($jumlah item).</h3>";
}

echo "
<br>
<a href='index.php'
style='
    padding: 10px 18px;
    background: #267b58;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-family: sans-serif;
    font-weight: bold;
    display: inline-block;
'>
    👉 Buka Sistem Inventaris Apotek
</a>
";
?>
