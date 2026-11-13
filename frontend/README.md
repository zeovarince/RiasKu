# Frontend RiasKu

Frontend RiasKu dibuat menggunakan **Vue 3 + Vite** dengan pola **SPA (Single Page Application)**. Aplikasi memakai Vue Router untuk navigasi, Pinia untuk state bersama, Axios untuk HTTP request, dan CSS biasa untuk styling. Project ini **tidak menggunakan Tailwind CSS**.

## 1. Persiapan

Pastikan Node.js sesuai dengan batas versi pada `package.json` (Node `^22.18.0` atau `>=24.12.0`) dan npm tersedia.

```bash
cd frontend
```

Salin konfigurasi environment:

**Windows PowerShell**

```powershell
Copy-Item .env.example .env
```

**macOS / Linux**

```bash
cp .env.example .env
```

Install dependency dan jalankan Vite:

```bash
npm install
npm run dev
```

Buka alamat yang ditampilkan Vite, biasanya `http://localhost:5173`.

Build untuk memeriksa hasil produksi:

```bash
npm run build
```

## 2. Environment variable

Isi file `.env` lokal:

```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_USE_MOCK_DATA=true
VITE_CART_API_ENABLED=false
```

| Variable | Fungsi |
|---|---|
| `VITE_API_BASE_URL` | Base URL Laravel API yang diakses browser. |
| `VITE_USE_MOCK_DATA=true` | Mengambil data produk/kategori dari `src/services/mockData.js`. Cocok saat backend belum siap. |
| `VITE_USE_MOCK_DATA=false` | Mengambil produk/kategori dari Laravel API. |
| `VITE_CART_API_ENABLED=true` | Mengaktifkan sinkronisasi cart ke endpoint Laravel. Membutuhkan Bearer token dan API cart sudah tersedia. |

Jangan commit `.env`. File yang boleh masuk Git adalah `.env.example`. Setelah mengubah `.env`, restart Vite.

## 3. Struktur folder `src`

```text
src/
├── assets/
│   └── main.css              # Design tokens dan styling global
├── components/
│   ├── AppHeader.vue         # Header customer
│   ├── AppFooter.vue         # Footer
│   ├── ProductCard.vue       # Kartu produk yang reusable
│   ├── ProductArtwork.vue    # Ilustrasi SVG fallback produk
│   └── ...                   # Komponen bersama / modal
├── layouts/
│   ├── CustomerLayout.vue    # Kerangka halaman customer
│   ├── AdminLayout.vue       # Kerangka halaman admin
│   └── AuthLayout.vue        # Kerangka autentikasi
├── router/
│   └── index.js              # Daftar URL dan komponen halaman
├── services/
│   ├── api.js                # Konfigurasi Axios dan token
│   ├── productService.js     # Request kategori dan produk
│   ├── cartService.js        # Request cart ke Laravel
│   └── mockData.js           # Data demo frontend
├── stores/
│   ├── cart.js               # State keranjang dengan Pinia
│   ├── auth.js               # State autentikasi
│   └── ui.js                 # State modal/UI
├── utils/
│   └── currency.js           # Format mata uang Rupiah
├── views/
│   ├── customer/
│   │   ├── HomeView.vue
│   │   ├── ProductListView.vue
│   │   ├── ProductDetailView.vue
│   │   ├── CartView.vue
│   │   └── ...
│   ├── auth/
│   └── admin/
└── App.vue
```

**Aturan penting:** folder `frontend/` paling luar adalah root aplikasi Vue. Kode aplikasi yang aktif ada di `frontend/src/`. Jangan membuat folder aplikasi kedua di `frontend/frontend/`; jika folder duplikat tersebut ada dari percobaan extract sebelumnya, pastikan isinya tidak lagi dipakai lalu hapus duplikat itu agar semua anggota mengedit `frontend/src/` yang sama.

## 4. Di mana harus mengubah kode?

| Mau mengubah... | File/folder |
|---|---|
| Header, logo, menu, pencarian di header | `src/components/AppHeader.vue` |
| Footer | `src/components/AppFooter.vue` |
| Tampilan landing page customer | `src/views/customer/HomeView.vue` |
| Daftar produk, pencarian, kategori, sorting | `src/views/customer/ProductListView.vue` |
| Kartu satu produk | `src/components/ProductCard.vue` |
| Ilustrasi produk fallback | `src/components/ProductArtwork.vue` |
| Detail produk, varian, jumlah | `src/views/customer/ProductDetailView.vue` |
| Keranjang belanja | `src/views/customer/CartView.vue` |
| State keranjang dan localStorage | `src/stores/cart.js` |
| Endpoint produk/kategori | `src/services/productService.js` |
| Endpoint keranjang | `src/services/cartService.js` |
| Data demo | `src/services/mockData.js` |
| URL/route halaman | `src/router/index.js` |
| Warna, font, jarak, responsive CSS | `src/assets/main.css` |

## 5. Alur kerja fitur frontend

Contoh: membuat fitur baru untuk halaman katalog.

1. Buat atau pilih branch fitur dari `development` terbaru.
2. Buat halaman di `src/views/customer/` jika halaman memiliki URL.
3. Buat komponen reusable di `src/components/`.
4. Daftarkan URL di `src/router/index.js`.
5. Letakkan request HTTP di `src/services/`, jangan menulis Axios langsung di komponen halaman.
6. Jika data perlu dipakai lintas komponen, simpan state di `src/stores/` dengan Pinia.
7. Uji tampilan menggunakan mock data, lalu uji lagi dengan Laravel API.
8. Jalankan `npm run build`, commit, push, dan buat Pull Request ke `development`.

### Contoh menambah halaman

Buat `src/views/customer/PromoView.vue`, lalu tambahkan route pada `src/router/index.js`:

```js
{
  path: 'promo',
  name: 'promo',
  component: () => import('@/views/customer/PromoView.vue'),
}
```

Gunakan `RouterLink` untuk navigasi internal, bukan anchor biasa:

```vue
<RouterLink to="/promo">Promo</RouterLink>
```

### Contoh memanggil API

Komponen memanggil service:

```js
import { getProducts } from '@/services/productService'

const products = await getProducts({ q: 'cushion' })
```

Service bertanggung jawab atas Axios dan pemilihan mock/API. Dengan begitu, komponen tidak perlu tahu detail URL backend.

## 6. Pengerjaan Sprint 2

### PPL-15 — Katalog, Pencarian & Detail Produk

File utama:

- `src/views/customer/ProductListView.vue`
- `src/views/customer/ProductDetailView.vue`
- `src/components/ProductCard.vue`
- `src/components/CategoryQuickLinks.vue`
- `src/components/ProductArtwork.vue`
- `src/services/productService.js`

Alur data: `ProductListView` meminta produk/kategori melalui `productService`, menerapkan pencarian/filter/pengurutan, lalu me-render setiap produk dengan `ProductCard`. Detail diambil berdasarkan ID route `/products/:id`.

### PPL-16 — Keranjang Belanja

File utama:

- `src/views/customer/CartView.vue`
- `src/stores/cart.js`
- `src/services/cartService.js`
- `src/components/ProductCard.vue`
- `src/views/customer/ProductDetailView.vue`

`cart.js` adalah sumber state keranjang. Secara default data cart disimpan di localStorage agar fitur bisa dikerjakan tanpa menunggu endpoint backend. Jika tim backend sudah menyediakan endpoint cart dan autentikasi, sinkronisasi server dapat diaktifkan melalui `VITE_CART_API_ENABLED=true`.

## 7. Styling dan desain

- Styling global berada di `src/assets/main.css`.
- Gunakan CSS variables yang sudah tersedia di bagian `:root` untuk warna, font, dan tokens.
- Project ini menggunakan CSS biasa, bukan Tailwind. Jangan menambahkan utility class Tailwind kecuali tim menyepakati migrasi secara terpisah.
- Komponen yang dipakai di beberapa halaman diletakkan di `components/`; komponen yang khusus satu halaman boleh tetap berada di halaman tersebut.
- Pertahankan responsivitas desktop, tablet, dan mobile.

## 8. Branch dan Pull Request

Branch integrasi repository saat ini bernama `development`.

```bash
git fetch origin
git switch development
git pull origin development

git switch -c feature/ppl-15-catalog-product
```

Setelah selesai:

```bash
git add frontend/src docs
git commit -m "feat(catalog): complete product catalog and cart frontend"
git push -u origin feature/ppl-15-catalog-product
```

Buat Pull Request dari branch fitur ke `development`, bukan ke `main`. Untuk issue berikutnya, gunakan branch terpisah dan nama yang memuat ID issue.

## 9. Troubleshooting

- `npm run dev` tidak bisa: pastikan terminal berada di folder `frontend` dan `npm install` sudah dijalankan.
- API gagal: cek `VITE_API_BASE_URL`, status Laravel, dan konfigurasi CORS Laravel.
- Produk demo tidak muncul: pastikan `VITE_USE_MOCK_DATA=true` dan restart Vite.
- Cart tidak tersinkron: pastikan user sudah login, token disimpan pada `sessionStorage` sebagai `riasku_access_token`, endpoint cart tersedia, lalu aktifkan `VITE_CART_API_ENABLED=true`.
- File tidak muncul di Git: pastikan tidak berada di `node_modules/`, `dist/`, atau folder duplikat `frontend/frontend/`.

## Dokumentasi API

Lihat [`../docs/API-FRONTEND.md`](../docs/API-FRONTEND.md) untuk kontrak endpoint katalog, produk detail, dan keranjang. Kontrak tersebut adalah kebutuhan frontend yang perlu disepakati dengan backend; dokumentasi bukan bukti endpoint Laravel sudah diimplementasikan.
