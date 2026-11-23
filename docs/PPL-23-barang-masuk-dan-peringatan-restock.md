# PPL-23 — Barang Masuk & Peringatan Restock

## 1. Tujuan

Fitur ini menyediakan alur inventaris admin untuk:

1. Mencatat barang masuk (penerimaan stok) beserta produk, varian, jumlah, tanggal, supplier, catatan/faktur, dan admin pencatat.
2. Menambah stok varian secara atomik bersamaan dengan penyimpanan riwayat (menggunakan transaksi database dan row lock).
3. Menyediakan riwayat barang masuk dengan filter produk, varian, rentang tanggal, dan pagination.
4. Menentukan ambang minimum stok per produk (`min_stock`) dan menampilkan peringatan **Stok Menipis** / **Stok Habis** pada daftar produk, dashboard, dan halaman barang masuk.
5. Menyediakan quick action dari peringatan restock langsung ke form barang masuk untuk produk terkait.

Restore condition yang dipakai: `total_stock = SUM(stok varian aktif) <= min_stock`. Jika `total_stock == 0`, statusnya **Stok Habis**; jika `0 < total_stock <= min_stock`, statusnya **Stok Menipis**; selain itu **Stok Aman**.

## 2. Perubahan Skema Database

### 2.1 Tabel `produk` (migrasi `add_min_stock_to_produk_table`)

| Kolom | Tipe | Default | Keterangan |
| --- | --- | --- | --- |
| `min_stock` | `unsignedInteger` | `5` | Ambang minimum stok untuk memicu peringatan restock. Ditambahkan setelah `is_active`, di-`cast` sebagai `integer`, dan masuk `$fillable` pada `App\Models\Produk`. |

Migrasi bersifat idempoten (`Schema::hasColumn`) sehingga aman jika kolom sudah ada.

### 2.2 Tabel baru `stok_masuk` (migrasi `create_stok_masuk_table`)

| Kolom | Tipe | Nullable | Keterangan |
| --- | --- | --- | --- |
| `id` | `bigIncrements` | tidak | Primary key. |
| `produk_id` | `foreignId` | tidak | FK ke `produk.id`, `ON DELETE CASCADE`. |
| `produk_varian_id` | `foreignId` | tidak | FK ke `produk_varian.id`, `ON DELETE CASCADE`. |
| `quantity` | `unsignedInteger` | tidak | Jumlah barang masuk (pcs), minimal 1. |
| `unit_cost` | `unsignedInteger` | ya | Harga modal satuan; bila diisi, menyinkronkan `produk.base_price`. |
| `supplier` | `string(150)` | ya | Nama/kode supplier. |
| `notes` | `text` | ya | Catatan faktur, batch, atau keterangan lain. |
| `tanggal_masuk` | `date` | ya pada request, tidak pada DB | Tanggal penerimaan; default request = hari ini. |
| `recorded_by` | `foreignId` | tidak | FK ke `admin.id`, `ON DELETE RESTRICT`. |
| `created_at`, `updated_at` | `timestamp` | tidak | Audit trail standar Laravel. |

Index: `(produk_id, tanggal_masuk)` dan `tanggal_masuk`.

### 2.3 Relasi Eloquent

- `StokMasuk::produk()` → `belongsTo(Produk::class, 'produk_id')`
- `StokMasuk::varian()` → `belongsTo(ProdukVarian::class, 'produk_varian_id')`
- `StokMasuk::recordedBy()` → `belongsTo(Admin::class, 'recorded_by')`
- `Produk::stokMasuks()` → `hasMany(StokMasuk::class, 'produk_id')`
- `ProdukVarian::stokMasuks()` → `hasMany(StokMasuk::class, 'produk_varian_id')`
- `Produk` mengekspos atribut turunan `needs_restock` (`bool`) dan `restock_status` (`string`) lewat `$appends`, serta scope `needsRestock()` untuk query.

Stok sebenarnya tetap disimpan di `produk_varian.stock`; `produk.total_stock` adalah accessor penjumlahan varian aktif.

## 3. Spesifikasi API

Semua endpoint berada di bawah prefix `/api/admin` dan memerlukan header `Authorization: Bearer <token>` dengan peran admin (`auth:sanctum` + `EnsureAdmin`). Format envelope mengikuti konvensi proyek: `message`, `data`, `meta` (khusus list).

### 3.1 `POST /api/admin/inventory/incoming`

Alias: `POST /api/admin/stock-in`.

Request JSON:

```json
{
  "produk_id": 12,
  "produk_varian_id": 27,
  "quantity": 8,
  "unit_cost": 31000,
  "supplier": "Supplier A",
  "notes": "Faktur INV-2026-113",
  "tanggal_masuk": "2026-11-23"
}
```

| Field | Aturan | Keterangan |
| --- | --- | --- |
| `produk_id` | `required`, `exists:produk,id` | Produk induk. |
| `produk_varian_id` | `required`, `exists:produk_varian,id` | Harus merupakan varian dari `produk_id`. |
| `quantity` | `required`, `integer`, `min:1` | Jumlah pcs yang ditambahkan. |
| `unit_cost` | `nullable`, `integer`, `min:0` | Jika diisi, `produk.base_price` ikut diperbarui. |
| `supplier` | `nullable`, `string`, `max:150` | Nama/kode supplier. |
| `notes` | `nullable`, `string`, `max:1000` | Catatan/faktur. |
| `tanggal_masuk` | `nullable`, `date_format:Y-m-d` | Alias: `date`. Default: tanggal hari ini. |

Response `201 Created`:

```json
{
  "message": "Barang masuk berhasil dicatat.",
  "data": {
    "id": 1,
    "produk_id": 12,
    "produk_varian_id": 27,
    "quantity": 8,
    "unit_cost": 31000,
    "supplier": "Supplier A",
    "notes": "Faktur INV-2026-113",
    "tanggal_masuk": "2026-11-23T00:00:00.000000Z",
    "produk": { "id": 12, "name": "Glow Velvet Lip Tint" },
    "varian": { "id": 27, "name": "Natural 01", "stock": 10 },
    "recorded_by": { "id": 3, "name": "Admin Gudang" }
  },
  "product": {
    "id": 12,
    "name": "Glow Velvet Lip Tint",
    "total_stock": 10,
    "min_stock": 5,
    "needs_restock": false,
    "restock_status": "Stok Aman"
  },
  "warnings": []
}
```

Catatan: `data` memuat relasi `produk`, `varian`, dan `recordedBy`; karena relasi `recordedBy` diserialisasi Eloquent sebagai `recorded_by`, kunci tersebut berisi objek admin (bukan lagi angka ID). `warnings` berisi pesan peringatan bila stok setelah transaksi masih berada pada atau di bawah ambang minimum.

Kesalahan: `401` tanpa/invalid token, `403` untuk non-admin, `422` untuk validasi gagal (termasuk varian tidak cocok dengan produk: kunci `produk_varian_id`). Seluruh operasi berada di dalam `DB::transaction` dengan `lockForUpdate()` pada varian; jika ada kegagalan, riwayat dan penambahan stok digulung bersamaan.

### 3.2 `GET /api/admin/inventory/incoming`

Alias: `GET /api/admin/stock-in`.

Query params:

| Param | Keterangan |
| --- | --- |
| `produk_id` | Filter produk. |
| `produk_varian_id` | Filter varian. |
| `date_from`, `date_to` | Filter rentang `tanggal_masuk` (format `Y-m-d`, inklusif). |
| `per_page` | 1–100, default 15. |
| `page` | Halaman pagination standar Laravel. |

Response `200`:

```json
{
  "message": "Riwayat barang masuk berhasil diambil.",
  "data": [
    {
      "id": 1,
      "produk_id": 12,
      "produk_varian_id": 27,
      "quantity": 8,
      "tanggal_masuk": "2026-11-23T00:00:00.000000Z",
      "supplier": "Supplier A",
      "notes": "Faktur INV-2026-113",
      "produk": { "id": 12, "name": "Glow Velvet Lip Tint" },
      "varian": { "id": 27, "name": "Natural 01" },
      "recorded_by": { "id": 3, "name": "Admin Gudang" }
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

### 3.3 `GET /api/admin/inventory/restock`

Alias: `GET /api/admin/stock/low`. Query: `per_page` (1–100, default 15), `page`.

Mengembalikan produk dengan `SUM(stock varian aktif) <= min_stock`, diurutkan dari produk terbaru. Response `200`:

```json
{
  "message": "Daftar produk yang perlu restock berhasil diambil.",
  "data": [
    {
      "id": 12,
      "name": "Glow Velvet Lip Tint",
      "sku": "RSK-LIP-01",
      "total_stock": 3,
      "min_stock": 5,
      "needs_restock": true,
      "restock_status": "Stok Menipis",
      "variants": []
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

### 3.4 `GET /api/admin/produk?restock=1`

Endpoint daftar produk existing kini menerima filter `restock=1` (alias `needs_restock=1`) dan menghasilkan produk yang perlu restock dengan envelope yang sama seperti endpoint produk biasa. Seluruh response produk juga menyertakan `min_stock`, `needs_restock`, dan `restock_status`.

### 3.5 `POST /api/admin/produk` dan `PUT /api/admin/produk/{produk}`

Kedua endpoint kini menerima field opsional `min_stock` (`nullable|integer|min:0`). Nilai default tetap `5` dari skema database bila tidak dikirim.

## 4. Struktur Frontend

| File | Perubahan |
| --- | --- |
| `frontend/src/services/adminService.js` | Menambah `getIncomingStocks()`, `createIncomingStock()`, `getRestockAlerts()`; `incomingStock()` dipertahankan sebagai alias kompatibilitas. |
| `frontend/src/views/admin/IncomingStockView.vue` | Dari halaman statis menjadi form API-driven: pilih produk & varian, jumlah, tanggal, supplier, modal, catatan; validasi error per field; loading/success banner; tabel riwayat dengan filter produk + rentang tanggal + pagination; panel “Segera Restock” dengan quick action. Membaca query `?product_id=...` (`&variant_id=...`) untuk preset form. |
| `frontend/src/views/admin/DashboardView.vue` | Panel “Segera Restock” kini diambil dari `getRestockAlerts()`, menampilkan stok/minimum dan status, serta `RouterLink` ke form dengan `product_id`. |
| `frontend/src/views/admin/ProductsView.vue` | Kolom **Restock** baru: badge **Stok Habis** / **Stok Menipis**, teks “Aman” untuk normal, minimum stok di kolom stok, quick action Restock, serta opsi filter **Perlu Restock**. |
| `frontend/src/views/admin/ProductCreateView.vue` | Form tambah/ubah produk kini memiliki input **Batas Minimum Stok** (`min_stock`, default 5). |
| `frontend/src/components/admin/AdminIcon.vue` | Menambah ikon `alert-triangle` untuk banner error. |

Semua halaman memakai komponen Admin yang sudah ada (`AdminPageHeader`, `AdminBadge`, `AdminIcon`) dan stylesheet `admin.css`; tidak ada dependency frontend baru.

## 5. Skenario Pengujian Manual

### 5.1 Otomatis

```bash
cd backend-api
vendor/bin/pint --dirty --format agent
php -d extension=pdo_sqlite vendor/bin/pest --compact tests/Feature/StokMasukTest.php
php -d extension=pdo_sqlite vendor/bin/pest --compact
cd ../frontend
npm run build
```

> Catatan lingkungan lokal: ekstensi `pdo_sqlite` tersedia tetapi tidak aktif di `php.ini` bawaan, sehingga pada perintah di atas ditambahkan `-d extension=pdo_sqlite`. Jika extension sudah aktif, perintah `php artisan test` biasa dapat dipakai.

### 5.2 Manual via UI

1. Login sebagai admin, buka **Produk → Tambah Produk**, isi data produk dengan **Batas Minimum Stok = 5**, simpan. Pastikan produk baru tampil dengan keterangan “min. 5 pcs”.
2. Buka **Produk**. Untuk produk dengan total stok 5 atau kurang, pastikan kolom Restock menampilkan badge **Stok Menipis** (atau **Stok Habis** jika 0) dan tombol **Restock** tersedia.
3. Klik tombol **Restock**/quick action pada dashboard. Pastikan halaman **Barang Masuk** terbuka dengan produk dan varian pertama sudah terpilih.
4. Isi jumlah misalnya `8`, supplier, tanggal, catatan faktur, lalu klik **Simpan Barang Masuk**. Pastikan:
   - banner sukses muncul;
   - stok varian bertambah 8 pcs (cek kembali di halaman Produk);
   - baris baru muncul di tabel riwayat dengan qty `+8 pcs`;
   - panel “Segera Restock” berkurang bila stok sudah melewati ambang.
5. Coba simpan tanpa produk/varian atau dengan jumlah `0`; pastikan aplikasi menampilkan pesan validasi per field dan tidak ada baris riwayat baru.
6. Gunakan filter produk dan rentang tanggal pada tabel riwayat; pastikan hasil dan pagination sesuai filter.
7. Uji akses tanpa token atau dengan token pelanggan ke endpoint API; pastikan berturut-turut `401` dan `403`.

### 5.3 Manual via API (curl)

```bash
# Riwayat dengan filter
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8000/api/admin/inventory/incoming?produk_id=12&date_from=2026-11-01&date_to=2026-11-30&per_page=15"

# Peringatan restock
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8000/api/admin/inventory/restock?per_page=10"

# Produk perlu restock lewat endpoint produk
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8000/api/admin/produk?restock=1"
```
