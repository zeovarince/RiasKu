<script setup>
import ProductArtwork from '@/components/ProductArtwork.vue'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'

const props = defineProps({ product: { type: Object, required: true } })
const cart = useCartStore()
const ui = useUiStore()

function addProduct() {
  cart.addItem(props.product)
}
</script>

<template>
  <article class="product-card">
    <div class="product-visual">
      <span class="product-tag" :class="{ 'tag-outline': product.tag === 'COD SURABAYA' }">{{ product.tag }}</span>
      <ProductArtwork :type="product.art" />
    </div>
    <div class="product-meta">{{ product.code }}</div>
    <RouterLink :to="`/products/${product.id}`" class="product-name">{{ product.name }}</RouterLink>
    <div class="product-rating"><span>★★★★★</span> {{ product.rating }} ({{ product.reviewCount }} {{ product.reviewLabel || 'Ulasan' }})</div>
    <button class="review-link" type="button" @click="ui.openModal('review', { product })">Lihat ulasan pelanggan</button>
    <div class="product-price">{{ product.priceLabel }}</div>
    <RouterLink v-if="product.type === 'service'" to="/consultation" class="product-action">◷ Konsultasi Jadwal</RouterLink>
    <div v-else class="product-card-actions">
      <RouterLink :to="`/products/${product.id}`" class="product-action product-detail-action">Lihat Detail &amp; Beli</RouterLink>
      <button class="product-action" type="button" @click="addProduct">＋ Keranjang</button>
    </div>
  </article>
</template>
