# Kontrak API Frontend

Dokumen ini merangkum endpoint API pelanggan yang dipakai frontend RiasKu.

## Konfigurasi dan autentikasi

- Base URL lokal API pelanggan: `http://localhost:8000/api`.
- Base URL lokal API admin: `http://localhost:8000/api/admin`; keduanya dijalankan dari `backend-api/`.
- Atur `VITE_USE_MOCK_DATA=false` di `frontend/.env` untuk memakai katalog dari API. Nilai selain `false` mengaktifkan mock data.
- Endpoint pelanggan terlindungi memakai `auth:sanctum` dan middleware `EnsurePelanggan`. Kirim token Sanctum sebagai bearer token.
- Semua endpoint memakai prefix `/api`.

## Endpoint

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| POST | `/api/auth/register` | Publik | Registrasi pelanggan |
| POST | `/api/auth/login` | Publik | Login pelanggan |
| POST | `/api/auth/google` | Publik | Login Google |
| GET | `/api/auth/me` | Pelanggan | Profil pelanggan aktif |
| POST | `/api/auth/logout` | Pelanggan | Menghapus token aktif |
| GET | `/api/categories` | Publik | Daftar kategori |
| GET | `/api/products` | Publik | Katalog dan pencarian produk |
| GET | `/api/products/{id}` | Publik | Detail produk |
| GET | `/api/products/{product}/reviews` | Publik | Daftar ulasan produk yang tampil |
| POST | `/api/products/{product}/reviews` | Pelanggan | Menambah atau memperbarui ulasan sendiri |
| DELETE | `/api/products/{product}/reviews` | Pelanggan | Menghapus ulasan sendiri |
| GET | `/api/admin/reviews` | Admin | Daftar ulasan untuk moderasi |
| PATCH | `/api/admin/reviews/{review}` | Admin | Mengubah status ulasan |
| GET | `/api/shipping/zones?q=` | Publik | Zona aktif; dibatasi 60 request per menit |
| POST | `/api/shipping/rates` | Pelanggan | Quote berat dan ongkir |
| POST | `/api/checkout` | Pelanggan | Membuat pesanan |
| GET | `/api/orders` | Pelanggan | Daftar pesanan milik pelanggan |
| GET | `/api/orders/{orderCode}` | Pelanggan | Detail dan status pesanan milik pelanggan |

`GET /api/products` menerima parameter `q` atau `search`, `category`, `sort`, `per_page`, dan `page`.
Respons daftar produk dan kategori menggunakan envelope `message` dan `data`; daftar produk dapat menyertakan `meta` saat paginasi. Frontend menormalisasi atribut produk dan variasi sebelum dipakai oleh komponen.

## Rating dan ulasan produk (PPL-25)

`GET /api/products/{product}/reviews` mengembalikan hanya ulasan berstatus `approved`, diurutkan dari yang terbaru. Tanpa parameter `page` atau `per_page`, respons berisi maksimal 50 ulasan:

```json
{
  "message": "Daftar ulasan berhasil dimuat.",
  "data": [
    {
      "id": 1,
      "product_id": 12,
      "user_name": "pelanggan",
      "rating": 5,
      "comment": "Teksturnya ringan dan tahan seharian.",
      "created_at": "2026-11-23T04:00:00.000000Z"
    }
  ]
}
```

Kirim `page` dan/atau `per_page` (maksimal 50) untuk mendapatkan `meta` berisi `current_page`, `last_page`, `per_page`, dan `total`.

`POST /api/products/{product}/reviews` memerlukan token pelanggan. Request hanya mengirim `rating` (bilangan bulat 1-5) dan `comment` (5-500 karakter). `user_name` ditentukan server dari username pelanggan yang login, bukan dari request. Satu pelanggan hanya memiliki satu ulasan per produk; pengiriman kedua memperbarui ulasan yang ada dan mengembalikan HTTP `200` (pengiriman pertama `201`). Ulasan baru otomatis berstatus `approved`.

`DELETE /api/products/{product}/reviews` menghapus ulasan milik pelanggan yang sedang login.

`GET /api/admin/reviews` menerima `q`, `status` (`all`, `approved`, atau `hidden`), serta `per_page`, dan mengembalikan data dengan `id`, `product_id`, `product_name`, `product_sku`, `customer`, `customer_email`, `rating`, `comment`, `status`, dan `created_at`. `PATCH /api/admin/reviews/{review}` menerima `status` berupa `approved` atau `hidden`. Ulasan yang disembunyikan tidak masuk daftar publik dan tidak dihitung pada `rating` maupun `review_count` produk.

Atribut `rating` dan `review_count` pada respons produk dihitung dari ulasan berstatus `approved` (bukan nilai hardcoded). Rata-rata dibulatkan menjadi satu angka desimal; produk tanpa ulasan mengembalikan `rating` bernilai `null`.

## Quote ongkir

`POST /api/shipping/rates` memerlukan token pelanggan. Harga, berat, dan ongkir selalu dihitung ulang oleh server.

Request hanya mengirim zona, produk, varian, dan jumlah:

```json
{
  "destination_zone_id": 85,
  "items": [
    {
      "product_id": 12,
      "variant_id": 34,
      "quantity": 1
    }
  ]
}
```

Contoh response:

```json
{
  "message": "Ongkos kirim berhasil dihitung.",
  "data": {
    "destination": {
      "id": 85,
      "province": "Jawa Timur",
      "city": "Surabaya",
      "district": "Genteng",
      "postal_code": "60271"
    },
    "subtotal": 52000,
    "weight_grams": 2700,
    "packaging_weight_grams": 0,
    "options": [
      {
        "courier": "JNE",
        "service": "REG",
        "billable_weight_kg": 3,
        "min_billable_weight_grams": 1000,
        "rounding_step_grams": 1000,
        "cost": 27000,
        "etd_min_days": 1,
        "etd_max_days": 2
      }
    ]
  }
}
```

`weight_grams` adalah berat aktual produk ditambah kemasan. Berat tagihan ada pada setiap opsi karena mengikuti aturan kurir. Tarif seed merupakan estimasi awal dan masih perlu diverifikasi terhadap tarif resmi JNE/J&T.

## Checkout dan pesanan

`POST /api/checkout` menerima data penerima, metode pembayaran, zona, layanan yang dipilih, dan item:

```json
{
  "name": "Nama Penerima",
  "phone": "08123456789",
  "address": "Alamat lengkap",
  "payment_method": "online",
  "destination_zone_id": 85,
  "courier": "JNE",
  "service": "REG",
  "notes": null,
  "items": [
    {
      "product_id": 12,
      "variant_id": 34,
      "quantity": 1
    }
  ]
}
```

Respons checkout memuat pesanan dan item, termasuk `order_code`, `subtotal`, `shipping_cost`, `total`, `weight_grams`, `packaging_weight_grams`, serta `billable_weight_kg` untuk layanan terpilih. Harga dan total final dari client tidak dipakai.

Request yang tidak valid mengembalikan HTTP `422` dengan detail pada `errors`. Pesanan dan daftar pesanan hanya dapat diakses oleh pelanggan pemiliknya.

## Aturan berat dan ongkir

- Berat aktual produk dihitung dari `weight_grams × quantity`. Jika berat produk nol, server memakai `shipping.default_weight_grams`.
- Server menambahkan `shipping.packaging_weight_grams` satu kali per pesanan.
- JNE dan J&T memakai minimum tagihan 1 kg dan pembulatan ke atas per kg. Ongkir adalah berat tagihan dikali tarif per kg.
- Ambang gratis ongkir, jika diaktifkan, mengubah biaya menjadi nol tanpa mengubah berat yang dikembalikan.
- Berat volumetrik belum aktif karena tabel produk belum memiliki dimensi. Setelah kolom dimensi tersedia, gunakan nilai terbesar antara berat aktual dan berat volumetrik (`P × L × T / 6000` kg).

## Catatan integrasi

- Cart frontend disimpan lokal. Endpoint `/api/cart` yang tersedia di backend masih stub dan bukan sumber data checkout.
- Endpoint pembayaran `/api/orders/{orderId}/payments` dan booking `/api/bookings` belum tersedia pada route API pelanggan saat ini.
- Jangan mengirim atau mempercayai `unit_price`, `weight_grams`, `shipping_cost`, maupun `total` dari client.
- Jangan menyimpan API key atau secret di variabel `VITE_*`; nilainya dapat diakses dari browser.
- Untuk development lintas origin, konfigurasi CORS agar mengizinkan frontend pelanggan (`http://localhost:5173`) dan panel admin (`http://localhost:5174`).
