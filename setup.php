<?php
require_once 'koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(100) NOT NULL,
    kategori ENUM('Kopi', 'Non-Kopi', 'Makanan', 'Cemilan') NOT NULL,
    harga INT NOT NULL,
    stok INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "<h3 style='color: green;'>1. Tabel menu berhasil dibuat / sudah tersedia.</h3>";
} else {
    echo "<h3 style='color: red;'>Gagal membuat tabel: " . $conn->error . "</h3>";
}

$cek = $conn->query("SELECT * FROM menu");

if ($cek && $cek->num_rows == 0) {

    $data = "INSERT INTO menu (nama_menu, kategori, harga, stok) VALUES
        ('Espresso Single', 'Kopi', 12000, 50),
        ('Kopi Susu Gula Aren', 'Kopi', 18000, 40),
        ('Matcha Latte', 'Non-Kopi', 20000, 30),
        ('Roti Bakar Cokelat', 'Makanan', 15000, 25)";

    if ($conn->query($data)) {
        echo "<h3 style='color: green;'>2. Data contoh berhasil ditambahkan.</h3>";
    } else {
        echo "<h3 style='color: red;'>Gagal menambahkan data contoh.</h3>";
    }

} else {

    $jumlah = $cek ? $cek->num_rows : 0;

    echo "<h3 style='color: blue;'>2. Data menu sudah ada ($jumlah item).</h3>";
}

echo "
<br>
<a href='index.php'
style='
    padding: 10px 15px;
    background: brown;
    color: white;
    text-decoration: none;
    border-radius: 5px;
'>
    👉 Buka Halaman Utama
</a>
";
?>

