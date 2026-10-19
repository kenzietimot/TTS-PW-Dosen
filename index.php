<?php
require_once 'koneksi.php';

$searchInput = $_GET['search'] ?? '';
$categoryInput = $_GET['kategori'] ?? '';
$statusInput = $_GET['status'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$kategori = is_string($categoryInput) ? trim($categoryInput) : '';
$status = is_string($statusInput) ? $statusInput : '';
$allowedCategories = [
    'Obat Bebas',
    'Obat Bebas Terbatas',
    'Obat Keras',
    'Obat Herbal Terstandar',
    'Obat Tradisional',
    'Narkotika'
];

$sql = "SELECT * FROM menu WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND nama_menu LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($kategori !== '' && in_array($kategori, $allowedCategories, true)) {
    $sql .= " AND kategori = ?";
    $params[] = $kategori;
    $types .= 's';
} else {
    $kategori = '';
}

$sql .= ' ORDER BY id DESC';
$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusMessages = [
    'add_success' => 'Produk berhasil ditambahkan.',
    'edit_success' => 'Produk berhasil diperbarui.',
    'delete_success' => 'Produk berhasil dihapus.'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6faf7">
    <title>SehatMart — Apotek Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light sticky-top pharmacy-nav">
    <div class="container">
        <a class="navbar-brand brand-mark" href="index.php">
            <span class="brand-icon"><i class="bi bi-plus-lg"></i></span>
            <span>sehat<span class="brand-highlight">mart</span><small>APOTEK ONLINE</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav mx-auto nav-links">
                <li class="nav-item"><a class="nav-link" href="#produk">Produk</a></li>
                <li class="nav-item"><a class="nav-link" href="#cara-belanja">Cara belanja</a></li>
                <li class="nav-item"><a class="nav-link" href="#kontak">Kontak</a></li>
            </ul>
            <div class="nav-actions">
                <button class="icon-button" type="button" data-bs-toggle="modal" data-bs-target="#settingsModal" aria-label="Pengaturan"><i class="bi bi-sliders"></i></button>
                <button class="icon-button cart-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartPanel" aria-label="Buka keranjang">
                    <i class="bi bi-bag"></i><span class="cart-count" id="cartCount">0</span>
                </button>
                <button class="account-button" type="button" data-bs-toggle="modal" data-bs-target="#accountModal"><i class="bi bi-person-circle"></i><span id="accountLabel">Akun</span></button>
            </div>
        </div>
    </div>
</nav>

<main>
    <section class="hero-section">
        <div class="container hero-content">
            <div class="hero-copy">
                <span class="eyebrow"><i class="bi bi-patch-check-fill"></i> Teman sehat setiap hari</span>
                <h1>Sehat dimulai dari <span>pilihan yang tepat.</span></h1>
                <p>Temukan kebutuhan kesehatan harianmu dengan mudah. Pilih produk, cek informasi, dan belanja dari rumah.</p>
                <a class="btn btn-primary-green" href="#produk">Jelajahi produk <i class="bi bi-arrow-down-right"></i></a>
                <div class="hero-note"><i class="bi bi-shield-check"></i> Gunakan obat sesuai aturan pakai. Konsultasikan obat resep dengan apoteker.</div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
                <div class="art-plus plus-one">+</div><div class="art-plus plus-two">+</div>
                <div class="medicine-bottle"><div class="bottle-cap"></div><div class="bottle-label"><span>daily</span><strong>CARE</strong><i class="bi bi-heart-pulse-fill"></i></div></div>
                <div class="floating-pill pill-one"></div><div class="floating-pill pill-two"></div>
                <div class="hero-sticker"><i class="bi bi-heart-pulse"></i><span>Rawat diri,<br>mulai hari ini</span></div>
            </div>
        </div>
    </section>

    <section class="trust-strip" aria-label="Informasi layanan">
        <div class="container trust-items">
            <div><span class="trust-icon"><i class="bi bi-capsule"></i></span><span><strong>Pilihan produk</strong><small>Kebutuhan kesehatan harian</small></span></div>
            <div><span class="trust-icon"><i class="bi bi-search-heart"></i></span><span><strong>Cari dengan mudah</strong><small>Temukan produk yang dibutuhkan</small></span></div>
            <div><span class="trust-icon"><i class="bi bi-chat-heart"></i></span><span><strong>Utamakan keamanan</strong><small>Baca label dan aturan pakai</small></span></div>
        </div>
    </section>

    <section class="catalog-section section-space" id="produk">
        <div class="container">
            <div class="section-heading">
                <div><span class="eyebrow">PILIHAN UNTUKMU</span><h2>Produk kesehatan</h2><p>Jelajahi pilihan produk atau cari yang sedang kamu butuhkan.</p></div>
                <a href="tambah.php" class="btn btn-outline-green admin-add"><i class="bi bi-plus-lg"></i> Tambah produk</a>
            </div>

            <form method="GET" action="index.php#produk" class="search-panel" role="search">
                <label class="visually-hidden" for="searchInput">Cari nama obat atau produk</label>
                <i class="bi bi-search search-icon"></i>
                <input id="searchInput" type="search" name="search" placeholder="Cari nama obat atau produk..." value="<?= $escape($search); ?>">
                <label class="visually-hidden" for="categorySelect">Pilih kategori</label>
                <select id="categorySelect" name="kategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($allowedCategories as $category): ?>
                        <option value="<?= $escape($category); ?>" <?= $kategori === $category ? 'selected' : ''; ?>><?= $escape($category); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary-green">Cari produk</button>
                <?php if ($search !== '' || $kategori !== ''): ?>
                    <a class="clear-search" href="index.php#produk" aria-label="Hapus pencarian"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>

            <div class="catalog-meta">
                <span><?= $search !== '' || $kategori !== '' ? 'Hasil pencarian' : 'Produk pilihan' ?> <strong><?= count($products); ?></strong></span>
                <span><i class="bi bi-info-circle"></i> Harga dan stok dapat berubah</span>
            </div>

            <?php if ($products): ?>
                <div class="product-grid">
                    <?php foreach (array_slice($products, 0, 8) as $product): ?>
                        <article class="product-card">
                            <div class="product-visual">
                                <span class="product-category"><?= $escape($product['kategori']); ?></span>
                                <div class="product-symbol"><i class="bi bi-capsule"></i></div>
                                <span class="product-stock <?= (int) $product['stok'] > 0 ? '' : 'out-of-stock'; ?>">
                                    <?= (int) $product['stok'] > 0 ? 'Tersedia' : 'Stok habis'; ?>
                                </span>
                            </div>
                            <div class="product-info">
                                <h3><?= $escape($product['nama_menu']); ?></h3>
                                <p><?= (int) $product['stok'] > 0 ? 'Produk kesehatan pilihan untuk kebutuhanmu.' : 'Produk sedang tidak tersedia.'; ?></p>
                                <div class="product-buy">
                                    <strong>Rp <?= number_format((int) $product['harga'], 0, ',', '.'); ?></strong>
                                    <button class="add-to-cart" type="button" aria-label="Tambah <?= $escape($product['nama_menu']); ?> ke keranjang" data-id="<?= (int) $product['id']; ?>" data-name="<?= $escape($product['nama_menu']); ?>" data-price="<?= (int) $product['harga']; ?>" <?= (int) $product['stok'] <= 0 ? 'disabled' : ''; ?>><i class="bi bi-plus-lg"></i></button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><span><i class="bi bi-search"></i></span><h3>Produk belum ditemukan</h3><p>Coba kata kunci lain atau hapus filter pencarian.</p><a href="index.php#produk" class="btn btn-outline-green">Lihat semua produk</a></div>
            <?php endif; ?>

            <?php if (count($products) > 8): ?>
                <p class="more-products">Menampilkan 8 dari <?= count($products); ?> hasil. Gunakan pencarian untuk menemukan produk lainnya.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="safety-section section-space" id="cara-belanja">
        <div class="container safety-card">
            <div class="safety-icon"><i class="bi bi-prescription2"></i></div>
            <div><span class="eyebrow">BELANJA DENGAN BIJAK</span><h2>Kesehatanmu, prioritas kami.</h2><p>Periksa kemasan, tanggal kedaluwarsa, dan aturan pakai sebelum menggunakan obat. Untuk obat resep, ikuti petunjuk dokter atau apoteker.</p></div>
            <a href="#kontak" class="btn btn-light-green">Info selengkapnya <i class="bi bi-arrow-right"></i></a>
        </div>
    </section>

    <section class="inventory-section section-space" id="kelola-produk">
        <div class="container">
            <div class="section-heading inventory-heading">
                <div><span class="eyebrow">PENGELOLAAN</span><h2>Daftar inventaris</h2><p>Kelola informasi produk yang tampil di katalog.</p></div>
                <a href="tambah.php" class="btn btn-primary-green"><i class="bi bi-plus-lg"></i> Tambah produk</a>
            </div>
            <div class="inventory-card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Nama produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            <?php if ($products): foreach ($products as $product): ?>
                                <tr>
                                    <td class="inventory-name"><?= $escape($product['nama_menu']); ?></td>
                                    <td><span class="table-category"><?= $escape($product['kategori']); ?></span></td>
                                    <td>Rp <?= number_format((int) $product['harga'], 0, ',', '.'); ?></td>
                                    <td><?= (int) $product['stok']; ?> unit</td>
                                    <td class="text-end text-nowrap">
                                        <a href="edit.php?id=<?= (int) $product['id']; ?>" class="table-action edit-action" aria-label="Edit <?= $escape($product['nama_menu']); ?>"><i class="bi bi-pencil"></i></a>
                                        <button type="button" class="table-action delete-action" aria-label="Hapus <?= $escape($product['nama_menu']); ?>" onclick="confirmDelete(<?= (int) $product['id']; ?>)"><i class="bi bi-trash3"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada produk. Tambahkan produk pertama untuk mulai mengisi katalog.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-section section-space" id="kontak">
        <div class="container contact-card">
            <div class="contact-mark"><i class="bi bi-chat-square-heart"></i></div>
            <div class="contact-copy"><span class="eyebrow">KAMI SIAP MEMBANTU</span><h2>Ada yang ingin ditanyakan?</h2><p>Untuk pertanyaan tentang penggunaan obat, konsultasikan langsung dengan dokter atau apoteker. Untuk bantuan pesanan, hubungi apotek tempat kamu bertransaksi.</p></div>
            <a href="#cara-belanja" class="btn btn-primary-green">Panduan keamanan obat <i class="bi bi-arrow-up-right"></i></a>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-content"><a class="navbar-brand brand-mark" href="index.php"><span class="brand-icon"><i class="bi bi-plus-lg"></i></span><span>sehat<span class="brand-highlight">mart</span><small>APOTEK ONLINE</small></span></a><p>Teman sehat untuk kebutuhan setiap hari.</p><span>© <?= date('Y'); ?> SehatMart. Gunakan obat secara bijak.</span></div>
</footer>

<div class="offcanvas offcanvas-end cart-panel" tabindex="-1" id="cartPanel" aria-labelledby="cartPanelTitle">
    <div class="offcanvas-header"><div><span class="eyebrow">BELANJAANMU</span><h2 class="offcanvas-title" id="cartPanelTitle">Keranjang</h2></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button></div>
    <div class="offcanvas-body"><div id="cartItems" class="cart-items"></div><div class="cart-empty" id="cartEmpty"><i class="bi bi-bag"></i><h3>Keranjang masih kosong</h3><p>Tambahkan produk dari katalog untuk melihatnya di sini.</p></div><div class="cart-summary" id="cartSummary"><div><span>Total sementara</span><strong id="cartTotal">Rp 0</strong></div><p>Keranjang ini hanya tersimpan di browser dan belum memproses pembayaran.</p></div></div>
</div>

<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content custom-modal"><div class="modal-header"><div><span class="eyebrow">PROFIL LOKAL</span><h2 class="modal-title" id="accountModalTitle">Akun kamu</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><p class="modal-description">Simpan nama untuk personalisasi halaman ini. Fitur akun ini tersimpan di browser, belum menggunakan login.</p><label class="form-label" for="accountName">Nama panggilan</label><input class="form-control" id="accountName" type="text" maxlength="40" placeholder="Contoh: Dita"><button class="btn btn-primary-green w-100 mt-3" id="saveAccount" type="button">Simpan nama</button><button class="btn btn-link text-muted w-100 mt-1" id="clearAccount" type="button">Hapus nama tersimpan</button></div></div></div>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content custom-modal"><div class="modal-header"><div><span class="eyebrow">PREFERENSI</span><h2 class="modal-title" id="settingsModalTitle">Pengaturan</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><div class="setting-row"><div><strong>Tampilan nyaman</strong><small>Gunakan latar yang lebih redup.</small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="themeToggle" aria-label="Aktifkan tampilan redup"></div></div><p class="modal-description mt-3 mb-0">Preferensi disimpan di browser ini.</p></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const currency = new Intl.NumberFormat('id-ID');
let cart = [];
try {
    cart = JSON.parse(localStorage.getItem('sehatmart-cart') || '[]');
    if (!Array.isArray(cart)) cart = [];
} catch (error) {
    console.error('Keranjang lokal tidak dapat dibaca.', error);
}

function saveCart() {
    localStorage.setItem('sehatmart-cart', JSON.stringify(cart));
    renderCart();
}

function renderCart() {
    const items = document.getElementById('cartItems');
    const empty = document.getElementById('cartEmpty');
    const summary = document.getElementById('cartSummary');
    const quantity = cart.reduce((total, item) => total + item.quantity, 0);
    document.getElementById('cartCount').textContent = quantity;
    items.replaceChildren();

    cart.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'cart-item';
        const details = document.createElement('div');
        const name = document.createElement('strong');
        name.textContent = item.name;
        const price = document.createElement('small');
        price.textContent = `Rp ${currency.format(item.price)} × ${item.quantity}`;
        details.append(name, price);
        const remove = document.createElement('button');
        remove.className = 'cart-remove';
        remove.type = 'button';
        remove.setAttribute('aria-label', `Hapus ${item.name} dari keranjang`);
        remove.innerHTML = '<i class="bi bi-trash3"></i>';
        remove.addEventListener('click', () => {
            cart = cart.filter((cartItem) => cartItem.id !== item.id);
            saveCart();
        });
        row.append(details, remove);
        items.append(row);
    });

    empty.hidden = cart.length > 0;
    summary.classList.toggle('visible', cart.length > 0);
    document.getElementById('cartTotal').textContent = `Rp ${currency.format(cart.reduce((total, item) => total + item.price * item.quantity, 0))}`;
}

document.querySelectorAll('.add-to-cart').forEach((button) => {
    button.addEventListener('click', () => {
        Swal.fire({
            title: 'Tambahkan ke keranjang?',
            text: `${button.dataset.name} — Rp ${currency.format(Number(button.dataset.price))}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#267b58',
            cancelButtonColor: '#718078',
            confirmButtonText: 'Ya, tambahkan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const id = Number(button.dataset.id);
            const item = cart.find((cartItem) => cartItem.id === id);
            if (item) item.quantity += 1;
            else cart.push({ id, name: button.dataset.name, price: Number(button.dataset.price), quantity: 1 });
            saveCart();
            button.innerHTML = '<i class="bi bi-check2"></i>';
            window.setTimeout(() => { button.innerHTML = '<i class="bi bi-plus-lg"></i>'; }, 900);
        });
    });
});

const accountName = document.getElementById('accountName');
function loadAccount() {
    const name = localStorage.getItem('sehatmart-name') || '';
    accountName.value = name;
    document.getElementById('accountLabel').textContent = name || 'Akun';
}
document.getElementById('saveAccount').addEventListener('click', () => {
    const name = accountName.value.trim();
    if (!name) {
        accountName.focus();
        return;
    }
    localStorage.setItem('sehatmart-name', name);
    loadAccount();
    bootstrap.Modal.getInstance(document.getElementById('accountModal')).hide();
});
document.getElementById('clearAccount').addEventListener('click', () => {
    localStorage.removeItem('sehatmart-name');
    loadAccount();
});

const themeToggle = document.getElementById('themeToggle');
themeToggle.checked = localStorage.getItem('sehatmart-dim') === 'true';
document.body.classList.toggle('dim-theme', themeToggle.checked);
themeToggle.addEventListener('change', () => {
    document.body.classList.toggle('dim-theme', themeToggle.checked);
    localStorage.setItem('sehatmart-dim', String(themeToggle.checked));
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus produk ini?',
        text: 'Produk akan dihapus dari katalog.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d15f4b',
        cancelButtonColor: '#718078',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `hapus.php?id=${id}`;
    });
}

renderCart();
loadAccount();

<?php if (isset($statusMessages[$status])): ?>
Swal.fire('Berhasil!', <?= json_encode($statusMessages[$status]); ?>, 'success');
<?php endif; ?>
</script>
</body>
</html>
