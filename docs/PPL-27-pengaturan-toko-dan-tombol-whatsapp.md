# PPL-27 — Pengaturan Toko & Tombol WhatsApp

## 1. Ringkasan Fitur & Tujuan

PPL-27 menyediakan satu sumber konfigurasi untuk identitas toko dan kontak WhatsApp. Admin mengubah profil melalui dashboard. Footer, tombol WhatsApp melayang, dan aksi tanya produk di halaman pelanggan membaca data yang sama dari API.

Tombol WhatsApp secara default nonaktif. Admin harus mengisi nomor Indonesia yang valid sebelum mengaktifkannya, sehingga website tidak menampilkan nomor contoh sebagai kontak toko.

## 2. Perubahan Database & Normalisasi Nomor WhatsApp

Migrasi `2026_11_25_000001_create_store_settings_table.php` membuat tabel `store_settings` dengan pola single-row (`id = 1`). `StoreSettings::current()` membuat data default secara otomatis apabila baris belum ada; `StoreSettingsSeeder` juga membuat baris itu saat database di-seed dan tidak menimpa pengaturan yang telah disimpan.

| Kolom | Default | Keterangan |
| --- | --- | --- |
| `store_name` | `RiasKu` | Nama toko, wajib diisi, maksimal 120 karakter. |
| `store_tagline` | string kosong | Deskripsi singkat, maksimal 255 karakter. |
| `store_logo` | `null` | URL logo, maksimal 2048 karakter. Upload file belum digunakan. |
| `store_address` | string kosong | Alamat toko/pengiriman, maksimal 2000 karakter. |
| `operational_hours` | string kosong | Jam operasional, maksimal 500 karakter. |
| `whatsapp_number` | `null` | Nomor dinormalisasi ke format `628...`. |
| `whatsapp_default_message` | `Halo RiasKu, saya ingin bertanya.` | Template maksimal 500 karakter; mendukung `{product_name}` dan `{order_id}`. |
| `is_whatsapp_active` | `false` | Tombol baru aktif jika nomor valid juga tersedia. |

Backend menerima nomor Indonesia dalam bentuk `08...`, `8...`, `62...`, atau `+62...`, dengan spasi, tanda kurung, titik, dan tanda hubung opsional. Contoh `+62 812-3456-7890` dan `0812 3456 7890` disimpan sebagai `6281234567890`. Nomor yang tidak sesuai ditolak dengan HTTP `422`. Nomor wajib valid saat `is_whatsapp_active=true`; ketika tombol nonaktif, nomor publik dikembalikan sebagai `null`.

## 3. Spesifikasi API

Semua response menggunakan envelope `message` dan `data`.

### 3.1 `GET /api/store-settings` — publik

Tidak membutuhkan autentikasi. Mengembalikan profil toko dan konfigurasi pesan. `whatsapp_number` hanya disertakan saat tombol WhatsApp aktif.

Contoh response:

```json
{
  "message": "Pengaturan toko berhasil dimuat.",
  "data": {
    "store_name": "RiasKu",
    "store_tagline": "Kosmetik pilihan",
    "store_logo": null,
    "store_address": "Surabaya, Jawa Timur",
    "operational_hours": "Senin–Sabtu, 09.00–17.00 WIB",
    "whatsapp_number": "6281234567890",
    "whatsapp_default_message": "Halo RiasKu, saya ingin bertanya tentang {product_name}.",
    "is_whatsapp_active": true,
    "updated_at": "2026-11-25T08:00:00.000000Z"
  }
}
```

### 3.2 `GET /api/admin/settings/store` — admin

Membaca seluruh konfigurasi, termasuk nomor WhatsApp ketika tombol nonaktif. Membutuhkan bearer token Sanctum untuk akun admin.

### 3.3 `PUT /api/admin/settings/store` — admin

Memperbarui profil toko dan konfigurasi WhatsApp. Membutuhkan bearer token Sanctum untuk akun admin.

Request body:

```json
{
  "store_name": "RiasKu Studio",
  "store_tagline": "Kosmetik pilihan untuk setiap hari",
  "store_logo": "https://example.com/riasku.png",
  "store_address": "Surabaya, Jawa Timur",
  "operational_hours": "Senin–Sabtu, 09.00–17.00 WIB",
  "whatsapp_number": "081234567890",
  "whatsapp_default_message": "Halo RiasKu, saya ingin bertanya tentang {product_name}.",
  "is_whatsapp_active": true
}
```

Response sukses berisi data tersimpan; nomor WhatsApp sudah dinormalisasi ke `6281234567890`. Field wajib adalah `store_name` dan `is_whatsapp_active`; field lain boleh kosong. Kesalahan autentikasi menghasilkan `401`, akun non-admin `403`, dan validasi input `422` dengan daftar `errors` per field.

## 4. Struktur Komponen & Halaman Frontend

| File | Peran |
| --- | --- |
| `frontend/src/views/admin/StoreSettingsView.vue` | Form profil, nomor WhatsApp, toggle aktif, validasi field, notifikasi berhasil/gagal, dan pratinjau pesan langsung. |
| `frontend/src/services/adminService.js` | Memanggil `GET` dan `PUT /api/admin/settings/store`. |
| `frontend/src/stores/storeSettings.js` | Menyimpan cache konfigurasi publik untuk layout dan halaman customer. |
| `frontend/src/components/AppFooter.vue` | Menampilkan nama, deskripsi, alamat, jam operasional, dan kontak WhatsApp aktif. |
| `frontend/src/layouts/CustomerLayout.vue` | Memuat data toko dan menampilkan FAB WhatsApp di kanan bawah bila status aktif dan nomor valid. Link memakai `https://wa.me/{nomor}?text={pesan-encoded}`. |
| `frontend/src/views/customer/ProductDetailView.vue` | Menyediakan aksi **Tanya Produk via WhatsApp** dengan nama produk di pesan. |
| `frontend/src/utils/whatsapp.js` | Normalisasi defensif nomor dan pembuatan pesan/link WhatsApp. |

Template pesan mendukung placeholder `{product_name}` dan `{order_id}`. Aksi tanya produk mengisi nama produk; apabila template tidak memuat placeholder produk, nama produk ditambahkan pada akhir pesan. Perubahan konfigurasi terlihat di area pelanggan setelah data publik dimuat ulang atau halaman dibuka kembali.

## 5. Skenario Pengujian

### 5.1 Otomatis

```bash
cd backend-api
vendor/bin/pint --dirty --format agent
php -d extension=pdo_sqlite vendor/bin/pest --compact tests/Feature/StoreSettingsTest.php
php -d extension=pdo_sqlite vendor/bin/pest --compact
cd ../frontend
npm run build
```

`StoreSettingsTest` mencakup default publik, normalisasi nomor saat penyimpanan, publikasi konfigurasi aktif, penyembunyian nomor ketika nonaktif, validasi nomor, serta pembatasan route admin (`401` tanpa token dan `403` untuk pelanggan).

### 5.2 Manual via UI

1. Login admin, buka **Pengaturan Toko**, ubah nama, deskripsi, URL logo, alamat, jam operasional, dan pesan. Simpan dan pastikan notifikasi sukses muncul serta data tetap terisi setelah halaman dimuat ulang.
2. Masukkan nomor `0812 3456-7890`, aktifkan tombol WhatsApp, lalu simpan. Pastikan nomor tersimpan/ditampilkan sebagai `6281234567890` dan pratinjau pesan diperbarui saat template diedit.
3. Buka halaman pelanggan atau muat ulang. Pastikan footer memuat identitas baru, FAB WhatsApp tampil, dan link menggunakan nomor `6281234567890` serta pesan yang di-URL-encode.
4. Buka detail produk dan tekan **Tanya Produk via WhatsApp**. Pastikan pesan memuat nama produk aktif.
5. Nonaktifkan toggle WhatsApp dan simpan. Muat ulang halaman pelanggan; FAB, kontak WA footer, dan aksi WA produk harus tersembunyi.
6. Coba simpan dengan nomor tidak valid saat toggle aktif. Pastikan error validasi muncul di form dan konfigurasi sebelumnya tidak berubah.
7. Panggil endpoint admin tanpa token dan dengan token pelanggan; pastikan response `401` dan `403`. `GET /api/store-settings` harus tetap dapat dibaca tanpa login.
