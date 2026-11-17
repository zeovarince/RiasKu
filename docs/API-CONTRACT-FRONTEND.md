# RiasKu Frontend API Contract

Frontend masih menyediakan mock data katalog dengan `VITE_USE_MOCK_DATA=true`. Alur checkout memakai Laravel API pelanggan dan token Sanctum.

Saat mengintegrasikan katalog live, ubah `VITE_USE_MOCK_DATA=false` pada `frontend/.env`; endpoint yang digunakan frontend tercantum di bawah.

| Method | Endpoint | Penggunaan |
|---|---|---|
| GET | `/api/categories` | Mengambil kategori pada homepage |
| GET | `/api/products?featured=1&per_page=4` | Mengambil produk best seller |
| GET | `/api/products` | Katalog dan pencarian produk |
| GET | `/api/products/{id}` | Detail produk |
| POST | `/api/auth/login` | Login pelanggan/admin |
| POST | `/api/auth/register` | Registrasi pelanggan |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/shipping/zones?q=` | Daftar zona pengiriman aktif (publik) |
| POST | `/api/shipping/rates` | Menghitung berat dan opsi ongkir (pelanggan terautentikasi) |
| POST | `/api/checkout` | Membuat pesanan dengan harga dan ongkir hasil hitung server |
| GET | `/api/orders` | Daftar pesanan milik pelanggan |
| GET | `/api/orders/{orderCode}` | Melihat status pesanan milik pelanggan |
| POST | `/api/orders/{orderId}/payments` | Membuat instruksi pembayaran online |
| POST | `/api/bookings` | Permintaan konsultasi/reservasi |

## Bentuk response yang disarankan

Gunakan format konsisten:

```json
{
  "data": [],
  "message": "OK"
}
```

Produk minimal memuat:

```json
{
  "id": 1,
  "code": "RSK-CUSH-01",
  "name": "Velvet Matte Cushion SPF 35",
  "price": 185000,
  "image_url": null,
  "category": "complexion",
  "rating": 4.9,
  "review_count": 184,
  "stock": 10,
  "is_featured": true
}
```

Quote pengiriman menghitung ulang berat produk dan kemasan di server. Berat tagihan ada pada setiap opsi karena mengikuti aturan kurir:

```json
{
  "data": {
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

Tarif seed adalah estimasi awal dan harus diverifikasi terhadap daftar tarif resmi JNE/J&T. Berat volumetrik belum aktif karena produk belum memiliki kolom dimensi; setelah kolom tersebut tersedia, berat tagihan produk menggunakan nilai terbesar antara berat aktual dan volumetrik (`P × L × T / 6000` kg).

## Catatan integrasi

- Endpoint checkout dan pengiriman di atas tersedia pada API pelanggan; endpoint katalog/pembayaran lainnya tetap mengikuti implementasi backend masing-masing.
- Isi quote checkout hanya mengirim product ID, variant ID, jumlah, dan zona. Harga, berat, serta ongkir dihitung ulang oleh server.
- Jangan menyimpan secret/payment private key pada `VITE_*`; variabel Vite terekspos ke browser.
- Jika API memakai Laravel Sanctum, sepakati strategi cookie/CSRF dengan backend. Jika memakai bearer token, jangan menyimpan token sensitif di localStorage tanpa pertimbangan keamanan.
- Untuk development lokal lintas origin, backend perlu mengatur CORS agar mengizinkan origin frontend, misalnya `http://localhost:5173`.
- Saat menjalankan via Docker Compose, browser tetap menggunakan `http://localhost:8000/api`, sedangkan koneksi antarkontainer memakai nama service Docker.
