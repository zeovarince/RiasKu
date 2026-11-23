# PPL-26 — Rekap Keuangan Bulanan

## Ringkasan fitur dan tujuan

Rekap Keuangan membantu admin melihat pendapatan dari pesanan sukses, kuantitas produk terjual, biaya restock, estimasi modal barang terjual, dan tren pendapatan harian. Admin dapat memilih bulan dan tahun, mencari rincian transaksi, berpindah halaman, serta mengunduh laporan CSV yang dapat dibuka di Excel.

Semua endpoint berada di /api/admin dan memakai middleware auth:sanctum serta EnsureAdmin.

## Logika perhitungan dan sumber data

| Metrik | Sumber dan formula |
| --- | --- |
| Pendapatan kotor | Jumlah pesanan_item.line_total; fallback ke subtotal, lalu unit_price × quantity. Ongkos kirim tidak dihitung sebagai omzet produk. |
| Pesanan sukses | Satu pesanan unik dihitung bila payment_status bernilai dibayar, lunas, atau settlement; ada pembayaran settlement; atau status pesanan bernilai selesai. Pesanan yang status utama atau legacy-nya dibatalkan selalu dikecualikan. |
| Produk terjual | Jumlah pesanan_item.quantity dari pesanan sukses pada periode. |
| Biaya barang masuk | stok_masuk.quantity × unit_cost untuk restock yang tanggalnya ada di periode dan mempunyai unit_cost. Baris tanpa modal tetap tampil di rincian dengan nominal kosong. |
| Estimasi HPP | Kuantitas terjual per produk dikalikan rata-rata tertimbang stok_masuk.unit_cost sampai akhir bulan. Produk tanpa riwayat restock bermodal memakai produk.base_price saat laporan dihitung. |
| Laba kotor estimasi | Pendapatan kotor dikurangi estimasi HPP. Ini berbeda dari kas restock bulan tersebut karena waktu pembelian stok dan penjualan bisa berada di bulan yang berbeda. |

Sumber tabel: pesanan, pesanan_item, pembayaran, produk, dan stok_masuk. Tanggal transaksi pesanan memakai pembayaran.paid_at terakhir berstatus settlement; jika tidak ada, sistem memakai pesanan.updated_at sebagai fallback. Tanggal stok masuk memakai stok_masuk.tanggal_masuk.

Database menyimpan timestamp dalam UTC. Filter periode membentuk batas awal dan akhir bulan di Asia/Jakarta, mengubahnya ke UTC untuk query, lalu mengelompokkan tren harian kembali ke tanggal Jakarta. Dengan demikian transaksi yang dekat tengah malam masuk ke tanggal dan bulan lokal yang tepat.

Laporan hanya mencakup pesanan online yang tersimpan di tabel pesanan dan pembelian stok pada stok_masuk. Halaman Penjualan DoD saat ini belum memiliki endpoint/tabel transaksi persisten, jadi transaksi tersebut belum menjadi bagian rekap.

## Spesifikasi API

Query month dan year opsional; bila tidak dikirim, API memakai bulan dan tahun berjalan di Asia/Jakarta. month harus bernilai 1–12, year 2000–2100. Contoh akses memakai token admin.

### GET /api/admin/reports/monthly

Mengembalikan KPI dan tren harian untuk periode.

    GET /api/admin/reports/monthly?month=11&year=2026
    Authorization: Bearer <token>

Contoh response (daily_trend dipersingkat):

    {
      "message": "Rekap keuangan bulanan berhasil dimuat.",
      "data": {
        "period": {
          "month": 11,
          "year": 2026,
          "start_date": "2026-11-01",
          "end_date": "2026-11-30",
          "timezone": "Asia/Jakarta"
        },
        "summary": {
          "gross_revenue": 1250000,
          "successful_orders": 18,
          "products_sold": 87,
          "stock_expense": 890000,
          "estimated_cogs": 470000,
          "gross_profit": 780000,
          "average_order_value": 69444
        },
        "daily_trend": [
          { "date": "2026-11-01", "day": 1, "revenue": 0 },
          { "date": "2026-11-02", "day": 2, "revenue": 125000 }
        ]
      }
    }

daily_trend selalu memuat setiap tanggal dalam bulan tersebut, termasuk hari tanpa pendapatan.

### GET /api/admin/reports/monthly/transactions

Mengembalikan daftar gabungan pendapatan pesanan dan pengeluaran barang masuk.

| Parameter | Aturan | Keterangan |
| --- | --- | --- |
| month, year | opsional; sama seperti ringkasan | Periode laporan. |
| search | opsional, teks maksimal 100 karakter | Cari ID pesanan, pelanggan, produk, varian, supplier, atau catatan barang masuk. |
| page | integer minimal 1 | Halaman saat ini, default 1. |
| per_page | integer 1–100 | Jumlah baris per halaman, default 15. |

    GET /api/admin/reports/monthly/transactions?month=11&year=2026&search=lip&per_page=15&page=1
    Authorization: Bearer <token>

Contoh response:

    {
      "message": "Rincian transaksi bulanan berhasil dimuat.",
      "data": [
        {
          "date": "2026-11-23T10:30:00+07:00",
          "type": "Pendapatan",
          "transaction_id": "RSK-20261123-ABCDE",
          "description": "Glow Velvet Lip Tint (Rose) × 2",
          "quantity": 2,
          "amount": 76000,
          "status": "Lunas"
        },
        {
          "date": "2026-11-22T00:00:00+07:00",
          "type": "Pengeluaran",
          "transaction_id": "STK-12",
          "description": "Glow Velvet Lip Tint (Rose) · Supplier A",
          "quantity": 8,
          "amount": 248000,
          "status": "Tercatat"
        }
      ],
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 2,
        "last_page": 1
      }
    }

amount pada barang masuk bernilai null bila unit_cost belum dicatat.

### GET /api/admin/reports/monthly/export

Mengunduh CSV UTF-8 dengan BOM supaya karakter Indonesia terbaca di Excel. Kolom nominal memisahkan pendapatan dan pengeluaran. Parameter periode serta search mengikuti endpoint rincian; ekspor memuat seluruh hasil pencarian tanpa pagination.

    GET /api/admin/reports/monthly/export?month=11&year=2026
    Authorization: Bearer <token>

Nama file: riasku-rekap-keuangan-2026-11.csv.

## Struktur frontend

| File | Perubahan |
| --- | --- |
| backend-api/app/Http/Controllers/Api/Admin/MonthlyReportController.php | Validasi periode, query KPI/tren, rincian transaksi, pencarian, pagination, dan ekspor CSV. |
| backend-api/routes/api_admin.php | Tiga endpoint laporan di dalam grup middleware admin. |
| frontend/src/layouts/AdminLayout.vue | Label navigasi admin menjadi Rekap Keuangan. |
| frontend/src/services/adminService.js | Pemanggil API ringkasan, rincian, dan ekspor CSV. |
| frontend/src/views/admin/MonthlyReportView.vue | Filter bulan/tahun, lima kartu KPI, grafik SVG harian, tabel transaksi, pencarian, pagination, dan tombol CSV. |
| frontend/src/components/admin/AdminIcon.vue | Ikon unduh untuk tombol ekspor. |

Grafik dibuat dengan SVG bawaan halaman; tidak menambah dependency visualisasi.

## Skenario pengujian

1. Login sebagai admin; pastikan request tanpa token mendapat 401, token pelanggan mendapat 403, dan token admin dapat membuka ketiga endpoint.
2. Buat pesanan lunas/settlement, pesanan berstatus selesai, pesanan pending, serta pesanan dibatalkan. Pastikan hanya status sukses yang masuk perhitungan dan pesanan batal tetap dikecualikan meski ada settlement terlambat.
3. Buat pembayaran dekat batas bulan UTC/Jakarta. Pastikan tanggal lokal menentukan bulan dan hari pada KPI/grafik.
4. Bandingkan omzet dengan total baris item (tanpa ongkos kirim), total pesanan unik, dan jumlah kuantitas item.
5. Catat barang masuk dengan dan tanpa unit_cost. Pastikan biaya restock dan HPP estimasi menghitung baris bermodal, fallback HPP memakai base_price, dan baris tanpa modal menampilkan nominal kosong.
6. Ganti filter bulan dan tahun di halaman admin; pastikan KPI, grafik, tabel, dan ekspor memakai periode baru.
7. Cari dengan ID pesanan, nama produk, dan supplier; periksa hasil pagination serta unduhan CSV (header, UTF-8, jumlah baris, nominal masuk/keluar).
8. Kirim month=13, year=1999, per_page=0, atau page=0; pastikan API mengembalikan validasi 422.
