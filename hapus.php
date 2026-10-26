<?php
/**
 * hapus.php - Pemrosesan Hapus Data Barang Inventaris Apotek
 * UTS Pemrograman Web - SehatMart
 */
require_once 'koneksi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    $namaBarang = "Barang";

    // Ambil nama barang sebelum dihapus untuk informasi notifikasi flash
    $targetTbl = $tableBarang ?? 'barang';
    $getStmt = $conn->prepare("SELECT COALESCE(NULLIF(nama_barang, ''), nama_menu, 'Barang') FROM `{$targetTbl}` WHERE id = ?");
    if ($getStmt) {
        $getStmt->bind_param("i", $id);
        $getStmt->execute();
        $getStmt->bind_result($foundName);
        if ($getStmt->fetch() && !empty($foundName)) {
            $namaBarang = $foundName;
        }
        $getStmt->close();
    }

    // Eksekusi penghapusan pada tabel aktif
    $stmt = $conn->prepare("DELETE FROM `{$targetTbl}` WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $executed = $stmt->execute();
        $stmt->close();

        // Jika ada tabel mirror 'menu' dan tabel utama bukan 'menu', sinkronkan
        if ($targetTbl !== 'menu') {
            $checkMenu = $conn->query("SHOW TABLES LIKE 'menu'");
            if ($checkMenu && $checkMenu->num_rows > 0) {
                $delMenu = $conn->prepare("DELETE FROM `menu` WHERE id = ?");
                if ($delMenu) {
                    $delMenu->bind_param("i", $id);
                    $delMenu->execute();
                    $delMenu->close();
                }
            }
        }

        if ($executed) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'title' => 'Berhasil Dihapus!',
                'message' => 'Data barang "' . $namaBarang . '" telah berhasil dihapus dari inventaris apotek.'
            ];
            header("Location: index.php?status=delete_success");
            exit();
        }
    }

    $_SESSION['flash'] = [
        'type' => 'danger',
        'title' => 'Gagal Menghapus!',
        'message' => 'Terjadi kesalahan sistem saat menghapus data barang.'
    ];
} else {
    $_SESSION['flash'] = [
        'type' => 'warning',
        'title' => 'Perhatian!',
        'message' => 'ID data barang tidak valid atau tidak ditemukan.'
    ];
}

header("Location: index.php");
exit();
?>