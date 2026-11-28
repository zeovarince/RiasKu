# PPL-19 — Pembayaran Online (QRIS, Virtual Account & E-Wallet)

**Issue GitHub:** `#21`  
**PIC:** `suryamaulana98`  
**Sprint:** Sprint 3 (implementasi inti) & Sprint 5 (hardening production, pembatalan pending order, dan perbaikan deteksi channel e-wallet)

---

## 1. Ringkasan Fitur & Tujuan

PPL-19 mengintegrasikan pembayaran non-tunai RiasKu menggunakan **Midtrans Core API (`POST /v2/charge`)** dengan dukungan mode sandbox/simulator lokal terkendali untuk pengujian. Implementasi mencakup:

1. **Pilihan Metode Pembayaran Lengkap:**
   - **QRIS** (gambar QR & string payload + timer kedaluwarsa).
   - **Virtual Account (VA):** Bank BCA, BNI, BRI, dan **Mandiri Bill** (`biller_code` + `bill_key`).
   - **E-Wallet:** GoPay, ShopeePay, dan DANA (mempertahankan `payment_type` masing-masing channel pada response API maupun label modal pembayaran).
2. **Integrasi Ongkir & Checkout Terpadu:** Total tagihan yang dikirim ke Midtrans merupakan penjumlahan subtotal produk + ongkos kirim zona tujuan (`PPL-18` + `PPL-19`), dengan validasi stok dan pengurangan stok atomik saat pesanan dibuat.
3. **Sinkronisasi Status Real-Time & Webhook:**
   - Auto-polling status pembayaran di `PaymentModal.vue` tiap 5 detik + tombol cek status manual (`POST /api/payments/{orderNumber}/sync-status`).
   - Endpoint webhook Midtrans (`POST /api/payments/midtrans/webhook`) memverifikasi signature **SHA-512**, mengubah status pesanan menjadi `diproses` saat `settlement`, dan **merestorasi stok** secara otomatis saat transaksi `expire` / `cancel` / `deny`.
4. **Hardening Production & Simulator Terkendali (Sprint 5):**
   - Di mode `production`, `MidtransService` menolak fallback simulasi diam-diam apabila request charge gagal atau mengembalikan payload HTTP 200 dengan `status_code >= 400`.
   - Endpoint simulator 1-klik (`POST /api/payments/{id}/simulate-pay`) hanya aktif bila `MIDTRANS_ALLOW_SANDBOX_SIMULATOR=true` di luar production dan otomatis ditolak di production.
   - Pelanggan dapat membatalkan pesanan online berstatus pending (`POST /api/orders/{id}/cancel`), yang otomatis merestorasi stok dan mempertahankan item keranjang.
   - Pembuatan ulang payment intent (`POST /api/orders/{id}/payments`) otomatis membuat record baru bila channel pembayaran diganti, dan menyertakan instruksi bayar lengkap saat reuse.

---

## 2. Struktur Database

| Tabel / Model | Kolom Kunci | Keterangan |
| :--- | :--- | :--- |
| `pesanan` (`App\Models\Pesanan`) | `order_code`, `pelanggan_id`, `payment_method`, `payment_status`, `order_status`, `subtotal`, `shipping_cost`, `total_amount` | Menyimpan ringkasan pesanan, metode bayar (`online` / `cod`), dan status transaksi. |
| `pesanan_item` (`App\Models\PesananItem`) | `pesanan_id`, `produk_id`, `produk_varian_id`, `quantity`, `unit_price`, `line_total` | Snapshot item & harga saat checkout. |
| `pembayaran` (`App\Models\Pembayaran`) | `pesanan_id`, `order_number`, `payment_type`, `bank`, `va_number`, `bill_key`, `biller_code`, `qr_url`, `deeplink_url`, `transaction_status`, `midtrans_mode`, `paid_at`, `expired_at` | Menyimpan detail transaksi Midtrans/simulator, instruksi bayar, dan jejak pelunasan. |

---

## 3. Spesifikasi API

| Method | Endpoint | Akses | Fungsi |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/checkout` / `/api/orders` | Customer (`auth:sanctum`) | Membuat pesanan baru + memicu charge pembayaran online (`payment_method: "online"`, `payment_channel: "qris" \| "bca_va" \| "bni_va" \| "bri_va" \| "mandiri_bill" \| "gopay" \| "shopeepay" \| "dana"`). Mengembalikan detail `order` dan `payment` beserta `midtrans_mode`. |
| `POST` | `/api/orders/{id}/payments` | Customer (`auth:sanctum`) | Membuat atau mengganti channel pembayaran untuk pesanan pending. |
| `POST` | `/api/orders/{id}/cancel` | Customer (`auth:sanctum`) | Membatalkan pesanan online pending milik sendiri dan mengembalikan stok varian. |
| `GET` | `/api/payments/{id}` | Customer (`auth:sanctum`) | Melihat status dan instruksi pembayaran satu transaksi (hanya pemilik pesanan). |
| `POST` | `/api/payments/{orderNumber}/sync-status` | Customer (`auth:sanctum`) | Menarik status terbaru dari Midtrans API (atau status simulasi lokal). |
| `POST` | `/api/payments/{id}/simulate-pay` | Customer (`auth:sanctum`) | Simulasi pelunasan cepat di sandbox/lokal ketika `MIDTRANS_ALLOW_SANDBOX_SIMULATOR=true` (ditolak untuk COD & production). |
| `POST` | `/api/payments/midtrans/webhook` | Publik (Midtrans Server) | Menerima notifikasi server-to-server Midtrans dengan verifikasi signature SHA-512 (alias: `/api/payments/midtrans-notification`). |

---

## 4. Konfigurasi Environment (`backend-api/.env`)

```env
MIDTRANS_MERCHANT_ID=
MIDTRANS_CLIENT_KEY=
MIDTRANS_SERVER_KEY=<sandbox-server-key>
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_ALLOW_SANDBOX_SIMULATOR=true
MIDTRANS_BASE_URL=https://api.sandbox.midtrans.com
```

- Jika `MIDTRANS_SERVER_KEY` terisi pada `MIDTRANS_IS_PRODUCTION=false`, transaksi berjalan di **Midtrans Sandbox** dan dapat dilunasi melalui `https://simulator.sandbox.midtrans.com/` atau tombol simulator lokal (bila `MIDTRANS_ALLOW_SANDBOX_SIMULATOR=true`).
- Jika `MIDTRANS_SERVER_KEY` kosong di lingkungan lokal, backend otomatis memakai mode simulasi lokal tanpa error sambil tetap mempertahankan `payment_type` tiap metode (termasuk `gopay`, `shopeepay`, `dana`).

---

## 5. Struktur Komponen & Service Frontend

| File | Peran |
| :--- | :--- |
| `frontend/src/views/customer/CheckoutView.vue` | Form alamat, pilih zona & ongkir, pemilihan metode pembayaran online (QRIS / VA / Mandiri / E-Wallet), dan pengiriman request checkout. |
| `frontend/src/components/PaymentModal.vue` | Modal instruksi pembayaran lengkap: QRIS, nomor VA + tombol salin, Mandiri Bill Key, E-Wallet (`isEwallet` diprioritaskan dengan label `GOPAY`/`SHOPEEPAY`/`DANA`), countdown timer, auto-polling status 5 detik, dan tombol simulator sandbox. |
| `frontend/src/views/customer/OrderStatusView.vue` | Menampilkan riwayat pesanan, tombol lanjut bayar / cek status, dan aksi pembatalan untuk pesanan pending. |
| `frontend/src/services/paymentService.js` | Integrasi API payment intent, status, sync-status, dan simulate-pay. |
| `frontend/src/services/checkoutService.js` | Integrasi API `/api/checkout`, `/api/orders`, dan `/api/orders/{id}/cancel`. |

---

## 6. Skenario Pengujian

### 6.1 Pengujian Otomatis
```bash
cd backend-api
php artisan test tests/Feature/CustomerPaymentTest.php tests/Feature/Sprint5BugfixAndDashboardTest.php tests/Feature/QaIssue34BugfixTest.php
```

Cakupan test:
- Charge QRIS, BCA VA, Mandiri Bill, dan unified checkout (subtotal + ongkir) baik dalam mode HTTP fake Midtrans maupun simulator lokal (`CustomerPaymentTest`).
- Verifikasi signature webhook SHA-512 (`200` valid, `403` invalid) serta restorasi stok saat `expire`/`cancel`/`deny` (`CustomerPaymentTest`).
- Penolakan silent fallback di production, perilaku `createIntent` saat ganti channel, pembatalan pending order, proteksi simulator di production (`Sprint5BugfixAndDashboardTest`).
- Preservasi `payment_type` untuk `gopay` dan `shopeepay` pada simulasi lokal (`QaIssue34BugfixTest`).
