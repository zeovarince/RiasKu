# PPL-15 — Katalog, Pencarian & Detail Produk

**Issue GitHub:** `#17`  
**PIC:** `suryamaulana98`  
**Sprint:** Sprint 2 (implementasi inti) & Sprint 5 (penyempurnaan paginasi, sorting rating, foto HD asli, dan hero slider)

---

## 1. Ringkasan Fitur & Tujuan

PPL-15 menyediakan pengalaman jelajah katalog kosmetik dan layanan tata rias RiasKu dari sisi pelanggan yang terhubung langsung ke backend Laravel + PostgreSQL. Implementasi mencakup:

1. Daftar kategori aktif dan navigasi cepat kategori di beranda maupun halaman katalog.
2. Daftar produk aktif dengan pencarian kata kunci (`q`), filter kategori (mendukung ID numerik maupun `slug`), pengurutan (`newest`, `price_asc`, `price_desc`, `rating`, `best_seller`), dan paginasi berbasis server.
3. Halaman detail produk lengkap dengan galeri foto, pemilihan varian (warna/shade/ukuran), stok real-time per varian, status pre-order, ulasan pelanggan, dan tombol tambah ke keranjang.
4. Aset 10 foto produk HD asli yang tersimpan di repository (`frontend/public/images/products/`) serta seeder katalog idempoten (`KatalogSeeder`) sehingga seluruh lingkungan lokal anggota tim langsung menampilkan foto produk asli.
5. Hero slider beranda (3 slide) dan thumbnail modal ulasan produk yang seluruhnya memakai foto asli dengan optimasi prioritas LCP (`fetchpriority`).

---

## 2. Struktur Database & Aset Foto

| Tabel / Model | Peran |
| :--- | :--- |
| `kategori` (`App\Models\Kategori`) | Menyimpan daftar kategori produk/layanan beserta `slug` dan `icon`. |
| `produk` (`App\Models\Produk`) | Menyimpan SKU, nama, brand, deskripsi, berat, harga modal/jual, `min_stock`, flag pre-order, `best_seller_score`, dan status aktif. |
| `produk_varian` (`App\Models\ProdukVarian`) | Menyimpan varian/shade per produk beserta stok masing-masing varian. |
| `produk_foto` (`App\Models\ProdukFoto`) | Menyimpan daftar foto produk dan penanda `is_primary`. |

### Resolver URL Foto (`ProdukFoto` & `ProdukFotoController`)
- `ProdukFoto::getImageUrlAttribute()` mengembalikan path publik bertanda slash awal (`/images/products/...`) secara langsung tanpa membungkusnya dengan `/storage/`, sementara file upload lokal tetap diarahkan ke `/storage/...` dan URL eksternal (`http://` / `https://`) dikembalikan apa adanya.
- `Api\Admin\ProdukFotoController::destroy()` melewati penghapusan disk `public` untuk path publik `/...` atau URL eksternal agar aset bawaan repository tidak gagal dihapus.
- `Database\Seeders\KatalogSeeder` bersifat idempoten (`updateOrCreate` berbasis `sku`) dan menyediakan **10 produk lintas 7 kategori** yang masing-masing terhubung ke foto di `frontend/public/images/products/`:
  - `RSK-CUSH-01` → `/images/products/velvet-matte-cushion.jpg`
  - `RSK-LIP-04` → `/images/products/glaze-tinted-lip-oil.jpg`
  - `RSK-POWD-02` → `/images/products/translucent-setting-powder.jpg`
  - `RSK-BRV-010` → `/images/products/paket-rias-pengantin.jpg`
  - `RSK-PO-01` → `/images/products/special-edition-velvet-tint.jpg`
  - `Kahf-001` → `/images/products/kahf-face-wash.jpg`
  - `BRB-001` → `/images/products/barber-daily-face-wash.jpg`
  - `RSK-EYE-01` → `/images/products/soft-glam-eyeshadow-palette.jpg`
  - `RSK-WIS-01` → `/images/products/paket-rias-wisuda.jpg`
  - `RSK-BRSH-01` → `/images/products/pro-silk-makeup-brush-set.jpg`

---

## 3. Spesifikasi API

Semua endpoint katalog bersifat publik (tanpa autentikasi) di bawah prefix `/api`.

### 3.1 `GET /api/categories` (alias: `GET /api/kategori`)
Mengembalikan daftar kategori yang memiliki produk aktif atau terdaftar di sistem.

### 3.2 `GET /api/products` (alias: `GET /api/produk`)
Mengembalikan daftar produk aktif dengan dukungan pencarian, filter, pengurutan, dan paginasi.

| Query Param | Keterangan |
| :--- | :--- |
| `q` / `search` | Pencarian case-insensitive pada nama produk, SKU, brand, dan deskripsi via helper `App\Support\SqlSearch`. |
| `category` / `kategori` | Filter berdasarkan `kategori_id` atau `slug` kategori. |
| `sort` | `newest`, `price_asc`, `price_desc`, `rating` (tetap menyertakan produk tanpa ulasan secara aman), atau `best_seller`. |
| `page`, `per_page` | Paginasi server-side. |

### 3.3 `GET /api/products/{id}` (alias: `GET /api/produk/{id}`)
Mengembalikan detail lengkap satu produk aktif beserta varian, foto (`image_url` dan array `fotos`), ringkasan rating, dan status stok/pre-order. Produk nonaktif mengembalikan HTTP `404`.

---

## 4. Struktur Komponen & Halaman Frontend

| File | Peran |
| :--- | :--- |
| `frontend/src/views/customer/HomeView.vue` | Landing page, hero slider 3 slide dengan foto asli (`fetchpriority="high"` pada slide pertama), quick links kategori, dan seksi produk pilihan dengan filter kategori dinamis. |
| `frontend/src/views/customer/ProductListView.vue` | Halaman katalog lengkap dengan search bar, filter kategori, dropdown sorting, grid produk, empty state, dan paginasi. |
| `frontend/src/views/customer/ProductDetailView.vue` | Halaman detail produk, pemilih varian/shade, kontrol kuantitas sesuai batas stok, tombol tambah ke keranjang, dan aksi ulasan/WhatsApp. |
| `frontend/src/components/ProductCard.vue` | Kartu produk reusable dengan foto utama, badge best seller/stok/COD, rating, harga, dan tombol aksi cepat. |
| `frontend/src/components/CategoryQuickLinks.vue` | Navigasi pintas kategori di halaman beranda menuju filter katalog terkait. |
| `frontend/src/components/GlobalModalHost.vue` | Menampilkan modal ulasan produk lengkap dengan thumbnail foto produk asli (`fetchpriority="low"`). |
| `frontend/src/services/productService.js` | Integrasi Axios ke `/api/categories`, `/api/products`, dan `/api/products/{id}` (mendukung toggle `VITE_USE_MOCK_DATA=false`). |
| `frontend/src/services/homeService.js` | Memuat data kategori dan daftar best seller/produk pilihan untuk halaman beranda. |

---

## 5. Skenario Pengujian

### 5.1 Pengujian Otomatis
```bash
cd backend-api
php artisan test tests/Feature/CustomerCatalogTest.php tests/Feature/Sprint5BugfixAndDashboardTest.php tests/Feature/QaIssue34BugfixTest.php
```

Cakupan test:
- Listing kategori aktif dan produk aktif beserta label stok & varian (`CustomerCatalogTest`).
- Pencarian, filter kategori berbasis ID maupun `slug`, pengurutan `rating` dengan produk unrated, dan server-side pagination (`CustomerCatalogTest`, `Sprint5BugfixAndDashboardTest`).
- Detail produk aktif & respons `404` untuk produk nonaktif (`CustomerCatalogTest`).
- Verifikasi `KatalogSeeder` mengisi 10 produk dengan foto primer `/images/products/...` (`QaIssue34BugfixTest`).

### 5.2 Verifikasi Manual / E2E
1. Buka `/` — pastikan 3 slide hero slider dan seluruh kartu produk pilihan menampilkan foto asli tanpa broken image.
2. Buka `/products` — uji pencarian kata kunci, filter setiap kategori, perubahan urutan harga/rating, dan navigasi halaman.
3. Buka `/products/:id` — ubah pilihan varian, sesuaikan kuantitas, dan pastikan batas stok diterapkan dengan benar.
