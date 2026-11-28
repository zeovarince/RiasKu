# PPL-16 — Keranjang Belanja

**Issue GitHub:** `#18`  
**PIC:** `suryamaulana98`  
**Sprint:** Sprint 2 (implementasi inti) & Sprint 5 (penyempurnaan sinkronisasi sesi, perilaku pasca-checkout, dan hidrasi login modal)

---

## 1. Ringkasan Fitur & Tujuan

PPL-16 menyediakan fitur keranjang belanja pelanggan yang konsisten antara state frontend (Pinia + `localStorage`) dan penyimpanan persisten backend PostgreSQL melalui API `/api/cart`. Implementasi mencakup:

1. Penambahan produk dan varian ke keranjang dari kartu katalog maupun halaman detail produk, dengan penggabungan kuantitas otomatis untuk pasangan produk + varian yang sama.
2. Penyesuaian kuantitas (`+` / `−`) dengan validasi batas stok varian di frontend dan backend, penghapusan item satuan, serta pengosongan seluruh keranjang.
3. Perhitungan otomatis total item, subtotal per item, dan total harga belanja secara real-time, lengkap dengan *empty state* ketika keranjang kosong.
4. **Smart Bidirectional Sync** (`VITE_CART_API_ENABLED=true`): saat pengguna login (baik via halaman `/auth/login` maupun modal `GlobalModalHost`), item keranjang disinkronkan dengan server sehingga tidak hilang setelah refresh halaman (F5) atau pindah perangkat.
5. **Keamanan & Konsistensi Pasca-Checkout:**
   - Logout langsung membersihkan state keranjang lokal agar tidak terbawa ke akun lain.
   - Checkout **COD** langsung mengosongkan keranjang server setelah pesanan berhasil dibuat.
   - Checkout **Pembayaran Online** mempertahankan keranjang sampai pembayaran berstatus `settlement` (dan tetap terjaga jika pesanan pending dibatalkan), sedangkan jalur checkout legacy `CheckoutController` turut membersihkan keranjang dengan benar.

---

## 2. Struktur Database

| Tabel / Model | Kolom Utama | Keterangan |
| :--- | :--- | :--- |
| `keranjang` (`App\Models\Keranjang`) | `id`, `pelanggan_id` | Satu keranjang aktif per pelanggan terautentikasi. |
| `keranjang_item` (`App\Models\KeranjangItem`) | `id`, `keranjang_id`, `produk_id`, `produk_varian_id`, `quantity`, `unit_price` | Menyimpan item, varian terpilih, kuantitas, dan harga satuan saat dimasukkan. |

---

## 3. Spesifikasi API

Seluruh endpoint keranjang berada di bawah middleware `auth:sanctum` dan `EnsurePelanggan` (`/api/cart*`). Permintaan tanpa token mengembalikan HTTP `401`.

| Method | Endpoint | Fungsi |
| :--- | :--- | :--- |
| `GET` | `/api/cart` | Mengambil seluruh item keranjang pelanggan beserta detail produk, foto (`image_url`), varian, stok, dan ringkasan subtotal/total. |
| `POST` | `/api/cart/items` | Menambahkan item (`produk_id`, `produk_varian_id`, `quantity`). Jika produk + varian sudah ada di keranjang, kuantitas ditambahkan selama tidak melampaui stok tersedia. |
| `PATCH` | `/api/cart/items/{id}` | Mengubah kuantitas satu item keranjang dalam batas stok varian (`422` bila melebihi stok atau `< 1`). |
| `DELETE` | `/api/cart/items/{id}` | Menghapus satu item dari keranjang. |
| `DELETE` | `/api/cart` | Mengosongkan seluruh item keranjang pelanggan. |

---

## 4. Struktur Store & Komponen Frontend

| File | Peran |
| :--- | :--- |
| `frontend/src/stores/cart.js` | Store Pinia untuk state keranjang, kalkulasi subtotal/total, persistensi `localStorage`, `hydrateCart()`, serta sinkronisasi API ke `/api/cart*`. |
| `frontend/src/services/cartService.js` | Wrapper Axios untuk `GET`, `POST`, `PATCH`, dan `DELETE` endpoint keranjang. |
| `frontend/src/views/customer/CartView.vue` | Halaman keranjang belanja (`/cart`): daftar item berfoto asli, kontrol kuantitas, tombol hapus, ringkasan belanja, *empty state*, dan tombol lanjut ke `/checkout`. |
| `frontend/src/components/AppHeader.vue` | Menampilkan badge jumlah item keranjang secara real-time, menu profil dropdown, dan pembersihan keranjang saat logout. |
| `frontend/src/components/GlobalModalHost.vue` | Memanggil `auth.setSession()` diikuti `cart.hydrateCart()` saat pelanggan login atau mendaftar melalui modal autentikasi. |

---

## 5. Skenario Pengujian

### 5.1 Pengujian Otomatis
```bash
cd backend-api
php artisan test tests/Feature/CustomerCartTest.php tests/Feature/Sprint5BugfixAndDashboardTest.php
```

Cakupan test:
- Guest tanpa token ditolak mengakses `/api/cart` (`401`).
- Mengambil keranjang kosong, menambah item baru, auto-increment saat menambah produk + varian yang sama, update kuantitas dalam batas stok, hapus item, dan kosongkan keranjang (`CustomerCartTest`).
- Pembersihan keranjang pada checkout COD & jalur legacy `CheckoutController`, serta pemeliharaan keranjang pada pending online checkout hingga settlement (`Sprint5BugfixAndDashboardTest`).

### 5.2 Verifikasi Manual / E2E
1. Login sebagai pelanggan (`surya@riasku.com`), tambahkan produk dengan varian tertentu dari `/products` atau `/products/:id`.
2. Buka `/cart`, ubah kuantitas `+` / `−`, pastikan subtotal & total diperbarui instan.
3. Tekan **F5 (Refresh)** — pastikan seluruh item tetap utuh tersinkronisasi dari PostgreSQL.
4. Logout lalu login kembali — pastikan keranjang bersih saat logout dan kembali termuat setelah login.
