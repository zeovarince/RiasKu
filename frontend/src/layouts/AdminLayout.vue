<script setup>
const links = [
  { label: 'Dashboard', to: '/admin/dashboard' },
  { label: 'Toko pelanggan', to: '/' },
]
</script>

<template>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <RouterLink class="admin-brand" to="/admin/dashboard">RiasKu<span>.</span></RouterLink>
      <p class="eyebrow">ADMINISTRATION</p>
      <RouterLink v-for="link in links" :key="link.to" :to="link.to" class="admin-nav-link">{{ link.label }}</RouterLink>
    </aside>
    <main class="admin-content"><RouterView /></main>
  </div>
</template>
