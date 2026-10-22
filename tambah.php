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
    'Narkotika',
    'Perawatan Pribadi'
];

$satuan_produk = [
    'pcs', 'tablet', 'kapsul', 'strip', 'blister', 'botol', 'tube',
    'box', 'pack', 'roll', 'sachet', 'vial', 'ampul', 'ml', 'liter'
];

$error = '';
$schemaError = '';
$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$textLength = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

$form = [
    'nama_menu' => '',
    'kategori' => '',
    'merek' => '',
    'satuan' => 'pcs',
    'harga_beli' => '0',
    'harga' => '0',
    'stok' => '0',
    'tanggal_kedaluwarsa' => '',
    'deskripsi' => '',
    'opsi_stok_habis' => 'restok'
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
    'tanggal_kedaluwarsa' => "ALTER TABLE menu ADD COLUMN tanggal_kedaluwarsa DATE NULL AFTER stok",
    'deskripsi' => "ALTER TABLE menu ADD COLUMN deskripsi TEXT NULL AFTER tanggal_kedaluwarsa"
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
    $tanggalInput = $form['tanggal_kedaluwarsa'];
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
    } elseif ($hargaBeli === false || $hargaJual === false || $stok === false) {
        $error = 'Harga dan stok harus berupa bilangan bulat yang valid.';
    } elseif ($hargaBeli < 0 || $hargaJual < 0 || $stok < 0) {
        $error = 'Harga dan jumlah stok tidak boleh bernilai negatif.';
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
        $deskripsiDb = $deskripsi !== '' ? $deskripsi : null;

        try {
            $stmt = $conn->prepare(
                'INSERT INTO menu (nama_menu, kategori, merek, satuan, harga_beli, harga, stok, tanggal_kedaluwarsa, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$stmt) {
                throw new RuntimeException('Query tidak dapat disiapkan.');
            }

            $stmt->bind_param(
                'ssssiiiss',
                $nama,
                $kategori,
                $merekDb,
                $satuan,
                $hargaBeli,
                $hargaJual,
                $stok,
                $tanggalDb,
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

// Ambil data produk yang stoknya habis (0 unit) untuk opsi cepat Restok / Hapus
$produkStokHabis = [];
$resHabis = $conn->query("SELECT id, nama_menu, kategori, harga, satuan FROM menu WHERE stok <= 0 ORDER BY id DESC LIMIT 5");
if ($resHabis) {
    $produkStokHabis = $resHabis->fetch_all(MYSQLI_ASSOC);
    $resHabis->free();
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
<nav class="navbar navbar-expand-lg navbar-light sticky-top pharmacy-nav">
    <div class="container">
        <a class="navbar-brand brand-mark" href="index.php">
            <span class="brand-icon"><i class="bi bi-box-seam"></i></span>
            <span>sehat<span class="brand-highlight">mart</span><small>INVENTARIS APOTEK</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav mx-auto nav-links">
                <li class="nav-item"><a class="nav-link" href="index.php#ringkasan"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#tabel-inventaris"><i class="bi bi-table me-1"></i> Data Stok</a></li>
                <li class="nav-item"><a class="nav-link active" href="tambah.php" aria-current="page"><i class="bi bi-plus-lg me-1"></i> Tambah Produk</a></li>
            </ul>
            <div class="nav-actions">
                <a href="index.php" class="btn btn-outline-green">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Inventaris
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5">
    <div class="form-page-wrapper">
        <div class="page-header-card">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <div>
                    <span class="header-eyebrow"><i class="bi bi-box-seam-fill"></i> Modul Inventaris</span>
                    <h1 class="h3 fw-bold mt-2 mb-1">Tambah Produk Apotek</h1>
                    <p class="text-secondary small mb-0">Catat obat, vitamin, suplemen, alat kesehatan, dan perlengkapan medis baru ke dalam katalog.</p>
                </div>
                <div class="d-none d-sm-block">
                    <span class="badge bg-white text-success border border-success-subtle p-2 px-3 rounded-pill fw-semibold">
                        <i class="bi bi-shield-check me-1"></i> Sistem Terproteksi
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($produkStokHabis)): ?>
            <!-- Panel Produk Stok Habis Terdeteksi (Opsi Restok atau Hapus) -->
            <div class="out-of-stock-panel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h3><i class="bi bi-exclamation-triangle-fill text-warning"></i> Perhatian: Terdapat <?= count($produkStokHabis); ?> Produk Stok Habis di Apotek</h3>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Perlu Tindakan</span>
                </div>
                <p class="mb-3">Jika produk yang ingin Anda input sudah ada di daftar obat habis ini, Anda dapat langsung memilih untuk me-<strong>Restok Kembali</strong> atau me-<strong>Hapus</strong> produk:</p>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($produkStokHabis as $itemHabis): ?>
                        <div class="out-of-stock-item">
                            <div>
                                <strong><?= $escape($itemHabis['nama_menu']); ?></strong>
                                <span class="badge bg-light text-secondary border ms-1"><?= $escape($itemHabis['kategori']); ?></span>
                                <span class="badge bg-danger-subtle text-danger ms-1">Stok: 0 <?= $escape($itemHabis['satuan'] ?? 'pcs'); ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="edit.php?id=<?= (int)$itemHabis['id']; ?>" class="btn btn-sm btn-outline-success" title="Restok produk ini">
                                    <i class="bi bi-arrow-repeat me-1"></i> Restok Kembali
                                </a>
                                <a href="hapus.php?id=<?= (int)$itemHabis['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus produk <?= $escape(addslashes($itemHabis['nama_menu'])); ?> secara permanen?');" title="Hapus produk">
                                    <i class="bi bi-trash3 me-1"></i> Hapus
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($schemaError !== ''): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    <div><?= $escape($schemaError); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= $escape($error); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="tambah.php" class="main-form-card">
            <!-- BAGIAN 1: INFORMASI DASAR PRODUK -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon-badge">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <h2 class="section-title">Informasi Dasar Produk</h2>
                        <p class="section-desc">Nama barang, merek distributor, kategori farmasi, dan satuan kemasan.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nama_menu" class="form-label">
                            <span>Nama Produk <span class="required-star">*</span></span>
                        </label>
                        <input id="nama_menu" name="nama_menu" type="text" class="form-control" maxlength="100" value="<?= $escape($form['nama_menu']); ?>" placeholder="Contoh: Paracetamol 500 mg, Betadine 30 ml" required>
                        <div class="form-text">Maksimal 100 karakter.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="merek" class="form-label">
                            <span>Merek / Produsen</span>
                            <span class="badge bg-light text-muted fw-normal border">Opsional</span>
                        </label>
                        <input id="merek" name="merek" type="text" class="form-control" maxlength="100" value="<?= $escape($form['merek']); ?>" placeholder="Contoh: Kimia Farma, Kalbe">
                    </div>

                    <div class="col-md-6">
                        <label for="kategori" class="form-label">
                            <span>Kategori Produk <span class="required-star">*</span></span>
                        </label>
                        <select id="kategori" name="kategori" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_produk as $kategoriPilihan): ?>
                                <option value="<?= $escape($kategoriPilihan); ?>" <?= $form['kategori'] === $kategoriPilihan ? 'selected' : ''; ?>>
                                    <?= $escape($kategoriPilihan); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="satuan" class="form-label">
                            <span>Satuan Stok <span class="required-star">*</span></span>
                        </label>
                        <select id="satuan" name="satuan" class="form-select" required>
                            <?php foreach ($satuan_produk as $satuanPilihan): ?>
                                <option value="<?= $escape($satuanPilihan); ?>" <?= $form['satuan'] === $satuanPilihan ? 'selected' : ''; ?>>
                                    <?= $escape(ucfirst($satuanPilihan)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Satuan dasar inventaris (misal: strip, botol, tablet, pcs).</div>
                    </div>

                    <div class="col-12">
                        <label for="deskripsi" class="form-label">
                            <span>Deskripsi / Catatan Tambahan</span>
                            <span class="badge bg-light text-muted fw-normal border">Opsional</span>
                        </label>
                        <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3" maxlength="2000" placeholder="Contoh: Indikasi umum, aturan pakai singkat, atau petunjuk penyimpanan khusus..."><?= $escape($form['deskripsi']); ?></textarea>
                        <div class="form-text">Maksimal 2.000 karakter.</div>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 2: HARGA DAN MANAJEMEN STOK -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon-badge">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <h2 class="section-title">Harga dan Manajemen Stok</h2>
                        <p class="section-desc">Atur biaya pengadaan (beli), harga jual apotek, serta kuantitas stok awal produk.</p>
                    </div>
                </div>

                <div class="row g-3 align-items-stretch">
                    <div class="col-md-6">
                        <label for="harga_beli" class="form-label">
                            <span>Harga Beli per Satuan <span class="required-star">*</span></span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input id="harga_beli" name="harga_beli" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['harga_beli']); ?>" required>
                        </div>
                        <div class="form-text">Harga pokok pembelian dari distributor/PBF.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="harga" class="form-label">
                            <span>Harga Jual per Satuan <span class="required-star">*</span></span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input id="harga" name="harga" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['harga']); ?>" required>
                        </div>
                        <div id="profitPreview" class="profit-badge neutral">
                            <i class="bi bi-calculator"></i>
                            <span>Margin keuntungan akan dihitung otomatis</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="stok" class="form-label">
                            <span>Stok Awal <span class="required-star">*</span></span>
                        </label>
                        <input id="stok" name="stok" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok']); ?>" required>
                        <div class="form-text">Jumlah kuantitas fisik produk yang tersedia saat ini.</div>

                        <!-- Peringatan Dinamis jika Stok = 0 -->
                        <div id="stokZeroAlert" class="stock-empty-alert" style="display: none;">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                <div>
                                    <strong class="d-block text-danger">Stok diisi 0 (Stok Habis)</strong>
                                    <span class="small text-muted">Produk berstatus habis. Pilih kebijakan tindakan:</span>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2 mt-sm-0">
                                <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('opsiRestok').checked = true;">
                                    <i class="bi bi-arrow-repeat me-1"></i> Siap Restok
                                </button>
                                <a href="index.php" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash3 me-1"></i> Batal / Hapus
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="expiry-info-card">
                            <i class="bi bi-box-seam text-success"></i>
                            <div>
                                <strong>Pengelolaan Stok Otomatis</strong>
                                <p>Kuantitas stok produk akan langsung tercatat di inventaris dan diperbarui saat terjadi transaksi.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Jika Stok Habis -->
                    <div class="col-12 mt-3">
                        <label class="form-label mb-1">
                            <span>Pilihan Tindakan Jika Stok Habis (0 Unit)</span>
                            <span class="badge bg-light text-muted fw-normal border">Kebijakan Inventaris</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check stock-action-card p-3 border rounded-3 bg-light-subtle h-100">
                                    <input class="form-check-input ms-0 me-2" type="radio" name="opsi_stok_habis" id="opsiRestok" value="restok" <?= ($form['opsi_stok_habis'] ?? 'restok') === 'restok' ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="opsiRestok">
                                        <strong class="d-block text-success mb-1"><i class="bi bi-arrow-repeat me-1"></i> Izinkan Restok Kembali</strong>
                                        <span class="small text-muted">Produk tetap tersimpan di katalog saat stok 0, siap untuk di-restok ulang sewaktu-waktu.</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check stock-action-card p-3 border rounded-3 bg-light-subtle h-100">
                                    <input class="form-check-input ms-0 me-2" type="radio" name="opsi_stok_habis" id="opsiHapus" value="hapus" <?= ($form['opsi_stok_habis'] ?? '') === 'hapus' ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="opsiHapus">
                                        <strong class="d-block text-danger mb-1"><i class="bi bi-trash3 me-1"></i> Siapkan Opsi Hapus</strong>
                                        <span class="small text-muted">Tandai produk untuk langsung dihapus dari katalog jika stok sudah habis atau tidak dijual lagi.</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 3: INFORMASI KEDALUWARSA -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon-badge">
                        <i class="bi bi-calendar2-check"></i>
                    </div>
                    <div>
                        <h2 class="section-title">Tanggal Kedaluwarsa</h2>
                        <p class="section-desc">Masa berlaku produk untuk keamanan dan rotasi inventaris (opsional).</p>
                    </div>
                </div>

                <div class="row g-3 align-items-stretch">
                    <div class="col-md-6">
                        <label for="tanggal_kedaluwarsa" class="form-label">
                            <span>Tanggal Kedaluwarsa Produk</span>
                            <span class="badge bg-light text-muted fw-normal border">Opsional</span>
                        </label>
                        <input id="tanggal_kedaluwarsa" name="tanggal_kedaluwarsa" type="date" class="form-control" value="<?= $escape($form['tanggal_kedaluwarsa']); ?>">
                        <div class="form-text">Pilih tanggal jika obat memiliki batas pakai (Expired Date).</div>
                    </div>
                    <div class="col-md-6">
                        <div class="expiry-info-card">
                            <i class="bi bi-shield-check"></i>
                            <div>
                                <strong>Informasi Masa Berlaku</strong>
                                <p>Produk non-obat (seperti kapas, perban, kasa, dan alat kesehatan tertentu) dapat mengosongkan tanggal kedaluwarsa ini jika tidak tercantum.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TOMBOL AKSI -->
            <div class="form-actions-footer">
                <a href="index.php" class="btn btn-cancel">
                    <i class="bi bi-arrow-left"></i> Batal
                </a>
                <button type="submit" class="btn btn-submit-save" <?= $schemaError !== '' ? 'disabled' : ''; ?>>
                    <i class="bi bi-check2-circle"></i> Simpan Produk
                </button>
            </div>
        </form>

        <div class="footnote-tip">
            <i class="bi bi-info-circle text-success"></i>
            <span>Kolom bertanda <span class="text-danger fw-bold">*</span> wajib diisi. Kolom lainnya bersifat opsional.</span>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const beliInput = document.getElementById('harga_beli');
        const jualInput = document.getElementById('harga');
        const profitBadge = document.getElementById('profitPreview');
        const stokInput = document.getElementById('stok');
        const stokZeroAlert = document.getElementById('stokZeroAlert');

        function updateMargin() {
            if (!beliInput || !jualInput || !profitBadge) return;
            const beli = parseInt(beliInput.value, 10) || 0;
            const jual = parseInt(jualInput.value, 10) || 0;

            if (beli <= 0 && jual <= 0) {
                profitBadge.className = 'profit-badge neutral';
                profitBadge.innerHTML = '<i class="bi bi-calculator"></i> <span>Masukkan harga untuk melihat margin</span>';
                return;
            }

            const selisih = jual - beli;
            const persen = beli > 0 ? ((selisih / beli) * 100).toFixed(1) : 0;
            const formatter = new Intl.NumberFormat('id-ID');

            if (selisih > 0) {
                profitBadge.className = 'profit-badge positive';
                profitBadge.innerHTML = `<i class="bi bi-arrow-up-circle-fill"></i> <span>Estimasi Margin: +Rp ${formatter.format(selisih)} (${persen}%)</span>`;
            } else if (selisih < 0) {
                profitBadge.className = 'profit-badge negative';
                profitBadge.innerHTML = `<i class="bi bi-arrow-down-circle-fill"></i> <span>Harga jual di bawah harga beli (-Rp ${formatter.format(Math.abs(selisih))})</span>`;
            } else {
                profitBadge.className = 'profit-badge neutral';
                profitBadge.innerHTML = `<i class="bi bi-dash-circle"></i> <span>Harga jual sama dengan harga beli (Impas)</span>`;
            }
        }

        function checkZeroStock() {
            if (!stokInput || !stokZeroAlert) return;
            const stokVal = parseInt(stokInput.value, 10);
            if (stokVal === 0 || stokInput.value.trim() === '0') {
                stokZeroAlert.style.display = 'flex';
            } else {
                stokZeroAlert.style.display = 'none';
            }
        }

        beliInput.addEventListener('input', updateMargin);
        jualInput.addEventListener('input', updateMargin);
        stokInput.addEventListener('input', checkZeroStock);

        updateMargin();
        checkZeroStock();
    });
</script>
</body>
</html>
