# PRD: Autentikasi dan Manajemen Produk (PPL-13 dan PPL-14)

| | |
| --- | --- |
| Proyek | RiasKu, website e-commerce UMKM kosmetik |
| Fitur | PPL-13 Autentikasi, PPL-14 Manajemen Produk, Kategori dan Harga |
| Sprint | Sprint 2 |
| PIC | Riel |
| Versi | 2 (menyesuaikan dua RESTful API dari Evan dan login Google) |
| Tanggal | 15 November 2026 |
| Status | Draf untuk disepakati tim |

## 1. Latar belakang dan tujuan

RiasKu menjual produk kosmetik milik satu pemilik UMKM. Sistemnya terdiri dari dua aplikasi: **app utama** untuk pengunjung dan pelanggan, dan **dashboard** untuk pemilik. Masing-masing dilayani satu RESTful API sendiri. Dua hal harus ada sebelum fitur lain berjalan: pengguna perlu masuk dengan identitas yang jelas, dan pemilik perlu cara untuk mengisi dan merawat daftar produk.

PPL-13 membuat pelanggan dapat mendaftar, masuk dengan email atau akun Google, dan keluar, serta membuat pemilik dapat masuk ke dashboard. Pelanggan wajib masuk sebelum checkout, dan fungsi pengelolaan toko hanya terbuka untuk pemilik. PPL-14 membuat pemilik dapat mengelola kategori, produk, varian, stok awal, foto, dan harga lewat dashboard.

Tujuan akhirnya: pemilik dapat login ke dashboard, menambah produk lengkap dengan harga dan foto, dan produk itu siap ditampilkan di katalog app utama (PPL-15).

## 2. Acuan backend

Bagian ini merangkum kondisi backend per 15 November 2026 (branch `development`, setelah PR #44).

| | API App Utama | API Dashboard |
| --- | --- | --- |
| Folder | `riasku-restful-api` | `dashboard-restful-api` |
| Pengguna | Pengunjung dan pelanggan | Pemilik (admin) |
| Fungsi pada fitur ini | Register, login (email dan Google), logout, data akun, nantinya katalog dan checkout | Login admin, logout, data akun, kelola kategori, produk, varian, dan foto |
| Kondisi awal kode | Kerangka Laravel bawaan. Belum ada `routes/api.php` dan Sanctum | Kerangka Laravel dengan `routes/api.php`, konfigurasi Sanctum, dan migration token akses |

Hal yang berlaku untuk keduanya:
- Format komunikasi JSON lewat HTTP (RESTful). Kode status standar: 200 berhasil, 201 dibuat, 401 belum login, 403 tidak berhak, 404 tidak ditemukan, 422 data tidak valid.
- Autentikasi memakai token akses (Laravel Sanctum). Setelah login, API mengembalikan token, dan frontend mengirimnya di setiap permintaan.
- Keduanya memakai satu database yang sama. Tabel bawaan `users` dari kerangka Laravel tidak dipakai, diganti `admin` dan `pelanggan` sesuai ERD.
- Frontend berada di folder `frontend`, dokumen di `docs`.

## 3. Pengguna

| Pengguna | Penjelasan | Kebutuhan pada fitur ini |
| --- | --- | --- |
| Pengunjung | Orang yang membuka toko dan belum masuk | Mendaftar dan masuk |
| Pelanggan | Pengunjung yang sudah punya akun dan sudah masuk | Masuk (email atau Google), keluar, dan nanti checkout |
| Pemilik (admin) | Pemilik UMKM yang mengelola toko | Masuk ke dashboard dan mengelola kategori serta produk |

## 4. Ruang lingkup

**Termasuk dalam PPL-13 dan PPL-14**
- Register pelanggan, login pelanggan dengan email dan password, login pelanggan dengan akun Google, logout.
- Login admin di dashboard dengan email dan password, dan logout.
- Pembatasan akses: fungsi dashboard hanya untuk admin, checkout hanya untuk pelanggan yang sudah masuk.
- Kelola kategori.
- Kelola produk beserta varian, stok awal, foto, harga eceran, harga pack, dan pengaturan Pre-Order.
- Daftar produk di dashboard dengan pencarian dan penyaringan.
- Label stok pada produk (Tersedia, Pre-Order, Stok Habis).

**Tidak termasuk**
- Katalog, pencarian, dan detail produk untuk pembeli (PPL-15) dan keranjang (PPL-16).
- Checkout, pengiriman, dan pembayaran (Sprint 3).
- Penambahan stok dari grosir lewat Barang Masuk (PPL-23).
- Skor Best Seller (PPL-24).
- Pembuatan akun admin lewat antarmuka. Akun admin dibuat langsung oleh tim.
- Login Google untuk admin. Admin tetap memakai email dan password.
- Pengelolaan akun pelanggan oleh admin.

## 5. Kebutuhan fungsional: Autentikasi (PPL-13)

### 5.1 Pelanggan: register dan login dengan email (API App Utama)

| Kode | Kebutuhan |
| --- | --- |
| A1 | Pengunjung dapat mendaftar sebagai pelanggan dengan mengisi username, email, dan password. |
| A2 | Sistem menolak pendaftaran bila email atau username sudah dipakai pelanggan lain. |
| A3 | Sistem memeriksa format email dan panjang password sebelum menyimpan. |
| A4 | Password disimpan dalam bentuk terenkripsi (hash), tidak pernah sebagai teks asli. |
| A5 | Setelah register berhasil, pelanggan diarahkan ke halaman Login. |
| A6 | Pelanggan masuk dengan email dan password. |
| A7 | Setelah login, pelanggan kembali ke halaman asalnya (checkout bila datang dari checkout, selain itu beranda). |
| A8 | Pesan gagal login sama untuk email yang tidak ditemukan dan password yang salah: "email atau password salah". |
| A9 | Akun pelanggan yang dinonaktifkan tidak dapat masuk dan menerima pesan bahwa akunnya dinonaktifkan. |
| A10 | Checkout menolak pengunjung yang belum login dan mengarahkannya ke Login. Isi keranjang tetap utuh setelah login. |

### 5.2 Pelanggan: login dengan akun Google (API App Utama)

| Kode | Kebutuhan |
| --- | --- |
| G1 | Halaman Login dan Register memiliki tombol "Masuk dengan Google". |
| G2 | Setelah pelanggan memilih akun Google, frontend mengirim bukti identitas dari Google ke API. API memverifikasinya ke Google sebelum mempercayainya. |
| G3 | Bila email Google belum terdaftar, sistem membuat akun pelanggan baru secara otomatis. Akun itu tidak punya password. |
| G4 | Bila email Google sudah terdaftar sebagai pelanggan dan email itu terverifikasi oleh Google, sistem memakai akun yang sama dan menghubungkannya ke akun Google. |
| G5 | Akun pelanggan yang dinonaktifkan tidak dapat masuk lewat Google. |
| G6 | Akun yang dibuat lewat Google dan belum punya password menerima pesan yang mengarahkan ke tombol Google bila mencoba login dengan password. |
| G7 | Setelah login Google berhasil, perilaku pengalihan sama dengan A7. |

### 5.3 Admin: login di dashboard (API Dashboard)

| Kode | Kebutuhan |
| --- | --- |
| D1 | Admin masuk dengan email dan password melalui halaman Login dashboard. |
| D2 | Pesan gagal login sama untuk email yang tidak ditemukan dan password yang salah. |
| D3 | Akun admin dibuat langsung oleh tim (seeder). Tidak ada register admin. |
| D4 | Setelah login, admin masuk ke dashboard. |

### 5.4 Token, sesi, dan pembatasan akses (kedua API)

| Kode | Kebutuhan |
| --- | --- |
| T1 | Login yang berhasil mengembalikan token akses dan data singkat akun (nama, email, dan jenis akun). Frontend menyertakan token di setiap permintaan ke fungsi yang dilindungi. |
| T2 | Logout mencabut token, sehingga token itu tidak bisa dipakai lagi. |
| T3 | Permintaan tanpa token atau dengan token tidak valid dijawab 401. Permintaan dengan token valid tetapi tidak berhak dijawab 403. |
| T4 | Seluruh fungsi API Dashboard, selain login, menolak permintaan tanpa token admin. |
| T5 | API Dashboard menolak token milik pelanggan, walaupun token itu valid di database yang sama. |
| T6 | Fungsi pelanggan di API App Utama (checkout, Pesanan Saya) menolak permintaan tanpa token pelanggan. |

## 6. Kebutuhan fungsional: Manajemen Produk, Kategori dan Harga (PPL-14, API Dashboard)

### 6.1 Kategori

| Kode | Kebutuhan |
| --- | --- |
| K1 | Admin dapat menambah kategori dengan nama yang unik. |
| K2 | Admin dapat mengubah nama kategori. |
| K3 | Admin dapat menghapus kategori hanya bila belum punya produk. Bila sudah punya produk, penghapusan ditolak dengan pesan yang menjelaskan alasannya. |
| K4 | Daftar kategori menampilkan jumlah produk di tiap kategori. |

### 6.2 Produk

| Kode | Kebutuhan |
| --- | --- |
| P1 | Admin dapat menambah produk dengan data: kategori, nama produk, merek, berat (gram), harga modal, dan harga eceran sebagai data wajib. Ingredients singkat dan nomor BPOM bersifat opsional. |
| P2 | Admin dapat mengubah data produk. |
| P3 | Admin dapat menonaktifkan dan mengaktifkan kembali produk. Produk tidak dihapus permanen. Produk nonaktif tidak tampil di toko tetapi riwayat pesanan lama tetap utuh. |
| P4 | Admin melihat daftar produk dengan foto utama, nama, merek, kategori, harga eceran, harga pack, total stok, label stok, dan status aktif. Daftar dibagi per halaman. |
| P5 | Daftar produk dapat dicari berdasarkan nama atau merek, dan disaring berdasarkan kategori, label stok, dan status aktif. |
| P6 | Label stok dihitung otomatis dari total stok semua varian: Tersedia bila stok lebih dari nol, Pre-Order bila stok nol dan produk bisa Pre-Order, Stok Habis bila stok nol dan tidak bisa Pre-Order. Label tidak diisi manual. |

### 6.3 Harga dan pack

| Kode | Kebutuhan |
| --- | --- |
| H1 | Harga eceran ditetapkan sebagai harga modal ditambah Rp1.000 sampai Rp2.000. Sistem memeriksa aturan ini dan memberi peringatan bila harga eceran di luar rentang. |
| H2 | Produk dapat dijual per pack. Admin mengisi jumlah pcs dalam satu pack dan harga per pcs saat membeli pack. |
| H3 | Harga per pcs untuk pack harus berada di antara harga modal dan harga eceran. Bila tidak, penyimpanan ditolak dengan pesan kesalahan. |
| H4 | Produk yang tidak dijual per pack boleh mengosongkan isi pack dan harga pack. |

### 6.4 Varian dan stok

| Kode | Kebutuhan |
| --- | --- |
| V1 | Satu produk punya satu atau lebih varian. Produk tanpa varian memakai satu varian bernama "Standar". |
| V2 | Nama varian unik dalam satu produk. |
| V3 | Stok disimpan per varian. Admin mengisi stok awal saat membuat varian. Penambahan stok berikutnya dari grosir dilakukan lewat Barang Masuk. |
| V4 | Varian dapat dinonaktifkan tanpa dihapus. |

### 6.5 Pre-Order

| Kode | Kebutuhan |
| --- | --- |
| R1 | Admin dapat menandai produk sebagai bisa Pre-Order. |
| R2 | Bila Pre-Order aktif, admin wajib mengisi estimasi 1 sampai 6 hari. Bila tidak aktif, estimasi dikosongkan. |

### 6.6 Foto

| Kode | Kebutuhan |
| --- | --- |
| F1 | Admin dapat menambah beberapa foto untuk satu produk dan menghapus foto. |
| F2 | Admin dapat menentukan satu foto utama dan mengatur urutan foto. |
| F3 | Foto asli disimpan apa adanya. Watermark toko ditambahkan saat foto ditampilkan di toko. |

## 7. Daftar endpoint (usulan, mengikuti spesifikasi API Evan)

Tabel ini usulan awal yang dibuat dari kebutuhan di atas. Nama dan bentuk akhirnya mengikuti spesifikasi API yang disepakati dengan Evan.

**API App Utama**

| Metode | Endpoint | Fungsi | Akses |
| --- | --- | --- | --- |
| POST | `/api/register` | Register pelanggan | Publik |
| POST | `/api/login` | Login pelanggan dengan email dan password | Publik |
| POST | `/api/login/google` | Login atau daftar dengan akun Google | Publik |
| POST | `/api/logout` | Cabut token | Pelanggan |
| GET | `/api/me` | Data akun yang sedang masuk | Pelanggan |

**API Dashboard**

| Metode | Endpoint | Fungsi | Akses |
| --- | --- | --- | --- |
| POST | `/api/login` | Login admin | Publik |
| POST | `/api/logout` | Cabut token | Admin |
| GET | `/api/me` | Data akun admin | Admin |
| GET, POST | `/api/kategori` | Daftar dan tambah kategori | Admin |
| PUT, DELETE | `/api/kategori/{id}` | Ubah nama dan hapus kategori | Admin |
| GET, POST | `/api/produk` | Daftar (cari dan saring) dan tambah produk | Admin |
| GET, PUT | `/api/produk/{id}` | Detail dan ubah produk | Admin |
| PATCH | `/api/produk/{id}/status` | Aktifkan atau nonaktifkan produk | Admin |
| POST | `/api/produk/{id}/varian` | Tambah varian | Admin |
| PUT, PATCH | `/api/varian/{id}` | Ubah varian dan statusnya | Admin |
| POST | `/api/produk/{id}/foto` | Unggah foto | Admin |
| PATCH, DELETE | `/api/foto/{id}` | Atur foto utama dan urutan, atau hapus foto | Admin |

## 8. Perubahan database yang diperlukan

| Perubahan | Alasan |
| --- | --- |
| Ganti tabel bawaan `users` dengan `admin` dan `pelanggan` sesuai ERD | Rancangan kita memisahkan akun admin dan pelanggan |
| `pelanggan.password_hash` boleh kosong | Pelanggan yang masuk lewat Google tidak punya password (G3, G6) |
| Tambah `pelanggan.google_id` (unik, boleh kosong) | Menghubungkan akun pelanggan dengan akun Google (G4) |
| Tabel token akses (`personal_access_tokens`) dipakai bersama | Sudah ada di migration API Dashboard, dipakai untuk login admin dan pelanggan |
| Tabel katalog: `kategori`, `produk`, `produk_varian`, `produk_foto` | Kebutuhan PPL-14 |

ERD dan skrip database perlu diperbarui untuk dua perubahan pertama yang berkaitan dengan Google.

## 9. Aturan bisnis ringkas

| Aturan | Isi |
| --- | --- |
| Identitas akun | Email dan username pelanggan unik di tabel pelanggan. Email admin unik di tabel admin. |
| Akun admin | Dibuat langsung oleh tim, tidak ada register admin. |
| Login Google | Hanya untuk pelanggan. Admin tetap memakai email dan password. |
| Akses | Pelanggan tidak dapat memakai fungsi admin, dan token pelanggan ditolak oleh API Dashboard. |
| Penghapusan | Produk, varian, dan akun tidak dihapus permanen, hanya dinonaktifkan. Kategori hanya dihapus bila belum punya produk. |
| Harga eceran | Modal + Rp1.000 sampai Rp2.000. |
| Harga pack | Harga per pcs berada di antara modal dan eceran. |
| Pre-Order | Estimasi 1 sampai 6 hari bila aktif. |
| Label stok | Dihitung dari stok, tidak disimpan. |

## 10. Kebutuhan non-fungsional

- **Keamanan:** password terenkripsi, token dicabut saat logout, pesan gagal login tidak membocorkan apakah emailnya terdaftar, dan percobaan login dibatasi jumlahnya agar tidak bisa ditebak berulang.
- **Rahasia konfigurasi:** Client ID dan Client Secret Google, kunci aplikasi, dan kata sandi database hanya disimpan di berkas `.env`, tidak pernah di repo.
- **CORS:** API hanya menerima permintaan dari alamat frontend app utama dan dashboard.
- **Validasi:** setiap isian diperiksa di tampilan dan di sisi server. Kesalahan dikembalikan dengan kode 422 dan pesan per kolom dalam bahasa yang mudah dipahami.
- **Integritas data:** tidak ada penghapusan permanen yang merusak riwayat pesanan.
- **Tampilan:** mengikuti spesifikasi desain panel admin RiasKu (rail ikon dengan flyout, palet soft pastel, font Poppins) dan responsif untuk layar tablet sampai ponsel.
- **Pengujian:** fungsi API diuji otomatis memakai kerangka pengujian yang sudah ada di kedua project.

## 11. Kriteria penerimaan

**Autentikasi**
1. Pengunjung dapat mendaftar dengan data valid dan diarahkan ke Login. Pendaftaran dengan email atau username yang sudah dipakai ditolak.
2. Pelanggan yang login dengan email dan password kembali ke halaman asalnya.
3. Login dengan password salah atau email tidak terdaftar menampilkan pesan yang sama.
4. Pelanggan baru yang masuk lewat Google otomatis punya akun, dan masuk lagi dengan Google berikutnya memakai akun yang sama.
5. Pelanggan yang sudah punya akun dengan email yang sama dihubungkan, bukan dibuatkan akun ganda.
6. Akun pelanggan nonaktif tidak dapat masuk, baik dengan password maupun Google.
7. Admin dapat login di dashboard dan mendapat token. Token pelanggan ditolak oleh API Dashboard dengan kode 403.
8. Setelah logout, token tidak berlaku lagi dan fungsi yang dilindungi menjawab 401.
9. Pengunjung yang menekan checkout diarahkan ke Login, dan isi keranjang masih ada setelah login.

**Manajemen produk**
1. Admin dapat membuat kategori, mengubah namanya, dan menghapusnya hanya bila kosong.
2. Admin dapat mengisi form produk lengkap, menekan simpan, dan produk muncul di daftar setelah halaman dimuat ulang.
3. Peringatan muncul bila harga eceran di luar modal + Rp1.000 sampai Rp2.000. Penyimpanan ditolak bila harga pack tidak berada di antara modal dan eceran.
4. Estimasi Pre-Order wajib 1 sampai 6 hari saat Pre-Order aktif.
5. Produk tanpa varian otomatis memiliki varian "Standar".
6. Label stok sesuai kondisi stok dan status Pre-Order pada tiga keadaan: stok ada, stok nol dengan Pre-Order, stok nol tanpa Pre-Order.
7. Produk yang dinonaktifkan hilang dari tampilan toko tetapi tetap ada di daftar admin dengan status Nonaktif dan dapat diaktifkan kembali.
8. Admin dapat menambah foto, menentukan foto utama, dan mengubah urutan.

## 12. Ketergantungan

- Spesifikasi API dan rancangan database dari Evan harus selaras dengan dokumen ini sebelum tampilan disambungkan ke data asli.
- Project Google Cloud dengan OAuth Client ID: alamat redirect pengembangan dan produksi didaftarkan, dan Client ID serta Secret dibagikan ke tim lewat jalur aman (bukan repo). Selama layar persetujuan berstatus Testing, hanya akun penguji yang bisa masuk. Statusnya diubah ke produksi sebelum deploy di Sprint 5.
- Alamat redirect produksi harus HTTPS, sejalan dengan deploy dan HTTPS di Sprint 5.
- Desain UI Login, Register, Produk, dan Kategori dari tim UI/UX.
- PPL-13 harus selesai lebih dulu daripada PPL-14, karena pengelolaan produk memerlukan login admin.
- PPL-15 dan PPL-16 (katalog dan keranjang) memakai data produk dari PPL-14. API App Utama perlu membaca data produk yang ditulis API Dashboard.

## 13. Hal yang belum diputuskan

| Hal | Usulan sementara |
| --- | --- |
| Halaman login | Terpisah per aplikasi: login admin di dashboard, login dan register pelanggan di app utama. Ini mengubah rancangan awal yang memakai satu halaman login untuk keduanya |
| Pemilik migration | Satu project saja yang memegang migration, mulai dari `dashboard-restful-api` (sudah punya migration token), dan project lain memakai database yang sama |
| Berbagi Model dan aturan bisnis antar dua API | Ditentukan bersama Evan supaya aturan yang sama (misalnya label stok) tidak berbeda hasil |
| Penghubungan akun Google dengan akun email yang sudah ada | Dihubungkan hanya bila email dari Google terverifikasi |
| Username untuk akun yang dibuat lewat Google | Dibuat otomatis dari nama atau bagian depan email, dibuat unik |
| Panjang minimum password | Delapan karakter |
| Aturan harga eceran di luar rentang | Hanya peringatan, tidak memblokir penyimpanan |
| Batas ukuran dan jumlah foto per produk, serta format yang diterima | Ditetapkan bersama Evan |
| Pengelolaan akun pelanggan oleh admin (misalnya menonaktifkan akun) | Di luar backlog sprint ini |

## 14. Rujukan desain

- ERD dan skrip database: tabel admin, pelanggan, kategori, produk, produk_varian, produk_foto.
- Use Case Diagram Pelanggan dan Pemilik.
- Activity Diagram: Login, Register, Kelola Produk.
- Sequence Diagram: Register dan Login, Kelola Produk.
- Class Diagram (kelas Produk dan ProdukVarian).
- Spesifikasi desain panel admin RiasKu.
- Repo: `riasku-restful-api`, `dashboard-restful-api`, `frontend`, dan `docs` di branch `development`.

Diagram dan ERD yang ada masih berdasarkan satu halaman login dan belum memuat login Google. Perlu diperbarui bila usulan di bagian 8 dan 13 disetujui.
