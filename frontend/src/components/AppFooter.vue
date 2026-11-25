<script setup>
import { computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useStoreSettingsStore } from '@/stores/storeSettings'
import { buildWhatsAppUrl } from '@/utils/whatsapp'

const auth = useAuthStore()
const isCustomer = computed(() => auth.isAuthenticated && auth.user?.account_type === 'pelanggan')
const store = useStoreSettingsStore()
const whatsappUrl = computed(() => buildWhatsAppUrl(store.settings))

onMounted(() => store.load())
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-store-info">
        <strong>{{ store.settings.store_name }}</strong>
        <span v-if="store.settings.store_tagline">{{ store.settings.store_tagline }}</span>
        <span v-if="store.settings.store_address">{{ store.settings.store_address }}</span>
        <span v-if="store.settings.operational_hours">Jam operasional: {{ store.settings.operational_hours }}</span>
        <a v-if="store.hasActiveWhatsApp && whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener noreferrer">
          WhatsApp {{ store.settings.whatsapp_number }}
        </a>
        <small>© {{ new Date().getFullYear() }} {{ store.settings.store_name }}. Hak cipta dilindungi.</small>
      </div>
      <nav aria-label="Navigasi footer">
        <RouterLink to="/">Beranda</RouterLink>
        <RouterLink to="/products">Katalog</RouterLink>
        <RouterLink v-if="isCustomer" to="/checkout">Ketentuan COD Surabaya</RouterLink>
        <RouterLink v-if="isCustomer" to="/orders/track">Cek Resi &amp; Status</RouterLink>
      </nav>
    </div>
  </footer>
</template>
