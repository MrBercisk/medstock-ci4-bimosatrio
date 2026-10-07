# MedStock: Penerimaan Obat dan Stok per Batch

Aplikasi web kecil untuk mencatat penerimaan obat dari pemasok, memperbaiki penerimaan yang keliru, dan melihat stok tersedia per obat beserta rincian batch. Batch yang sudah kedaluwarsa tetap tercatat sebagai stok fisik, tetapi tidak dihitung sebagai stok tersedia. Setiap penerimaan dapat ditelusuri ke petugas yang membuat dan mengubahnya.

Repositori: https://github.com/MrBercisk/medstock-ci4-bimosatrio

## Daftar isi

1. [Teknologi dan prasyarat](#1-teknologi-dan-prasyarat)
2. [Database dan ERD](#2-database-dan-erd)
3. [Menjalankan aplikasi](#3-menjalankan-aplikasi)
4. [Akun demo, login, dan logout](#4-akun-demo-login-dan-logout)
5. [Endpoint API](#5-endpoint-api)
6. [Pengujian dan verifikasi](#6-pengujian-dan-verifikasi)
7. [Postman Collection](#7-postman-collection)
8. [Keputusan desain](#8-keputusan-desain)
9. [Asumsi dan keterbatasan](#9-asumsi-dan-keterbatasan)
10. [Penggunaan AI](#10-penggunaan-ai)

---

## 1. Teknologi dan prasyarat

| Komponen | Versi / pilihan |
|---|---|
| PHP | 8.2.12 |
| CodeIgniter | 4.7.4 |
| Database | MySQL 8.4.3 |
| Frontend | Vue 3 lewat CDN (tanpa build), Bootstrap 5 |
| Autentikasi | Session/cookie bawaan CodeIgniter 4 |

Catatan database: pengembangan awal dilakukan pada MariaDB 10.4.32 (XAMPP). Feature test dan lima skenario soal kemudian dijalankan ulang pada **MySQL 8.4.3** (Laragon). Skema dan query hanya memakai fitur umum (InnoDB, foreign key, ENUM, `UNION ALL`, `SELECT ... FOR UPDATE`). Belum diuji pada MySQL 5.7.

**Prasyarat**

- PHP 8.2 atau lebih baru dengan ekstensi `intl`, `mbstring`, `mysqlnd`, `json` (cek dengan `php -m`)
- Composer 2
- MySQL 5.7+ atau MariaDB yang kompatibel
- Git
- Koneksi internet saat membuka UI (Vue, Bootstrap, ikon, dan font dimuat dari CDN)

## 2. Database dan ERD

Diagram ERD (Mermaid `erDiagram`) ada di [`docs/erd.md`](docs/erd.md).

### Asal tabel

| Tabel | Asal | Fungsi |
|---|---|---|
| `suppliers`, `medicines` | `database/seed_farmasi.sql` | Katalog pemasok dan obat beserta status aktif |
| `seed_batch_stock` | `database/seed_farmasi.sql` | Stok awal per batch (dipakai apa adanya) |
| `stock_usage` | `database/seed_farmasi.sql` | Pemakaian stock (dipakai apa adanya) |
| `users` | migration | Akun petugas dan rolenya |
| `receipts` | migration | Header penerimaan, `created_by`, `updated_by` |
| `receipt_items` | migration | Rincian obat, batch, kedaluwarsa, jumlah |
| `receipt_logs` | migration | Riwayat aksi buat/ubah beserta petugasnya |

Tabel `migrations` milik CodeIgniter ikut dibuat saat migration dijalankan.

### Urutan membuat skema dari database kosong

Urutan ini tidak boleh dibalik, karena migration memiliki foreign key ke `suppliers` dan `medicines` yang dibuat oleh berkas seed.

1. Buat database kosong `farmasi` (utf8mb4).
2. Impor `database/seed_farmasi.sql` (membuat 4 tabel lampiran beserta datanya).
3. `php spark migrate` (membuat `users`, `receipts`, `receipt_items`, `receipt_logs`).
4. `php spark db:seed UserSeeder` (membuat tepat dua akun demo untuk officer dan supervisor).

### Primary key, foreign key, unique, dan indeks

| Tabel | Constraint | Tujuan |
|---|---|---|
| semua tabel aplikasi | PK `id` (BIGINT UNSIGNED) | Identitas baris |
| `users` | UNIQUE `email` | Satu akun per email |
| `receipts` | UNIQUE `reference_no` | Nomor referensi unik antar penerimaan |
| `receipts` | FK `supplier_id`, `created_by`, `updated_by` (RESTRICT) | Menjaga relasi dan jejak petugas |
| `receipt_items` | UNIQUE `(receipt_id, medicine_id, batch_no)` | Satu batch hanya sekali per penerimaan |
| `receipt_items` | INDEX `(medicine_id, batch_no)` | Mempercepat perhitungan stok dan cek kedaluwarsa |
| `receipt_items` | FK `receipt_id` (CASCADE), `medicine_id` (RESTRICT) | Item adalah bagian dari penerimaan |
| `receipt_logs` | FK `receipt_id`, `user_id` (RESTRICT) | Riwayat tidak ikut hilang |
| `seed_batch_stock` | UNIQUE `(medicine_id, batch_no)` | Dari berkas lampiran |
| `stock_usage` | INDEX `(medicine_id, batch_no)` | Dari berkas lampiran |

Identitas batch adalah pasangan `(medicine_id, batch_no)`, sehingga nomor batch yang sama boleh dipakai obat berbeda.

### Model stok

Stok **dihitung dari transaksi**, tidak disimpan sebagai saldo:

```
stok fisik per batch = stok awal (seed_batch_stock) + SUM(receipt_items) - SUM(stock_usage)
```

- Data seed tetap digunakan apa adanya, jadi tidak ada risiko data terhitung dua kali.
- Tidak ada kolom saldo yang rawan tidak sinkron. Stok dihitung ulang dari receipt_items, jadi PUT yang sama dua kali tidak menambah stok.
- Jika item penerimaan dihapus, stoknya otomatis ikut berkurang.
- Tidak ada tabel batches terpisah, jadi tidak ada batch yang tertinggal setelah item dihapus. Tanggal kedaluwarsa per batch dijaga oleh service.

Status kedaluwarsa: batch dianggap kedaluwarsa jika `expires_on < on_date`. Pada tanggal kedaluwarsa, batch masih dianggap tersedia. `on_date` hanya digunakan untuk menentukan status, sedangkan jumlah dihitung dari seluruh transaksi yang tersimpan.

### Konsistensi saat penyimpanan gagal

Pembuatan dan pembaruan penerimaan berjalan dalam **satu transaksi database** yang mencakup header, item, dan log aksi:

1. Validasi bentuk (Request) dan aturan bisnis (Service).
2. `transBegin`, lalu simpan header, item, dan log.
3. `transCommit`. Jika ada baris tidak valid atau operasi database gagal, `transRollback` membatalkan seluruh perubahan, termasuk log.

Pada pembaruan, baris penerimaan dikunci dengan `SELECT ... FOR UPDATE` di dalam transaksi, dan hak ubah diperiksa ulang setelah baris dikunci.

## 3. Menjalankan aplikasi

### 1. Clone dan pasang dependensi

```bash
git clone https://github.com/MrBercisk/medstock-ci4-bimosatrio.git
cd medstock-ci4-bimosatrio
composer install
```

### 2. Konfigurasi environment

```bash
cp .env.example .env        # Windows (cmd): copy .env.example .env
```

Sesuaikan bagian database di `.env`. Berkas `.env` tidak ikut ter-commit, dan `.env.example` tidak berisi kata sandi.

```ini
database.default.hostname = localhost
database.default.database = farmasi
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3307
```

Jika MySQL Anda tidak berjalan di port 3307, ubah `database.default.port` (dan `database.tests.port`). Zona waktu aplikasi diatur ke `Asia/Jakarta` (`app.appTimezone`). Isi `username` dan `password` sesuai MySQL lokal Anda. Grup `database.tests.*` di berkas yang sama dipakai untuk pengujian otomatis (lihat bagian 6).

### 3. Buat database dan impor seed

```bash
mysql -u root -p -e "CREATE DATABASE farmasi CHARACTER SET utf8mb4"
mysql -u root -p farmasi < database/seed_farmasi.sql
```

Alternatif tanpa terminal: buat database `farmasi` (utf8mb4) di Navicat atau phpMyAdmin, lalu jalankan `database/seed_farmasi.sql`. Berkas seed hanya dapat diimpor ke database kosong.

### 4. Migration dan akun demo

```bash
php spark migrate
php spark db:seed UserSeeder
```

Jalankan `UserSeeder` **sekali saja**. Email bersifat unik, sehingga eksekusi kedua ditolak.

### 5. Jalankan server

```bash
php spark serve
```

Buka `http://localhost:8080`. UI memakai jalur absolut (`/api`, `/js`, `/css`), jadi aplikasi harus dibuka dari alamat tersebut, bukan dari subfolder.

Untuk mengulang dari awal, hapus database `farmasi` lalu ulangi langkah 3 dan 4.

## 4. Akun demo, login, dan logout

| Peran | Email | Kata sandi |
|---|---|---|
| Petugas penerimaan (`receiving_officer`) | `petugas@farmasi.test` | `Petugas#2026` |
| Supervisor farmasi (`pharmacy_supervisor`) | `supervisor@farmasi.test` | `Supervisor#2026` |

**Lewat UI:** buka `http://localhost:8080`, isi email dan kata sandi, klik **Masuk**. Klik **Keluar** di pojok kanan atas untuk logout.

**Lewat API:** `POST /api/login` dengan body JSON `{"email": "...", "password": "..."}`, lalu `POST /api/logout`.

### Autentikasi

- Memakai **session/cookie** bawaan CodeIgniter 4 (`FileHandler`, sesi disimpan di `writable/session/`). Setelah login, cookie `ci_session` dikirim otomatis pada request berikutnya (Postman dan browser menyimpannya sendiri).
- Kata sandi disimpan sebagai hash (`password_hash`) dan diverifikasi dengan `password_verify`.
- Hanya `user_id` yang disimpan di sesi. Pada setiap request, `AuthFilter` memuat pengguna beserta perannya dari database. Identitas dan peran dari body request **tidak pernah** dipakai untuk keputusan hak akses.
- ID sesi diganti (`regenerate`) setelah login untuk mencegah session fixation, dan logout menghancurkan sesi.
- Pesan login salah sama untuk email tidak terdaftar maupun sandi salah.

### Hak akses

| Tindakan | Petugas penerimaan | Supervisor farmasi |
|---|---|---|
| Membuat penerimaan | Boleh | Boleh |
| Melihat daftar/detail semua penerimaan dan laporan stok | Boleh | Boleh |
| Mengubah penerimaan sendiri | Boleh | Boleh |
| Mengubah penerimaan orang lain | Ditolak (403) | Boleh |

Hak ubah diperiksa di backend (`ReceiptPolicy`) pada setiap request PUT. Jika ditolak, penerimaan, stok, dan log aksi tidak berubah. Respons penerimaan memuat `can_update` yang dihitung server, dan UI hanya menampilkan tombol Edit jika nilainya `true`.

## 5. Endpoint API

Semua respons berupa JSON:

```json
{ "success": true, "message": "OK", "data": { } }
{ "success": false, "message": "Pesan kesalahan", "errors": { "field": "keterangan" } }
```

| Metode | Path | Login | Fungsi |
|---|---|---|---|
| POST | `/api/login` | tidak | Login, membuat sesi |
| POST | `/api/logout` | tidak | Logout (idempoten, tetap sukses jika belum login) |
| GET | `/api/me` | ya | Pengguna yang sedang login |
| GET | `/api/stocks?on_date=YYYY-MM-DD` | ya | Laporan stok seluruh obat aktif |
| GET | `/api/receipts` | ya | Daftar penerimaan beserta item |
| GET | `/api/receipts/{id}` | ya | Detail penerimaan, item, pembuat, pengubah terakhir, riwayat |
| POST | `/api/receipts` | ya | Membuat penerimaan beserta seluruh item |
| PUT | `/api/receipts/{id}` | ya | Memperbarui penerimaan beserta seluruh item |
| GET | `/api/suppliers` | ya | Pemasok aktif (untuk pilihan di UI) |
| GET | `/api/medicines` | ya | Obat aktif (untuk pilihan di UI) |
| GET | `/api/batches` | ya | Batch yang sudah tercatat (stok awal dan penerimaan) |

Kode status: `200` sukses, `201` dibuat, `401` belum login, `403` tidak berhak, `404` tidak ditemukan, `422` validasi atau aturan bisnis gagal, `500` kesalahan server.

### Contoh: login lalu membuat penerimaan

```bash
curl -c cookies.txt -H "Content-Type: application/json" \
  -d '{"email":"petugas@farmasi.test","password":"Petugas#2026"}' \
  http://localhost:8080/api/login

curl -b cookies.txt -H "Content-Type: application/json" \
  -d '{
    "reference_no": "PB-001",
    "supplier_id": 1,
    "received_at": "2026-10-03T10:00:00+07:00",
    "items": [
      {"medicine_id": 101, "batch_no": "PCT-2601", "expires_on": "2027-12-31", "quantity": 10},
      {"medicine_id": 104, "batch_no": "IBU-2602", "expires_on": "2028-06-30", "quantity": 5}
    ]
  }' \
  http://localhost:8080/api/receipts
```

Membaca stok pada tanggal tetap:

```bash
curl -b cookies.txt "http://localhost:8080/api/stocks?on_date=2026-10-03"
```

### Aturan PUT

Daftar `items` pada PUT dianggap sebagai isi akhir penerimaan. Item yang dikirim dipertahankan atau diperbarui, item yang tidak dikirim dihapus, item baru ditambahkan. `created_by` tidak berubah, `updated_by` dan `updated_at` mengikuti pengguna yang terakhir mengubah data. Setiap perubahan juga dicatat sebagai satu baris `update` di `receipt_logs`.

### Aturan validasi penerimaan

- `reference_no` wajib, unik, maksimal 50 karakter. Saat PUT, nomor milik penerimaan itu sendiri tidak dianggap duplikat.
- Pemasok dan obat harus ada dan berstatus aktif.
- `items` minimal satu baris. `quantity` bilangan bulat positif.
- Kombinasi obat dan nomor batch hanya boleh muncul sekali dalam satu penerimaan.
- Batch yang sama harus memiliki tanggal kedaluwarsa yang konsisten dengan stok awal dan penerimaan lain.
- `received_at` wajib ISO 8601 dengan zona waktu. Zona dikonversi ke Asia/Jakarta lebih dulu, baru diambil tanggalnya. `expires_on` harus lebih akhir dari tanggal penerimaan tersebut (kedaluwarsa pada hari penerimaan ditolak).

### Bentuk laporan stok

Per obat: `medicine_id`, `code`, `name`, `unit`, `physical_quantity`, `available_quantity`, `expired_quantity`, `available_batches[]`, dan `expired_batches[]` (masing-masing berisi `batch_no`, `expires_on`, `quantity`). Respons dibungkus `data: { on_date, medicines: [...] }`.

## 6. Pengujian dan verifikasi

### Pengujian otomatis

Test berjalan lewat HTTP penuh (route, filter, controller, service, database) pada database terpisah **`farmasi_test`**. Berkas: `tests/feature/ReceiptFlowTest.php` (28 test). Test mencakup angka contoh soal, batas kedaluwarsa, lima skenario inti, kasus validasi yang harus 422 tanpa menyimpan apa pun, dan 404.

**Persiapan database test (sekali saja)**

1. Buat database kosong `farmasi_test` (utf8mb4).
2. Impor `database/seed_farmasi.sql`.
3. Impor `database/test_schema.sql` (membuat `users`, `receipts`, `receipt_items`, `receipt_logs`).
4. Pastikan grup `database.tests.*` di `.env` mengarah ke `farmasi_test` (isi username dan password sesuai MySQL lokal). `DBPrefix` harus dikosongkan.

**Menjalankan**

```bash
vendor/bin/phpunit tests/feature
```

Test mengosongkan `receipts`, `receipt_items`, dan `receipt_logs` di database test pada setiap awal, dan akan **berhenti** jika nama database aktif tidak berakhiran `_test`, sehingga data demo di `farmasi` tidak tersentuh. Peringatan `No code coverage driver available` dapat diabaikan.

Catatan: `database/test_schema.sql` adalah salinan skema migration untuk database test, dan harus diperbarui jika migration berubah. Berkas ini sengaja tidak menyebut collation, sehingga mengikuti collation bawaan server seperti tabel seed. Perintah `php spark migrate -g tests` tidak menargetkan `farmasi_test` pada lingkungan pengembangan saya (penyebabnya belum diselidiki), sehingga skema test disediakan sebagai SQL.

### Verifikasi manual (lima skenario soal)

Mulai dari database `farmasi` yang bersih (hanya seed). Bersihkan data uji dengan:

```sql
DELETE FROM receipt_logs;
DELETE FROM receipt_items;
DELETE FROM receipts;
```

Angka awal pada `on_date=2026-10-03`: stok tersedia obat 101 = 134 (PCT-2601 = 94, PCT-2602 = 40) dan kedaluwarsa 8 (PCT-2501), 102 = 16, 103 = 15, 104 = 3, 106 = 0, 107 = 0 tersedia (fisik 6, seluruhnya kedaluwarsa).

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Login petugas, `POST /api/receipts` dengan payload contoh (`PB-001`) | 201. Tersedia: 101 = **144**, 104 = **8**. Kedaluwarsa 101 tetap 8 |
| 2 | `PUT` penerimaan itu: qty 101 jadi 7, hapus 104, tambah 103 batch `SAL-2601` (qty 3, kedaluwarsa 2027-11-30) | 200. Tersedia: 101 = **141**, 103 = **18**, 104 = **3** |
| 3 | Kirim PUT yang sama sekali lagi | 200. Ketiga total tetap 141, 18, 3 |
| 4 | Petugas membuat penerimaan, supervisor mengubahnya | Detail: pembuat = petugas, pengubah terakhir = supervisor, riwayat memuat `create` dan `update` |
| 5 | Supervisor membuat penerimaan. Petugas melihat di daftar dan detail, lalu mencoba PUT | Daftar dan detail 200 (`can_update: false`), PUT **403** dan tidak ada yang berubah. Request tanpa login **401** |

Pengecekan pada skenario 5: tabel `receipt_logs` tidak bertambah, `updated_by` tetap kosong, dan stok tidak berubah.

## 7. Postman Collection

Berkas ada di folder [`docs/postman/`](docs/postman/):

- `medstock.postman_collection.json`: seluruh API (login petugas, login supervisor, logout, me, penerimaan, stok, dan data pilihan)
- `medstock.postman_environment.json`: variabel `base_url`

**Cara memakai**

1. Postman: **Import**, pilih kedua berkas.
2. Pilih environment `local`, pastikan `base_url` = `http://localhost:8080`, dan server berjalan (`php spark serve`).
3. Jalankan `POST Login - Petugas` atau `POST Login - Supervisor`. Cookie `ci_session` disimpan Postman otomatis, tidak perlu header Authorization.
4. Jalankan request lain. Cookie dibagi per domain, jadi login supervisor menimpa sesi petugas: jalankan login yang sesuai sebelum tiap skenario.

## 8. Keputusan desain

| Topik | Keputusan |
|---|---|
| Alur kode | Route, `AuthFilter`, Controller, Request (validasi bentuk), Service (aturan bisnis dan transaksi), Resource (bentuk JSON) |
| `received_at` | Satu kolom DATETIME dalam waktu Asia/Jakarta. Tanggal kalender diturunkan saat validasi, tidak disimpan ganda |
| Konsistensi kedaluwarsa batch | Dicek ke stok awal dan item penerimaan **lain** (tidak termasuk penerimaan yang sedang diubah) |
| Pengubah terakhir | `updated_by` dan `updated_at` kosong (`null`) untuk penerimaan yang belum pernah diubah |
| Riwayat aksi | Tabel `receipt_logs` dengan aksi `create` dan `update`. PUT yang identik tetap mencatat satu aksi `update` |
| Batch dengan stok fisik nol | **Disembunyikan** dari rincian batch. Obat tetap tampil dan total tetap benar |
| Obat tanpa batch | Tetap tampil, semua jumlah nol dan rincian batch kosong |
| Obat hanya berbatch kedaluwarsa | Tetap tampil, stok tersedia nol |
| `on_date` kosong | Memakai tanggal hari ini di Asia/Jakarta |
| `reference_no` | Diisi klien, tanpa aturan format selain wajib, unik, maksimal 50 karakter |
| Kode status | 404 hanya untuk resource di URL yang tidak ada. Referensi di body yang tidak valid (pemasok atau obat tidak ada atau nonaktif) memakai 422 |
| Logout | Idempoten: tetap sukses walau belum login |
| Penghapusan | Tidak ada fitur hapus. Foreign key `RESTRICT` menjaga histori, dan obat atau pemasok dinonaktifkan lewat `is_active` |
| Peran | Kolom ENUM di `users`, karena hanya ada dua peran tetap |
| Collation | `DBCollat` dikosongkan di `app/Config/Database.php`, sehingga tabel migration memakai collation bawaan charset `utf8mb4`, sama dengan tabel seed (MySQL 8: `utf8mb4_0900_ai_ci`, MariaDB: `utf8mb4_general_ci`). Query batch yang menggabungkan tabel seed dan tabel aplikasi (`UNION`) bergantung pada kesamaan ini. Jika dipaksa berbeda, MySQL 8 menolak dengan error "Illegal mix of collations" |

## 9. Asumsi dan keterbatasan

- **Database**: diuji pada MySQL 8.4.3 dan MariaDB 10.4.32. Belum diuji pada MySQL 5.7.
- **CSRF** belum diaktifkan. Cookie sesi memakai `SameSite=Lax` dan `HttpOnly`, tetapi konfig tersebut bukan pengganti perlindungan CSRF.
- Tanpa pembatasan percobaan login. Sesi disimpan di file.
- Tanpa pagination dan pencarian. Tanpa halaman log aksi. Filter `on_date` belum ada di UI (tersedia sebagai parameter API).
- Login dan logout tidak tercakup test otomatis. Keduanya diverifikasi manual lewat UI dan Postman.
- UI memerlukan internet karena aset dimuat dari CDN.
- Skema database test (`test_schema.sql`) adalah salinan terpisah dari migration dan harus dijaga sinkron.

## 10. Penggunaan AI dan referensi

**Alat AI yang dipakai:** Claude (Anthropic) Seluruh kode aplikasi (migration, seeder, request, service, resource, policy, controller, antarmuka, dan feature test), ERD, serta draf dokumentasi ditulis oleh AI atas permintaan saya, lalu saya salin ke proyek.

**Verifikasi dan penyesuaian yang dilakukan**

- Menentukan struktur direktori ala Laravel (Request untuk validasi, Service untuk logika bisnis, Resource untuk bentuk respons JSON, Policy untuk hak akses) berdasarkan pengalaman saya, dan meminta AI mengikuti struktur itu.
- Memilih teknologi dalam batas soal (CodeIgniter 4, Vue via CDN, autentikasi session) dan memilih di antara opsi desain yang diajukan AI.
- Mereview kode tiap tahap, menjalankannya, dan memverifikasi hasilnya: laporan stok dicocokkan dengan angka pada soal, skenario penerimaan diuji lewat Postman dan UI, dan 28 feature test dijalankan.
- Memasang MySQL 8.4.3 (Laragon, port 3307) untuk menguji ulang. Di sana saya menemukan error "Illegal mix of collations", lalu memeriksa collation tabel lewat `information_schema` dan memverifikasi perbaikannya lewat Postman.
- Menguji ERD di Mermaid Live, menyiapkan database test, dan menyusun serta mengekspor Postman Collection.
- Mengelola repositori: cabang per tahap, commit, dan Pull Request.
