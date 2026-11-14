<script setup>
import { onMounted } from 'vue'
import ProductArtwork from '@/components/ProductArtwork.vue'
import { useCartStore } from '@/stores/cart'
import { formatRupiah } from '@/utils/currency'

const cart = useCartStore()

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
            <strong>{{ formatRupiah(item.price) }}</strong>
          </div>
          <div class="cart-quantity-wrap">
            <span class="cart-field-label">Jumlah</span>
            <div class="quantity-control">
              <button type="button" :aria-label="`Kurangi ${item.name}`" :disabled="item.quantity <= 1" @click="cart.updateQuantity(item.cartKey, item.quantity - 1)">−</button>
              <span>{{ item.quantity }}</span>
              <button type="button" :aria-label="`Tambah ${item.name}`" :disabled="item.quantity >= (item.stock ?? 9999)" @click="cart.updateQuantity(item.cartKey, item.quantity + 1)">+</button>
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
        <RouterLink to="/checkout" class="button button-primary cart-checkout-button">Lanjut ke Checkout →</RouterLink>
        <p class="cart-cod-note">COD tersedia untuk area Surabaya. Metode pengiriman dan biaya akhir akan dikonfirmasi pada tahap checkout.</p>
      </aside>
    </div>

    <div v-else class="empty-state cart-empty-state">
      <div class="empty-cart-icon" aria-hidden="true">♡</div>
      <h2>Keranjang masih kosong</h2>
      <p>Yuk, temukan produk kosmetik favoritmu di katalog RiasKu.</p>
      <RouterLink to="/products" class="button button-primary">Jelajahi Katalog</RouterLink>
    </div>
  </section>
</template>
