<?php
require_once 'koneksi.php';

// Kategori dan satuan dibuat tetap agar input konsisten.
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
    'Perawatan Pribadi'
];

$satuan_produk = [
    'pcs', 'tablet', 'kapsul', 'strip', 'blister', 'botol', 'tube',
    'box', 'pack', 'roll', 'sachet', 'vial', 'ampul', 'ml', 'liter'
];

$error = '';
$schemaError = '';
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$textLength = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

$form = [
    'nama_menu' => '',
    'kategori' => '',
    'merek' => '',
    'satuan' => 'pcs',
    'harga_beli' => '0',
    'harga' => '0',
    'stok' => '0',
    'stok_minimum' => '5',
    'tanggal_kedaluwarsa' => '',
    'nomor_batch' => '',
    'deskripsi' => ''
];

/*
 * Karena permintaan perubahan hanya pada tambah.php, kolom tambahan dibuat
 * otomatis jika belum tersedia. Struktur dasar tabel menu tetap dipakai.
 * File lain tidak perlu diganti untuk menyimpan data dari form ini.
 */
$kolomTambahan = [
    'merek' => "ALTER TABLE menu ADD COLUMN merek VARCHAR(100) NULL AFTER kategori",
    'satuan' => "ALTER TABLE menu ADD COLUMN satuan VARCHAR(30) NOT NULL DEFAULT 'pcs' AFTER merek",
    'harga_beli' => "ALTER TABLE menu ADD COLUMN harga_beli INT NOT NULL DEFAULT 0 AFTER satuan",
    'stok_minimum' => "ALTER TABLE menu ADD COLUMN stok_minimum INT NOT NULL DEFAULT 5 AFTER stok",
    'tanggal_kedaluwarsa' => "ALTER TABLE menu ADD COLUMN tanggal_kedaluwarsa DATE NULL AFTER stok_minimum",
    'nomor_batch' => "ALTER TABLE menu ADD COLUMN nomor_batch VARCHAR(100) NULL AFTER tanggal_kedaluwarsa",
    'deskripsi' => "ALTER TABLE menu ADD COLUMN deskripsi TEXT NULL AFTER nomor_batch"
];

foreach ($kolomTambahan as $namaKolom => $sqlTambahKolom) {
    $kolomAman = $conn->real_escape_string($namaKolom);
    $hasilCek = $conn->query("SHOW COLUMNS FROM menu LIKE '{$kolomAman}'");

    if (!$hasilCek) {
        $schemaError = 'Tidak dapat memeriksa struktur tabel menu. Pastikan database db_apotik dan tabel menu sudah dibuat melalui setup.php.';
        break;
    }

    $sudahAda = $hasilCek->num_rows > 0;
    $hasilCek->free();

    if (!$sudahAda && !$conn->query($sqlTambahKolom)) {
        $schemaError = 'Kolom tambahan belum dapat dibuat. Pastikan akun database memiliki izin ALTER TABLE. Detail: ' . $conn->error;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaError === '') {
    foreach ($form as $field => $defaultValue) {
        $postedValue = $_POST[$field] ?? $defaultValue;
        $form[$field] = is_string($postedValue) ? trim($postedValue) : $defaultValue;
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
                'INSERT INTO menu (nama_menu, kategori, merek, satuan, harga_beli, harga, stok, stok_minimum, tanggal_kedaluwarsa, nomor_batch, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$stmt) {
                throw new RuntimeException('Query tidak dapat disiapkan.');
            }

            $stmt->bind_param(
                'ssssiiiisss',
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
                $deskripsiDb
            );
            $stmt->execute();
            $stmt->close();

            header('Location: index.php?status=add_success');
            exit();
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'Produk gagal disimpan. Pastikan struktur tabel menu sudah siap dan data yang dimasukkan valid.';
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
    <title>Tambah Produk - SehatMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light pharmacy-nav">
    <div class="container">
        <a class="navbar-brand brand-mark" href="index.php">
            <span class="brand-icon"><i class="bi bi-plus-lg"></i></span>
            <span>sehat<span class="brand-highlight">mart</span><small>INVENTARIS APOTEK</small></span>
        </a>
        <a href="index.php" class="btn btn-outline-green"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>
</nav>

<main class="container py-4 py-lg-5" style="max-width: 1000px;">
    <div class="mb-4">
        <span class="eyebrow">PENGELOLAAN INVENTARIS</span>
        <h1 class="h2 fw-bold mt-2 mb-2">Tambah produk apotek</h1>
        <p class="text-secondary mb-0">Catat obat, vitamin, suplemen, alat kesehatan, perban, antiseptik, dan produk apotek lainnya.</p>
    </div>

    <?php if ($schemaError !== ''): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= $escape($schemaError); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= $escape($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="tambah.php" class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-box-seam text-success me-2"></i>Informasi produk</h2>
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="nama_menu" class="form-label">Nama produk <span class="text-danger">*</span></label>
                    <input id="nama_menu" name="nama_menu" type="text" class="form-control" maxlength="100" value="<?= $escape($form['nama_menu']); ?>" placeholder="Contoh: Perban gulung 5 cm" required>
                </div>
                <div class="col-md-4">
                    <label for="merek" class="form-label">Merek</label>
                    <input id="merek" name="merek" type="text" class="form-control" maxlength="100" value="<?= $escape($form['merek']); ?>" placeholder="Contoh: Betadine">
                </div>
                <div class="col-md-6">
                    <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                    <select id="kategori" name="kategori" class="form-select" required>
                        <option value="">-- Pilih kategori --</option>
                        <?php foreach ($kategori_produk as $kategoriPilihan): ?>
                            <option value="<?= $escape($kategoriPilihan); ?>" <?= $form['kategori'] === $kategoriPilihan ? 'selected' : ''; ?>><?= $escape($kategoriPilihan); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="satuan" class="form-label">Satuan stok <span class="text-danger">*</span></label>
                    <select id="satuan" name="satuan" class="form-select" required>
                        <?php foreach ($satuan_produk as $satuanPilihan): ?>
                            <option value="<?= $escape($satuanPilihan); ?>" <?= $form['satuan'] === $satuanPilihan ? 'selected' : ''; ?>><?= $escape(ucfirst($satuanPilihan)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Contoh: strip, botol, tube, roll, atau pcs.</div>
                </div>
                <div class="col-12">
                    <label for="deskripsi" class="form-label">Deskripsi / catatan produk</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3" maxlength="2000" placeholder="Contoh: ukuran kemasan, isi produk, atau catatan inventaris."><?= $escape($form['deskripsi']); ?></textarea>
                    <div class="form-text">Maksimal 2.000 karakter.</div>
                </div>
            </div>

            <hr class="my-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-cash-coin text-success me-2"></i>Harga dan stok</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="harga_beli" class="form-label">Harga beli per satuan (Rp) <span class="text-danger">*</span></label>
                    <input id="harga_beli" name="harga_beli" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['harga_beli']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="harga" class="form-label">Harga jual per satuan (Rp) <span class="text-danger">*</span></label>
                    <input id="harga" name="harga" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['harga']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="stok" class="form-label">Stok awal <span class="text-danger">*</span></label>
                    <input id="stok" name="stok" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="stok_minimum" class="form-label">Batas stok minimum <span class="text-danger">*</span></label>
                    <input id="stok_minimum" name="stok_minimum" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok_minimum']); ?>" required>
                    <div class="form-text">Sebagai acuan saat stok mulai menipis.</div>
                </div>
            </div>

            <hr class="my-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-upc-scan text-success me-2"></i>Batch dan kedaluwarsa</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nomor_batch" class="form-label">Nomor batch</label>
                    <input id="nomor_batch" name="nomor_batch" type="text" class="form-control" maxlength="100" value="<?= $escape($form['nomor_batch']); ?>" placeholder="Contoh: BATCH-001">
                </div>
                <div class="col-md-6">
                    <label for="tanggal_kedaluwarsa" class="form-label">Tanggal kedaluwarsa</label>
                    <input id="tanggal_kedaluwarsa" name="tanggal_kedaluwarsa" type="date" class="form-control" value="<?= $escape($form['tanggal_kedaluwarsa']); ?>">
                    <div class="form-text">Boleh dikosongkan jika produk tidak memiliki tanggal kedaluwarsa.</div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mt-4">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Batal</a>
                <button type="submit" class="btn btn-primary-green" <?= $schemaError !== '' ? 'disabled' : ''; ?>><i class="bi bi-check2-circle me-1"></i> Simpan produk</button>
            </div>
        </div>
    </form>
    <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Field bertanda * wajib diisi. Informasi batch dan kedaluwarsa bersifat opsional.</p>
</main>
</body>
</html>
