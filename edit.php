<?php
require_once 'koneksi.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: index.php");
    exit();
}

$error = "";
$kategori_menu = [
    'Obat Bebas',
    'Obat Bebas Terbatas',
    'Obat Keras',
    'Obat Herbal Terstandar',
    'Obat Tradisional',
    'Narkotika'
];

$stmt = $conn->prepare("SELECT * FROM menu WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama_menu']);
    $kategori = trim($_POST['kategori']);
    $harga = filter_var($_POST['harga'], FILTER_VALIDATE_INT);
    $stok = filter_var($_POST['stok'], FILTER_VALIDATE_INT);

    if ($nama == "" || !in_array($kategori, $kategori_menu, true) || $harga === false || $stok === false) {
        $error = "Semua field wajib diisi dengan benar!";
    } elseif ($harga < 0 || $stok < 0) {
        $error = "Harga dan stok tidak boleh negatif!";
    } else {
        $stmt = $conn->prepare(
            "UPDATE menu SET nama_menu = ?, kategori = ?, harga = ?, stok = ? WHERE id = ?"
        );

        $stmt->bind_param("ssiii", $nama, $kategori, $harga, $stok, $id);

        if ($stmt->execute()) {
            header("Location: index.php?status=edit_success");
            exit();
        } else {
            $error = "Gagal memperbarui data.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Menu - Apotik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5" style="max-width: 600px;">
    <div class="card shadow-sm border-0">

        <div class="card-header bg-warning text-dark fw-bold">
            Edit Menu Apotik
        </div>

        <div class="card-body">

            <?php if ($error != ""): ?>
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
                        value="<?= htmlspecialchars($data['nama_menu']); ?>"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-select" required>
                        <?php foreach ($kategori_menu as $kategori): ?>
                            <option value="<?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?>" <?= $data['kategori'] === $kategori ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Harga (Rp)</label>
                    <input
                        type="number"
                        name="harga"
                        class="form-control"
                        value="<?= htmlspecialchars($data['harga']); ?>"
                        min="0"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Stok</label>
                    <input
                        type="number"
                        name="stok"
                        class="form-control"
                        value="<?= htmlspecialchars($data['stok']); ?>"
                        min="0"
                        required
                    >
                </div>

                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-warning">Update Menu</button>
                </div>

            </form>

        </div>
    </div>
</div>

</body>
</html>
