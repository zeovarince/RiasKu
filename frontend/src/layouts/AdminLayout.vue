<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { logoutAdmin } from '@/services/adminAuthService'

const route = useRoute()
const auth = useAuthStore()
const items = [
  { label: 'Dashboard', to: '/admin/dashboard', icon: 'dashboard' },
  { label: 'Produk', to: '/admin/products', icon: 'products' },
  { label: 'Kategori', to: '/admin/categories', icon: 'categories' },
  { label: 'Ulasan', to: '/admin/reviews', icon: 'reviews' },
  { label: 'Pesanan', to: '/admin/orders', icon: 'orders', badge: '3' },
  { label: 'Barang masuk', to: '/admin/inventory/incoming', icon: 'inventory' },
  { label: 'Penjualan DoD', to: '/admin/sales/direct', icon: 'sales' },
  { label: 'Rekap bulanan', to: '/admin/reports/monthly', icon: 'reports' },
  { label: 'Pengaturan toko', to: '/admin/settings', icon: 'settings' },
]
const section = computed(() => {
  const current = items.find((item) => route.path === item.to || route.path.startsWith(`${item.to}/`))
  return current?.label || 'Dashboard'
})

async function signOut() {
  try {
    await logoutAdmin()
  } catch {
    // Clear the local session even when the API is unreachable.
  } finally {
    auth.clearSession()
    window.location.assign('/admin/login')
  }
}
</script>

<template>
  <div class="admin-layout">
    <aside class="admin-sidebar" aria-label="Navigasi admin">
      <RouterLink class="admin-mark-link" to="/admin/dashboard" title="RiasKu Admin">
        <img :src="'/images/riasku-mark.png'" alt="Logo RiasKu" class="admin-mark" />
      </RouterLink>
      <div class="admin-sidebar-divider"></div>
      <nav class="admin-sidebar-nav">
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
          class="admin-sidebar-link"
          :class="{ 'is-active': route.path === item.to || route.path.startsWith(`${item.to}/`) }"
          :title="item.label"
          :aria-label="item.label"
        >
          <AdminIcon :name="item.icon" :size="20" />
          <span v-if="item.badge" class="admin-sidebar-badge">{{ item.badge }}</span>
          <span class="admin-sidebar-tooltip">{{ item.label }}</span>
        </RouterLink>
      </nav>
      <div class="admin-sidebar-spacer"></div>
      <RouterLink to="/" class="admin-sidebar-link admin-store-link" title="Lihat toko pelanggan">
        <AdminIcon name="store" :size="19" />
        <span class="admin-sidebar-tooltip">Lihat toko</span>
      </RouterLink>
      <button class="admin-profile admin-profile-button" type="button" title="Keluar" @click="signOut">{{ (auth.user?.name || 'Admin').slice(0, 2).toUpperCase() }}</button>
    </aside>

    <div class="admin-main-column">
      <header class="admin-topbar">
        <div class="admin-breadcrumb"><span>Admin</span><b>/</b><strong>{{ section }}</strong></div>
        <div class="admin-topbar-actions">
          <span class="admin-online-dot"></span><span>{{ auth.user?.name || 'Admin RiasKu' }}</span>
          <button class="admin-avatar admin-profile-button" type="button" title="Keluar" @click="signOut">Keluar</button>
        </div>
      </header>
      <main class="admin-content"><RouterView /></main>
    </div>
  </div>
</template>
