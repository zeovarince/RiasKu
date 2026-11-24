# PPL-24 — Fitur Best Seller (Scoring)

## 1. Ringkasan Fitur & Tujuan

Fitur Best Seller memberi peringkat produk berdasarkan performa penjualan nyata, bukan label statis dari SKU. Implementasi mencakup:

1. Perhitungan skor **0–100** yang mengutamakan frekuensi pesanan unik, dengan jumlah unit dan rating ulasan sebagai faktor pendukung.
2. Jendela penjualan **90 hari terakhir** agar produk lama tidak terus mendominasi etalase.
3. Penyimpanan skor pada tabel `produk` (`best_seller_score`) yang diperbarui otomatis saat pesanan lunas/selesai atau ulasan berubah, serta dijadwalkan setiap hari.
4. Endpoint publik khusus best seller dan opsi `sort=best_seller` pada katalog yang sudah ada.
5. Badge **Top #1/#2/#3 Best Seller** pada kartu produk di beranda dan katalog.
6. Panel monitoring **Produk Best Seller** pada dashboard admin, lengkap dengan total terjual, jumlah pesanan, skor, dan sinyal restock dari PPL-23.

Produk baru yang belum memiliki penjualan selalu memiliki `best_seller_score = 0` dan tidak masuk daftar best seller.

## 2. Formula & Logika Perhitungan Skor

### 2.1 Sumber data

Skor dihitung dari:

| Metrik | Sumber | Keterangan |
| --- | --- | --- |
| `orders_count` (`O`) | `COUNT(DISTINCT pesanan.id)` | Frekuensi pembelian: banyak pesanan unik yang memuat produk tersebut. |
| `units_sold` (`U`) | `SUM(pesanan_item.quantity)` | Total unit terjual dalam jendela 90 hari. |
| `average_rating` (`R`) | `AVG(ulasan_produk.rating)` | Rata-rata rating ulasan berstatus `approved`. |
| `max_orders` (`Omax`) | Nilai maksimum `O` seluruh katalog | Untuk normalisasi metrik pesanan. |
| `max_units` (`Umax`) | Nilai maksimum `U` seluruh katalog | Untuk normalisasi metrik unit. |

Pesanan yang dihitung adalah pesanan yang **tidak dibatalkan** dan memenuhi salah satu kondisi berikut:

- `payment_status` bernilai `dibayar`, `lunas`, atau `settlement`; atau
- `order_status`/`status` bernilai `selesai`; atau
- memiliki baris `pembayaran` dengan `transaction_status = settlement`.

Selain itu, tanggal transaksi (`COALESCE(pesanan.updated_at, pesanan.created_at)`) harus berada dalam **90 hari terakhir**. Pesanan berstatus `dibatalkan` selalu dikecualikan, termasuk jika pembayarannya sempat lunas.

### 2.2 Normalisasi dan bobot

```
orders_norm  = min(1, O / Omax)
units_norm   = min(1, U / Umax)
rating_norm  = min(1, R / 5)

score = 100 × (0.60 × orders_norm + 0.25 × units_norm + 0.15 × rating_norm)
```

| Komponen | Bobot | Alasan |
| --- | --- | --- |
| Pesanan unik | 60% | Metrik utama frekuensi: produk yang dibeli pada lebih banyak pesanan mendapat skor lebih tinggi. |
| Unit terjual | 25% | Metrik pendukung volume agar jumlah barang tetap diperhitungkan. |
| Rating ulasan | 15% | Menjaga kualitas produk tetap berpengaruh tanpa mengalahkan penjualan. |

Aturan tambahan:

- Jika `U = 0`, maka `score = 0` walaupun produk memiliki rating. Produk tanpa penjualan tidak boleh masuk best seller.
- Normalisasi memakai nilai maksimum seluruh katalog pada saat perhitungan, sehingga skor antarproduk tetap sebanding.
- Skor disimpan dengan presisi 4 desimal dan ditampilkan 2 desimal pada respons admin.
- Rating hanya berasal dari ulasan `approved`; ulasan `hidden` tidak memengaruhi skor.

### 2.3 Kapan skor dihitung/diperbarui

| Pemicu | Implementasi |
| --- | --- |
| Pesanan berpindah status (termasuk menjadi `selesai`/`dibatalkan`) | `OrderStatusService::transition()` memanggil `BestSellerService::refreshForOrder()` setelah transaksi database selesai. |
| Konfirmasi pembayaran COD | `OrderStatusService::markCodPaid()` memanggil refresh untuk produk pada pesanan tersebut. |
| Ulasan dibuat/diperbarui/dihapus | `UlasanProdukController` (publik) memanggil `refreshForProduct()`. |
| Moderasi ulasan admin (`approved`/`hidden`) | `Api\Admin\UlasanProdukController::update()` memanggil `refreshForProduct()`. |
| Penjadwalan harian | Command `best-seller:refresh` dijadwalkan setiap hari pukul 02:00 pada `routes/console.php`. |
| Manual | `php artisan best-seller:refresh`. |

Perubahan status pesanan menghitung ulang skor seluruh katalog. Normalisasi memakai maksimum unit dan pesanan katalog; ketika nilai maksimum berubah, skor produk lain ikut berubah meskipun metrik penjualannya tetap. Refresh hanya produk dalam pesanan dapat meninggalkan skor dan peringkat yang tidak sebanding. Perubahan ulasan tetap cukup menghitung ulang produk terkait karena rating tidak menjadi dasar normalisasi lintas produk.

## 3. Perubahan Struktur Database

Migrasi: `2026_11_24_000001_add_best_seller_metrics_to_produk_table.php` (idempoten dengan `Schema::hasColumn`).

| Kolom `produk` | Tipe | Default | Keterangan |
| --- | --- | --- | --- |
| `best_seller_score` | `decimal(8,4)` | `0` | Skor best seller 0–100. Diindeks untuk sorting. |
| `best_seller_units_sold` | `unsignedInteger` | `0` | Unit terjual dalam jendela perhitungan. |
| `best_seller_orders_count` | `unsignedInteger` | `0` | Jumlah pesanan unik. |
| `best_seller_last_calculated_at` | `timestamp` nullable | `null` | Waktu terakhir produk dihitung ulang. |

Index: `produk_best_seller_score_index` pada `best_seller_score`.

Perubahan model `App\Models\Produk`:

- Kolom baru masuk `$fillable` dan `$casts`.
- Atribut turunan baru pada `$appends`: `is_best_seller` (bool) dan `best_seller_rank` (int/null).
- Scope `bestSeller()` untuk memfilter produk dengan skor > 0.
- Accessor `tag` tidak lagi mengarang label `BEST SELLER #1` dari SKU; badge best seller kini murni berasal dari skor. Tag lama (COD Surabaya, sisa stok, ready stock) tetap dipertahankan.

## 4. Spesifikasi API

Envelope mengikuti konvensi proyek: `message`, `data`, dan `meta`/`summary` untuk daftar.

### 4.1 `GET /api/products/best-sellers`

Alias: `GET /api/produk/best-sellers`. Publik, tanpa autentikasi.

Query params:

| Param | Aturan | Keterangan |
| --- | --- | --- |
| `limit` | integer 1–50, default 8 | Jumlah produk. Alias: `per_page`. |

Response `200`:

```json
{
  "message": "Daftar produk best seller berhasil dimuat.",
  "data": [
    {
      "id": 12,
      "sku": "RSK-CUSH-01",
      "name": "Velvet Matte Cushion SPF 35",
      "brand": "RiasKu",
      "retail_price": 185000,
      "total_stock": 2,
      "stock_label": "Tersedia",
      "needs_restock": true,
      "restock_status": "Stok Menipis",
      "rating": "4.9",
      "review_count": 184,
      "best_seller_score": 97.42,
      "best_seller_orders_count": 84,
      "best_seller_units_sold": 120,
      "best_seller_last_calculated_at": "2026-11-24T02:00:00.000000Z",
      "is_best_seller": true,
      "best_seller_rank": 1
    }
  ],
  "meta": {
    "limit": 8,
    "window_days": 90,
    "weights": {
      "orders_count": 0.6,
      "units_sold": 0.25,
      "rating": 0.15
    },
    "calculated_at": "2026-11-24 02:00:00"
  }
}
```

Catatan: `base_price` dan `price_warning` disembunyikan pada endpoint publik. `needs_restock`, `restock_status`, `total_stock`, dan `min_stock` konsisten dengan PPL-23 sehingga badge restock dapat langsung dirender.

### 4.2 `GET /api/products` (dukungan sorting)

Query `sort` menerima nilai baru:

- `best_seller` (alias `best-seller`, `terlaris`) → urut `best_seller_orders_count DESC`, lalu `best_seller_score DESC`, `best_seller_units_sold DESC`, dan `id ASC`. Frekuensi pesanan menjadi dasar peringkat; skor komposit menjadi pemecah seri.
- Filter `best_seller=1` (alias `is_best_seller=1`) hanya menampilkan produk dengan skor > 0.

Contoh:

```
GET /api/products?sort=best_seller&per_page=12
GET /api/products?best_seller=1
```

### 4.3 `GET /api/admin/produk/best-sellers`

Alias: `GET /api/admin/products/best-sellers`. Memerlukan `Authorization: Bearer <token>` admin (`auth:sanctum` + `EnsureAdmin`).

Query params:

| Param | Aturan | Keterangan |
| --- | --- | --- |
| `limit` | integer 1–50, default 10 | Jumlah produk. Alias: `per_page`. |
| `include_zero` | boolean, default false | Jika `1`, sertakan produk berskor 0 untuk audit. |

Response `200`:

```json
{
  "message": "Daftar produk best seller berhasil dimuat.",
  "data": [
    {
      "rank": 1,
      "product_id": 12,
      "name": "Velvet Matte Cushion SPF 35",
      "sku": "RSK-CUSH-01",
      "brand": "RiasKu",
      "category": "Complexion",
      "retail_price": 185000,
      "best_seller_score": 97.42,
      "units_sold": 120,
      "orders_count": 84,
      "rating": "4.9",
      "review_count": 184,
      "total_stock": 2,
      "min_stock": 5,
      "needs_restock": true,
      "restock_status": "Stok Menipis",
      "best_seller_last_calculated_at": "2026-11-24T02:00:00.000000Z"
    }
  ],
  "summary": {
    "products": 1,
    "total_units_sold": 120,
    "average_score": 97.42,
    "needs_restock": 1
  },
  "meta": {
    "limit": 10,
    "include_zero": false,
    "window_days": 90,
    "weights": {
      "orders_count": 0.6,
      "units_sold": 0.25,
      "rating": 0.15
    },
    "calculated_at": "2026-11-24 02:00:00"
  }
}
```

Kesalahan: `401` tanpa/invalid token, `403` untuk token pelanggan.

## 5. Struktur Komponen & Halaman Frontend

| File | Perubahan |
| --- | --- |
| `frontend/src/services/productService.js` | `normalizeProduct()` memetakan `best_seller_score`, `best_seller_units_sold`, `best_seller_orders_count`, `best_seller_rank`, dan `is_best_seller`. Fungsi baru `getBestSellers()` memanggil endpoint publik; mock data tetap didukung. Sorting mock `best_seller` ditambahkan. |
| `frontend/src/services/homeService.js` | Beranda memakai `getBestSellers({ limit: 4 })`, bukan lagi `featured`. |
| `frontend/src/components/ProductCard.vue` | Badge baru **TOP #1/#2/#3 BEST SELLER** (atau **BEST SELLER** untuk peringkat lain) dirender pada stack badge bersama tag stok PPL-23. |
| `frontend/src/assets/main.css` | Style `.product-badge-stack` dan `.product-badge-best-seller` (gradient rose-burgundy, responsif). |
| `frontend/src/views/customer/HomeView.vue` | Section “Paling Diminati (Best Seller)” menampilkan produk hasil scoring nyata dengan penjelasan metrik. |
| `frontend/src/views/customer/ProductListView.vue` | Opsi sortir **Terlaris (Best Seller)** dan pengurutan client-side berdasarkan skor, unit terjual, lalu ulasan. |
| `frontend/src/services/adminService.js` | `getBestSellers(params)` → `/api/admin/produk/best-sellers`. |
| `frontend/src/views/admin/DashboardView.vue` | Panel **Produk Best Seller**: peringkat, produk/SKU, total terjual, jumlah pesanan, rating/ulasan, skor 0–100, stok + status restock, dan aksi Restock/Detail. |

Tidak ada dependency frontend baru. Semua halaman memakai komponen admin yang sudah ada (`AdminPageHeader`, `AdminBadge`, `AdminIcon`).

## 6. Skenario Pengujian

### 6.1 Otomatis

```bash
cd backend-api
vendor/bin/pint --dirty --format agent
php -d extension=pdo_sqlite vendor/bin/pest --compact tests/Feature/BestSellerTest.php
php -d extension=pdo_sqlite vendor/bin/pest --compact
cd ../frontend
npm run build
```

> Catatan lingkungan lokal: ekstensi `pdo_sqlite` tersedia tetapi tidak aktif di `php.ini` bawaan, sehingga perintah di atas menambahkan `-d extension=pdo_sqlite`.

Cakupan `tests/Feature/BestSellerTest.php`:

1. Skor hanya menghitung pesanan dibayar/selesai dalam 90 hari; pesanan pending, batal, dan transaksi lama diabaikan.
2. Produk tanpa penjualan tetap berskor 0.
3. Produk yang dibeli pada lebih banyak pesanan terpisah mendapat skor lebih tinggi daripada produk dengan pembelian besar dalam satu pesanan.
4. Endpoint publik mengembalikan peringkat, skor, unit terjual, dan sinyal restock PPL-23.
5. Sorting `sort=best_seller` dan filter `best_seller=1` bekerja.
6. Endpoint admin mengembalikan metrik pembentuk skor dan ringkasan; akses tanpa token `401`.
7. Konfirmasi COD otomatis memperbarui skor produk terkait.
8. Ketika pesanan baru mengubah maksimum unit terjual katalog, skor produk lain ikut dinormalisasi ulang.

### 6.2 Manual via UI

1. Pastikan ada minimal dua produk dengan pesanan lunas/selesai. Buka **Beranda**; section Best Seller harus menampilkan produk dengan badge **TOP #1 BEST SELLER** pada kartu pertama.
2. Buka **Katalog**, pilih sortir **Terlaris (Best Seller)**; produk yang muncul pada lebih banyak pesanan harus berada di atas produk yang hanya dibeli dalam pesanan besar, dan badge tampil.
3. Buat pesanan baru untuk produk peringkat bawah, ubah status menjadi **Selesai** (atau konfirmasi COD lunas), lalu muat ulang Beranda. Frekuensi pesanan produk tersebut bertambah dan peringkatnya diperbarui.
4. Batalkan pesanan yang sebelumnya lunas; produk harus kehilangan kontribusi unit/pesanan dari pesanan tersebut.
5. Buka **Dashboard Admin**; panel **Produk Best Seller** harus menampilkan kolom Total Terjual, Pesanan, Rating, Skor, serta badge **Stok Menipis/Habis** untuk produk yang perlu restock, dengan tautan **Restock** ke form barang masuk.
6. Tambahkan ulasan bintang 5 melalui halaman detail produk; skor produk dapat berubah (komponen rating 15%) tanpa perlu menunggu jadwal harian.
7. Produk baru tanpa penjualan tidak boleh muncul di section Best Seller maupun panel admin (kecuali `include_zero=1`).

### 6.3 Manual via API (curl)

```bash
# Daftar best seller publik
curl "http://127.0.0.1:8000/api/products/best-sellers?limit=8"

# Katalog diurutkan berdasarkan skor
curl "http://127.0.0.1:8000/api/products?sort=best_seller&per_page=12"

# Monitoring admin
curl -H "Authorization: Bearer $ADMIN_TOKEN" \
  "http://127.0.0.1:8000/api/admin/produk/best-sellers?limit=10"

# Hitung ulang seluruh skor secara manual
cd backend-api && php artisan best-seller:refresh
```

### 6.4 Verifikasi akurasi skor

Gunakan data uji berikut:

| Produk | Unit lunas | Pesanan unik | Rating | Perkiraan posisi |
| --- | --- | --- | --- | --- |
| A | 15 | 2 | 5.0 | Peringkat 1 |
| B | 4 | 1 | 3.0 | Peringkat 2 |
| C | 0 | 0 | 5.0 | Skor 0, tidak masuk daftar |

Dengan `Umax = 15` dan `Omax = 2`:

```
score A = 100 × (0.60×1 + 0.25×1 + 0.15×1)          = 100.00
score B = 100 × (0.60×1/2 + 0.25×4/15 + 0.15×3/5) = 100 × (0.30 + 0.0667 + 0.09) = 45.67
score C = 0
```

Nilai aktual dapat sedikit berbeda karena pembulatan 4 desimal dan rating rata-rata seluruh ulasan `approved`.
