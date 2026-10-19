<?php
require_once 'koneksi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $nama = trim($_POST['nama_menu']);
    $kategori = trim($_POST['kategori']);
    $harga = filter_var($_POST['harga'], FILTER_VALIDATE_INT);
    $stok = filter_var($_POST['stok'], FILTER_VALIDATE_INT);

    if ($nama == '' || $kategori == '' || $harga === false || $stok === false) {
        $error = "Semua field wajib diisi dengan benar!";
    } elseif ($harga < 0 || $stok < 0) {
        $error = "Harga dan stok tidak boleh bernilai negatif!";
    } else {

        $stmt = $conn->prepare(
            "INSERT INTO menu (nama_menu, kategori, harga, stok) VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param("ssii", $nama, $kategori, $harga, $stok);

        if ($stmt->execute()) {
            header("Location: index.php?status=add_success");
            exit();
        } else {
            $error = "Gagal menyimpan data ke database.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Menu - Kedai Kopi</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<div class="container mt-5" style="max-width: 600px;">

    <div class="card shadow-sm border-0">

        <div class="card-header bg-primary text-white fw-bold">
            Tambah Menu Baru
        </div>

        <div class="card-body">

            <?php if ($error != ''): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>


            <form action="" method="POST">

                <div class="mb-3">
                    <label class="form-label">Nama Menu</label>

                    <input
                        type="text"
                        name="nama_menu"
                        class="form-control"
                        placeholder="Contoh: Caramel Macchiato"
                        required
                    >
                </div>


                <div class="mb-3">
                    <label class="form-label">Kategori</label>

                    <select name="kategori" class="form-select" required>

                        <option value="">-- Pilih Kategori --</option>
                        <option value="Obat Keras">Obat Keras</option>
                        <option value="Obat Bebas">Obat Bebas</option>
                        <option value="Obat Bebas Terbatas">Obat Bebas Terbatas</option>
                        <option value="Narkotika">Narkotika</option>
                        <option value="Obat Herbal Terstandar">Obat Herbal Terstandar</option>
                        <option value="Obat Tradisional">Obat Tradisional</option>

                    </select>
                </div>


                <div class="mb-3">
                    <label class="form-label">Harga (Rp)</label>

                    <input
                        type="number"
                        name="harga"
                        class="form-control"
                        min="0"
                        placeholder="15000"
                        required
                    >
                </div>


                <div class="mb-3">
                    <label class="form-label">Stok Awal</label>

                    <input
                        type="number"
                        name="stok"
                        class="form-control"
                        min="0"
                        placeholder="20"
                        required
                    >
                </div>


                <div class="d-flex justify-content-between">

                    <a href="index.php" class="btn btn-secondary">
                        Batal
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Simpan Menu
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>

</body>
</html>

