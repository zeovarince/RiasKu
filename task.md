## PPL-13 Autentikasi: backend
- [x] Tambah `pelanggan.google_id` (unik, boleh kosong) dan buat `password_hash` pelanggan boleh kosong
- [x] Buat Model Admin dan Pelanggan dengan token akses (Sanctum)
- [x] Seeder satu akun admin (D3)
- [x] API App Utama: register (A1 sampai A5)
- [x] API App Utama: login email dan password, pesan gagal seragam, tolak akun nonaktif (A6, A8, A9)
- [x] API Dashboard: login admin dengan pesan gagal seragam (D1, D2)
- [x] Kedua API: logout (cabut token) dan `GET /me` (T1, T2)
- [x] Middleware akses: fungsi dashboard hanya untuk admin, token pelanggan ditolak (T4, T5)
- [x] Middleware akses: fungsi pelanggan (checkout, Pesanan Saya) hanya untuk pelanggan (T6)
- [x] Batasi percobaan login (rate limit) dan atur CORS untuk dua frontend
- [x] Uji otomatis: register, login, logout, akses ditolak (401 dan 403)

## PPL-13 Autentikasi: login Google
- [x] Buat project Google Cloud, layar persetujuan, dan OAuth Client ID (redirect lokal dan produksi)
- [x] Simpan Client ID dan Secret di `.env` (bukan di repo)
- [x] Endpoint `POST /api/login/google`: verifikasi identitas ke Google (G2)
- [x] Buat akun otomatis bila email baru, hubungkan bila email sudah ada dan terverifikasi (G3, G4)
- [x] Tolak akun nonaktif dan akun tanpa password yang login lewat password (G5, G6)
- [x] Uji otomatis jalur Google (dengan verifikasi Google ditiru)

## PPL-13 Autentikasi: frontend
- [x] App utama: halaman Login dan Register (A1 sampai A9), tombol "Masuk dengan Google" (G1)
- [x] App utama: penjaga halaman checkout, arahkan ke Login, keranjang tetap utuh, kembali ke halaman asal (A7, A10)
- [x] Dashboard: halaman Login admin dan penjaga seluruh halaman dashboard (D1, D4)
- [x] Simpan token, kirim di setiap permintaan, tangani 401 (logout otomatis)

## PPL-14 Manajemen Produk: backend (API Dashboard)
- [x] Migration dan Model: `kategori`, `produk`, `produk_varian`, `produk_foto`
- [x] Kategori: daftar, tambah, ubah nama, hapus hanya bila kosong (K1 sampai K4)
- [x] Produk: tambah, ubah, detail (P1, P2)
- [x] Produk: aktifkan dan nonaktifkan, tanpa hapus permanen (P3)
- [x] Produk: daftar dengan pencarian, penyaringan, dan pembagian halaman (P4, P5)
- [x] Label stok dihitung dari stok varian dan status Pre-Order (P6)
- [x] Validasi harga: peringatan eceran di luar modal + Rp1.000 sampai Rp2.000, tolak harga pack di luar modal dan eceran (H1 sampai H4)
- [x] Varian: tambah, ubah, nonaktifkan, varian "Standar" otomatis, nama unik per produk (V1 sampai V4)
- [x] Pre-Order: estimasi 1 sampai 6 hari wajib bila aktif (R1, R2)
- [x] Foto: unggah, hapus, foto utama, urutan (F1 sampai F3)
- [x] Uji otomatis: validasi harga, label stok, aturan hapus kategori, akses admin

## PPL-14 Manajemen Produk: frontend (dashboard)
- [x] Halaman Kategori: daftar, tambah, ubah, hapus
- [x] Halaman daftar Produk: pencarian, filter, label stok, status aktif
- [x] Form Produk: informasi, harga (dengan peringatan), varian dan stok, toggle Pre-Order dengan estimasi
- [x] Pengelola foto produk: unggah, foto utama, urutan
- [x] Tampilkan pesan kesalahan 422 di bawah kolom yang bermasalah

## Penutup
- [x] Uji ujung ke ujung: admin login, tambah produk lengkap, produk muncul di daftar setelah dimuat ulang
- [ ] Perbarui ERD, SQL, dan diagram Login dan Register bila usulan Google disetujui
- [ ] Buka Pull Request ke `development`, minta satu review, lalu tutup issue PPL-13 dan PPL-14
