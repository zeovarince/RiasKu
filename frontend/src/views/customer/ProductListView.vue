<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ProductCard from '@/components/ProductCard.vue'
import { getCategories, getProducts } from '@/services/productService'

const route = useRoute()
const router = useRouter()
const products = ref([])
const categories = ref([])
const loading = ref(true)
const error = ref('')
const search = ref(String(route.query.q || ''))
const selectedCategory = ref(String(route.query.category || 'all'))
const sortBy = ref(String(route.query.sort || 'recommended'))
const currentPage = ref(1)
const pageSize = 8

const categoryOptions = computed(() => [
  { slug: 'all', name: 'Semua' },
  ...categories.value,
])

const filteredProducts = computed(() => {
  const query = search.value.trim().toLowerCase()
  let result = products.value.filter((product) => {
    const searchable = [product.name, product.code, product.description, product.categoryLabel]
      .filter(Boolean).join(' ').toLowerCase()
    const matchesQuery = !query || searchable.includes(query)
    const matchesCategory = selectedCategory.value === 'all' ||
      String(product.category).toLowerCase() === selectedCategory.value.toLowerCase() ||
      String(product.categoryLabel).toLowerCase() === selectedCategory.value.toLowerCase()
    return matchesQuery && matchesCategory
  })

  result = [...result]
  if (sortBy.value === 'price-asc') result.sort((a, b) => Number(a.price) - Number(b.price))
  if (sortBy.value === 'price-desc') result.sort((a, b) => Number(b.price) - Number(a.price))
  if (sortBy.value === 'rating') result.sort((a, b) => Number(b.rating) - Number(a.rating))
  if (sortBy.value === 'name') result.sort((a, b) => a.name.localeCompare(b.name, 'id'))
  return result
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / pageSize)))
const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredProducts.value.slice(start, start + pageSize)
})

watch([search, selectedCategory, sortBy], () => { currentPage.value = 1 })
watch(() => route.query, (query) => {
  search.value = String(query.q || '')
  selectedCategory.value = String(query.category || 'all')
  sortBy.value = String(query.sort || 'recommended')
}, { deep: true })

async function loadCatalog() {
  loading.value = true
  error.value = ''
  try {
    const [productRows, categoryRows] = await Promise.all([
      getProducts({ per_page: 100 }),
      getCategories(),
    ])
    products.value = productRows
    categories.value = categoryRows
  } catch (err) {
    error.value = err.message || 'Produk belum dapat dimuat. Periksa koneksi dan coba lagi.'
  } finally {
    loading.value = false
  }
}

function submitSearch() {
  router.replace({
    name: 'products',
    query: {
      ...(search.value.trim() ? { q: search.value.trim() } : {}),
      ...(selectedCategory.value !== 'all' ? { category: selectedCategory.value } : {}),
      ...(sortBy.value !== 'recommended' ? { sort: sortBy.value } : {}),
    },
  })
}

function clearFilters() {
  search.value = ''
  selectedCategory.value = 'all'
  sortBy.value = 'recommended'
  router.replace({ name: 'products' })
}

onMounted(loadCatalog)
</script>

<template>
  <section class="page-container inner-page catalog-page">
    <div class="catalog-heading">
      <div>
        <span class="eyebrow-badge">KOLEKSI RESMI RIASKU</span>
        <h1 class="page-title">Katalog Produk</h1>
        <p class="page-subtitle">Temukan kosmetik pilihan dan layanan rias untuk setiap momen istimewa.</p>
      </div>
      <span class="catalog-count" v-if="!loading">{{ filteredProducts.length }} produk ditemukan</span>
    </div>

    <form class="catalog-toolbar" @submit.prevent="submitSearch">
      <label class="catalog-search-box">
        <span aria-hidden="true">⌕</span>
        <input v-model="search" type="search" placeholder="Cari nama produk, kode, atau deskripsi..." aria-label="Cari produk" />
        <button v-if="search" type="button" aria-label="Hapus pencarian" @click="search = ''">×</button>
      </label>
      <label class="catalog-sort-label">
        <span>Urutkan</span>
        <select v-model="sortBy" class="form-control" aria-label="Urutkan produk">
          <option value="recommended">Rekomendasi</option>
          <option value="name">Nama A–Z</option>
          <option value="price-asc">Harga terendah</option>
          <option value="price-desc">Harga tertinggi</option>
          <option value="rating">Rating tertinggi</option>
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
        @click="selectedCategory = category.slug"
      >{{ category.name }}</button>
      <button v-if="search || selectedCategory !== 'all' || sortBy !== 'recommended'" class="catalog-reset" type="button" @click="clearFilters">Reset filter</button>
    </div>

    <div v-if="loading" class="catalog-state" role="status">
      <span class="loading-spinner"></span>
      <p>Memuat katalog RiasKu...</p>
    </div>
    <div v-else-if="error" class="catalog-state catalog-state-error">
      <strong>Belum dapat memuat katalog</strong>
      <p>{{ error }}</p>
      <button class="button button-outline" type="button" @click="loadCatalog">Coba lagi</button>
    </div>
    <div v-else-if="filteredProducts.length === 0" class="catalog-state">
      <strong>Produk tidak ditemukan</strong>
      <p>Coba kata kunci atau kategori yang berbeda.</p>
      <button class="button button-outline" type="button" @click="clearFilters">Hapus filter</button>
    </div>
    <template v-else>
      <div class="product-grid catalog-grid">
        <ProductCard v-for="product in paginatedProducts" :key="product.id" :product="product" />
      </div>
      <nav v-if="totalPages > 1" class="catalog-pagination" aria-label="Halaman katalog">
        <button type="button" :disabled="currentPage === 1" @click="currentPage--">← Sebelumnya</button>
        <span>Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <button type="button" :disabled="currentPage === totalPages" @click="currentPage++">Berikutnya →</button>
      </nav>
    </template>
  </section>
</template>
