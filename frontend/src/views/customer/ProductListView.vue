<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ProductCard from '@/components/ProductCard.vue'
import { getCategories, getProductsPaginated } from '@/services/productService'

const route = useRoute()
const router = useRouter()
const products = ref([])
const categories = ref([])
const loading = ref(true)
const error = ref('')
const search = ref(String(route.query.q || ''))
const selectedCategory = ref(String(route.query.category || 'all'))
const selectedStockLabel = ref(String(route.query.stock_label || 'all'))
const sortBy = ref(String(route.query.sort || 'recommended'))
const currentPage = ref(Math.max(1, Number(route.query.page) || 1))
const pageSize = 12
const paginationMeta = ref({ current_page: 1, last_page: 1, per_page: pageSize, total: 0 })

let requestSeq = 0
let searchDebounceTimer = null

const stockOptions = [
  { value: 'all', label: 'Semua Status Stok' },
  { value: 'Tersedia', label: 'Tersedia' },
  { value: 'Pre-Order', label: 'Pre-Order' },
  { value: 'Stok Habis', label: 'Stok Habis' },
]

const categoryOptions = computed(() => [
  { slug: 'all', name: 'Semua' },
  ...categories.value,
])

const totalPages = computed(() => Math.max(1, Number(paginationMeta.value.last_page) || 1))
const totalProducts = computed(() => Number(paginationMeta.value.total) || 0)

function buildQueryParams() {
  return {
    ...(search.value.trim() ? { q: search.value.trim() } : {}),
    ...(selectedCategory.value !== 'all' ? { category: selectedCategory.value } : {}),
    ...(selectedStockLabel.value !== 'all' ? { stock_label: selectedStockLabel.value } : {}),
    ...(sortBy.value !== 'recommended' ? { sort: sortBy.value } : {}),
    ...(currentPage.value > 1 ? { page: String(currentPage.value) } : {}),
  }
}

async function fetchProducts() {
  const seq = ++requestSeq
  loading.value = true
  error.value = ''

  try {
    const { items, meta } = await getProductsPaginated({
      q: search.value.trim() || undefined,
      category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
      stock_label: selectedStockLabel.value !== 'all' ? selectedStockLabel.value : undefined,
      sort: sortBy.value,
      page: currentPage.value,
      per_page: pageSize,
    })

    if (seq !== requestSeq) return
    products.value = items
    paginationMeta.value = meta
    if (meta.current_page !== currentPage.value) {
      currentPage.value = meta.current_page
    }
  } catch (err) {
    if (seq !== requestSeq) return
    error.value = err.response?.data?.message || err.message || 'Produk belum dapat dimuat. Periksa koneksi dan coba lagi.'
  } finally {
    if (seq === requestSeq) {
      loading.value = false
    }
  }
}

async function loadInitialCatalog() {
  try {
    const [categoryRows] = await Promise.all([
      getCategories(),
      fetchProducts(),
    ])
    categories.value = categoryRows
  } catch (err) {
    if (!error.value) {
      error.value = err.message || 'Kategori belum dapat dimuat.'
    }
  }
}

function syncUrlAndFetch(resetPage = false) {
  if (resetPage) currentPage.value = 1
  router.replace({ name: 'products', query: buildQueryParams() })
  fetchProducts()
}

function submitSearch() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  syncUrlAndFetch(true)
}

function selectCategory(slug) {
  selectedCategory.value = slug
  syncUrlAndFetch(true)
}

function selectStockLabel(label) {
  selectedStockLabel.value = label
  syncUrlAndFetch(true)
}

function handleSortChange() {
  syncUrlAndFetch(true)
}

function goToPage(page) {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  currentPage.value = page
  syncUrlAndFetch(false)
}

function clearFilters() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  search.value = ''
  selectedCategory.value = 'all'
  selectedStockLabel.value = 'all'
  sortBy.value = 'recommended'
  currentPage.value = 1
  router.replace({ name: 'products' })
  fetchProducts()
}

watch(search, () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    syncUrlAndFetch(true)
  }, 300)
})

watch(() => route.query, (query) => {
  const nextQ = String(query.q || '')
  const nextCat = String(query.category || 'all')
  const nextStock = String(query.stock_label || 'all')
  const nextSort = String(query.sort || 'recommended')
  const nextPage = Math.max(1, Number(query.page) || 1)

  const changed = nextQ !== search.value
    || nextCat !== selectedCategory.value
    || nextStock !== selectedStockLabel.value
    || nextSort !== sortBy.value
    || nextPage !== currentPage.value

  if (changed) {
    search.value = nextQ
    selectedCategory.value = nextCat
    selectedStockLabel.value = nextStock
    sortBy.value = nextSort
    currentPage.value = nextPage
    fetchProducts()
  }
})

onMounted(loadInitialCatalog)
onBeforeUnmount(() => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
})
</script>

<template>
  <section class="page-container inner-page catalog-page">
    <div class="catalog-heading">
      <div>
        <span class="eyebrow-badge">KOLEKSI RESMI RIASKU</span>
        <h1 class="page-title">Katalog Produk</h1>
        <p class="page-subtitle">Temukan kosmetik pilihan dan layanan rias untuk setiap momen istimewa.</p>
      </div>
      <span class="catalog-count" v-if="!loading">{{ totalProducts }} produk ditemukan</span>
    </div>

    <form class="catalog-toolbar" @submit.prevent="submitSearch">
      <label class="catalog-search-box">
        <span aria-hidden="true">⌕</span>
        <input v-model="search" type="search" placeholder="Cari nama produk, kode, atau deskripsi..." aria-label="Cari produk" />
        <button v-if="search" type="button" aria-label="Hapus pencarian" @click="search = ''">×</button>
      </label>
      <label class="catalog-sort-label">
        <span>Urutkan</span>
        <select v-model="sortBy" class="form-control" aria-label="Urutkan produk" @change="handleSortChange">
          <option value="recommended">Rekomendasi</option>
          <option value="name">Nama A–Z</option>
          <option value="price-asc">Harga terendah</option>
          <option value="price-desc">Harga tertinggi</option>
          <option value="rating">Rating tertinggi</option>
          <option value="best_seller">Terlaris (Best Seller)</option>
        </select>
      </label>
      <button class="button button-primary catalog-search-submit" type="submit">Cari</button>
    </form>

    <div class="catalog-category-row" aria-label="Filter kategori">
      <button
        v-for="category in categoryOptions"
        :key="category.slug"
        type="button"
        class="filter-pill"
        :class="{ active: selectedCategory === category.slug }"
        @click="selectCategory(category.slug)"
      >{{ category.name }}</button>
      <button v-if="search || selectedCategory !== 'all' || selectedStockLabel !== 'all' || sortBy !== 'recommended'" class="catalog-reset" type="button" @click="clearFilters">Reset filter</button>
    </div>

    <div class="catalog-stock-row" aria-label="Filter status stok">
      <span class="catalog-filter-label">Status Stok:</span>
      <button
        v-for="opt in stockOptions"
        :key="opt.value"
        type="button"
        class="filter-pill"
        :class="{ active: selectedStockLabel === opt.value }"
        @click="selectStockLabel(opt.value)"
      >{{ opt.label }}</button>
    </div>

    <div v-if="loading" class="catalog-state" role="status">
      <span class="loading-spinner"></span>
      <p>Memuat katalog RiasKu...</p>
    </div>
    <div v-else-if="error" class="catalog-state catalog-state-error">
      <strong>Belum dapat memuat katalog</strong>
      <p>{{ error }}</p>
      <button class="button button-outline" type="button" @click="fetchProducts">Coba lagi</button>
    </div>
    <div v-else-if="products.length === 0" class="catalog-state">
      <strong>Produk tidak ditemukan</strong>
      <p>Coba kata kunci atau kategori yang berbeda.</p>
      <button class="button button-outline" type="button" @click="clearFilters">Hapus filter</button>
    </div>
    <template v-else>
      <div class="product-grid catalog-grid">
        <ProductCard v-for="product in products" :key="product.id" :product="product" />
      </div>
      <nav v-if="totalPages > 1" class="catalog-pagination" aria-label="Halaman katalog">
        <button type="button" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">← Sebelumnya</button>
        <span>Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <button type="button" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">Berikutnya →</button>
      </nav>
    </template>
  </section>
</template>
