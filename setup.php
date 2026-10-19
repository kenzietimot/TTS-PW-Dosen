<?php
require_once 'koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(100) NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    harga INT NOT NULL,
    stok INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "<h3 style='color: green;'>1. Tabel menu berhasil dibuat / sudah tersedia.</h3>";
} else {
    echo "<h3 style='color: red;'>Gagal membuat tabel: " . $conn->error . "</h3>";
}

$migrasi = "ALTER TABLE menu MODIFY kategori VARCHAR(100) NOT NULL";
if ($conn->query($migrasi)) {
    echo "<h3 style='color: green;'>2. Kolom kategori siap untuk kategori apotek.</h3>";
} else {
    echo "<h3 style='color: red;'>Gagal memperbarui kolom kategori: " . $conn->error . "</h3>";
}

$cek = $conn->query("SELECT * FROM menu");

if ($cek && $cek->num_rows == 0) {

    $data = "INSERT INTO menu (nama_menu, kategori, harga, stok) VALUES
        ('Paracetamol 500 mg', 'Obat Bebas', 5000, 40),
        ('Vitamin C 500 mg', 'Obat Bebas', 12000, 35),
        ('Antasida DOEN', 'Obat Bebas', 7000, 25),
        ('Minyak Kayu Putih 60 ml', 'Obat Tradisional', 18000, 20)";

    if ($conn->query($data)) {
        echo "<h3 style='color: green;'>3. Data contoh apotek berhasil ditambahkan.</h3>";
    } else {
        echo "<h3 style='color: red;'>Gagal menambahkan data contoh.</h3>";
    }

} else {

    $jumlah = $cek ? $cek->num_rows : 0;

    echo "<h3 style='color: blue;'>3. Data produk sudah ada ($jumlah item).</h3>";
}

echo "
<br>
<a href='index.php'
style='
    padding: 10px 15px;
    background: #267b58;
    color: white;
    text-decoration: none;
    border-radius: 5px;
'>
    👉 Buka Halaman Utama
</a>
";
?>
