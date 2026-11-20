<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const isCustomer = computed(() => auth.isAuthenticated && auth.user?.account_type === 'pelanggan')
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner">
      <p>© 2026 RiasKu Studio Surabaya. Hak cipta dilindungi.</p>
      <nav aria-label="Navigasi footer">
        <RouterLink to="/">Beranda</RouterLink>
        <RouterLink to="/products">Katalog</RouterLink>
        <RouterLink v-if="isCustomer" to="/checkout">Ketentuan COD Surabaya</RouterLink>
        <RouterLink v-if="isCustomer" to="/orders/track">Cek Resi &amp; Status</RouterLink>
      </nav>
    </div>
  </footer>
</template>
