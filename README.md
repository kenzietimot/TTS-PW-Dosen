# Sistem Inventaris Apotek — SehatMart

Aplikasi web **PHP Native** untuk mengelola daftar barang dalam inventaris apotek.  
Dibuat untuk **UTS Pemrograman Web** tanpa menggunakan framework (tidak ada Laravel/Composer).

---

## 📁 Struktur File

| File | Fungsi |
|------|--------|
| `.env` | Konfigurasi database (DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_TABLE) |
| `.env.example` | Template konfigurasi database |
| `koneksi.php` | Parser `.env` + inisialisasi koneksi MySQLi |
| `setup.php` | Membuat database, tabel, dan data contoh |
| `index.php` | **Read** — Halaman utama, menampilkan semua barang dalam tabel HTML |
| `tambah.php` | **Create** — Form tambah barang baru |
| `edit.php` | **Update** — Form edit data barang |
| `hapus.php` | **Delete** — Proses hapus barang dari database |
| `style.css` | Stylesheet tampilan aplikasi |

---

## 🗄️ Struktur Database

**Nama Database:** `db_apotik` (dapat dikonfigurasi via `.env`)  
**Nama Tabel:** `barang` (alias `menu` untuk kompatibilitas)

| No | Field | Tipe | Keterangan |
|----|-------|------|------------|
| 1 | `id` | INT AUTO_INCREMENT | Primary Key |
| 2 | `nama_barang` / `nama_menu` | VARCHAR(100) | Nama produk / obat |
| 3 | `kategori` | VARCHAR(100) | Kategori farmasi |
| 4 | `merek` | VARCHAR(100) NULL | Pabrik / Principal |
| 5 | `satuan` | VARCHAR(30) | Satuan unit (strip, botol, dll) |
| 6 | `harga_beli` | INT | Harga pokok pembelian (HPP) |
| 7 | `harga` | INT | Harga jual apotek |
| 8 | `jumlah` / `stok` | INT | Jumlah fisik persediaan |
| 9 | `stok_minimum` | INT | Ambang batas reorder point |
| 10 | `tanggal_kedaluwarsa` | DATE NULL | Masa kedaluwarsa (Expired) |
| 11 | `deskripsi` | TEXT NULL | Catatan penyimpanan & kegunaan |
| 12 | `created_at` | TIMESTAMP | Waktu pendataan barang |

---

## ✅ Fitur CRUD yang Diimplementasikan

### ➕ Create (Tambah Barang) — `tambah.php`
- Form HTML dengan field: nama barang, jumlah/stok, harga beli, harga jual, kategori, merek, satuan, tanggal kedaluwarsa, deskripsi
- Validasi server-side sebelum insert
- Menggunakan **Prepared Statement** MySQLi (aman dari SQL Injection)
- Setelah berhasil → **Session Flash Message** sukses di `index.php`

### 📋 Read (Tampilkan Barang) — `index.php`
- Menampilkan semua barang dari tabel dalam format **tabel HTML**
- Menampilkan semua kolom data + kolom **"Aksi"** (Edit & Hapus)
- Filter berdasarkan nama, kategori, status stok
- KPI ringkasan inventaris (total SKU, stok habis, menipis, dll)

### ✏️ Update (Edit Barang) — `edit.php`
- Link "Edit" mengarahkan ke form yang sudah terisi data barang terpilih
- Validasi server-side sebelum UPDATE
- Menggunakan **Prepared Statement** MySQLi
- Setelah berhasil → **Session Flash Message** sukses di `index.php`

### 🗑️ Delete (Hapus Barang) — `hapus.php`
- Tombol "Hapus" menggunakan **konfirmasi JavaScript** (SweetAlert2) sebelum penghapusan
- Setelah hapus → **Session Flash Message** dengan nama barang yang dihapus

---

## ⚙️ Cara Menjalankan

1. **Pastikan Laragon sudah dijalankan** (Start All — Apache + MySQL)
2. **Clone/salin** project ke folder `C:\laragon\www\TTS-PW-Dosen\`
3. **Buat file `.env`** (salin dari `.env.example`) dan sesuaikan konfigurasi:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=db_apotik
   DB_USERNAME=root
   DB_PASSWORD=
   DB_TABLE=barang
   ```
4. **Jalankan setup**: Buka browser ke `http://localhost/TTS-PW-Dosen/setup.php`
5. **Buka aplikasi**: Klik tombol "Buka Aplikasi" atau langsung ke `http://localhost/TTS-PW-Dosen/`

---

## 🔐 Keamanan

- **Prepared Statement** untuk semua query database (mencegah SQL Injection)
- **htmlspecialchars** untuk semua output ke HTML (mencegah XSS)
- **CSRF Token** untuk operasi update stok via AJAX
- **filter_input / filter_var** untuk validasi input numerik
- **Session Flash Message** untuk notifikasi yang aman

---

## 📝 Catatan UTS

- ❌ **Tidak menggunakan** Laravel, CodeIgniter, atau framework PHP apapun
- ❌ **Tidak menggunakan** Composer / autoloader
- ✅ **PHP Native** prosedural dengan mysqli
- ✅ Konfigurasi database dibaca dari file **`.env`**
- ✅ Program dipecah menjadi **beberapa file** sesuai fungsinya
- ✅ Notifikasi menggunakan **Session Flash Message**
- ✅ Konfirmasi hapus menggunakan **JavaScript confirm**