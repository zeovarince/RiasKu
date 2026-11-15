<script setup>
import { useRoute } from 'vue-router'
import heroImage from '@/assets/auth/hero.webp'
import brandIcon from '@/assets/auth/brand-icon.png'

defineProps({
  active: { type: String, default: 'login' },
  title: { type: String, required: true },
  subtitle: { type: String, required: true },
  showTabs: { type: Boolean, default: true },
  badge: { type: String, default: '' },
})

const route = useRoute()

function tabLocation(name) {
  return { name, query: route.query }
}
</script>

<template>
  <div class="auth-redesign shell">
    <section class="visual" aria-label="RiasKu">
      <img class="hero-img" :src="heroImage" alt="Pelanggan menemukan produk favoritnya di RiasKu." width="1229" height="1280" fetchpriority="high" />
      <i class="sun" aria-hidden="true"></i>
    </section>

    <main class="panel">
      <div class="panel-top">
        <RouterLink class="brand" to="/" aria-label="RiasKu, ke beranda">
          <img class="brand-icon" :src="brandIcon" alt="" />
          <span class="wordmark" role="img" aria-label="RiasKu"></span>
        </RouterLink>
        <RouterLink class="back" to="/">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
          Kembali ke toko
        </RouterLink>
      </div>

      <div class="panel-main">
        <div class="card">
          <nav v-if="showTabs" class="tabs" role="tablist" aria-label="Masuk atau daftar" :data-active="active">
            <span class="tab-ind" aria-hidden="true"></span>
            <RouterLink class="tab" :to="tabLocation('login')" role="tab" :aria-selected="active === 'login'" :tabindex="active === 'login' ? 0 : -1">Masuk</RouterLink>
            <RouterLink class="tab" :to="tabLocation('register')" role="tab" :aria-selected="active === 'register'" :tabindex="active === 'register' ? 0 : -1">Daftar</RouterLink>
          </nav>

          <section class="pane active" role="tabpanel">
            <div v-if="badge" class="badge-wrapper">
              <span class="admin-badge-tag">{{ badge }}</span>
            </div>
            <h1>{{ title }}</h1>
            <p class="sub">{{ subtitle }}</p>
            <slot />
          </section>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.badge-wrapper {
  margin-bottom: 12px;
}
.admin-badge-tag {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  background-color: var(--apricot, #F6D6A8);
  color: var(--espresso, #4A2C35);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
</style>
