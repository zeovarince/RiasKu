<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AdminIcon from '@/components/admin/AdminIcon.vue'

const route = useRoute()
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
      <div class="admin-profile" title="Admin RiasKu">AD</div>
    </aside>

    <div class="admin-main-column">
      <header class="admin-topbar">
        <div class="admin-breadcrumb"><span>Admin</span><b>/</b><strong>{{ section }}</strong></div>
        <div class="admin-topbar-actions">
          <span class="admin-online-dot"></span><span>Admin RiasKu</span>
          <span class="admin-avatar">AD</span>
        </div>
      </header>
      <main class="admin-content"><RouterView /></main>
    </div>
  </div>
</template>
