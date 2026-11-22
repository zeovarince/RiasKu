<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { logout } from '@/services/authService'

const cart = useCartStore()
const ui = useUiStore()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const searchQuery = ref('')
const cartCount = computed(() => cart.totalItems)

const isDropdownOpen = ref(false)
const showProfileModal = ref(false)
const dropdownRef = ref(null)

const isCustomer = computed(() => {
  return auth.isAuthenticated && auth.user?.account_type === 'pelanggan'
})

const userDisplayName = computed(() => {
  return auth.user?.username || auth.user?.name || auth.user?.email?.split('@')[0] || 'Pelanggan'
})

const userInitial = computed(() => {
  const name = userDisplayName.value || 'U'
  return name.charAt(0).toUpperCase()
})

function submitSearch() {
  const query = searchQuery.value.trim()
  if (query) router.push({ name: 'products', query: { q: query } })
}

function toggleDropdown() {
  isDropdownOpen.value = !isDropdownOpen.value
}

function closeDropdown() {
  isDropdownOpen.value = false
}

function openProfileModal() {
  closeDropdown()
  showProfileModal.value = true
}

function handleOutsideClick(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    closeDropdown()
  }
}

async function signOut() {
  closeDropdown()
  try {
    await logout()
  } catch {
    // Clear the local session even when the API is unreachable.
  } finally {
    cart.resetCart()
    auth.clearSession()
    await router.push('/')
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
})

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
})
</script>

<template>
  <header class="site-header">
    <div class="site-header-inner">
      <RouterLink to="/" class="brand" aria-label="RiasKu beranda">RiasKu<span>.</span></RouterLink>

      <nav class="main-nav" aria-label="Navigasi utama">
        <RouterLink to="/" exact-active-class="is-active">Beranda</RouterLink>
        <RouterLink to="/products" active-class="is-active">Katalog Produk</RouterLink>
        <RouterLink to="/products/1" :class="{ 'is-active': route.path.startsWith('/products/') }">Detail Produk</RouterLink>
        <RouterLink v-if="isCustomer" to="/checkout" active-class="is-active">Checkout &amp; COD</RouterLink>
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

        <!-- Dropdown Profil Pengguna (Tombol Keluar berada di dalam dropdown) -->
        <div v-if="isCustomer" ref="dropdownRef" class="profile-dropdown-wrapper">
          <button
            class="profile-trigger-btn"
            type="button"
            :class="{ active: isDropdownOpen }"
            aria-haspopup="true"
            :aria-expanded="isDropdownOpen"
            @click="toggleDropdown"
          >
            <div class="user-avatar-circle">
              <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="Avatar" class="avatar-photo" />
              <span v-else class="avatar-initial">{{ userInitial }}</span>
            </div>
            <span class="user-display-name">{{ userDisplayName }}</span>
            <svg class="chevron-icon" :class="{ rotated: isDropdownOpen }" viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
          </button>

          <!-- Dropdown Menu -->
          <div v-if="isDropdownOpen" class="profile-dropdown-menu">
            <div class="dropdown-user-header">
              <div class="dropdown-avatar">
                <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="Avatar" />
                <span v-else>{{ userInitial }}</span>
              </div>
              <div class="dropdown-user-meta">
                <strong class="user-name-title">{{ userDisplayName }}</strong>
                <small class="user-email-subtitle">{{ auth.user?.email || 'Akun Pelanggan' }}</small>
              </div>
            </div>

            <div class="dropdown-divider"></div>

            <button class="dropdown-action-btn" type="button" @click="openProfileModal">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span>Lihat Profil</span>
            </button>

            <RouterLink to="/orders/track" class="dropdown-action-btn" @click="closeDropdown">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
              <span>Cek Status Pesanan</span>
            </RouterLink>

            <RouterLink to="/cart" class="dropdown-action-btn" @click="closeDropdown">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              <span>Keranjang Belanja ({{ cartCount }})</span>
            </RouterLink>

            <div class="dropdown-divider"></div>

            <button class="dropdown-action-btn logout-action" type="button" @click="signOut">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              <span>Keluar (Logout)</span>
            </button>
          </div>
        </div>

        <RouterLink v-else class="button button-primary button-login" to="/auth/login">Masuk / Daftar</RouterLink>
      </div>
    </div>
  </header>

  <!-- Modal Dialog: Lihat Profil Pelanggan -->
  <div v-if="showProfileModal" class="modal-backdrop" role="dialog" aria-modal="true" @click.self="showProfileModal = false">
    <div class="profile-modal-card">
      <header class="profile-modal-header">
        <div class="header-title-box">
          <span class="eyebrow-badge">INFORMASI AKUN</span>
          <h2>Profil Pelanggan RiasKu</h2>
        </div>
        <button class="modal-close-btn" type="button" aria-label="Tutup modal" @click="showProfileModal = false">×</button>
      </header>

      <div class="profile-modal-body">
        <div class="profile-avatar-banner">
          <div class="profile-avatar-big">
            <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="Avatar" />
            <span v-else>{{ userInitial }}</span>
          </div>
          <div class="avatar-banner-text">
            <h3>{{ userDisplayName }}</h3>
            <span class="badge-role">Pelanggan Resmi</span>
          </div>
        </div>

        <div class="profile-details-list">
          <div class="detail-row">
            <span class="detail-label">Nama Lengkap / Username</span>
            <strong class="detail-val">{{ auth.user?.username || auth.user?.name || '-' }}</strong>
          </div>
          <div class="detail-row">
            <span class="detail-label">Alamat Email</span>
            <strong class="detail-val">{{ auth.user?.email || '-' }}</strong>
          </div>
          <div class="detail-row">
            <span class="detail-label">Status Akun</span>
            <span class="badge-verified">✓ Aktif &amp; Terverifikasi</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Wilayah Layanan</span>
            <span class="detail-val">Kota Surabaya &amp; Seluruh Indonesia</span>
          </div>
        </div>
      </div>

      <footer class="profile-modal-footer">
        <RouterLink to="/orders/track" class="button button-outline" @click="showProfileModal = false">
          Cek Pesanan Saya
        </RouterLink>
        <button class="button button-primary" type="button" @click="showProfileModal = false">
          Tutup
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.profile-dropdown-wrapper {
  position: relative;
  display: inline-block;
}

.profile-trigger-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: #fdf2f8;
  border: 1px solid #fbcfe8;
  padding: 4px 10px 4px 5px;
  border-radius: 9999px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
}

.profile-trigger-btn:hover,
.profile-trigger-btn.active {
  background: #fce7f3;
  border-color: #f472b6;
  box-shadow: 0 2px 8px rgba(244, 114, 182, 0.25);
}

.user-avatar-circle {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: linear-gradient(135deg, #be185d, #831843);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 11px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(190, 24, 93, 0.3);
}

.avatar-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initial {
  line-height: 1;
}

.user-display-name {
  font-size: 12px;
  font-weight: 600;
  color: #831843;
  max-width: 110px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chevron-icon {
  color: #be185d;
  transition: transform 0.2s ease;
}

.chevron-icon.rotated {
  transform: rotate(180deg);
}

/* Dropdown Menu */
.profile-dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 240px;
  background: #ffffff;
  border: 1px solid #f3d4e2;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
  padding: 6px;
  z-index: 1000;
  animation: fadeInDown 0.15s ease-out;
}

@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-6px); }
  to { opacity: 1; transform: translateY(0); }
}

.dropdown-user-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
}

.dropdown-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, #be185d, #831843);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  flex-shrink: 0;
  overflow: hidden;
}

.dropdown-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dropdown-user-meta {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.user-name-title {
  font-size: 13px;
  color: #3b1e2e;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-email-subtitle {
  font-size: 11px;
  color: #8c717e;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-divider {
  height: 1px;
  background: #f1e4eb;
  margin: 4px 0;
}

.dropdown-action-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #4b303d;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: inherit;
  box-sizing: border-box;
}

.dropdown-action-btn:hover {
  background: #fdf2f8;
  color: #be185d;
}

.dropdown-action-btn svg {
  color: #be185d;
  flex-shrink: 0;
}

.logout-action {
  color: #dc2626;
}

.logout-action svg {
  color: #dc2626;
}

.logout-action:hover {
  background: #fef2f2;
  color: #b91c1c;
}

/* Modal Profil */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(18, 14, 20, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 1rem;
}

.profile-modal-card {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.profile-modal-header {
  padding: 1.25rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid #f0e6ec;
}

.profile-modal-header h2 {
  font-size: 1.2rem;
  color: #3b1e2e;
  margin: 0.25rem 0 0;
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 1.75rem;
  line-height: 1;
  color: #8c717e;
  cursor: pointer;
}

.profile-modal-body {
  padding: 1.5rem;
}

.profile-avatar-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #fdf5f8;
  border: 1px solid #f3d4e2;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1.25rem;
}

.profile-avatar-big {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #be185d, #831843);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(190, 24, 93, 0.25);
}

.profile-avatar-big img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-banner-text h3 {
  margin: 0 0 0.25rem;
  font-size: 1.1rem;
  color: #3b1e2e;
}

.badge-role {
  font-size: 0.72rem;
  font-weight: 700;
  background: #ede9fe;
  color: #6d28d9;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
}

.profile-details-list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 0.65rem;
  border-bottom: 1px dashed #f0e6ec;
  font-size: 0.85rem;
}

.detail-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.detail-label {
  color: #7d5a6c;
}

.detail-val {
  color: #3b1e2e;
}

.badge-verified {
  color: #15803d;
  background: #dcfce7;
  font-weight: 700;
  font-size: 0.75rem;
  padding: 0.2rem 0.55rem;
  border-radius: 9999px;
}

.profile-modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #f0e6ec;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  background: #faf8fa;
}

@media (max-width: 768px) {
  .user-display-name {
    display: none;
  }
}
</style>
