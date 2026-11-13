<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ProductArtwork from '@/components/ProductArtwork.vue'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import { getProductById } from '@/services/productService'

const route = useRoute()
const cart = useCartStore()
const ui = useUiStore()
const product = ref(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    product.value = await getProductById(route.params.id)
  } catch (err) {
    error.value = 'Detail produk belum dapat dimuat.'
    console.error(err)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="page-container inner-page">
    <p v-if="loading" class="state-message">Memuat detail produk...</p>
    <p v-else-if="error" class="state-message state-error">{{ error }}</p>
    <div v-else-if="product" class="detail-layout">
      <div class="detail-visual"><ProductArtwork :type="product.art" /></div>
      <div class="detail-copy">
        <span class="eyebrow-badge">{{ product.code }}</span>
        <h1 class="page-title">{{ product.name }}</h1>
        <p class="product-rating"><span>★★★★★</span> {{ product.rating }} ({{ product.reviewCount }} Ulasan)</p>
        <p class="detail-price">{{ product.priceLabel }}</p>
        <p class="page-subtitle">{{ product.description }}</p>
        <p class="stock-note">Stok dan ketersediaan akan dikonfirmasi saat checkout.</p>
        <div class="detail-actions">
          <button class="button button-primary" type="button" @click="cart.addItem(product)">＋ Tambah ke Keranjang</button>
          <button class="button button-outline" type="button" @click="ui.openModal('review', { product })">☆ Tulis Ulasan</button>
        </div>
      </div>
    </div>
  </section>
</template>
