# Panduan Menjalankan, Menguji & Indeks Dokumentasi Sprint RiasKu

Dokumentasi ini merupakan panduan teknis terpadu bagi anggota tim pengembang dan penguji (*tester*) untuk menyiapkan lingkungan lokal (*localhost*), menyinkronkan database/seeder, serta meninjau rekapitulasi pekerjaan sprint **PPL-15, PPL-16, PPL-19, PPL-20, dan PPL-29**.

---

## 1. Indeks Dokumentasi Sprint (`docs/`)

| Kode PPL | Issue | Dokumen Teknis | Cakupan Utama |
| :--- | :---: | :--- | :--- |
| **PPL-15** | `#17` | [`PPL-15-katalog-pencarian-dan-detail-produk.md`](PPL-15-katalog-pencarian-dan-detail-produk.md) | Katalog produk, filter kategori (ID & slug), pencarian, sorting rating/harga, paginasi server, detail varian, 10 foto HD asli, dan hero slider beranda. |
| **PPL-16** | `#18` | [`PPL-16-keranjang-belanja.md`](PPL-16-keranjang-belanja.md) | API keranjang `/api/cart*`, Smart Bidirectional Sync (Pinia + PostgreSQL), validasi stok, dan pembersihan sesi logout/checkout. |
| **PPL-19** | `#21` | [`PPL-19-pembayaran-online-midtrans.md`](PPL-19-pembayaran-online-midtrans.md) | Pembayaran Midtrans Core API (QRIS, VA BCA/BNI/BRI, Mandiri Bill, E-Wallet GoPay/ShopeePay/DANA), webhook SHA-512, auto-polling, dan hardening production. |
| **PPL-20** | `#22` | [`PPL-20-pembayaran-cod-surabaya.md`](PPL-20-pembayaran-cod-surabaya.md) | Validasi ketat COD khusus Kota Surabaya (frontend & backend `422`), status pesanan COD, konfirmasi pelunasan Admin, dan anti-duplikasi kurir. |
| **PPL-29** | `#34` | [`PPL-29-qa-fungsional-dan-bugfix-sprint5.md`](PPL-29-qa-fungsional-dan-bugfix-sprint5.md) | Laporan pengujian QA End-to-End (Playwright MCP + live API), perbaikan bug Customer & Admin, live Dashboard Admin API, update WhatsApp (`+62 851-5606-4912`), dan 162 automated tests. |

### Peningkatan Tambahan Pasca-QA (UI Motion & Input Produk Barcode)
- **Editorial Silk Curtain Preloader & Sistem Animasi 60fps** (`frontend/index.html`, `App.vue`, `router/index.js`, `HomeView.vue`, `main.css`): Preloader tirai *"RiasKu."* saat reload (tanpa white-flash, semantik `<progress>`, patuh `prefers-reduced-motion`), entrance hero, SPA View Transitions dengan focus routing, scroll-driven reveal, dan tombol Pause/Play WCAG 2.2.2 pada hero slider.
- **Scan Barcode Produk & Auto-SKU Generator** (`ProductCreateView.vue`, `BarcodeScannerModal.vue`, `useCameraBarcodeScanner.js`, `useHardwareBarcodeScanner.js`, `skuGenerator.js`, `BarcodeProductLookupService.php`, migrasi `add_barcode_to_produk_table`): Mendukung **Scan Kamera Laptop/HP** (`barcode-detector` / `zxing-wasm`), **Upload / Foto Barcode**, dan **Alat Scanner USB/Bluetooth HID**, lengkap dengan `GET /api/admin/produk/lookup-barcode` (cek DB lokal → tombol cepat *Tambah Stok Masuk* / *Ubah Produk*, serta auto-fill opsional Open Beauty Facts) dan generator SKU otomatis dari kombinasi huruf nama produk (`RSK-XXXX-01`).

Dokumentasi lintas tim lain di folder `docs/`:
- Kontrak API Frontend: [`API-CONTRACT-FRONTEND.md`](API-CONTRACT-FRONTEND.md)
- Spesifikasi OpenAPI: [`riasku-openapi.json`](riasku-openapi.json)
- Diagram ERD Lengkap: [`riasku-erd-full.png`](riasku-erd-full.png)
- Dokumentasi fitur tim lain: [`PPL-23`](PPL-23-barang-masuk-dan-peringatan-restock.md), [`PPL-24`](PPL-24-best-seller-scoring.md), [`PPL-26`](PPL-26-rekap-keuangan-bulanan.md), [`PPL-27`](PPL-27-pengaturan-toko-dan-tombol-whatsapp.md).

---

## 2. Kebutuhan Sistem (Prerequisites)

1. **PHP**: Versi `>= 8.2` (ekstensi `pdo_pgsql`, `pgsql`, `openssl`, `mbstring`, `curl`, `fileinfo`, `gd`).
2. **Composer**: Versi `>= 2.x`.
3. **Node.js & npm**: Node.js `^22.18.0` (atau `>= 20.x`) dan npm `>= 9.x`.
4. **PostgreSQL**: Server aktif di port `5432` dengan database bernama `riasku` (atau MySQL sesuai `.env` lokal).
5. **Git**: Untuk sinkronisasi branch `development`.

---

## 3. Panduan Setup & Menjalankan Proyek di Lokal

### Langkah 1: Ambil Kode Terbaru dari `development`
```bash
git fetch origin
git checkout development
git pull origin development
```

### Langkah 2: Setup Backend API (`backend-api/`)
```bash
cd backend-api
composer install
```

Salin `.env.example` menjadi `.env` (jika belum ada):
```powershell
# Windows PowerShell:
Copy-Item .env.example .env

# macOS / Linux:
cp .env.example .env
```

Sesuaikan kredensial database lokal serta kunci Google OAuth & Midtrans Sandbox di `backend-api/.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=riasku
DB_USERNAME=postgres
DB_PASSWORD=password_postgres_anda

GOOGLE_CLIENT_ID=<GOOGLE_CLIENT_ID>
GOOGLE_CLIENT_SECRET=<GOOGLE_CLIENT_SECRET>

MIDTRANS_SERVER_KEY=<MIDTRANS_SANDBOX_SERVER_KEY>
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_ALLOW_SANDBOX_SIMULATOR=true
MIDTRANS_BASE_URL=https://api.sandbox.midtrans.com
```
> **Catatan:** Jangan pernah meng-commit `.env` ke GitHub. Jika hanya ingin memperbarui data foto produk dan nomor WhatsApp tanpa menghapus transaksi lama, jalankan dua seeder khusus di bawah alih-alih `migrate:fresh`.

Jalankan key generation, migrasi, dan seeder:
```bash
# Untuk setup pertama kali (database kosong):
php artisan key:generate
php artisan migrate:fresh --seed

# Atau untuk sinkronisasi katalog foto HD & nomor WhatsApp tanpa reset tabel:
php artisan migrate
php artisan db:seed --class=KatalogSeeder
php artisan db:seed --class=StoreSettingsSeeder
```

Jalankan server backend Laravel:
```bash
php artisan serve
```
Backend aktif di **`http://127.0.0.1:8000`**.

### Langkah 3: Setup Frontend (`frontend/`)
```bash
cd frontend
npm install
```

Pastikan `frontend/.env` terkonfigurasi ke backend asli:
```env
VITE_API_URL=http://127.0.0.1:8000/api
VITE_API_BASE_URL=http://127.0.0.1:8000/api
VITE_DASHBOARD_API_URL=http://127.0.0.1:8000/api/admin
VITE_GOOGLE_CLIENT_ID=<GOOGLE_CLIENT_ID>
VITE_GOOGLE_AUTH_URL=http://localhost:8000/auth/google/redirect
VITE_USE_MOCK_DATA=false
VITE_CART_API_ENABLED=true
```

Jalankan Vite dev server:
```bash
npm run dev
```
Frontend aktif di **`http://localhost:5173`**.

---

## 4. Kredensial Akun Pengujian (Default Seeder)

| Peran (Role) | Nama / Username | Email | Kata Sandi | Kegunaan |
| :--- | :--- | :--- | :--- | :--- |
| **Pelanggan Utama** | `surya` | `surya@riasku.com` | `password123` | Uji katalog, keranjang, checkout COD Surabaya & Midtrans Sandbox. |
| **Pelanggan Kedua** | `pelanggan` | `pelanggan@riasku.com` | `password123` | Uji transaksi dan isolasi kepemilikan pesanan antar akun. |
| **Admin Toko** | `Admin RiasKu` | `admin@riasku.com` | `password123` | Dashboard admin, produk, kategori, pesanan, konfirmasi COD, laporan bulanan, dan pengaturan toko. |

> Akun Admin dapat login melalui `/admin/login` maupun langsung dari form/modal login pelanggan — sistem otomatis mengenali role admin dan mengarahkan ke `/admin/dashboard`.

---

## 5. Verifikasi Otomatis (Backend & Frontend)

```bash
# Backend full test suite (162 tests, 1.170 assertions)
cd backend-api
php artisan test

# Frontend production build check
cd ../frontend
npm run build
```

---

## 6. Troubleshooting Singkat

1. **Menu "Checkout & COD" belum muncul di navbar:** Login terlebih dahulu sebagai pelanggan.
2. **Foto katalog masih belum muncul setelah pull:** Jalankan `php artisan db:seed --class=KatalogSeeder` di `backend-api/` agar path `/images/products/...` terpasang pada tabel `produk_foto`.
3. **Nomor WhatsApp belum berubah ke `+62 851-5606-4912`:** Jalankan `php artisan db:seed --class=StoreSettingsSeeder` di `backend-api/`.
4. **Perubahan `.env` backend belum terbaca:** Jalankan `php artisan config:clear` lalu restart `php artisan serve`.

---

*Diperbarui pada 29 November 2026 — sinkron dengan hasil integrasi Sprint 2, Sprint 3, dan Sprint 5 (`PR #58`).*
