# PPL-29 — Pengujian Fungsional QA & Perbaikan Bug Sprint 5

**Issue GitHub:** `#34` (ditutup melalui **PR #58** ke `development`)  
**PIC:** `suryamaulana98`  
**Sprint:** Sprint 5

---

## 1. Ringkasan & Metodologi Pengujian

Pengujian fungsional QA dilakukan secara menyeluruh (*end-to-end*) terhadap seluruh fitur **Customer** dan **Admin** dengan kondisi lingkungan nyata:

- **Backend:** Laravel (`backend-api/`, `http://127.0.0.1:8000`) terhubung langsung ke database **PostgreSQL** (`riasku`).
- **Frontend:** Vue 3 + Vite (`frontend/`, `http://localhost:5173`) dengan `VITE_USE_MOCK_DATA=false` dan `VITE_CART_API_ENABLED=true`.
- **Browser Automation:** Playwright MCP untuk pengujian alur interaksi UI, responsivitas Desktop (`1280×800`) & Mobile (`390×844`), pemantauan Console Error, Network Request (`4xx`/`5xx`), dan deteksi broken images.
- **Automated Regression Tests:** Pest / PHPUnit (`php artisan test`) dan Vite production build (`npm run build`).

---

## 2. Rangkuman Perbaikan Pra-QA Sprint 5 (Commit `de135b7`)

Sebelum siklus pengujian penuh Issue #34, beberapa penyempurnaan lintas modul Sprint 2–5 telah disatukan dan dikunci melalui `backend-api/tests/Feature/Sprint5BugfixAndDashboardTest.php`:

1. **Dashboard Admin Live API (`GET /api/admin/dashboard`):** Menambahkan `Api\Admin\DashboardController` beserta integrasinya di `frontend/src/views/admin/DashboardView.vue` untuk menampilkan metrik KPI live, grafik pendapatan, distribusi kategori, dan pesanan terbaru.
2. **Validasi & Keamanan Pembayaran (`MidtransService`, `OrderController`, `PaymentController`, `PaymentModal.vue`):**
   - Menolak fallback simulasi diam-diam dan payload error HTTP 200 di production.
   - Mengendalikan tombol simulator sandbox melalui `MIDTRANS_ALLOW_SANDBOX_SIMULATOR` (selalu ditolak di production).
   - Mendukung pembatalan pesanan online pending (`POST /api/orders/{id}/cancel`) dengan restorasi stok sambil menjaga keranjang.
   - Menolak string kota COD yang menipu seperti `"Bukan Surabaya"`.
3. **Katalog, Checkout & Performa (`ProdukController`, `ShippingController`, `OrderController`, `SqlSearch`):**
   - Mendukung filter kategori berbasis `slug`, sorting `rating` bagi produk unrated, dan server-side pagination.
   - Menghilangkan N+1 query ulasan di `GET /api/orders` dan melakukan batching lookup item di `POST /api/shipping/rates`.

---

## 3. Laporan Hasil Pengujian QA End-to-End (Issue #34)

### 3.1 Fitur Sisi Customer

| Modul / Fitur | Skenario Uji | Status Awal | Bug yang Ditemukan | Perbaikan & File Terubah | Status Akhir |
| :--- | :--- | :---: | :--- | :--- | :---: |
| **Landing Page & Hero Slider** | Layout desktop/mobile, slider 3 slide, filter best seller, responsivitas | ⚠️ Fail | Pill filter Best Seller menampilkan kategori kosong yang tidak memiliki produk pada daftar best seller; visual hero masih berupa ikon kartun | Menurunkan pill filter secara dinamis dari kategori produk yang tampil; mengganti ke-3 slide hero dengan foto HD asli + `fetchpriority="high"` di `HomeView.vue` & `main.css` | ✅ Pass |
| **Autentikasi Modal (Login & Register)** | Register & login pelanggan/admin via modal header | ❌ Fail | Modal login/register tidak memanggil `auth.setSession()` & `cart.hydrateCart()`, tidak mengirim `username` & `password_confirmation`, serta tidak mengarahkan akun admin ke `/admin/dashboard` | Memperbarui alur submit login/register, validasi form, sinkronisasi sesi & keranjang, redirect admin, dan atribut a11y (`aria-labelledby`, `role="alert"`, `role="status"`) di `GlobalModalHost.vue` | ✅ Pass |
| **Katalog & Detail Produk (PPL-15)** | Daftar produk, foto, stok, search, filter kategori, sorting, pagination, detail & varian | ✅ Pass | Mayoritas produk masih memakai ikon SVG kartun (permintaan peningkatan visual) | Menambahkan 10 foto HD asli ke `frontend/public/images/products/`, memperbarui `KatalogSeeder.php`, `ProdukFoto.php`, dan `ProdukFotoController.php` | ✅ Pass |
| **Keranjang Belanja (PPL-16)** | Tambah/ubah/hapus item, subtotal, empty state, sinkronisasi Pinia & API | ✅ Pass | Tidak ada bug fungsional setelah perbaikan hidrasi login modal | Terverifikasi sinkronisasi F5 dan navigasi checkout lancar | ✅ Pass |
| **Pembayaran Online (PPL-19)** | Checkout QRIS, VA BCA/BNI/BRI, Mandiri Bill, E-Wallet GoPay/ShopeePay/DANA | ❌ Fail | Fallback simulasi lokal menimpa `payment_type` e-wallet (`gopay`/`shopeepay`/`dana`) menjadi `qris`, sehingga modal pembayaran melabeli e-wallet sebagai QRIS | Mempertahankan `payment_type` asli di `MidtransService.php` dan memprioritaskan deteksi `isEwallet` serta label channel di `PaymentModal.vue` | ✅ Pass |
| **Pembayaran COD Surabaya (PPL-20)** | COD untuk Surabaya diterima; luar Surabaya (Gresik/Jakarta) diblokir | ✅ Pass | — | Validasi frontend (disabled) & backend (`422`) berfungsi 100% | ✅ Pass |
| **Tracking Pesanan, Ulasan, AI Chat & Konsultasi WA** | Cek status pesanan, kirim ulasan modal, chat AI, tombol WA toko | ✅ Pass | Thumbnail modal ulasan masih berupa SVG kartun; nomor WA perlu diarahkan ke `+62 851-5606-4912` | Mengganti thumbnail modal ulasan ke foto produk asli (`GlobalModalHost.vue`, `main.css`) & memperbarui `StoreSettingsSeeder.php` ke `6285156064912` | ✅ Pass |

### 3.2 Fitur Sisi Admin

| Modul / Fitur | Skenario Uji | Status Awal | Bug yang Ditemukan | Perbaikan & File Terubah | Status Akhir |
| :--- | :--- | :---: | :--- | :--- | :---: |
| **Login Admin & Sidebar** | Login admin, navigasi seluruh menu sidebar | ✅ Pass | — | Seluruh rute admin aktif tanpa 404/500 | ✅ Pass |
| **Dashboard, Inventaris, Barang Masuk, Penjualan, Laporan Bulanan, Pengaturan Toko** | Muat data live API, filter periode, pengaturan WA toko | ✅ Pass | — | Semua endpoint admin merespons `200 OK` tanpa console error | ✅ Pass |
| **Manajemen Produk & Kategori** | Daftar, pencarian, tambah/edit/status, tombol aksi tabel | ⚠️ Minor | Beberapa tombol aksi dan status feedback belum memiliki `aria-label` disambiguasi, `type="button"`, dan live region | Menambahkan `aria-label` spesifik, `type="button"`, dan `role="alert"`/`role="status"` di `ProductsView.vue` & `CategoriesView.vue` | ✅ Pass |
| **Pesanan, Update Status & Konfirmasi COD** | Ubah status pesanan ke `dikirim` dan konfirmasi pelunasan COD | ❌ Fail | Saat Admin mengubah status ke `dikirim` tanpa mengganti kurir, layanan kurir terduplikasi (contoh: `JNE OKE OKE`) | Menambahkan helper `Pesanan::formatCourierWithService()` di `Pesanan.php` dan menerapkannya pada `OrderStatusService.php` | ✅ Pass |

---

## 4. Pengujian Regresi & Verifikasi Akhir

Seluruh temuan QA telah dikunci dengan automated test baru di `backend-api/tests/Feature/QaIssue34BugfixTest.php`.

```bash
# 1. Jalankan seluruh test suite backend
cd backend-api
php artisan test
# Hasil: 157 passed (1,138 assertions), 0 failed

# 2. Bangun production bundle frontend
cd ../frontend
npm run build
# Hasil: built in 717ms, 0 error
```

Hasil verifikasi ulang Playwright MCP:
- **Console Errors:** `0`
- **Broken Images (`naturalWidth === 0`):** `0` (seluruh 10 produk katalog, 3 slide Hero Slider, dan thumbnail Modal Ulasan termuat penuh)
- **Responsivitas Mobile (`390×844`):** `scrollWidth (375) <= innerWidth (390)` — tidak ada horizontal overflow.
