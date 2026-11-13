<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ProductArtwork from '@/components/ProductArtwork.vue'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import { getProductById } from '@/services/productService'
import { formatRupiah } from '@/utils/currency'

const route = useRoute()
const cart = useCartStore()
const ui = useUiStore()
const product = ref(null)
const loading = ref(true)
const error = ref('')
const quantity = ref(1)
const selectedVariantId = ref('')
const addedMessage = ref('')

const variants = computed(() => Array.isArray(product.value?.variants) ? product.value.variants : [])
const selectedVariant = computed(() => variants.value.find((variant) => String(variant.id) === String(selectedVariantId.value)) || variants.value[0] || null)
const stock = computed(() => Number(selectedVariant.value?.stock ?? product.value?.stock ?? 0))
const unitPrice = computed(() => Number(selectedVariant.value?.price ?? product.value?.price ?? 0))
const canAdd = computed(() => product.value?.type !== 'service' && stock.value > 0 && quantity.value > 0 && quantity.value <= stock.value)

async function loadProduct(id) {
  loading.value = true
  error.value = ''
  product.value = null
  quantity.value = 1
  addedMessage.value = ''
  try {
    product.value = await getProductById(id)
    selectedVariantId.value = variants.value[0]?.id ? String(variants.value[0].id) : ''
  } catch (err) {
    error.value = err.message || 'Detail produk belum dapat dimuat.'
  } finally {
    loading.value = false
  }
}

function addToCart() {
  if (!product.value || !canAdd.value) return
  cart.addItem(product.value, quantity.value, selectedVariant.value)
  addedMessage.value = `${product.value.name} berhasil ditambahkan ke keranjang.`
}

watch(() => route.params.id, (id) => loadProduct(id), { immediate: true })
</script>

<template>
  <section class="page-container inner-page product-detail-page">
    <p v-if="loading" class="catalog-state" role="status">Memuat detail produk...</p>
    <div v-else-if="error" class="catalog-state catalog-state-error">
      <strong>Detail produk tidak tersedia</strong>
      <p>{{ error }}</p>
      <RouterLink to="/products" class="button button-outline">Kembali ke katalog</RouterLink>
    </div>
    <div v-else-if="product" class="detail-layout">
      <div class="detail-visual">
        <img v-if="product.image_url || product.image" :src="product.image_url || product.image" :alt="product.name" class="detail-product-image" />
        <ProductArtwork v-else :type="product.art || 'cushion'" />
        <span v-if="product.tag" class="detail-product-tag">{{ product.tag }}</span>
      </div>
      <div class="detail-copy">
        <span class="eyebrow-badge">{{ product.code || `PRODUK #${product.id}` }}</span>
        <p class="detail-category">{{ product.categoryLabel || 'Koleksi RiasKu' }}</p>
        <h1 class="page-title">{{ product.name }}</h1>
        <p class="product-rating"><span>★★★★★</span> {{ product.rating || '—' }} ({{ product.reviewCount ?? 0 }} Ulasan)</p>
        <p class="detail-price">{{ formatRupiah(unitPrice) }}</p>
        <p class="page-subtitle">{{ product.description }}</p>

        <div v-if="variants.length" class="detail-option">
          <label for="product-variant">Pilih varian</label>
          <select id="product-variant" v-model="selectedVariantId" class="form-control">
            <option v-for="variant in variants" :key="variant.id" :value="String(variant.id)">
              {{ variant.name }}{{ variant.stock !== undefined ? ` · Stok ${variant.stock}` : '' }}
            </option>
          </select>
        </div>

        <div class="detail-stock-row">
          <span :class="stock > 0 ? 'stock-available' : 'stock-empty'">{{ stock > 0 ? `Stok tersedia: ${stock}` : 'Stok habis' }}</span>
          <span v-if="product.sku">SKU: {{ product.sku }}</span>
        </div>

        <div v-if="product.type !== 'service'" class="detail-buy-row">
          <div class="detail-quantity" aria-label="Jumlah produk">
            <button type="button" aria-label="Kurangi jumlah" :disabled="quantity <= 1" @click="quantity--">−</button>
            <input v-model.number="quantity" type="number" min="1" :max="stock" />
            <button type="button" aria-label="Tambah jumlah" :disabled="quantity >= stock" @click="quantity++">+</button>
          </div>
          <button class="button button-primary" type="button" :disabled="!canAdd" @click="addToCart">＋ Tambah ke Keranjang</button>
        </div>
        <RouterLink v-else to="/consultation" class="button button-primary">Konsultasi Jadwal</RouterLink>

        <p v-if="addedMessage" class="form-notice" role="status">{{ addedMessage }} <RouterLink to="/cart">Lihat keranjang</RouterLink></p>
        <p v-if="stock <= 0 && product.type !== 'service'" class="form-error">Produk sedang tidak tersedia.</p>
        <div class="detail-benefits">
          <span>✓ Produk pilihan RiasKu</span>
          <span>✓ COD area Surabaya</span>
          <span>✓ Pengiriman seluruh Indonesia</span>
        </div>
        <button class="review-link" type="button" @click="ui.openModal('review', { product })">☆ Tulis ulasan produk ini</button>
      </div>
    </div>
  </section>
</template>
