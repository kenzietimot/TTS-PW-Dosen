<?php
require_once 'koneksi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateStatus = 'stock_error';
    $postedToken = $_POST['csrf_token'] ?? '';
    $stockId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $stockAction = $_POST['stock_action'] ?? '';

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

    $isValidToken = is_string($postedToken) && hash_equals($_SESSION['csrf_token'], $postedToken);
    $isValidId = $stockId !== false && $stockId !== null && $stockId > 0;
    $allowedActions = ['plus', 'minus', 'set_stock', 'adjust'];

    if ($isValidToken && $isValidId && is_string($stockAction) && in_array($stockAction, $allowedActions, true)) {
        try {
            $executed = false;

            if ($stockAction === 'set_stock') {
                $newStock = filter_input(INPUT_POST, 'new_stock', FILTER_VALIDATE_INT);
                if ($newStock !== false && $newStock !== null && $newStock >= 0 && $newStock <= 2147483647) {
                    $stmt = $conn->prepare('UPDATE menu SET stok = ? WHERE id = ?');
                    $stmt->bind_param('ii', $newStock, $stockId);
                    $stmt->execute();
                    $stmt->close();
                    $executed = true;
                }
            } elseif ($stockAction === 'adjust') {
                $delta = filter_input(INPUT_POST, 'delta', FILTER_VALIDATE_INT);
                if ($delta !== false && $delta !== null) {
                    $stmt = $conn->prepare('UPDATE menu SET stok = GREATEST(0, LEAST(2147483647, stok + ?)) WHERE id = ?');
                    $stmt->bind_param('ii', $delta, $stockId);
                    $stmt->execute();
                    $stmt->close();
                    $executed = true;
                }
            } elseif ($stockAction === 'plus') {
                $stmt = $conn->prepare('UPDATE menu SET stok = stok + 1 WHERE id = ? AND stok < 2147483647');
                $stmt->bind_param('i', $stockId);
                $stmt->execute();
                $stmt->close();
                $executed = true;
            } elseif ($stockAction === 'minus') {
                $stmt = $conn->prepare('UPDATE menu SET stok = stok - 1 WHERE id = ? AND stok > 0');
                $stmt->bind_param('i', $stockId);
                $stmt->execute();
                $stmt->close();
                $executed = true;
            }

            if ($executed) {
                $itemStmt = $conn->prepare('SELECT id, nama_menu, stok, stok_minimum, satuan, kategori, harga, harga_beli, merek, deskripsi, tanggal_kedaluwarsa FROM menu WHERE id = ?');
                $itemStmt->bind_param('i', $stockId);
                $itemStmt->execute();
                $updatedItem = $itemStmt->get_result()->fetch_assoc();
                $itemStmt->close();

                if ($updatedItem) {
                    $updateStatus = 'stock_updated';

                    if ($isAjax) {
                        $currStok = (int) $updatedItem['stok'];
                        $stokMin = (int) $updatedItem['stok_minimum'];

                        if ($currStok === 0) {
                            $stockClass = 'stock-badge-danger';
                            $stockLabel = 'Stok Habis';
                            $stockIcon = 'bi-x-circle-fill';
                        } elseif ($currStok <= $stokMin) {
                            $stockClass = 'stock-badge-warn';
                            $stockLabel = 'Perlu Restock';
                            $stockIcon = 'bi-exclamation-triangle-fill';
                        } else {
                            $stockClass = 'stock-badge-safe';
                            $stockLabel = 'Stok Aman';
                            $stockIcon = 'bi-check-circle-fill';
                        }

                        $kpiQuery = "SELECT 
                            COUNT(*) AS total_sku,
                            COALESCE(SUM(stok), 0) AS total_unit,
                            COALESCE(SUM(CASE WHEN stok > 0 AND stok <= stok_minimum THEN 1 ELSE 0 END), 0) AS total_menipis,
                            COALESCE(SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END), 0) AS total_habis,
                            COALESCE(SUM(CASE WHEN tanggal_kedaluwarsa IS NOT NULL AND tanggal_kedaluwarsa <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END), 0) AS total_exp_alert,
                            COALESCE(SUM(CASE WHEN harga_beli > 0 THEN harga_beli * stok ELSE harga * stok END), 0) AS total_nilai_aset
                        FROM menu";
                        $kpiRes = $conn->query($kpiQuery);
                        $kpiData = $kpiRes ? $kpiRes->fetch_assoc() : null;

                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Stok "' . $updatedItem['nama_menu'] . '" berhasil diverifikasi dan disimpan menjadi ' . number_format($currStok, 0, ',', '.') . ' ' . $updatedItem['satuan'] . '.',
                            'data' => [
                                'id' => (int) $updatedItem['id'],
                                'nama_menu' => $updatedItem['nama_menu'],
                                'stok' => $currStok,
                                'stok_minimum' => $stokMin,
                                'satuan' => $updatedItem['satuan'],
                                'stock_class' => $stockClass,
                                'stock_label' => $stockLabel,
                                'stock_icon' => $stockIcon,
                                'item' => $updatedItem,
                                'kpi' => $kpiData
                            ]
                        ]);
                        exit();
                    }
                } else {
                    $updateStatus = 'stock_error';
                }
            } else {
                $updateStatus = 'stock_error';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $updateStatus = 'stock_error';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Terjadi kesalahan sistem saat memperbarui stok. Silakan coba lagi.'
                ]);
                exit();
            }
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Permintaan tidak valid atau sesi telah kedaluwarsa. Muat ulang halaman.'
            ]);
            exit();
        }
    }

    $redirectParams = [];
    parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $redirectParams);
    unset($redirectParams['status']);
    $redirectQuery = http_build_query($redirectParams);
    $redirectUrl = 'index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : '');
    if ($redirectQuery !== '') {
        header('Location: ' . $redirectUrl . '&status=' . rawurlencode($updateStatus) . '#tabel-inventaris');
    } else {
        header('Location: ' . $redirectUrl . '?status=' . rawurlencode($updateStatus) . '#tabel-inventaris');
    }
    exit();
}

// Cek dan tangkap input filter
$searchInput   = $_GET['search'] ?? '';
$categoryInput  = $_GET['kategori'] ?? '';
$statusInput    = $_GET['status_stok'] ?? 'semua';
$sortInput      = $_GET['sort'] ?? 'terbaru';
$alertStatus    = $_GET['status'] ?? '';

$search      = is_string($searchInput) ? trim($searchInput) : '';
$kategori    = is_string($categoryInput) ? trim($categoryInput) : '';
$statusStok  = is_string($statusInput) ? trim($statusInput) : 'semua';
$sort        = is_string($sortInput) ? trim($sortInput) : 'terbaru';

$allowedCategories = [
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

$allowedStatus = ['semua', 'aman', 'menipis', 'habis', 'kedaluwarsa', 'segera_kedaluwarsa'];
if (!in_array($statusStok, $allowedStatus, true)) {
    $statusStok = 'semua';
}

if ($kategori !== '' && !in_array($kategori, $allowedCategories, true)) {
    $kategori = '';
}

// 1. Ambil Ringkasan Metrik Inventaris (KPI Keseluruhan)
$summaryQuery = "SELECT 
    COUNT(*) AS total_sku,
    COALESCE(SUM(stok), 0) AS total_unit,
    COALESCE(SUM(CASE WHEN stok > 0 AND stok <= stok_minimum THEN 1 ELSE 0 END), 0) AS total_menipis,
    COALESCE(SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END), 0) AS total_habis,
    COALESCE(SUM(CASE WHEN tanggal_kedaluwarsa IS NOT NULL AND tanggal_kedaluwarsa <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END), 0) AS total_exp_alert,
    COALESCE(SUM(CASE WHEN harga_beli > 0 THEN harga_beli * stok ELSE harga * stok END), 0) AS total_nilai_aset
FROM menu";

$summaryResult = $conn->query($summaryQuery);
$kpi = $summaryResult ? $summaryResult->fetch_assoc() : [
    'total_sku' => 0,
    'total_unit' => 0,
    'total_menipis' => 0,
    'total_habis' => 0,
    'total_exp_alert' => 0,
    'total_nilai_aset' => 0
];

// 2. Susun Query Filter Inventaris
$sql = "SELECT id, nama_menu, kategori, merek, satuan, harga_beli, harga, stok, stok_minimum, tanggal_kedaluwarsa, deskripsi FROM menu WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (nama_menu LIKE ? OR merek LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= 'ss';
}

if ($kategori !== '') {
    $sql .= " AND kategori = ?";
    $params[] = $kategori;
    $types .= 's';
}

if ($statusStok === 'menipis') {
    $sql .= " AND (stok > 0 AND stok <= stok_minimum)";
} elseif ($statusStok === 'habis') {
    $sql .= " AND stok = 0";
} elseif ($statusStok === 'aman') {
    $sql .= " AND stok > stok_minimum";
} elseif ($statusStok === 'kedaluwarsa') {
    $sql .= " AND tanggal_kedaluwarsa IS NOT NULL AND tanggal_kedaluwarsa < CURDATE()";
} elseif ($statusStok === 'segera_kedaluwarsa') {
    $sql .= " AND tanggal_kedaluwarsa IS NOT NULL AND tanggal_kedaluwarsa BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
}

switch ($sort) {
    case 'nama_asc':
        $sql .= " ORDER BY nama_menu ASC";
        break;
    case 'stok_asc':
        $sql .= " ORDER BY stok ASC, id DESC";
        break;
    case 'stok_desc':
        $sql .= " ORDER BY stok DESC, id DESC";
        break;
    case 'exp_asc':
        $sql .= " ORDER BY (tanggal_kedaluwarsa IS NULL), tanggal_kedaluwarsa ASC";
        break;
    case 'harga_desc':
        $sql .= " ORDER BY harga DESC";
        break;
    case 'terbaru':
    default:
        $sql .= " ORDER BY id DESC";
        break;
}

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$statusMessages = [
    'add_success' => 'Produk inventaris baru berhasil ditambahkan.',
    'edit_success' => 'Data inventaris berhasil diperbarui.',
    'delete_success' => 'Item produk berhasil dihapus dari sistem inventaris.',
    'stock_updated' => 'Jumlah stok berhasil diperbarui.',
    'stock_unchanged' => 'Stok tidak berubah karena sudah mencapai batas minimum atau maksimum.',
    'stock_error' => 'Stok tidak dapat diperbarui. Muat ulang halaman dan coba lagi.'
];

$today = new DateTimeImmutable('today');
$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6faf7">
    <title>Sistem Penataan & Inventaris Apotek — SehatMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script>
        (function () {
            try {
                if (localStorage.getItem('sehatmart-dim') === 'true') {
                    document.documentElement.classList.add('dim-theme');
                }
            } catch (e) {}
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<nav class="pharmacy-nav sticky-top no-print">
    <div class="container pharmacy-nav-content">
        <a class="brand-mark" href="index.php">
            <span class="brand-icon"><i class="bi bi-box-seam"></i></span>
            <span>sehat<span class="brand-highlight">mart</span><small>SISTEM INVENTARIS APOTEK</small></span>
        </a>
        <div class="nav-actions">
            <button class="icon-button" type="button" id="themeToggleBtn" title="Ganti Tema Tampilan" aria-label="Ganti tema tampilan">
                <i class="bi bi-moon-stars" id="themeIcon"></i>
            </button>
            <a href="tambah.php" class="btn btn-primary-green">
                <i class="bi bi-plus-lg"></i> Tambah Item
            </a>
        </div>
    </div>
</nav>

<main>
    <!-- Executive Operations Summary -->
    <section class="inventory-hero" id="ringkasan">
        <div class="container">
            <div class="hero-title-area">
                <div>
                    <span class="eyebrow"><i class="bi bi-clipboard2-pulse"></i> Manajemen Logistik & Tata Kelola Obat</span>
                    <h1>Penataan Inventaris Apotek</h1>
                    <p>Monitoring stok fisik, masa kedaluwarsa produk, batas minimum pemesanan kembali (reorder point), dan valuasi aset farmasi secara real-time.</p>
                </div>
                <div class="hero-actions no-print">
                    <a href="tambah.php" class="btn btn-primary-green">
                        <i class="bi bi-plus-circle me-1"></i> Catat Barang Masuk
                    </a>
                </div>
            </div>

            <!-- KPI Metric Cards Grid -->
            <div class="kpi-grid">
                <a href="index.php?status_stok=semua#tabel-inventaris" class="kpi-card <?= $statusStok === 'semua' && $search === '' && $kategori === '' ? 'active' : ''; ?>">
                    <div class="kpi-header">
                        <span class="kpi-label">Total SKU Obat</span>
                        <span class="kpi-icon icon-green"><i class="bi bi-capsule"></i></span>
                    </div>
                    <div class="kpi-value" id="kpiTotalSku"><?= number_format((int) $kpi['total_sku'], 0, ',', '.'); ?></div>
                    <div class="kpi-hint"><i class="bi bi-check2-circle text-success"></i> Varian produk aktif</div>
                </a>

                <a href="index.php?status_stok=menipis#tabel-inventaris" class="kpi-card <?= $statusStok === 'menipis' ? 'active' : ''; ?>">
                    <div class="kpi-header">
                        <span class="kpi-label">Stok Menipis</span>
                        <span class="kpi-icon icon-amber"><i class="bi bi-exclamation-triangle"></i></span>
                    </div>
                    <div class="kpi-value text-warning" id="kpiTotalMenipis"><?= number_format((int) $kpi['total_menipis'], 0, ',', '.'); ?></div>
                    <div class="kpi-hint text-warning"><i class="bi bi-arrow-down-right"></i> &le; Batas minimum</div>
                </a>

                <a href="index.php?status_stok=habis#tabel-inventaris" class="kpi-card <?= $statusStok === 'habis' ? 'active' : ''; ?>">
                    <div class="kpi-header">
                        <span class="kpi-label">Stok Habis</span>
                        <span class="kpi-icon icon-coral"><i class="bi bi-x-octagon"></i></span>
                    </div>
                    <div class="kpi-value text-danger" id="kpiTotalHabis"><?= number_format((int) $kpi['total_habis'], 0, ',', '.'); ?></div>
                    <div class="kpi-hint text-danger"><i class="bi bi-dash-circle"></i> Perlu restock segera</div>
                </a>

                <a href="index.php?status_stok=segera_kedaluwarsa#tabel-inventaris" class="kpi-card <?= $statusStok === 'segera_kedaluwarsa' ? 'active' : ''; ?>">
                    <div class="kpi-header">
                        <span class="kpi-label">Alert Kedaluwarsa</span>
                        <span class="kpi-icon icon-amber"><i class="bi bi-clock-history"></i></span>
                    </div>
                    <div class="kpi-value text-danger" id="kpiTotalExp"><?= number_format((int) $kpi['total_exp_alert'], 0, ',', '.'); ?></div>
                    <div class="kpi-hint text-danger"><i class="bi bi-bell"></i> &le; 90 hari / Expired</div>
                </a>

                <div class="kpi-card">
                    <div class="kpi-header">
                        <span class="kpi-label">Total Fisik Stok</span>
                        <span class="kpi-icon icon-blue"><i class="bi bi-boxes"></i></span>
                    </div>
                    <div class="kpi-value" id="kpiTotalUnit"><?= number_format((int) $kpi['total_unit'], 0, ',', '.'); ?></div>
                    <div class="kpi-hint"><i class="bi bi-archive text-info"></i> Seluruh unit di gudang</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-header">
                        <span class="kpi-label">Estimasi Nilai Aset</span>
                        <span class="kpi-icon icon-green"><i class="bi bi-cash-stack"></i></span>
                    </div>
                    <div class="kpi-value" id="kpiNilaiAset" style="font-size: 1.25rem;">
                        Rp <?= number_format((int) $kpi['total_nilai_aset'], 0, ',', '.'); ?>
                    </div>
                    <div class="kpi-hint"><i class="bi bi-graph-up text-success"></i> Valuasi persediaan</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Inventory Section -->
    <section class="section-space" id="tabel-inventaris">
        <div class="container">
            
            <!-- Quick Filter Tab Pills (No Print) -->
            <div class="filter-pills no-print">
                <a href="index.php?status_stok=semua#tabel-inventaris" class="pill-tab <?= $statusStok === 'semua' ? 'active' : ''; ?>">
                    <i class="bi bi-grid-fill"></i> Semua Obat <span class="pill-badge" id="pillTotalSku"><?= (int) $kpi['total_sku']; ?></span>
                </a>
                <a href="index.php?status_stok=aman#tabel-inventaris" class="pill-tab <?= $statusStok === 'aman' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle-fill text-success"></i> Stok Aman
                </a>
                <a href="index.php?status_stok=menipis#tabel-inventaris" class="pill-tab <?= $statusStok === 'menipis' ? 'active' : ''; ?>">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i> Perlu Reorder <span class="pill-badge" id="pillTotalMenipis"><?= (int) $kpi['total_menipis']; ?></span>
                </a>
                <a href="index.php?status_stok=habis#tabel-inventaris" class="pill-tab <?= $statusStok === 'habis' ? 'active' : ''; ?>">
                    <i class="bi bi-x-circle-fill text-danger"></i> Stok Kosong <span class="pill-badge" id="pillTotalHabis"><?= (int) $kpi['total_habis']; ?></span>
                </a>
                <a href="index.php?status_stok=segera_kedaluwarsa#tabel-inventaris" class="pill-tab <?= $statusStok === 'segera_kedaluwarsa' ? 'active' : ''; ?>">
                    <i class="bi bi-hourglass-split text-danger"></i> Dekat Expired <span class="pill-badge" id="pillTotalExp"><?= (int) $kpi['total_exp_alert']; ?></span>
                </a>
            </div>

            <!-- Inventory Search & Filter Toolbar -->
            <div class="inventory-toolbar no-print">
                <form method="GET" action="index.php#tabel-inventaris" class="toolbar-form" role="search">
                    <div class="search-input-wrap">
                        <i class="bi bi-search"></i>
                        <input type="search" name="search" placeholder="Cari nama obat atau merek pabrik..." value="<?= $escape($search); ?>">
                    </div>

                    <div>
                        <select name="kategori" class="form-select form-select-sm">
                            <option value="">Semua Kategori Farmasi</option>
                            <?php foreach ($allowedCategories as $cat): ?>
                                <option value="<?= $escape($cat); ?>" <?= $kategori === $cat ? 'selected' : ''; ?>><?= $escape($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <select name="status_stok" class="form-select form-select-sm">
                            <option value="semua" <?= $statusStok === 'semua' ? 'selected' : ''; ?>>Semua Level Stok</option>
                            <option value="aman" <?= $statusStok === 'aman' ? 'selected' : ''; ?>>Stok Aman (> Min)</option>
                            <option value="menipis" <?= $statusStok === 'menipis' ? 'selected' : ''; ?>>Stok Menipis (&le; Min)</option>
                            <option value="habis" <?= $statusStok === 'habis' ? 'selected' : ''; ?>>Stok Habis (0 Unit)</option>
                            <option value="segera_kedaluwarsa" <?= $statusStok === 'segera_kedaluwarsa' ? 'selected' : ''; ?>>Mendekati Kedaluwarsa</option>
                            <option value="kedaluwarsa" <?= $statusStok === 'kedaluwarsa' ? 'selected' : ''; ?>>Sudah Kedaluwarsa</option>
                        </select>
                    </div>

                    <div>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="terbaru" <?= $sort === 'terbaru' ? 'selected' : ''; ?>>Urutan: Terbaru</option>
                            <option value="nama_asc" <?= $sort === 'nama_asc' ? 'selected' : ''; ?>>Nama Obat (A - Z)</option>
                            <option value="stok_asc" <?= $sort === 'stok_asc' ? 'selected' : ''; ?>>Stok Terendah (Reorder)</option>
                            <option value="stok_desc" <?= $sort === 'stok_desc' ? 'selected' : ''; ?>>Stok Tertinggi</option>
                            <option value="exp_asc" <?= $sort === 'exp_asc' ? 'selected' : ''; ?>>Expired Terdekat</option>
                            <option value="harga_desc" <?= $sort === 'harga_desc' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary-green btn-sm px-3 btn-apply-filter">
                            <i class="bi bi-funnel"></i> Terapkan
                        </button>
                        <?php if ($search !== '' || $kategori !== '' || $statusStok !== 'semua' || $sort !== 'terbaru'): ?>
                            <a href="index.php#tabel-inventaris" class="btn btn-outline-secondary btn-sm btn-reset-filter" title="Reset filter" aria-label="Reset filter">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Table Container -->
            <div class="inventory-table-container">
                <div class="table-header-meta">
                    <div>
                        Menampilkan <strong><?= count($items); ?></strong> item terdata
                        <?php if ($search !== ''): ?> untuk pencarian "<strong><?= $escape($search); ?></strong>"<?php endif; ?>
                        <?php if ($kategori !== ''): ?> dalam kategori "<strong><?= $escape($kategori); ?></strong>"<?php endif; ?>
                        <?php if ($statusStok !== 'semua'): ?> dengan status "<strong><?= $escape(str_replace('_', ' ', $statusStok)); ?></strong>"<?php endif; ?>
                    </div>
                    <div class="table-meta-tip">
                        <span><i class="bi bi-info-circle text-success me-1"></i> Klik nama item untuk melihat deskripsi & catatan rak.</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table inventory-table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center">No</th>
                                <th>Produk & Merek</th>
                                <th>Kategori</th>
                                <th>Level Stok & Status</th>
                                <th>Harga Beli / Jual</th>
                                <th>Masa Kedaluwarsa</th>
                                <th class="text-end no-print" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($items): ?>
                                <?php $no = 1; foreach ($items as $item): ?>
                                    <?php
                                    $stok = (int) $item['stok'];
                                    $stokMin = isset($item['stok_minimum']) ? (int) $item['stok_minimum'] : 5;
                                    $satuan = $item['satuan'] ?? 'pcs';
                                    $hargaBeli = isset($item['harga_beli']) ? (int) $item['harga_beli'] : 0;
                                    $hargaJual = (int) $item['harga'];
                                    $expDate = $item['tanggal_kedaluwarsa'] ?? null;

                                    // Evaluasi Status Stok
                                    if ($stok === 0) {
                                        $stockClass = 'stock-badge-danger';
                                        $stockLabel = 'Stok Habis';
                                        $stockIcon = 'bi-x-circle-fill';
                                    } elseif ($stok <= $stokMin) {
                                        $stockClass = 'stock-badge-warn';
                                        $stockLabel = 'Perlu Restock';
                                        $stockIcon = 'bi-exclamation-triangle-fill';
                                    } else {
                                        $stockClass = 'stock-badge-safe';
                                        $stockLabel = 'Stok Aman';
                                        $stockIcon = 'bi-check-circle-fill';
                                    }

                                    // Evaluasi Status Kedaluwarsa
                                    $expBadge = '';
                                    if ($expDate) {
                                        try {
                                            $expObj = new DateTimeImmutable($expDate);
                                            $diff = $today->diff($expObj);
                                            $isExpired = $expObj < $today;
                                            $daysRemaining = (int) $diff->format('%r%a');

                                            if ($isExpired) {
                                                $expBadge = '<span class="exp-badge exp-danger"><i class="bi bi-x-octagon-fill"></i> Kadaluarsa (' . $expObj->format('d/m/Y') . ')</span>';
                                            } elseif ($daysRemaining <= 90) {
                                                $expBadge = '<span class="exp-badge exp-warning"><i class="bi bi-alarm-fill"></i> ' . $daysRemaining . ' hari (' . $expObj->format('d/m/Y') . ')</span>';
                                            } else {
                                                $expBadge = '<span class="exp-badge exp-safe"><i class="bi bi-calendar-check"></i> ' . $expObj->format('d M Y') . '</span>';
                                            }
                                        } catch (Exception $e) {
                                            $expBadge = '<span class="text-muted small">-</span>';
                                        }
                                    } else {
                                        $expBadge = '<span class="text-muted small">Non-expired</span>';
                                    }
                                    ?>
                                    <tr id="item-row-<?= (int) $item['id']; ?>" data-item-id="<?= (int) $item['id']; ?>">
                                        <td class="text-center text-muted small"><?= $no++; ?></td>
                                        <td>
                                            <div class="product-cell">
                                                <a href="javascript:void(0)" class="product-name item-detail-trigger" id="detail-trigger-<?= (int) $item['id']; ?>" onclick='showItemDetail(<?= json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                    <?= $escape($item['nama_menu']); ?>
                                                </a>
                                                <span class="product-brand">
                                                    <?php if (!empty($item['merek'])): ?>
                                                        <?= $escape($item['merek']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Generik / Tanpa Merek</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="table-category"><?= $escape($item['kategori']); ?></span>
                                        </td>
                                        <td>
                                            <div class="stock-wrap"
                                                 data-id="<?= (int) $item['id']; ?>"
                                                 data-nama="<?= $escape($item['nama_menu']); ?>"
                                                 data-satuan="<?= $escape($satuan); ?>"
                                                 data-stok-min="<?= $stokMin; ?>"
                                                 data-initial-stok="<?= $stok; ?>"
                                                 data-kategori="<?= $escape($item['kategori']); ?>">
                                                <div class="stock-level">
                                                    <span class="stock-number"><?= number_format($stok, 0, ',', '.'); ?></span>
                                                    <small class="stock-unit"><?= $escape($satuan); ?></small>
                                                    <span class="stock-delta-badge d-none" aria-live="polite"></span>
                                                </div>
                                                <span class="stock-badge <?= $stockClass; ?>">
                                                    <i class="bi <?= $stockIcon; ?>"></i> <span class="stock-badge-text"><?= $stockLabel; ?></span>
                                                </span>
                                                <small class="text-muted stock-min-label" style="font-size: .68rem;">Min: <?= $stokMin; ?> <?= $escape($satuan); ?></small>
                                                
                                                <div class="stock-adjust-controls no-print" aria-label="Ubah stok <?= $escape($item['nama_menu']); ?>">
                                                    <button type="button" class="stock-adjust-btn stock-btn-minus" title="Kurangi stok" aria-label="Kurangi satu <?= $escape($satuan); ?> untuk <?= $escape($item['nama_menu']); ?>" <?= $stok === 0 ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-dash-lg"></i>
                                                    </button>
                                                    <button type="button" class="stock-adjust-btn stock-btn-plus" title="Tambah stok" aria-label="Tambah satu <?= $escape($satuan); ?> untuk <?= $escape($item['nama_menu']); ?>" <?= $stok >= 2147483647 ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                </div>

                                                <div class="stock-verify-controls no-print d-none">
                                                    <button type="button" class="stock-verify-btn" title="Verifikasi apakah jumlah fisik stok sudah sesuai">
                                                        <i class="bi bi-check2"></i> Verifikasi
                                                    </button>
                                                    <button type="button" class="stock-cancel-btn" title="Batalkan penyesuaian">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="price-cell">
                                                <span class="price-sell">Jual: Rp <?= number_format($hargaJual, 0, ',', '.'); ?></span>
                                                <span class="price-buy">HPP: Rp <?= number_format($hargaBeli, 0, ',', '.'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $expBadge; ?>
                                        </td>
                                        <td class="text-end no-print">
                                            <div class="action-buttons">
                                                <button type="button" class="table-action detail-action" id="detail-btn-<?= (int) $item['id']; ?>" title="Lihat Detail & Lokasi Rak" onclick='showItemDetail(<?= json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' aria-label="Lihat detail obat">
                                                    <i class="bi bi-info-circle"></i>
                                                </button>
                                                <a href="edit.php?id=<?= (int) $item['id']; ?>" class="table-action edit-action" title="Edit Data Inventaris" aria-label="Edit <?= $escape($item['nama_menu']); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="table-action delete-action" title="Hapus Produk" onclick="confirmDelete(<?= (int) $item['id']; ?>, '<?= $escape($item['nama_menu']); ?>')" aria-label="Hapus <?= $escape($item['nama_menu']); ?>">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-0">
                                        <div class="inventory-empty">
                                            <div class="inventory-empty-icon"><i class="bi bi-inbox"></i></div>
                                            <h3>Tidak Ada Data Inventaris</h3>
                                            <p>Tidak ditemukan produk obat yang cocok dengan filter atau kriteria pencarian saat ini.</p>
                                            <a href="index.php#tabel-inventaris" class="btn btn-outline-green btn-sm me-2">
                                                <i class="bi bi-arrow-clockwise me-1"></i> Tampilkan Semua
                                            </a>
                                            <a href="tambah.php" class="btn btn-primary-green btn-sm">
                                                <i class="bi bi-plus-lg me-1"></i> Tambah Produk Baru
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal Detail Item & Catatan Penyimpanan Rak -->
<div class="modal fade" id="itemDetailModal" tabindex="-1" aria-labelledby="itemDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-bottom">
                <div>
                    <span class="eyebrow" id="detailKategori">KATEGORI OBAT</span>
                    <h2 class="modal-title h5 mb-0 fw-bold" id="detailNama">Nama Produk</h2>
                </div>
                <button type="button" class="modal-close-button" data-bs-dismiss="modal" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Merek / Pabrik</small>
                        <strong id="detailMerek">-</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Stok Saat Ini</small>
                        <strong class="detail-stock-value h5 mb-0" id="detailStok">-</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Batas Reorder Minimum</small>
                        <strong id="detailMinStok">-</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Harga Beli (HPP)</small>
                        <strong id="detailHargaBeli">-</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Harga Jual Satuan</small>
                        <strong class="detail-price-value" id="detailHargaJual">-</strong>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Tanggal Kedaluwarsa</small>
                        <div id="detailExp">-</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Deskripsi / Catatan Rak Penyimpanan</small>
                        <div class="detail-note-box p-3 rounded mt-1 border" id="detailDeskripsi" style="font-size: .88rem; white-space: pre-wrap;">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="detailEditBtn" class="btn btn-primary-green btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Data Ini
                </a>
            </div>
        </div>
    </div>
</div>

<footer class="site-footer no-print">
    <div class="container footer-content">
        <div class="brand-mark">
            <span class="brand-icon"><i class="bi bi-box-seam"></i></span>
            <span>sehat<span class="brand-highlight">mart</span><small>SISTEM INVENTARIS APOTEK</small></span>
        </div>
        <p>Sistem Pengendalian Stok & Pergudangan Farmasi.</p>
        <span>&copy; <?= date('Y'); ?> SehatMart Inventaris. Hak Cipta Dilindungi.</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const currency = new Intl.NumberFormat('id-ID');
const numFormat = new Intl.NumberFormat('id-ID');
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']); ?>;

// Konfirmasi Hapus Produk
function confirmDelete(id, nama) {
    Swal.fire({
        title: 'Hapus dari Inventaris?',
        text: `Data "${nama}" akan dihapus permanen dari sistem stok obat.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e2634d',
        cancelButtonColor: '#738078',
        confirmButtonText: 'Ya, Hapus Data',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `hapus.php?id=${id}`;
        }
    });
}

// Modal Detail Item
const detailModal = new bootstrap.Modal(document.getElementById('itemDetailModal'));
function showItemDetail(item) {
    document.getElementById('detailNama').textContent = item.nama_menu || '-';
    document.getElementById('detailKategori').textContent = item.kategori || 'OBAT';
    document.getElementById('detailMerek').textContent = item.merek || 'Generik / Tanpa Merek';
    document.getElementById('detailStok').textContent = `${Number(item.stok).toLocaleString('id-ID')} ${item.satuan || 'pcs'}`;
    document.getElementById('detailMinStok').textContent = `${Number(item.stok_minimum || 5).toLocaleString('id-ID')} ${item.satuan || 'pcs'}`;
    document.getElementById('detailHargaBeli').textContent = `Rp ${currency.format(Number(item.harga_beli || 0))}`;
    document.getElementById('detailHargaJual').textContent = `Rp ${currency.format(Number(item.harga || 0))}`;
    document.getElementById('detailExp').textContent = item.tanggal_kedaluwarsa ? item.tanggal_kedaluwarsa : 'Tidak ada tanggal kedaluwarsa';
    document.getElementById('detailDeskripsi').textContent = item.deskripsi ? item.deskripsi : 'Tidak ada catatan penyimpanan.';
    document.getElementById('detailEditBtn').href = `edit.php?id=${item.id}`;
    detailModal.show();
}

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

themeToggleBtn.addEventListener('click', () => {
    const isNowDim = !document.body.classList.contains('dim-theme');
    applyTheme(isNowDim);
    localStorage.setItem('sehatmart-dim', String(isNowDim));
});

// =========================================================================
// Multi-Step Interactive Stock Adjustment with Verification Workflow
// =========================================================================
(function initStockAdjustment() {
    const stockMap = new Map();

    function getStockBadgeInfo(stok, minStok) {
        if (stok === 0) {
            return {
                className: 'stock-badge-danger',
                icon: 'bi-x-circle-fill',
                label: 'Stok Habis'
            };
        }
        if (stok <= minStok) {
            return {
                className: 'stock-badge-warn',
                icon: 'bi-exclamation-triangle-fill',
                label: 'Perlu Restock'
            };
        }
        return {
            className: 'stock-badge-safe',
            icon: 'bi-check-circle-fill',
            label: 'Stok Aman'
        };
    }

    function renderStockUI(state) {
        const { wrapEl, stagedStok, initialStok, stokMin, satuan } = state;
        const delta = stagedStok - initialStok;
        state.delta = delta;

        const numberEl = wrapEl.querySelector('.stock-number');
        if (numberEl) {
            numberEl.textContent = numFormat.format(stagedStok);
        }

        const deltaBadge = wrapEl.querySelector('.stock-delta-badge');
        if (deltaBadge) {
            if (delta !== 0) {
                deltaBadge.classList.remove('d-none');
                deltaBadge.textContent = (delta > 0 ? `+${delta}` : `${delta}`) + ` ${satuan}`;
                deltaBadge.className = 'stock-delta-badge ' + (delta > 0 ? 'delta-pos' : 'delta-neg');
            } else {
                deltaBadge.classList.add('d-none');
            }
        }

        const badgeEl = wrapEl.querySelector('.stock-badge');
        const badgeTextEl = wrapEl.querySelector('.stock-badge-text');
        const badgeIconEl = badgeEl ? badgeEl.querySelector('i') : null;
        if (badgeEl) {
            const badgeInfo = getStockBadgeInfo(stagedStok, stokMin);
            badgeEl.classList.remove('stock-badge-safe', 'stock-badge-warn', 'stock-badge-danger');
            badgeEl.classList.add(badgeInfo.className);
            if (badgeTextEl) badgeTextEl.textContent = badgeInfo.label;
            if (badgeIconEl) {
                badgeIconEl.className = 'bi ' + badgeInfo.icon;
            }
        }

        const minusBtn = wrapEl.querySelector('.stock-btn-minus');
        if (minusBtn) {
            minusBtn.disabled = stagedStok <= 0;
        }

        const verifyGroup = wrapEl.querySelector('.stock-verify-controls');
        if (verifyGroup) {
            if (delta !== 0) {
                verifyGroup.classList.remove('d-none');
            } else {
                verifyGroup.classList.add('d-none');
            }
        }
    }

    document.querySelectorAll('.stock-wrap').forEach((wrapEl) => {
        const id = parseInt(wrapEl.dataset.id, 10);
        const initialStok = parseInt(wrapEl.dataset.initialStok, 10) || 0;
        const stokMin = parseInt(wrapEl.dataset.stokMin, 10) || 5;
        const satuan = wrapEl.dataset.satuan || 'pcs';
        const nama = wrapEl.dataset.nama || 'Produk';
        const kategori = wrapEl.dataset.kategori || 'Obat';

        const state = {
            id,
            initialStok,
            stagedStok: initialStok,
            delta: 0,
            stokMin,
            satuan,
            nama,
            kategori,
            wrapEl
        };

        stockMap.set(id, state);

        const plusBtn = wrapEl.querySelector('.stock-btn-plus');
        if (plusBtn) {
            plusBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (state.stagedStok < 2147483647) {
                    state.stagedStok += 1;
                    renderStockUI(state);
                }
            });
        }

        const minusBtn = wrapEl.querySelector('.stock-btn-minus');
        if (minusBtn) {
            minusBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (state.stagedStok > 0) {
                    state.stagedStok -= 1;
                    renderStockUI(state);
                }
            });
        }

        const cancelBtn = wrapEl.querySelector('.stock-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                state.stagedStok = state.initialStok;
                renderStockUI(state);
            });
        }

        const numberEl = wrapEl.querySelector('.stock-number');
        if (numberEl) {
            numberEl.style.cursor = 'pointer';
            numberEl.title = 'Klik untuk input angka langsung';
            numberEl.addEventListener('click', async () => {
                const { value: inputVal } = await Swal.fire({
                    title: `Input Stok: ${state.nama}`,
                    text: `Masukkan jumlah stok fisik baru (${state.satuan}):`,
                    input: 'number',
                    inputValue: state.stagedStok,
                    inputAttributes: {
                        min: 0,
                        max: 2147483647,
                        step: 1
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Terapkan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#059669',
                    inputValidator: (val) => {
                        if (val === '' || isNaN(val) || parseInt(val, 10) < 0) {
                            return 'Jumlah stok harus angka 0 atau lebih besar!';
                        }
                    }
                });

                if (inputVal !== undefined && inputVal !== null) {
                    const parsed = parseInt(inputVal, 10);
                    if (!isNaN(parsed) && parsed >= 0) {
                        state.stagedStok = parsed;
                        renderStockUI(state);
                    }
                }
            });
        }

        const verifyBtn = wrapEl.querySelector('.stock-verify-btn');
        if (verifyBtn) {
            verifyBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                await promptVerification(state);
            });
        }
    });

    async function promptVerification(state) {
        const diff = state.delta;
        const diffBadge = diff > 0
            ? `<span class="badge" style="background-color: #059669; font-size: 0.85rem; padding: 4px 8px;">+${diff} ${state.satuan}</span>`
            : `<span class="badge" style="background-color: #e11d48; font-size: 0.85rem; padding: 4px 8px;">${diff} ${state.satuan}</span>`;

        const result = await Swal.fire({
            title: 'Verifikasi Penyesuaian Stok',
            html: `
                <div class="text-start">
                    <p class="mb-3 text-muted" style="font-size: 0.9rem;">
                        Periksa ketersediaan fisik obat di rak apotek sebelum memverifikasi dan menyimpan data:
                    </p>
                    <div class="p-3 mb-3 rounded" style="background: rgba(5, 150, 105, 0.08); border-left: 4px solid #059669;">
                        <div class="fw-bold fs-6" style="color: #047857;">${state.nama}</div>
                        <small class="text-muted">Kategori: ${state.kategori} &bull; Batas Minimum: ${state.stokMin} ${state.satuan}</small>
                    </div>
                    <div class="p-3 rounded border mb-3" style="background: rgba(0, 0, 0, 0.02);">
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="text-muted">Stok Sebelumnya:</span>
                            <span class="fw-semibold">${numFormat.format(state.initialStok)} ${state.satuan}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="text-muted">Perubahan (Penyesuaian):</span>
                            <div>${diffBadge}</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                            <span class="fw-bold">Stok Akhir Baru:</span>
                            <strong class="fs-5" style="color: #059669;">${numFormat.format(state.stagedStok)} ${state.satuan}</strong>
                        </div>
                    </div>
                    <div class="text-center text-muted small mt-2">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Apakah jumlah fisik di rak apotek sudah sesuai dengan <strong>${numFormat.format(state.stagedStok)} ${state.satuan}</strong>?
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bi bi-check2-circle me-1"></i> Ya, Sudah Sesuai (Simpan)',
            cancelButtonText: 'Batal / Cek Lagi',
            reverseButtons: true,
            focusConfirm: true
        });

        if (result.isConfirmed) {
            await commitStock(state);
        }
    }

    async function commitStock(state) {
        const verifyBtn = state.wrapEl.querySelector('.stock-verify-btn');
        const cancelBtn = state.wrapEl.querySelector('.stock-cancel-btn');
        const originalVerifyContent = verifyBtn ? verifyBtn.innerHTML : 'Verifikasi';

        if (verifyBtn) {
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyimpan...';
        }
        if (cancelBtn) cancelBtn.disabled = true;

        try {
            const formData = new URLSearchParams();
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('id', String(state.id));
            formData.append('stock_action', 'set_stock');
            formData.append('new_stock', String(state.stagedStok));
            formData.append('delta', String(state.delta));
            formData.append('is_ajax', '1');

            const response = await fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            });

            const result = await response.json();

            if (response.ok && result.success) {
                state.initialStok = state.stagedStok;
                state.delta = 0;
                state.wrapEl.dataset.initialStok = String(state.stagedStok);

                renderStockUI(state);

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: result.message || 'Stok berhasil diverifikasi & disimpan!',
                    showConfirmButton: false,
                    timer: 2300,
                    timerProgressBar: true
                });

                if (result.data?.item) {
                    const updatedJson = JSON.stringify(result.data.item);
                    const detailTrigger = document.getElementById(`detail-trigger-${state.id}`);
                    const detailBtn = document.getElementById(`detail-btn-${state.id}`);
                    if (detailTrigger) detailTrigger.setAttribute('onclick', `showItemDetail(${updatedJson})`);
                    if (detailBtn) detailBtn.setAttribute('onclick', `showItemDetail(${updatedJson})`);
                }

                if (result.data?.kpi) {
                    const kpi = result.data.kpi;
                    const setEl = (id, val) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = val;
                    };
                    setEl('kpiTotalSku', numFormat.format(kpi.total_sku));
                    setEl('kpiTotalMenipis', numFormat.format(kpi.total_menipis));
                    setEl('kpiTotalHabis', numFormat.format(kpi.total_habis));
                    setEl('kpiTotalExp', numFormat.format(kpi.total_exp_alert));
                    setEl('kpiTotalUnit', numFormat.format(kpi.total_unit));
                    setEl('kpiNilaiAset', `Rp ${numFormat.format(kpi.total_nilai_aset)}`);
                    setEl('pillTotalSku', String(kpi.total_sku));
                    setEl('pillTotalMenipis', String(kpi.total_menipis));
                    setEl('pillTotalHabis', String(kpi.total_habis));
                    setEl('pillTotalExp', String(kpi.total_exp_alert));
                }
            } else {
                throw new Error(result.message || 'Gagal menyimpan penyesuaian stok.');
            }
        } catch (err) {
            Swal.fire({
                title: 'Gagal Menyimpan Stok',
                text: err.message || 'Terjadi gangguan jaringan atau sesi kedaluwarsa.',
                icon: 'error',
                confirmButtonColor: '#059669'
            });
            renderStockUI(state);
        } finally {
            if (verifyBtn) {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = originalVerifyContent;
            }
            if (cancelBtn) cancelBtn.disabled = false;
        }
    }

    window.addEventListener('beforeunload', (e) => {
        let hasUnsaved = false;
        for (const s of stockMap.values()) {
            if (s.delta !== 0) {
                hasUnsaved = true;
                break;
            }
        }
        if (hasUnsaved) {
            e.preventDefault();
            e.returnValue = 'Terdapat penyesuaian stok yang belum diverifikasi. Yakin ingin meninggalkan halaman?';
            return e.returnValue;
        }
    });
})();

// Alert Notifikasi Operasi
<?php if (isset($statusMessages[$alertStatus])): ?>
Swal.fire({
    title: <?= json_encode($alertStatus === 'stock_error' ? 'Gagal!' : ($alertStatus === 'stock_unchanged' ? 'Stok Tidak Berubah' : 'Berhasil!')); ?>,
    text: <?= json_encode($statusMessages[$alertStatus]); ?>,
    icon: <?= json_encode($alertStatus === 'stock_error' ? 'error' : ($alertStatus === 'stock_unchanged' ? 'warning' : 'success')); ?>,
    confirmButtonColor: '#267b58'
});
<?php endif; ?>
</script>
</body>
</html>
