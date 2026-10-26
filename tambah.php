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
    'deskripsi' => ''
];

/*
 * Kolom tambahan dibuat otomatis jika belum tersedia.
 * Struktur dasar tabel menu tetap dipakai.
 */
$kolomTambahan = [
    'merek' => "ALTER TABLE menu ADD COLUMN merek VARCHAR(100) NULL AFTER kategori",
    'satuan' => "ALTER TABLE menu ADD COLUMN satuan VARCHAR(30) NOT NULL DEFAULT 'pcs' AFTER merek",
    'harga_beli' => "ALTER TABLE menu ADD COLUMN harga_beli INT NOT NULL DEFAULT 0 AFTER satuan",
    'stok_minimum' => "ALTER TABLE menu ADD COLUMN stok_minimum INT NOT NULL DEFAULT 5 AFTER stok",
    'tanggal_kedaluwarsa' => "ALTER TABLE menu ADD COLUMN tanggal_kedaluwarsa DATE NULL AFTER stok_minimum",
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

        // Gunakan nama tabel aktif dari koneksi.php ($tableBarang)
        $tabelAktif = $tableBarang ?? 'menu';

        try {
            $stmt = $conn->prepare(
                "INSERT INTO `{$tabelAktif}` (nama_barang, nama_menu, kategori, merek, satuan, harga_beli, harga, jumlah, stok, tanggal_kedaluwarsa, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$stmt) {
                throw new RuntimeException('Query tidak dapat disiapkan: ' . $conn->error);
            }

            // nama_barang = nama_menu (alias standar UTS)
            // jumlah = stok (alias standar UTS)
            $stmt->bind_param(
                'sssssiiisss',
                $nama,    // nama_barang
                $nama,    // nama_menu
                $kategori,
                $merekDb,
                $satuan,
                $hargaBeli,
                $hargaJual,
                $stok,    // jumlah
                $stok,    // stok
                $tanggalDb,
                $deskripsiDb
            );
            $stmt->execute();
            $newId = (int) $conn->insert_id;
            $stmt->close();

            // Sinkronkan ke tabel 'menu' jika tabel aktif bukan 'menu'
            if ($tabelAktif !== 'menu') {
                $checkMenu = $conn->query("SHOW TABLES LIKE 'menu'");
                if ($checkMenu && $checkMenu->num_rows > 0) {
                    $syncStmt = $conn->prepare(
                        "INSERT INTO `menu` (id, nama_barang, nama_menu, kategori, merek, satuan, harga_beli, harga, jumlah, stok, tanggal_kedaluwarsa, deskripsi)
                         SELECT id, nama_barang, nama_menu, kategori, merek, satuan, harga_beli, harga, jumlah, stok, tanggal_kedaluwarsa, deskripsi
                         FROM `{$tabelAktif}` WHERE id = ?"
                    );
                    if ($syncStmt) {
                        $syncStmt->bind_param('i', $newId);
                        $syncStmt->execute();
                        $syncStmt->close();
                    }
                }
            }

            // Session flash message untuk notifikasi sukses di index.php
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['flash'] = [
                'type'    => 'success',
                'title'   => 'Berhasil Ditambahkan!',
                'message' => 'Barang "' . $nama . '" berhasil ditambahkan ke inventaris apotek.'
            ];

            header('Location: index.php?status=add_success');
            exit();
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'Produk gagal disimpan. Pastikan struktur tabel sudah siap dan data yang dimasukkan valid. Detail: ' . $exception->getMessage();
        }
    }
}

// Ambil data produk yang stoknya kritis (< 5 unit) untuk notifikasi cepat
$tabelAktif = $tableBarang ?? 'menu';
$produkStokHabis = [];
$resHabis = $conn->query("SELECT id, COALESCE(NULLIF(nama_barang,''), nama_menu) AS nama_menu, kategori, harga, satuan, COALESCE(jumlah, stok) AS stok FROM `{$tabelAktif}` WHERE COALESCE(jumlah, stok) < 5 ORDER BY stok ASC, id DESC LIMIT 5");
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
    <script>
        (function () {
            try {
                if (localStorage.getItem('sehatmart-dim') === 'true') {
                    document.documentElement.classList.add('dim-theme');
                }
            } catch (e) {}
        })();
    </script>
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
        <div class="d-flex align-items-center gap-2">
            <button class="icon-button" type="button" id="themeToggleBtn" title="Ganti Tema Tampilan" aria-label="Ganti tema tampilan">
                <i class="bi bi-moon-stars" id="themeIcon"></i>
            </button>
            <a href="index.php" class="btn btn-outline-green">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Inventaris
            </a>
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
                    <p class="page-subtitle">Catat obat, vitamin, suplemen, alat kesehatan, dan perlengkapan medis baru ke dalam katalog.</p>
                </div>
                <div class="d-none d-sm-block">
                    <span class="page-badge">
                        <i class="bi bi-shield-check me-1"></i> Sistem Terproteksi
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($produkStokHabis)): ?>
            <!-- Panel Notifikasi Stok Kritis (< 5 unit) -->
            <div class="out-of-stock-panel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h3><i class="bi bi-exclamation-triangle-fill text-warning"></i> Terdeteksi <?= count($produkStokHabis); ?> Produk Stok Kritis (Kurang dari 5 Unit)</h3>
                    <span class="status-badge status-badge-warning">Perlu Tindakan</span>
                </div>
                <p class="mb-3">Produk berikut memiliki stok di bawah batas minimum. Lakukan <strong>Restok Kembali</strong> atau <strong>Hapus</strong> jika sudah tidak dipasarkan:</p>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($produkStokHabis as $itemHabis): ?>
                        <?php
                        $stokItem = (int)$itemHabis['stok'];
                        $stokBadgeClass = $stokItem === 0 ? 'status-badge status-badge-danger' : 'status-badge status-badge-warning';
                        $stokLabel = $stokItem === 0 ? 'Stok Habis' : "Stok: {$stokItem}";
                        ?>
                        <div class="out-of-stock-item">
                            <div>
                                <strong><?= $escape($itemHabis['nama_menu']); ?></strong>
                                <span class="soft-badge ms-1"><?= $escape($itemHabis['kategori']); ?></span>
                                <span class="<?= $stokBadgeClass; ?> ms-1"><?= $stokLabel; ?> <?= $escape($itemHabis['satuan'] ?? 'pcs'); ?></span>
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
            <div class="alert alert-danger shadow-sm rounded-3 mb-4" role="alert">
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
                            <span class="soft-badge">Opsional</span>
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
                            <span class="soft-badge">Opsional</span>
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
                        <div id="profitPreview" class="form-text profit-preview neutral">
                            Masukkan harga untuk melihat estimasi margin.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="stok" class="form-label">
                            <span>Stok Awal <span class="required-star">*</span></span>
                        </label>
                        <input id="stok" name="stok" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok']); ?>" required>
                        <div class="form-text">Jumlah kuantitas fisik produk yang tersedia saat ini.</div>

                        <!-- Live Stock Status Notice (< 5 Unit / Habis) -->
                        <div id="stokNoticeHelper" class="stock-status-notice mt-2" style="display: none;"></div>
                    </div>

                    <div class="col-md-6">
                        <div class="expiry-info-card">
                            <i class="bi bi-box-seam text-success"></i>
                            <div>
                                <strong>Pengelolaan Stok Otomatis</strong>
                                <p>Kuantitas stok produk akan langsung tercatat di inventaris. Sistem memantau batas kritis stok minimum (5 unit) secara otomatis.</p>
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
                            <span class="soft-badge">Opsional</span>
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
                <button type="submit" class="btn btn-submit-save" id="btnSubmitForm" <?= $schemaError !== '' ? 'disabled' : ''; ?>>
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

<!-- MODAL KONFIRMASI KEBIJAKAN STOK (< 5 UNIT) -->
<div class="modal fade" id="stockPolicyModal" tabindex="-1" aria-labelledby="stockPolicyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content custom-policy-modal">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="policy-modal-icon-wrap" id="policyModalIconWrap">
                        <i class="bi bi-exclamation-triangle-fill fs-3" id="policyModalIcon"></i>
                    </div>
                    <div>
                        <span class="status-badge status-badge-warning mb-1" id="policyBadgeLevel">Peringatan Inventaris (< 5 Unit)</span>
                        <h3 class="modal-title h5 fw-bold mb-0" id="stockPolicyModalLabel">Konfirmasi Kebijakan Stok</h3>
                    </div>
                </div>
                <button type="button" class="policy-modal-close" data-bs-dismiss="modal" aria-label="Tutup">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body py-4">
                <!-- Info Box Produk -->
                <div class="policy-product-summary mb-3 p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-sm-7">
                            <span class="muted-copy d-block">Nama Produk:</span>
                            <strong class="fs-6" id="policyModalNamaProduk">-</strong>
                            <span class="soft-badge ms-1" id="policyModalKategori"></span>
                        </div>
                        <div class="col-sm-5 text-sm-end">
                            <span class="muted-copy d-block">Kuantitas Terdeteksi:</span>
                            <span class="status-badge status-badge-warning" id="policyModalStokStatus">0 Unit</span>
                        </div>
                    </div>
                </div>

                <p class="muted-copy mb-3" id="policyModalPrompt">
                    Sistem mendeteksi bahwa stok awal produk ini <strong>kurang dari batas minimum 5 unit</strong>. Sesuai kebijakan inventaris SehatMart, silakan tentukan tindakan:
                </p>

                <!-- 2 Pilihan Kebijakan -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="policy-choice-card selected-restok" id="cardRestok" onclick="selectPolicyChoice('restok')">
                            <div class="d-flex align-items-start gap-2">
                                <input class="form-check-input mt-1" type="radio" name="modal_policy_choice" id="choiceRestok" value="restok" checked>
                                <label class="form-check-label w-100 cursor-pointer" for="choiceRestok">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-success d-flex align-items-center gap-1">
                                            <i class="bi bi-arrow-repeat"></i> Izinkan Restok Kembali
                                        </strong>
                                        <span class="status-badge status-badge-success">Rekomendasi</span>
                                    </div>
                                    <p class="small muted-copy mb-0">
                                        Produk tetap tersimpan di katalog saat stok <span id="policyStockDisplay">0</span>, siap untuk di-restok ulang sewaktu-waktu.
                                    </p>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="policy-choice-card" id="cardHapus" onclick="selectPolicyChoice('hapus')">
                            <div class="d-flex align-items-start gap-2">
                                <input class="form-check-input mt-1" type="radio" name="modal_policy_choice" id="choiceHapus" value="hapus">
                                <label class="form-check-label w-100 cursor-pointer" for="choiceHapus">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-danger d-flex align-items-center gap-1">
                                            <i class="bi bi-x-circle"></i> Siapkan Opsi Hapus
                                        </strong>
                                        <span class="status-badge status-badge-danger">Batal Simpan</span>
                                    </div>
                                    <p class="small muted-copy mb-0">
                                        Batalkan penyimpanan ke katalog jika stok produk memang sudah habis atau tidak akan dipasarkan lagi.
                                    </p>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                    <i class="bi bi-pencil-square me-1"></i> Ubah Jumlah Stok
                </button>
                <button type="button" class="btn btn-submit-save" id="btnExecutePolicy">
                    <i class="bi bi-check2-circle me-1"></i> Simpan & Izinkan Restok
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Pengaturan Tema Redup / Normal
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');
    function applyTheme(isDim) {
        document.body.classList.toggle('dim-theme', isDim);
        document.documentElement.classList.toggle('dim-theme', isDim);
        if (themeIcon) {
            if (isDim) {
                themeIcon.classList.remove('bi-moon-stars');
                themeIcon.classList.add('bi-sun');
            } else {
                themeIcon.classList.remove('bi-sun');
                themeIcon.classList.add('bi-moon-stars');
            }
        }
    }
    const isDimSaved = localStorage.getItem('sehatmart-dim') === 'true';
    applyTheme(isDimSaved);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const isNowDim = !document.body.classList.contains('dim-theme');
            applyTheme(isNowDim);
            localStorage.setItem('sehatmart-dim', String(isNowDim));
        });
    }

    // Variabel Kebijakan Stok & Modal
    let policyConfirmed = false;
    let selectedPolicy = 'restok';
    const formEl = document.querySelector('form');
    const beliInput = document.getElementById('harga_beli');
    const jualInput = document.getElementById('harga');
    const profitBadge = document.getElementById('profitPreview');
    const stokInput = document.getElementById('stok');
    const namaInput = document.getElementById('nama_menu');
    const kategoriSelect = document.getElementById('kategori');
    const satuanSelect = document.getElementById('satuan');
    const stokNoticeHelper = document.getElementById('stokNoticeHelper');
    const stockPolicyModalEl = document.getElementById('stockPolicyModal');
    const stockPolicyModal = new bootstrap.Modal(stockPolicyModalEl);
    const btnExecutePolicy = document.getElementById('btnExecutePolicy');

    function updateMargin() {
        if (!beliInput || !jualInput || !profitBadge) return;
        const beli = parseInt(beliInput.value, 10) || 0;
        const jual = parseInt(jualInput.value, 10) || 0;

        if (beli <= 0 && jual <= 0) {
            profitBadge.className = 'form-text profit-preview neutral';
            profitBadge.textContent = 'Masukkan harga untuk melihat estimasi margin.';
            return;
        }

        const selisih = jual - beli;
        const persen = beli > 0 ? ((selisih / beli) * 100).toFixed(1) : 0;
        const formatter = new Intl.NumberFormat('id-ID');

        if (selisih > 0) {
            profitBadge.className = 'form-text profit-preview positive';
            profitBadge.textContent = `Estimasi margin: +Rp ${formatter.format(selisih)} (${persen}%).`;
        } else if (selisih < 0) {
            profitBadge.className = 'form-text profit-preview negative';
            profitBadge.textContent = `Harga jual di bawah harga beli (-Rp ${formatter.format(Math.abs(selisih))}).`;
        } else {
            profitBadge.className = 'form-text profit-preview neutral';
            profitBadge.textContent = 'Harga jual sama dengan harga beli (impas).';
        }
    }

    function checkStockLevel() {
        if (!stokInput || !stokNoticeHelper) return;
        const rawVal = stokInput.value.trim();
        if (rawVal === '') {
            stokNoticeHelper.style.display = 'none';
            return;
        }

        const stokVal = parseInt(rawVal, 10);
        if (isNaN(stokVal)) {
            stokNoticeHelper.style.display = 'none';
            return;
        }

        stokNoticeHelper.style.display = 'inline-flex';
        if (stokVal === 0) {
            stokNoticeHelper.className = 'stock-status-notice empty';
            stokNoticeHelper.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> <span>Stok 0 Unit (Habis). Sistem mendeteksi barang kosong.</span> <button type="button" class="policy-trigger-btn" onclick="openStockPolicyModal(0)">Konfirmasi Kebijakan</button>`;
        } else if (stokVal < 5) {
            stokNoticeHelper.className = 'stock-status-notice low';
            stokNoticeHelper.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i> <span>Stok ${stokVal} Unit (Kurang dari batas 5 unit).</span> <button type="button" class="policy-trigger-btn" onclick="openStockPolicyModal(${stokVal})">Konfirmasi Kebijakan</button>`;
        } else {
            stokNoticeHelper.className = 'stock-status-notice safe';
            stokNoticeHelper.innerHTML = `<i class="bi bi-check-circle-fill"></i> <span>Stok Aman (${stokVal} Unit)</span>`;
        }
    }

    function selectPolicyChoice(choice) {
        selectedPolicy = choice;
        const choiceRestok = document.getElementById('choiceRestok');
        const choiceHapus = document.getElementById('choiceHapus');
        const cardRestok = document.getElementById('cardRestok');
        const cardHapus = document.getElementById('cardHapus');

        if (choice === 'restok') {
            choiceRestok.checked = true;
            cardRestok.classList.add('selected-restok');
            cardHapus.classList.remove('selected-hapus');
            btnExecutePolicy.className = 'btn btn-submit-save';
            btnExecutePolicy.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan & Izinkan Restok';
        } else {
            choiceHapus.checked = true;
            cardHapus.classList.add('selected-hapus');
            cardRestok.classList.remove('selected-restok');
            btnExecutePolicy.className = 'btn btn-danger';
            btnExecutePolicy.innerHTML = '<i class="bi bi-x-circle me-1"></i> Batalkan & Kembali ke Katalog';
        }
    }

    function openStockPolicyModal(stokVal) {
        const iconWrap = document.getElementById('policyModalIconWrap');
        const iconEl = document.getElementById('policyModalIcon');
        const badgeLevel = document.getElementById('policyBadgeLevel');
        const modalTitle = document.getElementById('stockPolicyModalLabel');
        const modalNama = document.getElementById('policyModalNamaProduk');
        const modalKategori = document.getElementById('policyModalKategori');
        const modalStatus = document.getElementById('policyModalStokStatus');
        const stockDisplay = document.getElementById('policyStockDisplay');

        modalNama.textContent = (namaInput && namaInput.value.trim()) ? namaInput.value.trim() : 'Produk Baru';
        modalKategori.textContent = (kategoriSelect && kategoriSelect.value) ? kategoriSelect.value : 'Inventaris';
        stockDisplay.textContent = String(stokVal);

        if (stokVal === 0) {
            iconWrap.className = 'policy-modal-icon-wrap empty';
            iconEl.className = 'bi bi-exclamation-triangle-fill fs-3 text-danger';
            badgeLevel.className = 'status-badge status-badge-danger mb-1';
            badgeLevel.textContent = 'Stok Habis (0 Unit)';
            modalTitle.textContent = 'Konfirmasi Kebijakan: Stok Habis (0 Unit)';
            modalStatus.className = 'status-badge status-badge-danger';
            modalStatus.textContent = '0 ' + (satuanSelect ? satuanSelect.value : 'unit') + ' (Habis)';
        } else {
            iconWrap.className = 'policy-modal-icon-wrap low';
            iconEl.className = 'bi bi-exclamation-triangle-fill fs-3 text-warning';
            badgeLevel.className = 'status-badge status-badge-warning mb-1';
            badgeLevel.textContent = 'Stok Kritis / Menipis (< 5 Unit)';
            modalTitle.textContent = 'Konfirmasi Kebijakan: Stok Kurang dari 5 Unit';
            modalStatus.className = 'status-badge status-badge-warning';
            modalStatus.textContent = `${stokVal} ` + (satuanSelect ? satuanSelect.value : 'unit') + ' (Menipis)';
        }

        // Reset default ke Restok
        selectPolicyChoice('restok');
        stockPolicyModal.show();
    }

    // Eksekusi Tindakan Modal
    btnExecutePolicy.addEventListener('click', function () {
        if (selectedPolicy === 'restok') {
            policyConfirmed = true;
            stockPolicyModal.hide();
            formEl.submit();
        } else {
            // Pilihan Batal / Hapus pada tambah.php: Batalkan penambahan produk
            window.location.href = 'index.php';
        }
    });

    // Intersepsi Form Submit jika Stok < 5
    formEl.addEventListener('submit', function (e) {
        if (policyConfirmed) return; // Lanjutkan jika sudah dikonfirmasi di modal

        const rawVal = stokInput ? stokInput.value.trim() : '';
        const stokVal = parseInt(rawVal, 10);

        if (!isNaN(stokVal) && stokVal < 5) {
            e.preventDefault();
            openStockPolicyModal(stokVal);
        }
    });

    // Event Listener Input
    if (beliInput) beliInput.addEventListener('input', updateMargin);
    if (jualInput) jualInput.addEventListener('input', updateMargin);
    if (stokInput) {
        stokInput.addEventListener('input', checkStockLevel);
        stokInput.addEventListener('change', checkStockLevel);
    }

    // Inisialisasi awal
    updateMargin();
    checkStockLevel();
</script>
</body>
</html>
