<?php
require_once 'koneksi.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit();
}

$kategori_produk = [
    'Obat Bebas',
    'Obat Bebas Terbatas',
    'Obat Keras',
    'Vitamin dan Suplemen',
    'Alat Kesehatan',
    'Perawatan Luka',
    'Antiseptik dan Disinfektan',
    'Cairan Medis',
    'Obat Herbal Terstandar',
    'Obat Tradisional',
    'Narkotika',
    'Perawatan Pribadi'
];

$satuan_produk = [
    'pcs', 'tablet', 'kapsul', 'strip', 'blister', 'botol', 'tube',
    'box', 'pack', 'roll', 'sachet', 'vial', 'ampul', 'ml', 'liter'
];

$error = '';
$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$textLength = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

// Ambil data produk saat ini
$stmt = $conn->prepare('SELECT * FROM menu WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header('Location: index.php');
    exit();
}

$form = [
    'nama_menu' => $data['nama_menu'] ?? '',
    'kategori' => $data['kategori'] ?? '',
    'merek' => $data['merek'] ?? '',
    'satuan' => $data['satuan'] ?? 'pcs',
    'harga_beli' => (string) ($data['harga_beli'] ?? '0'),
    'harga' => (string) ($data['harga'] ?? '0'),
    'stok' => (string) ($data['stok'] ?? '0'),
    'stok_minimum' => (string) ($data['stok_minimum'] ?? '5'),
    'tanggal_kedaluwarsa' => $data['tanggal_kedaluwarsa'] ?? '',
    'nomor_batch' => $data['nomor_batch'] ?? '',
    'deskripsi' => $data['deskripsi'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $defaultValue) {
        $postedValue = $_POST[$field] ?? $defaultValue;
        $form[$field] = is_string($postedValue) ? trim($postedValue) : (string) $defaultValue;
    }

    $nama = $form['nama_menu'];
    $kategori = $form['kategori'];
    $merek = $form['merek'];
    $satuan = $form['satuan'];
    $hargaBeli = filter_var($form['harga_beli'], FILTER_VALIDATE_INT);
    $hargaJual = filter_var($form['harga'], FILTER_VALIDATE_INT);
    $stok = filter_var($form['stok'], FILTER_VALIDATE_INT);
    $stokMinimum = filter_var($form['stok_minimum'], FILTER_VALIDATE_INT);
    $tanggalInput = $form['tanggal_kedaluwarsa'];
    $nomorBatch = $form['nomor_batch'];
    $deskripsi = $form['deskripsi'];
    $tanggalDb = null;

    if ($nama === '' || $textLength($nama) > 100) {
        $error = 'Nama produk wajib diisi dan maksimal 100 karakter.';
    } elseif (!in_array($kategori, $kategori_produk, true)) {
        $error = 'Pilih kategori produk yang tersedia.';
    } elseif ($merek !== '' && $textLength($merek) > 100) {
        $error = 'Nama merek maksimal 100 karakter.';
    } elseif (!in_array($satuan, $satuan_produk, true)) {
        $error = 'Pilih satuan produk yang tersedia.';
    } elseif ($hargaBeli === false || $hargaJual === false || $stok === false || $stokMinimum === false) {
        $error = 'Harga dan stok harus berupa bilangan bulat yang valid.';
    } elseif ($hargaBeli < 0 || $hargaJual < 0 || $stok < 0 || $stokMinimum < 0) {
        $error = 'Harga dan jumlah stok tidak boleh bernilai negatif.';
    } elseif ($nomorBatch !== '' && $textLength($nomorBatch) > 100) {
        $error = 'Nomor batch maksimal 100 karakter.';
    } elseif ($textLength($deskripsi) > 2000) {
        $error = 'Deskripsi maksimal 2.000 karakter.';
    } else {
        if ($tanggalInput !== '') {
            $tanggal = DateTime::createFromFormat('!Y-m-d', $tanggalInput);
            $tanggalErrors = DateTime::getLastErrors();
            $tanggalTidakValid = !$tanggal
                || ($tanggalErrors !== false && ($tanggalErrors['warning_count'] > 0 || $tanggalErrors['error_count'] > 0))
                || ($tanggal && $tanggal->format('Y-m-d') !== $tanggalInput);

            if ($tanggalTidakValid) {
                $error = 'Tanggal kedaluwarsa tidak valid.';
            } else {
                $tanggalDb = $tanggalInput;
            }
        }
    }

    if ($error === '') {
        $merekDb = $merek !== '' ? $merek : null;
        $batchDb = $nomorBatch !== '' ? $nomorBatch : null;
        $deskripsiDb = $deskripsi !== '' ? $deskripsi : null;

        try {
            $stmt = $conn->prepare(
                'UPDATE menu SET nama_menu = ?, kategori = ?, merek = ?, satuan = ?, harga_beli = ?, harga = ?, stok = ?, stok_minimum = ?, tanggal_kedaluwarsa = ?, nomor_batch = ?, deskripsi = ? WHERE id = ?'
            );

            if (!$stmt) {
                throw new RuntimeException('Query update tidak dapat disiapkan.');
            }

            $stmt->bind_param(
                'ssssiiiisssi',
                $nama,
                $kategori,
                $merekDb,
                $satuan,
                $hargaBeli,
                $hargaJual,
                $stok,
                $stokMinimum,
                $tanggalDb,
                $batchDb,
                $deskripsiDb,
                $id
            );

            $stmt->execute();
            $stmt->close();

            header('Location: index.php?status=edit_success');
            exit();
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'Gagal memperbarui data inventaris. Pastikan kolom database lengkap dan data valid.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6faf7">
    <title>Edit Inventaris — SehatMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light pharmacy-nav">
    <div class="container">
        <a class="navbar-brand brand-mark" href="index.php">
            <span class="brand-icon"><i class="bi bi-box-seam"></i></span>
            <span>sehat<span class="brand-highlight">mart</span><small>INVENTARIS APOTEK</small></span>
        </a>
        <a href="index.php" class="btn btn-outline-green"><i class="bi bi-arrow-left me-1"></i> Kembali ke Inventaris</a>
    </div>
</nav>

<main class="container py-4 py-lg-5" style="max-width: 1000px;">
    <div class="mb-4">
        <span class="eyebrow">PENGELOLAAN STOK</span>
        <h1 class="h2 fw-bold mt-2 mb-2">Edit Data Produk Inventaris</h1>
        <p class="text-secondary mb-0">Perbarui spesifikasi item, lokasi batch, batas minimum stok, dan harga inventaris.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= $escape($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="edit.php?id=<?= $id; ?>" class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-capsule text-success me-2"></i>Informasi Produk</h2>
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="nama_menu" class="form-label">Nama Produk / Obat <span class="text-danger">*</span></label>
                    <input id="nama_menu" name="nama_menu" type="text" class="form-control" maxlength="100" value="<?= $escape($form['nama_menu']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="merek" class="form-label">Merek / Pabrik / Produsen</label>
                    <input id="merek" name="merek" type="text" class="form-control" maxlength="100" value="<?= $escape($form['merek']); ?>" placeholder="Contoh: Kimia Farma, Sanbe">
                </div>
                <div class="col-md-6">
                    <label for="kategori" class="form-label">Kategori Farmasi <span class="text-danger">*</span></label>
                    <select id="kategori" name="kategori" class="form-select" required>
                        <option value="">-- Pilih kategori --</option>
                        <?php foreach ($kategori_produk as $kategoriPilihan): ?>
                            <option value="<?= $escape($kategoriPilihan); ?>" <?= $form['kategori'] === $kategoriPilihan ? 'selected' : ''; ?>><?= $escape($kategoriPilihan); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="satuan" class="form-label">Satuan Stok Kemasan <span class="text-danger">*</span></label>
                    <select id="satuan" name="satuan" class="form-select" required>
                        <?php foreach ($satuan_produk as $satuanPilihan): ?>
                            <option value="<?= $escape($satuanPilihan); ?>" <?= $form['satuan'] === $satuanPilihan ? 'selected' : ''; ?>><?= $escape(ucfirst($satuanPilihan)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Bentuk fisik kemasan (misal: strip, botol, tube, box, dll).</div>
                </div>
                <div class="col-12">
                    <label for="deskripsi" class="form-label">Deskripsi / Catatan Penyimpanan</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3" maxlength="2000" placeholder="Catatan dosis, lokasi rak penyimpanan (misal: Rak A-2), atau indikasi medis."><?= $escape($form['deskripsi']); ?></textarea>
                    <div class="form-text">Maksimal 2.000 karakter.</div>
                </div>
            </div>

            <hr class="my-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-calculator text-success me-2"></i>Harga & Level Stok</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="harga_beli" class="form-label">Harga Beli / HPP (Rp) <span class="text-danger">*</span></label>
                    <input id="harga_beli" name="harga_beli" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['harga_beli']); ?>" required>
                    <div class="form-text">Harga perolehan barang dari distributor.</div>
                </div>
                <div class="col-md-6">
                    <label for="harga" class="form-label">Harga Jual Satuan (Rp) <span class="text-danger">*</span></label>
                    <input id="harga" name="harga" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['harga']); ?>" required>
                    <div class="form-text">Nilai acuan penjualan / tarif per unit.</div>
                </div>
                <div class="col-md-6">
                    <label for="stok" class="form-label">Jumlah Stok Fisik <span class="text-danger">*</span></label>
                    <input id="stok" name="stok" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok']); ?>" required>
                    <div class="form-text">Jumlah unit riil yang ada di gudang / etalase apotek.</div>
                </div>
                <div class="col-md-6">
                    <label for="stok_minimum" class="form-label">Batas Minimum Stok (Reorder Point) <span class="text-danger">*</span></label>
                    <input id="stok_minimum" name="stok_minimum" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok_minimum']); ?>" required>
                    <div class="form-text">Peringatan otomatis muncul bila stok &le; angka ini.</div>
                </div>
            </div>

            <hr class="my-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-upc-scan text-success me-2"></i>Nomor Batch & Masa Kedaluwarsa</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nomor_batch" class="form-label">Nomor Batch / Lot</label>
                    <input id="nomor_batch" name="nomor_batch" type="text" class="form-control" maxlength="100" value="<?= $escape($form['nomor_batch']); ?>" placeholder="Contoh: BTC-PCM-2024A">
                </div>
                <div class="col-md-6">
                    <label for="tanggal_kedaluwarsa" class="form-label">Tanggal Kedaluwarsa (Expired Date)</label>
                    <input id="tanggal_kedaluwarsa" name="tanggal_kedaluwarsa" type="date" class="form-control" value="<?= $escape($form['tanggal_kedaluwarsa']); ?>">
                    <div class="form-text">Boleh dikosongkan jika alkes non-kedaluwarsa.</div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mt-4 pt-2">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Batal</a>
                <button type="submit" class="btn btn-primary-green"><i class="bi bi-check2-circle me-1"></i> Simpan Perubahan</button>
            </div>
        </div>
    </form>
    <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Field bertanda * wajib diisi. Perubahan langsung terupdate di rekap inventaris.</p>
</main>
</body>
</html>
