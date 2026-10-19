<?php
require_once 'koneksi.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

$sql = "SELECT * FROM menu WHERE 1=1";
$params = [];
$types = "";

if ($search != '') {
    $sql .= " AND nama_menu LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if ($kategori != '') {
    $sql .= " AND kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kedai Kopi</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-coffee mb-4 shadow">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-cup-hot-fill me-2"></i>
            Kedai Kopi 
        </a>
    </div>
</nav>

<div class="container">

    <?php if (isset($_GET['status'])): ?>

        <?php if ($_GET['status'] == 'add_success'): ?>
            <script>
                Swal.fire('Berhasil!', 'Menu baru berhasil ditambahkan.', 'success');
            </script>

        <?php elseif ($_GET['status'] == 'edit_success'): ?>
            <script>
                Swal.fire('Berhasil!', 'Data menu berhasil diperbarui.', 'success');
            </script>

        <?php elseif ($_GET['status'] == 'delete_success'): ?>
            <script>
                Swal.fire('Berhasil!', 'Menu berhasil dihapus.', 'success');
            </script>
        <?php endif; ?>

    <?php endif; ?>


    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold text-dark">Daftar Menu Kopi</h3>

        <a href="tambah.php" class="btn btn-coffee shadow-sm">
            <i class="bi bi-plus-lg me-1"></i>
            Tambah Menu Baru
        </a>
    </div>


    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <form method="GET" action="index.php" class="row g-2">

                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Cari nama menu..."
                            value="<?= htmlspecialchars($search); ?>"
                        >
                    </div>
                </div>


                <div class="col-md-4">
                    <select name="kategori" class="form-select">
                        <option value=""> Kategori </option>

                        <?php
                        $kategori_list = ['Kopi', 'Non-Kopi', 'Makanan', 'Cemilan'];

                        foreach ($kategori_list as $kat):
                        ?>

                            <option
                                value="<?= $kat; ?>"
                                <?= ($kategori == $kat) ? 'selected' : ''; ?>
                            >
                                <?= $kat; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <div class="col-md-3 d-flex gap-2">

                    <button type="submit" class="btn btn-coffee-accent w-100">
                        <i class="bi bi-filter me-1"></i>
                        Filter
                    </button>

                    <?php if ($search != '' || $kategori != ''): ?>

                        <a
                            href="index.php"
                            class="btn btn-outline-danger"
                            title="Reset Filter"
                        >
                            <i class="bi bi-x-circle"></i>
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>
    </div>


    <div class="card shadow-sm">
        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-coffee-head">
                        <tr>
                            <th class="ps-3">No</th>
                            <th>Nama Menu</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($result->num_rows > 0): ?>

                            <?php
                            $no = 1;

                            while ($row = $result->fetch_assoc()):

                                $badge_class = 'badge-kopi';

                                if ($row['kategori'] == 'Non-Kopi') {
                                    $badge_class = 'badge-nonkopi';
                                } elseif ($row['kategori'] == 'Makanan') {
                                    $badge_class = 'badge-makanan';
                                } elseif ($row['kategori'] == 'Cemilan') {
                                    $badge_class = 'badge-cemilan';
                                }
                            ?>

                                <tr>

                                    <td class="ps-3">
                                        <?= $no++; ?>
                                    </td>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($row['nama_menu']); ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $badge_class; ?>">
                                            <?= htmlspecialchars($row['kategori']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        Rp <?= number_format($row['harga'], 0, ',', '.'); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['stok']); ?> unit
                                    </td>

                                    <td class="text-center">

                                        <a
                                            href="edit.php?id=<?= $row['id']; ?>"
                                            class="btn btn-sm btn-warning me-1"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                            Edit
                                        </a>

                                        <button
                                            onclick="confirmDelete(<?= $row['id']; ?>)"
                                            class="btn btn-sm btn-danger"
                                        >
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">

                                    <i class="bi bi-search fs-3 d-block mb-2"></i>

                                    Data menu tidak ditemukan.

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>
        </div>
    </div>

</div>


<script>
function confirmDelete(id) {

    Swal.fire({
        title: 'Apakah kamu yakin?',
        text: 'Data menu ini akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#dc3545',
        cancelColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'

    }).then((result) => {

        if (result.isConfirmed) {
            window.location.href = 'hapus.php?id=' + id;
        }

    });
}
</script>

</body>
</html>

