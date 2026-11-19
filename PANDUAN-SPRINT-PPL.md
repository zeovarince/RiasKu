# Panduan Menjalankan, Menguji & Dokumentasi Sprint RiasKu
## Dokumentasi Lengkap Sprint PPL-15, PPL-16, PPL-19, dan PPL-20

Dokumentasi ini disusun sebagai laporan komprehensif seluruh pekerjaan yang telah diselesaikan pada sprint ini, sekaligus panduan teknis bagi anggota tim pengembang dan penguji (*tester*) untuk menjalankan serta memvalidasi seluruh fungsionalitas aplikasi RiasKu di lingkungan lokal (*localhost*).

---

## 📌 Ringkasan Seluruh Pekerjaan yang Diselesaikan pada Sprint Ini

Berikut rekapitulasi lengkap fitur, perbaikan bug, dan optimasi yang telah diimplementasikan:

| Modul / Fitur | Kategori | Cakupan & Fungsionalitas yang Dikerjakan |
| :--- | :--- | :--- |
| **PPL-15** | **Katalog Produk & Keranjang** | Penjelajahan produk kosmetik, filter kategori, pemilihan varian (warna/ukuran), informasi stok real-time, penambahan item ke keranjang belanja, kontrol kuantitas (`+` / `−`), serta kalkulasi otomatis subtotal dan total harga. |
| **PPL-16** | **Sinkronisasi Keranjang Belanja** | Integrasi API keranjang `/api/cart`, penyimpanan item di database PostgreSQL, dan algoritma **Smart Bidirectional Sync** yang menjamin data keranjang tidak hilang saat me-refresh browser (F5) maupun berpindah halaman. |
| **PPL-19** | **Pembayaran Online (Midtrans Sandbox)** | Integrasi resmi Midtrans Core API (`POST /v2/charge`) untuk: <br>• **QRIS** dinamis lengkap dengan gambar QR dan string payload.<br>• **Virtual Account (VA)** Bank BCA, BNI, BRI, dan Mandiri Bill Key.<br>• **E-Wallet** GoPay & ShopeePay.<br>• **Live Status Sync** via auto-polling tiap 5 detik dan tombol manual.<br>• **Webhook Midtrans** dengan validasi signature SHA-512 dan otomatisasi restorasi stok jika pembayaran dibatalkan/kedaluwarsa. |
| **PPL-20** | **Pembayaran COD Surabaya** | Validasi pengiriman wilayah khusus: **hanya menerima COD untuk Kota Surabaya** (*case-insensitive*). Menolak pengiriman COD ke luar Surabaya dengan respon HTTP 422 dan pesan error validasi interaktif. Pesanan COD otomatis langsung berstatus `diproses` (*processing*). |
| **Autentikasi Terpadu & Google Sign-In** | **Sistem Keamanan & Akun** | • **Login Terpadu**: Akun Admin sekarang dapat login langsung dari form login pelanggan (`/auth/login`), sistem otomatis mendeteksi role admin dan mengarahkan ke `/admin/dashboard`.<br>• **Google Sign-In & Sign-Up**: Integrasi resmi OAuth 2.0 Google Client ID dengan tombol berlogo Google rapi.<br>• **Konfirmasi Kata Sandi**: Pendaftaran manual dilengkapi kolom konfirmasi password (`password_confirmation`) dan validasi kecocokan kata sandi.<br>• **Seeder Default**: Disediakan akun siap pakai untuk admin (`admin@riasku.com`) dan pelanggan (`surya@riasku.com` & `pelanggan@riasku.com`). |
| **UI/UX Header Profil Konsisten** | **Antarmuka Pengguna** | • **Header Profil Pelanggan**: Kapsul profil dengan avatar inisial, nama display, panah chevron, dropdown menu (*Lihat Profil, Cek Pesanan, Keranjang, Logout*), dan dialog modal rincian akun resmi.<br>• **Header Profil Admin**: Topbar admin diperbarui sehingga memiliki desain dropdown dan modal profil yang seragam dan elegan seperti header pengguna, menghapus tombol bulat lama yang terpotong.<br>• **Logout Bersih**: Sesi logout otomatis membersihkan item keranjang di client untuk keamanan akun. |
| **Sticky Header & Responsif Mobile** | **Layout & Desain** | • **Sticky Header**: Navbar toko dan topbar admin kini terkunci di bagian atas saat halaman di-scroll (*fixed/sticky*) dengan efek *blur glassmorphism*.<br>• **Visual Hero Image**: Gambar ilustrasi halaman login/register proporsional (*tidak terpotong mepet atau terlalu zoom*).<br>• **Responsif Penuh**: Tata letak form checkout, pelacakan pesanan, kartu katalog, dan dashboard admin dioptimalkan untuk mobile & tablet (`max-width: 768px`, `680px`, `640px`, `520px`). |
| **Quality Assurance (QA)** | **Testing & Build** | • **Unit & Feature Tests**: 54 test backend lulus 100% (308 assertions).<br>• **Frontend Production Build**: Vite build lulus 100% tanpa error maupun peringatan. |

---

## 💻 Kebutuhan Sistem (Prerequisites)

Pastikan lingkungan lokal Anda memenuhi persyaratan berikut:
1. **PHP**: Versi `>= 8.2` (dengan ekstensi `pdo_pgsql`, `pgsql`, `openssl`, `mbstring`, `curl`, `fileinfo`).
2. **Composer**: Versi `>= 2.x`.
3. **Node.js & npm**: Node.js `>= 18.x` dan npm `>= 9.x`.
4. **PostgreSQL**: Server aktif di port `5432` dengan database bernama `riasku`.
5. **Git**: Untuk manajemen branch dan Pull Request.

---

## 🚀 Panduan Setup & Menjalankan Proyek di Lokal

### Langkah 1: Ambil Branch Kerja
Buka terminal dan arahkan ke folder proyek `RiasKu`:
```bash
git fetch origin
git checkout feature/ppl-19-20-payment-cod
git pull origin feature/ppl-19-20-payment-cod
```

---

### Langkah 2: Setup Backend API (`backend-api/`)

1. **Masuk ke folder backend**:
   ```bash
   cd backend-api
   ```

2. **Install dependensi Composer**:
   ```bash
   composer install
   ```

3. **Salin file konfigurasi environment**:
   ```bash
   # Windows PowerShell:
   Copy-Item .env.example .env

   # Linux / macOS / Git Bash:
   cp .env.example .env
   ```

4. **Konfigurasi Database PostgreSQL di `.env`**:
   Buka file `.env` di text editor dan sesuaikan kredensial PostgreSQL lokal Anda:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=riasku
   DB_USERNAME=postgres
   DB_PASSWORD=password_postgres_anda
   ```

5. **Konfigurasi Kunci Midtrans Sandbox & Google OAuth di `.env`**:
   Pastikan kunci Midtrans dan Google telah terisi:
   ```env
   MIDTRANS_MERCHANT_ID=G141532128
   MIDTRANS_CLIENT_KEY=SB-Mid-client-WoxvUf3e2e-P_k_m
   MIDTRANS_SERVER_KEY=SB-Mid-server-x-lx1ERHJDvIsrXEPiL3-ByI
   MIDTRANS_IS_PRODUCTION=false
   MIDTRANS_BASE_URL=https://api.sandbox.midtrans.com

   GOOGLE_CLIENT_ID=630970103407-d64mdp7n6mck52alll9vjdjc0ffsn0js.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=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
   ```
   > **Catatan:** Jika `MIDTRANS_SERVER_KEY` dikosongkan, backend secara otomatis beralih ke mode simulator internal lokal tanpa mengalami kendala.

6. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

7. **Jalankan Migrasi Database & Seeder**:
   ```bash
   php artisan migrate:fresh --seed
   ```
   *Perintah ini akan membuat seluruh tabel di PostgreSQL dan mengisinya dengan data awal (kategori, produk kosmetik, varian, akun admin default, dan akun pelanggan default).*

8. **Jalankan Server Backend**:
   ```bash
   php artisan serve
   ```
   Backend aktif di: **`http://127.0.0.1:8000`**

---

### Langkah 3: Setup Frontend (`frontend/`)

1. **Buka terminal baru**, lalu masuk ke folder frontend:
   ```bash
   cd frontend
   ```

2. **Install dependensi Node.js**:
   ```bash
   npm install
   ```

3. **Pastikan file `.env` Frontend Terisi**:
   File `frontend/.env` harus berisi:
   ```env
   VITE_API_BASE_URL=http://127.0.0.1:8000/api
   VITE_CART_API_ENABLED=true
   VITE_GOOGLE_CLIENT_ID=630970103407-d64mdp7n6mck52alll9vjdjc0ffsn0js.apps.googleusercontent.com
   ```

4. **Jalankan Frontend Dev Server**:
   ```bash
   npm run dev
   ```
   Frontend aktif di: **`http://localhost:5173`**

---

## 🔑 Kredensial Akun Pengujian (Siap Pakai dari Seeder)

| Peran (Role) | Nama / Username | Alamat Email | Kata Sandi | Tujuan Uji |
| :--- | :--- | :--- | :--- | :--- |
| **Pelanggan Utama** | `surya` | `surya@riasku.com` | `password123` | Belanja, kelola keranjang, checkout COD Surabaya & Midtrans Sandbox. |
| **Pelanggan Kedua** | `pelanggan` | `pelanggan@riasku.com` | `password123` | Uji coba transaksi dengan akun pelanggan sekunder. |
| **Admin Toko** | `Admin RiasKu` | `admin@riasku.com` | `password123` | Dashboard admin, kelola produk, pesanan, verifikasi COD, laporan penjualan. |

> **Keunggulan Sistem Login:**
> - Akun Admin dapat login langsung dari form login biasa (`/auth/login`). Sistem mendeteksi `account_type === 'admin'` dan mengarahkannya otomatis ke `/admin/dashboard`.
> - Admin juga dapat login melalui tautan khusus di `/admin/login`.
> - Pengguna juga dapat mendaftar/login menggunakan **Akun Google** via tombol resmi Google.

---

## 🧪 Skenario Pengujian Fitur Secara Menyeluruh

### Skenario 1: Autentikasi, Profil Dropdown & Keamanan Sesi
1. Buka browser di `http://localhost:5173`.
2. Klik tombol **Masuk / Daftar** di navbar atas.
3. Masukkan email `surya@riasku.com` dan kata sandi `password123`.
4. Setelah login berhasil:
   - Tombol login berubah menjadi tombol profil pill: lingkaran avatar berinisial `S`, nama `surya`, dan ikon panah.
   - Menu **"Checkout & COD"** dan **"Cek Pesanan"** muncul di navigasi utama.
5. Klik pada tombol profil pill:
   - Terbuka dropdown menu berisi info pengguna, tombol **Lihat Profil**, **Cek Status Pesanan**, **Keranjang Belanja**, dan **Keluar (Logout)**.
   - Klik **Lihat Profil**: Terbuka modal dialog informasi akun resmi pelanggan.
6. Coba gulir (*scroll*) halaman ke bawah:
   - Navbar tetap berada di posisi atas (*sticky/fixed*) dengan efek *blur glassmorphism*.

---

### Skenario 2: Keranjang Belanja & Smart Sync (PPL-15 & PPL-16)
1. Buka menu **Katalog Produk**, pilih salah satu produk kosmetik.
2. Tentukan varian dan jumlah (misal 2 buah), lalu klik **＋ Tambah ke Keranjang**.
3. Ikon keranjang di navbar menampilkan badge angka merah `2`.
4. Buka halaman **Keranjang Belanja** (`/cart`):
   - Gunakan tombol `+` dan `−` untuk mengubah jumlah item. Subtotal dan ringkasan harga langsung diperbarui.
   - Tekan **F5 (Refresh browser)**: Buktikan item keranjang **tetap utuh dan tersimpan di database** (*Smart Bidirectional Sync*).
5. Uji Sesi Logout:
   - Buka dropdown profil -> Klik **Keluar (Logout)**.
   - Item keranjang lokal langsung bersih (0 item), dan menu Checkout otomatis disembunyikan.
6. Login kembali dengan akun yang sama: Item keranjang Anda otomatis dimuat kembali dari server.

---

### Skenario 3: Checkout Pembayaran COD Surabaya (PPL-20)
1. Di halaman keranjang, klik **Lanjut ke Checkout & COD →**.
2. Masukkan data penerima dengan kota **`Kota Surabaya`** atau **`Surabaya`** (misal alamat Gubeng / Rungkut).
3. Pilih metode pembayaran: **COD (Bayar di Tempat)**.
4. Klik **Pesan Sekarang**:
   - Pesanan berhasil dibuat!
   - Status pesanan langsung berstatus **`diproses`** (*processing*).
   - Status pembayaran berstatus **`menunggu_pembayaran_cod`**.
5. **Uji Kasus Negatif (Luar Surabaya)**:
   - Coba lakukan checkout COD dengan kota **`Sidoarjo`**, **`Gresik`**, atau **`Jakarta`**.
   - Sistem akan memblokir proses dan menampilkan pesan error:
     > *"Metode pembayaran COD hanya tersedia untuk pengiriman wilayah Kota Surabaya."*

---

### Skenario 4: Pembayaran Online Midtrans Real-Time (PPL-19)
1. Pada form checkout, masukkan alamat pengiriman (bisa ke kota manapun di seluruh Indonesia).
2. Pilih metode **Pembayaran Online**:
   - **QRIS**: Modal menampilkan gambar QR Code asli Midtrans, string payload, dan timer countdown 15 menit.
   - **Virtual Account (BCA / BNI / BRI)**: Modal menampilkan nomor VA resmi Midtrans beserta tombol *Copy* dan petunjuk ATM/m-Banking.
   - **Mandiri Bill**: Modal menampilkan Kode Perusahaan (`70012`) dan Nomor Bill Key.
   - **GoPay / ShopeePay**: Modal menampilkan QR Code E-Wallet.
3. Klik **Bayar Sekarang**. Modal pembayaran akan aktif melakukan auto-polling status tiap 5 detik.

---

### Skenario 5: Simulasi Pelunasan Pembayaran di Midtrans Simulator
1. Salin nomor Virtual Account yang muncul pada modal pembayaran (contoh VA BCA: `12890xxxxxxxx`).
2. Buka tab baru di browser: **`https://simulator.sandbox.midtrans.com/`**.
3. Pilih menu **BCA Virtual Account**, tempelkan nomor VA, klik **Inquire**, lalu klik **Pay**.
4. Kembali ke tab aplikasi RiasKu:
   - Dalam 5 detik (atau klik tombol **Cek Status Terkini**), status transaksi otomatis berubah menjadi **Lunas (Dibayar)** dan pesanan masuk ke tahap **Diproses**!

---

### Skenario 6: Dashboard Administrator & Profil Dropdown Admin
1. Login dengan akun admin: `admin@riasku.com` / `password123`.
2. Sistem akan mengarahkan Anda ke `/admin/dashboard`.
3. Perhatikan topbar admin di pojok kanan atas:
   - Terdapat indikator status **● Online**.
   - Terdapat tombol kapsul profil admin beravatar inisial `A` dan nama `Admin RiasKu`.
4. Klik tombol profil admin:
   - Terbuka dropdown menu berisi info admin, tautan **Lihat Profil Admin**, **Lihat Toko Pelanggan**, **Pengaturan Toko**, dan tombol **Keluar (Logout)**.
   - Klik **Lihat Profil Admin**: Terbuka modal dialog rincian peran (*Administrator Utama*), email resmi, dan hak akses penuh.

---

### Skenario 7: Menjalankan Pengujian Otomatis (Automated Tests)

Buka terminal di folder `backend-api` dan jalankan perintah pengujian:

```bash
# Jalankan seluruh test suite backend (54 tests)
php artisan test

# Atau jalankan per-modul:
php artisan test tests/Feature/CustomerAuthTest.php
php artisan test tests/Feature/CustomerPaymentTest.php
php artisan test tests/Feature/CustomerCartTest.php
```

> **Hasil:** Seluruh 54 test menghasilkan status **PASS (100% hijau)** dengan 308 assertions valid.

---

## 🚀 Panduan Melakukan Pull Request (PR)

Untuk memasukkan hasil pekerjaan sprint ini ke repositori utama:

1. **Pastikan Seluruh Perubahan di-Commit dan di-Push**:
   ```bash
   git status
   git add .
   git commit -m "feat: complete sprint ppl-15,16,19,20 with midtrans, cod surabaya, unified auth, and responsive UI"
   git push origin feature/ppl-19-20-payment-cod
   ```

2. **Buka Halaman GitHub Repository**:
   - Kunjungi: `https://github.com/zeovarince/RiasKu`
   - Akan muncul banner kuning *"feature/ppl-19-20-payment-cod had recent pushes"*.
   - Klik tombol **Compare & pull request**.

3. **Konfigurasi Target Branch**:
   - **Base repository/branch**: Pilih branch tujuan (biasanya `development`).
   - **Compare branch**: Pastikan memilih `feature/ppl-19-20-payment-cod`.

4. **Isi Deskripsi Pull Request**:
   - Anda dapat menyalin tabel dari **Bagian 1 (Ringkasan Seluruh Pekerjaan)** pada dokumen ini sebagai deskripsi PR agar tim/dosen penguji dapat melihat rangkuman kontribusi sprint Anda dengan jelas.
   - Klik **Create pull request**.

---

## 🛠️ FAQ & Troubleshooting

1. **Mengapa menu "Checkout & COD" tidak tampil di navbar?**  
   *Jawaban:* Menu Checkout sengaja diproteksi agar hanya muncul saat pengguna sudah login sebagai pelanggan. Silakan login terlebih dahulu.

2. **Kapan menggunakan simulasi vs Midtrans asli?**  
   *Jawaban:* Jika kredensial `MIDTRANS_SERVER_KEY` di `.env` backend terisi, aplikasi otomatis menggunakan Midtrans Sandbox live. Jika dikosongkan, backend beralih ke local simulator internal tanpa error.

3. **Pendaftaran manual kata sandi tidak cocok?**  
   *Jawaban:* Form register kini telah dilengkapi validasi kolom konfirmasi password. Pastikan kata sandi dan konfirmasi kata sandi bernilai sama.

---

*Dokumentasi ini diperbarui pada 20 November 2026 sebagai panduan resmi proyek akhir RiasKu.*
