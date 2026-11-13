<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'

const cart = useCartStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()
const searchQuery = ref('')
const cartCount = computed(() => cart.totalItems)
const isProductRoute = computed(() => route.path.startsWith('/products'))

function submitSearch() {
  const query = searchQuery.value.trim()
  if (query) router.push({ name: 'products', query: { q: query } })
}
</script>

<template>
  <div class="prototype-strip">
    <div class="prototype-strip-inner">
      <RouterLink to="/" class="step-pill" exact-active-class="active">1. Beranda</RouterLink>
      <RouterLink to="/products/1" class="step-pill" :class="{ active: isProductRoute }">2. Detail Produk</RouterLink>
      <RouterLink to="/checkout" class="step-pill" active-class="active">3. Checkout &amp; COD</RouterLink>
      <RouterLink to="/orders/track" class="step-pill" active-class="active">4. Cek Status</RouterLink>
    </div>
  </div>

  <header class="site-header">
    <div class="site-header-inner">
      <RouterLink to="/" class="brand" aria-label="RiasKu beranda">RiasKu<span>.</span></RouterLink>

      <nav class="main-nav" aria-label="Navigasi utama">
        <RouterLink to="/" exact-active-class="is-active">Beranda</RouterLink>
        <RouterLink to="/products" active-class="is-active">Katalog Produk</RouterLink>
        <RouterLink to="/products/1" :class="{ 'is-active': route.path.startsWith('/products/') }">Detail Produk</RouterLink>
        <RouterLink to="/checkout" active-class="is-active">Checkout &amp; COD</RouterLink>
        <RouterLink to="/orders/track" active-class="is-active">Cek Pesanan</RouterLink>
      </nav>

      <div class="header-actions">
        <form class="header-search" @submit.prevent="submitSearch">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4 4"/></svg>
          <input v-model="searchQuery" type="search" placeholder="Cari produk / riasan..." aria-label="Cari produk" />
        </form>
        <button class="button button-soft button-ai" type="button" @click="ui.openModal('ai')"><span class="sparkle">✦</span> Tanya AI</button>
        <RouterLink to="/cart" class="cart-icon" aria-label="Keranjang belanja">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 4.5h2l2.1 10.1h9.8l2.1-7.1H6.2"/><circle cx="9.2" cy="18.5" r="1.2"/><circle cx="17" cy="18.5" r="1.2"/></svg>
          <span v-if="cartCount" class="cart-count">{{ cartCount }}</span>
        </RouterLink>
        <button class="button button-primary button-login" type="button" @click="ui.openModal('login')">Masuk / Daftar</button>
      </div>
    </div>
  </header>
</template>
