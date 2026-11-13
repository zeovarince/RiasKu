# RiasKu

RiasKu adalah aplikasi toko kosmetik dan layanan tata rias. Repository menggunakan **Monorepo**: aplikasi frontend Vue dan backend Laravel disimpan dalam satu repository.

## Struktur repository

```text
RiasKu/
├── frontend/             # Vue 3 + Vite (SPA)
├── backend/              # Laravel REST API
├── docs/
│   └── API-FRONTEND.md   # Kontrak API yang dibutuhkan frontend
├── compose.yaml          # Docker Compose jika backend dan Docker sudah disiapkan
└── README.md
```

## Dokumentasi frontend

Panduan untuk menjalankan dan mengembangkan frontend ada di [`frontend/README.md`](frontend/README.md).

Dokumentasi endpoint, query parameter, request body, dan format response API ada di [`docs/API-FRONTEND.md`](docs/API-FRONTEND.md).

## Branch workflow

- `main`: versi stabil.
- `development`: integrasi hasil kerja tim.
- `feature/...`: pekerjaan per issue/fitur.

Mulai pekerjaan dari `development` terbaru, buat branch untuk issue, lalu ajukan Pull Request kembali ke `development`. Jangan commit langsung ke `main` atau `development`.
