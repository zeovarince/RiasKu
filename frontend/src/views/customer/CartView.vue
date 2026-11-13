<script setup>
import { useCartStore } from '@/stores/cart'
import { formatRupiah } from '@/utils/currency'
const cart = useCartStore()
</script>

<template>
  <section class="page-container inner-page">
    <span class="eyebrow-badge">PESANAN ANDA</span>
    <h1 class="page-title">Keranjang Belanja</h1>
    <div v-if="cart.items.length" class="cart-list">
      <article v-for="item in cart.items" :key="item.id" class="cart-row">
        <div class="cart-product-art"><span>R</span></div>
        <div class="cart-product-info"><strong>{{ item.name }}</strong><small>{{ item.code }}</small><strong>{{ formatRupiah(item.price) }}</strong></div>
        <div class="quantity-control">
          <button type="button" @click="cart.updateQuantity(item.id, item.quantity - 1)">−</button>
          <span>{{ item.quantity }}</span>
          <button type="button" @click="cart.updateQuantity(item.id, item.quantity + 1)">+</button>
        </div>
        <button class="text-button" type="button" @click="cart.removeItem(item.id)">Hapus</button>
      </article>
      <div class="cart-summary"><span>Subtotal</span><strong>{{ formatRupiah(cart.subtotal) }}</strong></div>
      <RouterLink to="/checkout" class="button button-primary">Lanjut ke Checkout</RouterLink>
    </div>
    <div v-else class="empty-state">
      <h2>Keranjang masih kosong</h2>
      <p>Yuk, temukan produk favoritmu di katalog RiasKu.</p>
      <RouterLink to="/products" class="button button-primary">Jelajahi Katalog</RouterLink>
    </div>
  </section>
</template>
