# PPL-20 — Pembayaran COD Surabaya

**Issue GitHub:** `#22`  
**PIC:** `suryamaulana98`  
**Sprint:** Sprint 3 (implementasi inti) & Sprint 5 (hardening validasi nama kota, anti-duplikasi kurir, dan QA E2E)

---

## 1. Ringkasan Fitur & Tujuan

PPL-20 menyediakan metode **Cash on Delivery (COD / Bayar di Tempat)** yang dibatasi secara ketat hanya untuk wilayah pengiriman **Kota Surabaya**, sekaligus alur verifikasi pelunasan tunai di panel Admin. Implementasi mencakup:

1. **Validasi Ketat Wilayah Surabaya (Frontend & Backend):**
   - Frontend mendeteksi zona/kota pengiriman secara real-time: opsi COD aktif hanya ketika tujuan pengiriman adalah Kota Surabaya, dan otomatis dinonaktifkan (*disabled*) dengan penjelasan informatif untuk wilayah di luar Surabaya (seperti Sidoarjo, Gresik, Jakarta, dll.).
   - Backend memvalidasi ulang baik `shipping_city` maupun `destination_zone_id`: menolak kota di luar Surabaya termasuk string menipu seperti `"Bukan Surabaya"` dengan respon HTTP `422` pada field `shipping_city`.
2. **Alur Status Pesanan COD:**
   - Pesanan COD yang lolos validasi langsung dibuat dengan `order_status = diproses`, `payment_status = menunggu_pembayaran_cod`, stok langsung berkurang, dan keranjang pelanggan langsung dikosongkan.
   - Pelanggan tidak dapat memicu endpoint simulasi bayar online (`/api/payments/{id}/simulate-pay`) pada pesanan COD.
3. **Konfirmasi Pelunasan COD & Pengiriman oleh Admin:**
   - Admin memproses pengiriman melalui `PATCH /api/admin/orders/{id}/status` (didukung helper `Pesanan::formatCourierWithService()` untuk mencegah duplikasi layanan kurir seperti `JNE OKE OKE`).
   - Saat kurir telah menerima pembayaran tunai, Admin mengonfirmasi pelunasan melalui `POST /api/admin/orders/{id}/confirm-cod-payment`, yang mengubah `payment_status` menjadi lunas/dibayar dan memicu pembaruan skor Best Seller (`BestSellerService::refreshForOrder()`).

---

## 2. Aturan Validasi Wilayah COD

Konfigurasi default diatur melalui `SHIPPING_COD_CITIES=Surabaya` di `backend-api/.env` (`config/shipping.php`).

| Input Wilayah | Hasil Validasi | Respon |
| :--- | :---: | :--- |
| `Surabaya`, `Kota Surabaya`, zona dengan kota `Surabaya` | ✅ Diterima | Pesanan dibuat (`201 Created`), `order_status = diproses`, `payment_status = menunggu_pembayaran_cod`. |
| `Gresik`, `Sidoarjo`, `Jakarta`, `Malang` | ❌ Ditolak | HTTP `422 Unprocessable Entity` dengan error validasi pada `shipping_city`. |
| String menipu seperti `Bukan Surabaya`, `Luar Surabaya` | ❌ Ditolak | HTTP `422 Unprocessable Entity` (pencocokan ketat berbasis token nama kota resmi, bukan sekadar substring). |

---

## 3. Spesifikasi API

| Method | Endpoint | Akses | Fungsi |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/checkout` / `/api/orders` | Customer (`auth:sanctum`) | Membuat pesanan COD dengan `payment_method: "cod"` dan `payment_channel: "cash"`. |
| `GET` | `/api/orders/{orderCode}/track` | Publik (rate-limited) | Melacak status pesanan COD dan informasi kurir pengiriman (`shipping_courier`) secara publik. |
| `PATCH` | `/api/admin/orders/{id}/status` | Admin (`EnsureAdmin`) | Memperbarui status pesanan (misalnya `diproses` → `dikirim` → `selesai`) tanpa menduplikasi nama layanan kurir yang sudah tersimpan. |
| `POST` | `/api/admin/orders/{id}/confirm-cod-payment` | Admin (`EnsureAdmin`) | Menandai pembayaran tunai COD sebagai lunas/dibayar dan memperbarui metrik penjualan terkait. |

---

## 4. Struktur Komponen Frontend

| File | Peran |
| :--- | :--- |
| `frontend/src/views/customer/CheckoutView.vue` | Memeriksa zona/kota terpilih, mengaktifkan opsi COD khusus Surabaya, menonaktifkan COD untuk luar Surabaya, dan menampilkan pesan validasi. |
| `frontend/src/views/customer/OrderStatusView.vue` | Menampilkan status pesanan COD (`diproses` / `dikirim` / `selesai`) dan status pembayaran COD pelanggan. |
| `frontend/src/views/admin/OrdersView.vue` & `OrderDetailView.vue` | Mengelola daftar pesanan, update status pengiriman ke `dikirim`/`selesai`, dan tombol **Konfirmasi Pelunasan COD**. |

---

## 5. Skenario Pengujian

### 5.1 Pengujian Otomatis
```bash
cd backend-api
php artisan test tests/Feature/CustomerPaymentTest.php tests/Feature/Sprint5BugfixAndDashboardTest.php tests/Feature/QaIssue34BugfixTest.php
```

Cakupan test:
- COD luar Surabaya dan zona non-Surabaya ditolak `422` (`CustomerPaymentTest`).
- COD dengan string menipu `"Bukan Surabaya"` ditolak `422` (`Sprint5BugfixAndDashboardTest`).
- COD wilayah Surabaya berhasil (`201`) dan langsung berstatus `diproses` serta mengosongkan keranjang (`CustomerPaymentTest`, `Sprint5BugfixAndDashboardTest`).
- Pelanggan tidak bisa mensimulasikan pembayaran online pada pesanan COD (`CustomerPaymentTest`).
- Admin dapat mengonfirmasi penerimaan pembayaran COD (`confirm-cod-payment`) dan mengubah status ke `dikirim` tanpa duplikasi layanan kurir (`CustomerPaymentTest`, `QaIssue34BugfixTest`).

### 5.2 Verifikasi Manual / E2E
1. Di `/checkout`, pilih zona di **Kota Surabaya** → pastikan metode **COD (Bayar di Tempat)** aktif dan pesanan dapat dibuat.
2. Ubah zona tujuan ke **Gresik** atau **Jakarta** → pastikan opsi COD otomatis **disabled** dan tidak bisa dipilih.
3. Login sebagai Admin, buka detail pesanan COD tersebut, ubah status ke `dikirim` (pastikan label kurir tidak ganda), lalu klik konfirmasi pelunasan COD.
