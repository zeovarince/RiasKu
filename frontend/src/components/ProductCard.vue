<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import ProductArtwork from '@/components/ProductArtwork.vue'
import RatingStars from '@/components/RatingStars.vue'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'

const props = defineProps({ product: { type: Object, required: true } })
const cart = useCartStore()
const ui = useUiStore()
const justAdded = ref(false)
let addedTimer = null

const variants = computed(() => (Array.isArray(props.product.variants) ? props.product.variants : []))

const defaultVariant = computed(() => {
  return variants.value.find((v) => Number(v.stock ?? 0) > 0) || variants.value[0] || null
})

const stockCount = computed(() => Number(props.product.stock ?? props.product.total_stock ?? 0))

const isPreorderProduct = computed(() => {
  if (props.product.stock_label === 'Pre-Order') return true
  if (props.product.stock_label === 'Tersedia' || props.product.stock_label === 'Stok Habis') return false
  return stockCount.value <= 0 && Boolean(props.product.is_preorder)
})

const canQuickAdd = computed(() => {
  if (stockCount.value > 0) return true
  return isPreorderProduct.value
})

const stockBadgeText = computed(() => {
  if (stockCount.value > 0) {
    return stockCount.value <= 3 ? `Tersedia · Sisa ${stockCount.value}` : 'Tersedia'
  }
  if (isPreorderProduct.value) {
    return props.product.preorder_estimate_days
      ? `Pre-Order · Est. ${props.product.preorder_estimate_days} Hari`
      : 'Pre-Order'
  }
  return 'Stok Habis'
})

const stockPillClass = computed(() => {
  if (stockCount.value > 0) return 'pill-ready'
  if (isPreorderProduct.value) return 'pill-preorder'
  return 'pill-empty'
})

const quickAddLabel = computed(() => {
  if (justAdded.value) return '✓ Ditambahkan'
  if (stockCount.value > 0) return '＋ Keranjang'
  if (isPreorderProduct.value) return 'Pre-Order'
  return 'Stok Habis'
})

const bestSellerBadge = computed(() => {
  const rank = Number(props.product.bestSellerRank ?? props.product.best_seller_rank ?? 0)
  const score = Number(props.product.bestSellerScore ?? props.product.best_seller_score ?? 0)
  const flagged = props.product.isBestSeller ?? props.product.is_best_seller ?? score > 0

  if (rank > 0) return rank <= 3 ? `TOP #${rank} BEST SELLER` : 'BEST SELLER'
  return flagged ? 'BEST SELLER' : null
})

function addProduct() {
  if (!canQuickAdd.value) return
  const added = cart.addItem(props.product, 1, defaultVariant.value)
  if (added) {
    justAdded.value = true
    if (addedTimer) clearTimeout(addedTimer)
    addedTimer = setTimeout(() => { justAdded.value = false }, 1400)
  }
}

onBeforeUnmount(() => {
  if (addedTimer) clearTimeout(addedTimer)
})
</script>

<template>
  <article class="product-card">
    <div class="product-visual">
      <div class="product-badge-stack">
        <span v-if="bestSellerBadge" class="product-badge-best-seller">{{ bestSellerBadge }}</span>
        <span v-if="product.tag" class="product-tag" :class="{ 'tag-outline': product.tag === 'COD SURABAYA' }">{{ product.tag }}</span>
      </div>
      <img v-if="product.image_url || product.image" :src="product.image_url || product.image" :alt="product.name" class="product-card-image" width="320" height="320" loading="lazy" decoding="async" />
      <ProductArtwork v-else :type="product.art" />
    </div>
    <div class="product-meta">{{ product.code }}</div>
    <RouterLink :to="`/products/${product.id}`" class="product-name">{{ product.name }}</RouterLink>
    <div class="product-stock-pill-row">
      <span class="stock-pill" :class="stockPillClass">{{ stockBadgeText }}</span>
    </div>
    <div class="product-rating">
      <RatingStars :model-value="Number(product.rating || 0)" size="0.95rem" />
      <span>{{ product.rating || '—' }} ({{ product.reviewCount ?? 0 }} {{ product.reviewLabel || 'Ulasan' }})</span>
    </div>
    <button class="review-link" type="button" @click="ui.openModal('review', { product })">Lihat ulasan pelanggan</button>
    <div class="product-price">{{ product.priceLabel }}</div>
    <RouterLink v-if="product.type === 'service'" to="/consultation" class="product-action">Konsultasi Jadwal</RouterLink>
    <div v-else class="product-card-actions">
      <RouterLink :to="`/products/${product.id}`" class="product-action product-detail-action">Lihat Detail &amp; Beli</RouterLink>
      <button
        class="product-action"
        type="button"
        :disabled="!canQuickAdd"
        @click="addProduct"
      >
        {{ quickAddLabel }}
      </button>
    </div>
  </article>
</template>
