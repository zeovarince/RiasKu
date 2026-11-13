# RiasKu — Frontend API Contract (Sprint 2)

Dokumen ini mendeskripsikan API yang dibutuhkan frontend Vue untuk **PPL-15 Katalog/Pencarian/Detail Produk** dan **PPL-16 Keranjang Belanja**.

> **Status:** kontrak usulan untuk koordinasi frontend-backend. Endpoint dan response di bawah belum boleh dianggap tersedia sampai implementasi Laravel selesai dan tim menyetujuinya. Jika struktur backend berbeda, update service frontend dan dokumen ini bersama-sama.

## 1. Konvensi umum

- Base URL development: `http://localhost:8000/api`
- Header: `Accept: application/json`
- Request body: `Content-Type: application/json`
- Format sukses: JSON dengan properti `data`.
- Format error yang disarankan Laravel: `{ "message": "...", "errors": { "field": ["... "] } }`.
- ID produk dan kategori dapat berupa integer atau string; frontend memperlakukan ID sebagai identifier, bukan menghitungnya.
- Harga menggunakan angka integer Rupiah (contoh `185000`), jangan kirim string berformat `Rp 185.000` sebagai nilai harga.
- Gambar dikirim sebagai URL absolut yang bisa dibuka browser melalui `image_url`.

## 2. Daftar endpoint

| Method | Endpoint | Auth | Fitur | Implementasi service |
|---|---|---|---|---|
| GET | `/categories` | Tidak | Daftar kategori | `getCategories()` |
| GET | `/products` | Tidak | Katalog, pencarian, filter, sorting | `getProducts(params)` |
| GET | `/products/{id}` | Tidak | Detail produk | `getProductById(id)` |
| GET | `/cart` | Ya | Ambil cart tersimpan | `getCart()` |
| POST | `/cart/items` | Ya | Tambah item ke cart | `addCartItem(payload)` |
| PATCH | `/cart/items/{cartItemId}` | Ya | Ubah kuantitas | `updateCartItem(id, payload)` |
| DELETE | `/cart/items/{cartItemId}` | Ya | Hapus item | `removeCartItem(id)` |
| DELETE | `/cart` | Ya | Kosongkan cart | `clearRemoteCart()` |

## 3. GET `/categories`

Digunakan oleh kategori cepat di beranda dan filter kategori pada katalog.

### Response contoh

```json
{
  "data": [
    { "id": 1, "slug": "complexion", "name": "Complexion", "icon": "complexion" },
    { "id": 2, "slug": "lip-care", "name": "Lip Care", "icon": "lip" },
    { "id": 3, "slug": "eye-makeup", "name": "Eye Makeup", "icon": "eye" }
  ]
}
```

Field minimal: `slug` dan `name`. `icon` opsional dan hanya digunakan untuk memilih ikon kategori frontend.

## 4. GET `/products`

### Query parameter

| Parameter | Tipe | Contoh | Keterangan |
|---|---|---|---|
| `q` | string | `cushion` | Cari nama, SKU/kode, dan deskripsi |
| `category` | string | `complexion` | Filter berdasarkan slug kategori |
| `sort` | string | `price-asc` | `recommended`, `name`, `price-asc`, `price-desc`, `rating` |
| `page` | integer | `1` | Nomor halaman |
| `per_page` | integer | `12` | Jumlah item per halaman |

### Response contoh

```json
{
  "data": [
    {
      "id": 1,
      "sku": "RSK-CUSH-01",
      "code": "RSK-CUSH-01",
      "name": "Velvet Matte Cushion SPF 35",
      "description": "Cushion dengan hasil velvet matte.",
      "price": 185000,
      "stock": 20,
      "image_url": "http://localhost:8000/storage/products/cushion.jpg",
      "category": { "id": 1, "slug": "complexion", "name": "Complexion" },
      "rating": 4.9,
      "review_count": 184,
      "variants": [
        { "id": 11, "name": "Natural 01", "price": 185000, "stock": 8 },
        { "id": 12, "name": "Warm 02", "price": 185000, "stock": 12 }
      ]
    }
  ],
  "meta": { "current_page": 1, "per_page": 12, "total": 1, "last_page": 1 }
}
```

Frontend memetakan response dari Laravel ke bentuk internal: `priceLabel`, `categoryLabel`, `reviewCount`, `art`, dan field lainnya. Field server yang disarankan adalah `sku`, `price`, `stock`, `image_url`, `category`, `rating`, `review_count`, dan `variants`.

## 5. GET `/products/{id}`

Mengambil detail produk termasuk varian dan stok.

### Response contoh

```json
{
  "data": {
    "id": 1,
    "sku": "RSK-CUSH-01",
    "name": "Velvet Matte Cushion SPF 35",
    "description": "Cushion dengan hasil velvet matte untuk tampilan natural.",
    "price": 185000,
    "stock": 20,
    "image_url": "http://localhost:8000/storage/products/cushion.jpg",
    "category": { "id": 1, "slug": "complexion", "name": "Complexion" },
    "rating": 4.9,
    "review_count": 184,
    "variants": [
      { "id": 11, "name": "Natural 01", "price": 185000, "stock": 8 },
      { "id": 12, "name": "Warm 02", "price": 185000, "stock": 12 }
    ]
  }
}
```

Status yang disarankan: `404` jika produk tidak ditemukan, `200` jika sukses.

## 6. Cart API (memerlukan autentikasi)

Cart server bersifat opsional pada frontend Sprint 2. Secara default, guest cart disimpan menggunakan `localStorage`. Sinkronisasi API hanya aktif ketika:

1. Backend sudah mengimplementasikan endpoint cart.
2. Frontend sudah menerima access token dan menyimpannya sebagai `sessionStorage.riasku_access_token`.
3. `.env` menggunakan `VITE_CART_API_ENABLED=true`.

### GET `/cart`

Response contoh:

```json
{
  "data": {
    "items": [
      {
        "id": 501,
        "quantity": 2,
        "unit_price": 185000,
        "product": {
          "id": 1,
          "sku": "RSK-CUSH-01",
          "name": "Velvet Matte Cushion SPF 35",
          "price": 185000,
          "stock": 20,
          "image_url": null
        },
        "variant": { "id": 11, "name": "Natural 01" }
      }
    ]
  }
}
```

### POST `/cart/items`

Request:

```json
{
  "product_id": 1,
  "variant_id": 11,
  "quantity": 2
}
```

Backend harus memvalidasi produk aktif, varian milik produk, dan stok. Response mengembalikan item cart yang tersimpan, minimal memiliki `id`, `quantity`, `product`, dan `variant`.

### PATCH `/cart/items/{cartItemId}`

Request:

```json
{ "quantity": 3 }
```

`quantity` harus integer minimal 1 dan tidak boleh melebihi stok yang tersedia.

### DELETE `/cart/items/{cartItemId}`

Menghapus satu item dari cart user yang sedang login.

### DELETE `/cart`

Mengosongkan cart user yang sedang login.

## 7. Authentication header untuk Cart API

Ketika token tersedia, Axios frontend mengirim:

```http
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

Nama mekanisme token perlu disamakan dengan backend (misalnya Laravel Sanctum). Jangan mengirim token di URL atau menyimpan secret di `VITE_*` environment variable.

## 8. Mode mock dan mode API

### Mode frontend mandiri

```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_USE_MOCK_DATA=true
VITE_CART_API_ENABLED=false
```

Katalog dan kategori berasal dari `src/services/mockData.js`; cart disimpan lokal. Mode ini memungkinkan frontend dibuat dan diuji sebelum backend siap.

### Mode integrasi backend

```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_USE_MOCK_DATA=false
VITE_CART_API_ENABLED=true
```

Aktifkan konfigurasi ini hanya setelah endpoint produk/kategori dan endpoint cart selesai serta sudah diuji. Restart Vite setelah mengubah `.env`.

## 9. File frontend terkait

- `src/services/api.js`: konfigurasi Axios, base URL, dan bearer token.
- `src/services/productService.js`: `getCategories`, `getProducts`, `getProductById` dan normalisasi response.
- `src/services/cartService.js`: request untuk cart server.
- `src/services/mockData.js`: contoh kategori dan produk.
- `src/stores/cart.js`: state keranjang, localStorage, dan opsi sync remote.
- `src/views/customer/ProductListView.vue`: UI katalog, filter, sorting, pagination lokal.
- `src/views/customer/ProductDetailView.vue`: detail produk dan pilihan varian.
- `src/views/customer/CartView.vue`: UI cart dan ringkasan subtotal.

## 10. Catatan integrasi untuk tim backend

- Sepakati nama field dan response terlebih dahulu sebelum endpoint dianggap final.
- Frontend menganggap `price` dan `unit_price` adalah integer Rupiah.
- Pastikan URL gambar bisa diakses dari browser frontend dan CORS mengizinkan origin Vite.
- Cart server wajib memvalidasi stok pada server; validasi frontend hanya untuk pengalaman pengguna.
- Jika menggunakan format Laravel API Resource yang berbeda, ubah normalizer di `productService.js` dan `cart.js` sekaligus update dokumen ini.
