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
    'tanggal_kedaluwarsa' => '',
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
    <style>
        :root {
            --form-bg: #f8faf8;
            --card-border: #e2ebe4;
            --card-bg: #ffffff;
            --input-border: #d4ded7;
            --input-focus: #267b58;
            --text-heading: #1d3328;
            --text-muted: #6f7f76;
            --accent-soft: #edf6f0;
        }

        body {
            background-color: var(--form-bg);
            color: var(--text-heading);
            min-height: 100vh;
        }

        .form-page-wrapper {
            max-width: 880px;
            margin: 0 auto;
        }

        .page-header-card {
            background: linear-gradient(135deg, #ffffff 0%, #f2f8f4 100%);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 20px rgba(38, 123, 88, 0.04);
        }

        .header-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: var(--accent-soft);
            color: var(--green, #267b58);
            border: 1px solid #d5e9dc;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .main-form-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            box-shadow: 0 12px 36px rgba(24, 85, 61, 0.06);
            overflow: hidden;
        }

        .form-section {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid #edf2ee;
            transition: background-color 0.2s ease;
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 1.5rem;
        }

        .section-icon-badge {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--green-soft, #e7f3eb);
            color: var(--green, #267b58);
            font-size: 1.25rem;
            flex-shrink: 0;
            border: 1px solid #d4e7db;
        }

        .section-title {
            margin: 0;
            font-size: 1.12rem;
            font-weight: 750;
            color: var(--text-heading);
            letter-spacing: -0.2px;
        }

        .section-desc {
            margin: 2px 0 0 0;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .form-label {
            font-size: 0.86rem;
            font-weight: 650;
            color: #2e4337;
            margin-bottom: 0.45rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-label .required-star {
            color: #dc3545;
            margin-left: 2px;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--input-border);
            border-radius: 11px;
            padding: 0.65rem 0.95rem;
            font-size: 0.92rem;
            color: #21372d;
            background-color: #ffffff;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .form-control:hover, .form-select:hover {
            border-color: #b8ccbe;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--green, #267b58);
            box-shadow: 0 0 0 4px rgba(38, 123, 88, 0.12);
            background-color: #ffffff;
        }

        .input-group-text {
            background-color: #f4f8f5;
            border: 1.5px solid var(--input-border);
            border-right: none;
            color: var(--green, #267b58);
            font-weight: 700;
            font-size: 0.88rem;
            border-radius: 11px 0 0 11px;
            padding: 0 0.9rem;
        }

        .input-group .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--green, #267b58);
        }

        .form-text {
            color: var(--text-muted);
            font-size: 0.77rem;
            margin-top: 0.35rem;
        }

        .expiry-info-card {
            background: linear-gradient(135deg, #f7faf8 0%, #edf5f0 100%);
            border: 1px solid #d9e8de;
            border-radius: 14px;
            padding: 1.15rem 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            height: 100%;
        }

        .expiry-info-card i {
            color: var(--green, #267b58);
            font-size: 1.3rem;
            line-height: 1.2;
            flex-shrink: 0;
        }

        .expiry-info-card strong {
            display: block;
            font-size: 0.86rem;
            color: var(--text-heading);
            margin-bottom: 2px;
        }

        .expiry-info-card p {
            margin: 0;
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        .profit-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 8px;
            margin-top: 6px;
            transition: all 0.2s ease;
        }
        .profit-badge.positive {
            background: #e6f6ec;
            color: #1b6d46;
            border: 1px solid #c5ebd2;
        }
        .profit-badge.negative {
            background: #fdeeee;
            color: #c03225;
            border: 1px solid #fad2cf;
        }
        .profit-badge.neutral {
            background: #f1f4f2;
            color: #607267;
            border: 1px solid #dbe2de;
        }

        .form-actions-footer {
            background: #fafcfa;
            border-top: 1px solid #edf2ee;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 46px;
            padding: 0 1.5rem;
            border: 1.5px solid #d4ded7;
            border-radius: 11px;
            background: #ffffff;
            color: #4a5c52;
            font-size: 0.9rem;
            font-weight: 650;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: #f0f4f1;
            border-color: #bccbc0;
            color: #21372d;
        }

        .btn-submit-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 46px;
            padding: 0 1.75rem;
            border: 1px solid var(--green, #267b58);
            border-radius: 11px;
            background: var(--green, #267b58);
            color: #ffffff;
            font-size: 0.92rem;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(38, 123, 88, 0.22);
            transition: all 0.2s ease;
        }

        .btn-submit-save:hover:not(:disabled) {
            background: var(--green-dark, #18553d);
            border-color: var(--green-dark, #18553d);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(38, 123, 88, 0.3);
            color: #ffffff;
        }

        .btn-submit-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .footnote-tip {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #7b8b82;
            font-size: 0.8rem;
            margin-top: 1rem;
            padding: 0 0.5rem;
        }

        @media (max-width: 576px) {
            .page-header-card {
                padding: 1.25rem 1.25rem;
            }
            .form-section {
                padding: 1.25rem 1.25rem;
            }
            .form-actions-footer {
                padding: 1.25rem 1.25rem;
                flex-direction: column-reverse;
            }
            .btn-cancel, .btn-submit-save {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light sticky-top pharmacy-nav">
    <div class="container">
        <a class="navbar-brand brand-mark" href="index.php">
            <span class="brand-icon"><i class="bi bi-plus-lg"></i></span>
            <span>sehat<span class="brand-highlight">mart</span><small>INVENTARIS APOTEK</small></span>
        </a>
        <a href="index.php" class="btn btn-outline-green">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog
        </a>
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

        beliInput.addEventListener('input', updateMargin);
        jualInput.addEventListener('input', updateMargin);
        updateMargin();
    });
</script>
</body>
</html>
