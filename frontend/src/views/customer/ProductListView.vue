<script setup>
import { computed, onMounted, ref } from 'vue'
import ProductCard from '@/components/ProductCard.vue'
import { getProducts } from '@/services/productService'

const products = ref([])
const loading = ref(true)
const search = ref('')
const error = ref('')

const visibleProducts = computed(() => products.value.filter((product) =>
  product.name.toLowerCase().includes(search.value.toLowerCase())
))

onMounted(async () => {
  try {
    products.value = await getProducts({ q: new URLSearchParams(window.location.search).get('q') || '' })
  } catch (err) {
    error.value = 'Produk belum dapat dimuat.'
    console.error(err)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="page-container inner-page">
    <span class="eyebrow-badge">KOLEKSI RIASKU</span>
    <h1 class="page-title">Katalog Produk</h1>
    <p class="page-subtitle">Temukan produk kosmetik dan layanan rias pilihan RiasKu.</p>
    <input v-model="search" class="form-control catalog-search" type="search" placeholder="Cari nama produk..." />
    <p v-if="loading" class="state-message">Memuat produk...</p>
    <p v-else-if="error" class="state-message state-error">{{ error }}</p>
    <div v-else class="product-grid catalog-grid">
      <ProductCard v-for="product in visibleProducts" :key="product.id" :product="product" />
    </div>
  </section>
</template>
