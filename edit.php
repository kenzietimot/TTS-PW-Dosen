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

$data = null;
if ($schemaError === '') {
    $stmt = $conn->prepare('SELECT id, nama_menu, kategori, merek, satuan, harga_beli, harga, stok, stok_minimum, tanggal_kedaluwarsa, deskripsi FROM menu WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        header('Location: index.php');
        exit();
    }
}

$form = [
    'nama_menu' => (string) ($data['nama_menu'] ?? ''),
    'kategori' => (string) ($data['kategori'] ?? ''),
    'merek' => (string) ($data['merek'] ?? ''),
    'satuan' => (string) ($data['satuan'] ?? 'pcs'),
    'harga_beli' => (string) ($data['harga_beli'] ?? '0'),
    'harga' => (string) ($data['harga'] ?? '0'),
    'stok' => (string) ($data['stok'] ?? '0'),
    'stok_minimum' => (string) ($data['stok_minimum'] ?? '5'),
    'tanggal_kedaluwarsa' => (string) ($data['tanggal_kedaluwarsa'] ?? ''),
    'deskripsi' => (string) ($data['deskripsi'] ?? '')
];

if ($form['kategori'] !== '' && !in_array($form['kategori'], $kategori_produk, true)) {
    $kategori_produk[] = $form['kategori'];
}
if ($form['satuan'] !== '' && !in_array($form['satuan'], $satuan_produk, true)) {
    $satuan_produk[] = $form['satuan'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaError === '') {
    foreach ($form as $field => $currentValue) {
        $postedValue = $_POST[$field] ?? $currentValue;
        $form[$field] = is_string($postedValue) ? trim($postedValue) : $currentValue;
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
                'UPDATE menu SET nama_menu = ?, kategori = ?, merek = ?, satuan = ?, harga_beli = ?, harga = ?, stok = ?, stok_minimum = ?, tanggal_kedaluwarsa = ?, deskripsi = ? WHERE id = ?'
            );

            if (!$stmt) {
                throw new RuntimeException('Query tidak dapat disiapkan.');
            }

            $stmt->bind_param(
                'ssssiiiissi',
                $nama,
                $kategori,
                $merekDb,
                $satuan,
                $hargaBeli,
                $hargaJual,
                $stok,
                $stokMinimum,
                $tanggalDb,
                $deskripsiDb,
                $id
            );
            $stmt->execute();
            $stmt->close();

            header('Location: index.php?status=edit_success');
            exit();
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'Produk gagal diperbarui. Pastikan struktur tabel menu sudah siap dan data yang dimasukkan valid.';
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
    <title>Edit Produk - SehatMart</title>
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
            <a href="index.php" class="btn btn-outline-green"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5" style="max-width: 1000px;">
    <div class="mb-4">
        <span class="eyebrow">PENGELOLAAN INVENTARIS</span>
        <h1 class="h2 fw-bold mt-2 mb-2">Edit produk apotek</h1>
        <p class="text-secondary mb-0">Perbarui informasi, harga, stok, dan tanggal kedaluwarsa produk.</p>
    </div>

    <?php if ($schemaError !== ''): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= $escape($schemaError); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= $escape($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="edit.php?id=<?= (int) $id; ?>" class="card border-0 shadow-sm" id="editForm">
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
                    <label for="stok" class="form-label">Stok saat ini <span class="text-danger">*</span></label>
                    <input id="stok" name="stok" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok']); ?>" required>
                    <!-- Live Stock Status Notice (< 5 Unit / Habis) -->
                    <div id="stokNoticeHelper" class="stock-status-notice mt-2" style="display: none;"></div>
                </div>
                <div class="col-md-6">
                    <label for="stok_minimum" class="form-label">Batas stok minimum <span class="text-danger">*</span></label>
                    <input id="stok_minimum" name="stok_minimum" type="number" class="form-control" min="0" step="1" value="<?= $escape($form['stok_minimum']); ?>" required>
                    <div class="form-text">Sebagai acuan saat stok mulai menipis (sistem otomatis mendeteksi jika &lt; 5 unit).</div>
                </div>
            </div>

            <hr class="my-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-calendar-check text-success me-2"></i>Kedaluwarsa</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="tanggal_kedaluwarsa" class="form-label">Tanggal kedaluwarsa</label>
                    <input id="tanggal_kedaluwarsa" name="tanggal_kedaluwarsa" type="date" class="form-control" value="<?= $escape($form['tanggal_kedaluwarsa']); ?>">
                    <div class="form-text">Boleh dikosongkan jika produk tidak memiliki tanggal kedaluwarsa.</div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mt-4">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Batal</a>
                <button type="submit" class="btn btn-primary-green" id="btnSubmitForm" <?= $schemaError !== '' ? 'disabled' : ''; ?>><i class="bi bi-check2-circle me-1"></i> Simpan perubahan</button>
            </div>
        </div>
    </form>
    <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Field bertanda * wajib diisi. Tanggal kedaluwarsa bersifat opsional.</p>
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
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mb-1" id="policyBadgeLevel">Peringatan Inventaris (< 5 Unit)</span>
                        <h3 class="modal-title h5 fw-bold mb-0" id="stockPolicyModalLabel">Konfirmasi Kebijakan Stok</h3>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body py-4">
                <!-- Info Box Produk -->
                <div class="policy-product-summary mb-3 p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-sm-7">
                            <span class="text-secondary small d-block">Nama Produk:</span>
                            <strong class="fs-6" id="policyModalNamaProduk"><?= $escape($form['nama_menu']); ?></strong>
                            <span class="badge bg-light text-secondary border ms-1" id="policyModalKategori"><?= $escape($form['kategori']); ?></span>
                        </div>
                        <div class="col-sm-5 text-sm-end">
                            <span class="text-secondary small d-block">Kuantitas Stok Baru:</span>
                            <span class="badge" id="policyModalStokStatus">0 Unit</span>
                        </div>
                    </div>
                </div>

                <p class="text-secondary mb-3" id="policyModalPrompt">
                    Sistem mendeteksi bahwa stok produk ini <strong>kurang dari batas minimum 5 unit</strong>. Sesuai kebijakan inventaris SehatMart, silakan tentukan tindakan:
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
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Tetap Simpan</span>
                                    </div>
                                    <p class="small text-muted mb-0">
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
                                            <i class="bi bi-trash3"></i> Siapkan Opsi Hapus
                                        </strong>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Hapus Katalog</span>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        Hapus produk ini langsung dari katalog inventaris jika stok sudah habis atau tidak akan dipasarkan lagi.
                                    </p>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-pencil-square me-1"></i> Ubah Jumlah Stok
                </button>
                <button type="button" class="btn btn-success" id="btnExecutePolicy">
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
    const formEl = document.getElementById('editForm');
    const stokInput = document.getElementById('stok');
    const stokMinInput = document.getElementById('stok_minimum');
    const namaInput = document.getElementById('nama_menu');
    const kategoriSelect = document.getElementById('kategori');
    const satuanSelect = document.getElementById('satuan');
    const stokNoticeHelper = document.getElementById('stokNoticeHelper');
    const stockPolicyModalEl = document.getElementById('stockPolicyModal');
    const stockPolicyModal = new bootstrap.Modal(stockPolicyModalEl);
    const btnExecutePolicy = document.getElementById('btnExecutePolicy');

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
            stokNoticeHelper.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i> <span>Stok ${stokVal} Unit — Di bawah batas kritis 5 unit.</span> <button type="button" class="policy-trigger-btn" onclick="openStockPolicyModal(${stokVal})">Konfirmasi Kebijakan</button>`;
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
            btnExecutePolicy.className = 'btn btn-success';
            btnExecutePolicy.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan & Izinkan Restok';
        } else {
            choiceHapus.checked = true;
            cardHapus.classList.add('selected-hapus');
            cardRestok.classList.remove('selected-restok');
            btnExecutePolicy.className = 'btn btn-danger';
            btnExecutePolicy.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Produk dari Katalog';
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

        modalNama.textContent = (namaInput && namaInput.value.trim()) ? namaInput.value.trim() : '<?= $escape($form['nama_menu']); ?>';
        modalKategori.textContent = (kategoriSelect && kategoriSelect.value) ? kategoriSelect.value : '<?= $escape($form['kategori']); ?>';
        stockDisplay.textContent = String(stokVal);

        if (stokVal === 0) {
            iconWrap.className = 'policy-modal-icon-wrap empty';
            iconEl.className = 'bi bi-x-circle-fill fs-3 text-danger';
            badgeLevel.className = 'badge bg-danger-subtle text-danger border border-danger-subtle mb-1';
            badgeLevel.textContent = 'Stok Habis (0 Unit)';
            modalTitle.textContent = 'Konfirmasi Kebijakan: Stok Habis (0 Unit)';
            modalStatus.className = 'badge bg-danger';
            modalStatus.textContent = '0 ' + (satuanSelect ? satuanSelect.value : 'unit') + ' (Habis)';
        } else {
            iconWrap.className = 'policy-modal-icon-wrap low';
            iconEl.className = 'bi bi-exclamation-triangle-fill fs-3 text-warning';
            badgeLevel.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mb-1';
            badgeLevel.textContent = 'Stok Kritis / Menipis (< 5 Unit)';
            modalTitle.textContent = 'Konfirmasi Kebijakan: Stok Kurang dari 5 Unit';
            modalStatus.className = 'badge bg-warning text-dark';
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
            // Pilihan Hapus pada edit.php: Konfirmasi dan arahkan ke hapus.php
            if (confirm('Hapus produk ini secara permanen dari katalog inventaris apotek?')) {
                window.location.href = 'hapus.php?id=<?= (int)$id; ?>';
            }
        }
    });

    // Intersepsi Form Submit jika Stok < 5
    formEl.addEventListener('submit', function (e) {
        if (policyConfirmed) return;

        const rawVal = stokInput ? stokInput.value.trim() : '';
        const stokVal = parseInt(rawVal, 10);

        if (!isNaN(stokVal) && stokVal < 5) {
            e.preventDefault();
            openStockPolicyModal(stokVal);
        }
    });

    // Event Listener Input
    if (stokInput) {
        stokInput.addEventListener('input', checkStockLevel);
        stokInput.addEventListener('change', checkStockLevel);
    }
    if (stokMinInput) {
        stokMinInput.addEventListener('input', checkStockLevel);
    }

    // Inisialisasi awal
    checkStockLevel();
</script>
</body>
</html>
