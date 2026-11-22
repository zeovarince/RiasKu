<script setup>
import { computed, onMounted } from 'vue'
import ProductArtwork from '@/components/ProductArtwork.vue'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { formatRupiah } from '@/utils/currency'

const cart = useCartStore()
const auth = useAuthStore()

const isCustomer = computed(() => {
  return auth.isAuthenticated && auth.user?.account_type === 'pelanggan'
})

const hasPreorderItems = computed(() => cart.items.some((item) => Boolean(item.is_preorder_item)))
const maxPreorderDays = computed(() => {
  const days = cart.items
    .filter((item) => Boolean(item.is_preorder_item))
    .map((item) => Number(item.preorder_estimate_days || 0))
    .filter((d) => d > 0)
  return days.length ? Math.max(...days) : null
})

onMounted(() => cart.hydrateCart())
</script>

<template>
  <section class="page-container inner-page cart-page">
    <span class="eyebrow-badge">PESANAN ANDA</span>
    <h1 class="page-title">Keranjang Belanja</h1>
    <p class="page-subtitle">Periksa kembali produk dan jumlah pesanan sebelum melanjutkan ke checkout.</p>

    <p v-if="cart.syncError" class="notice-message" role="status">{{ cart.syncError }} Keranjang lokal tetap tersimpan di browser.</p>
    <p v-if="cart.isSyncing" class="state-message">Menyinkronkan keranjang...</p>

    <div v-if="cart.items.length" class="cart-layout">
      <div class="cart-list">
        <article v-for="item in cart.items" :key="item.cartKey" class="cart-row">
          <div class="cart-product-art">
            <img v-if="item.image_url || item.image" :src="item.image_url || item.image" :alt="item.name" />
            <ProductArtwork v-else :type="item.art || 'cushion'" />
          </div>
          <div class="cart-product-info">
            <RouterLink :to="`/products/${item.id}`" class="cart-product-name">{{ item.name }}</RouterLink>
            <small>{{ item.code || item.sku || `Produk #${item.id}` }}</small>
            <small v-if="item.variantName">Varian: {{ item.variantName }}</small>
            <span v-if="item.is_preorder_item" class="stock-pill pill-preorder">
              Pre-Order{{ item.preorder_estimate_days ? ` · Est. ${item.preorder_estimate_days} hari` : '' }}
            </span>
            <span v-else class="stock-pill pill-ready">
              Tersedia (Stok {{ item.stock ?? 0 }})
            </span>
            <strong>{{ formatRupiah(item.price) }}</strong>
          </div>
          <div class="cart-quantity-wrap">
            <span class="cart-field-label">Jumlah</span>
            <div class="quantity-control">
              <button type="button" :aria-label="`Kurangi ${item.name}`" :disabled="item.quantity <= 1" @click="cart.updateQuantity(item.cartKey, item.quantity - 1)">−</button>
              <span>{{ item.quantity }}</span>
              <button type="button" :aria-label="`Tambah ${item.name}`" :disabled="item.quantity >= (item.is_preorder_item ? 99 : (item.stock ?? 9999))" @click="cart.updateQuantity(item.cartKey, item.quantity + 1)">+</button>
            </div>
          </div>
          <div class="cart-line-total">
            <span class="cart-field-label">Subtotal</span>
            <strong>{{ formatRupiah(item.price * item.quantity) }}</strong>
            <button class="text-button" type="button" @click="cart.removeItem(item.cartKey)">Hapus</button>
          </div>
        </article>

        <div class="cart-list-footer">
          <RouterLink to="/products" class="text-link">← Lanjut belanja</RouterLink>
          <button class="text-button" type="button" @click="cart.clearCart">Kosongkan keranjang</button>
        </div>
      </div>

      <aside class="cart-summary-card">
        <h2>Ringkasan Belanja</h2>
        <div class="summary-line"><span>Total item</span><strong>{{ cart.totalItems }} item</strong></div>
        <div class="summary-line"><span>Subtotal produk</span><strong>{{ formatRupiah(cart.subtotal) }}</strong></div>
        <div class="summary-line"><span>Pengiriman</span><span>Dihitung saat checkout</span></div>
        <div class="summary-line summary-total"><strong>Total sementara</strong><strong>{{ formatRupiah(cart.subtotal) }}</strong></div>
        <p v-if="hasPreorderItems" class="cart-po-summary-note">
          ⏳ Keranjang berisi produk <strong>Pre-Order</strong>. Pesanan akan dikirim serentak setelah proses penyiapan{{ maxPreorderDays ? ` (maksimal ${maxPreorderDays} hari kerja)` : '' }}.
        </p>
        <RouterLink
          v-if="isCustomer"
          to="/checkout"
          class="button button-primary cart-checkout-button"
        >
          Lanjut ke Checkout &amp; COD →
        </RouterLink>
        <RouterLink
          v-else
          to="/auth/login?redirect=/checkout"
          class="button button-primary cart-checkout-button"
        >
          Masuk untuk Checkout &amp; COD →
        </RouterLink>
        <p v-if="!isCustomer" class="cart-guest-note">
          🔒 Masuk atau daftar akun terlebih dahulu untuk melanjutkan ke tahap checkout dan pembayaran COD Surabaya.
        </p>
        <p v-else class="cart-cod-note">
          COD tersedia khusus wilayah Kota Surabaya. Metode pembayaran online (QRIS, VA, E-Wallet) tersedia untuk seluruh Indonesia.
        </p>
      </aside>
    </div>

    <div v-else class="empty-state cart-empty-state">
      <div class="empty-cart-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
      </div>
      <h2>Keranjang masih kosong</h2>
      <p>Yuk, temukan produk kosmetik favoritmu di katalog RiasKu.</p>
      <RouterLink to="/products" class="button button-primary">Jelajahi Katalog</RouterLink>
    </div>
  </section>
</template>

<style scoped>
.cart-guest-note {
  margin-top: 12px;
  font-size: 12px;
  color: #9d174d;
  background: #fdf2f8;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px dashed #f472b6;
  line-height: 1.45;
}
</style>
