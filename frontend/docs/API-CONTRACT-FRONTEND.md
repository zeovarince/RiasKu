# RiasKu Frontend API Contract (Draft)

Frontend saat ini menggunakan mock data dengan `VITE_USE_MOCK_DATA=true` agar halaman dapat dikembangkan sebelum Laravel API tersedia.

Ketika backend siap, ubah `VITE_USE_MOCK_DATA=false` pada `frontend/.env` dan implementasikan endpoint berikut di Laravel.

| Method | Endpoint | Penggunaan |
|---|---|---|
| GET | `/api/categories` | Mengambil kategori pada homepage |
| GET | `/api/products?featured=1&per_page=4` | Mengambil produk best seller |
| GET | `/api/products` | Katalog dan pencarian produk |
| GET | `/api/products/{id}` | Detail produk |
| POST | `/api/auth/login` | Login pelanggan/admin |
| POST | `/api/auth/register` | Registrasi pelanggan |
| POST | `/api/auth/logout` | Logout |
| POST | `/api/orders` | Membuat pesanan/checkout |
| GET | `/api/orders/{orderCode}` | Melihat status pesanan |
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

## Catatan integrasi

- Endpoint di atas adalah kontrak awal untuk disepakati dengan tim backend, bukan klaim bahwa endpoint tersebut sudah tersedia.
- Jangan menyimpan secret/payment private key pada `VITE_*`; variabel Vite terekspos ke browser.
- Jika API memakai Laravel Sanctum, sepakati strategi cookie/CSRF dengan backend. Jika memakai bearer token, jangan menyimpan token sensitif di localStorage tanpa pertimbangan keamanan.
- Untuk development lokal lintas origin, backend perlu mengatur CORS agar mengizinkan origin frontend, misalnya `http://localhost:5173`.
- Saat menjalankan via Docker Compose, browser tetap menggunakan `http://localhost:8000/api`, sedangkan koneksi antarkontainer memakai nama service Docker.
