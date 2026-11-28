# RiasKu

RiasKu adalah aplikasi toko kosmetik dan layanan tata rias berbasis **Monorepo** yang memisahkan frontend Vue 3 dan backend Laravel REST API dalam satu repository.

## Struktur Repository

```text
RiasKu/
├── backend-api/          # Laravel REST API + PostgreSQL
├── docs/                 # Dokumentasi sprint PPL, kontrak API, ERD & OpenAPI
├── frontend/             # Vue 3 + Vite (SPA)
├── .gitignore
└── README.md
```

## Dokumentasi Utama

- **Panduan Setup & Sprint:** [`docs/PANDUAN-SPRINT-PPL.md`](docs/PANDUAN-SPRINT-PPL.md)
- **Panduan Frontend:** [`frontend/README.md`](frontend/README.md)
- **Kontrak API Frontend:** [`docs/API-CONTRACT-FRONTEND.md`](docs/API-CONTRACT-FRONTEND.md)
- **Spesifikasi OpenAPI:** [`docs/riasku-openapi.json`](docs/riasku-openapi.json)
- **Diagram ERD Lengkap:** [`docs/riasku-erd-full.png`](docs/riasku-erd-full.png)

### Dokumentasi Fitur per Issue (PPL)

- [`PPL-15`](docs/PPL-15-katalog-pencarian-dan-detail-produk.md) — Katalog, Pencarian & Detail Produk
- [`PPL-16`](docs/PPL-16-keranjang-belanja.md) — Keranjang Belanja
- [`PPL-19`](docs/PPL-19-pembayaran-online-midtrans.md) — Pembayaran Online (QRIS, VA, E-Wallet)
- [`PPL-20`](docs/PPL-20-pembayaran-cod-surabaya.md) — Pembayaran COD Surabaya
- [`PPL-23`](docs/PPL-23-barang-masuk-dan-peringatan-restock.md) — Barang Masuk & Peringatan Restock
- [`PPL-24`](docs/PPL-24-best-seller-scoring.md) — Fitur Best Seller (Scoring)
- [`PPL-26`](docs/PPL-26-rekap-keuangan-bulanan.md) — Rekap Keuangan Bulanan
- [`PPL-27`](docs/PPL-27-pengaturan-toko-dan-tombol-whatsapp.md) — Pengaturan Toko & Tombol WhatsApp
- [`PPL-29`](docs/PPL-29-qa-fungsional-dan-bugfix-sprint5.md) — Pengujian Fungsional QA & Bugfix Sprint 5

## Branch Workflow

- `main`: versi stabil.
- `development`: integrasi hasil kerja tim.
- `feature/...`, `fix/...`, `docs/...`: pekerjaan per issue/tugas.

Mulai pekerjaan dari `development` terbaru, buat branch terpisah, lalu ajukan Pull Request kembali ke `development`. Jangan commit langsung ke `main` atau `development`.
