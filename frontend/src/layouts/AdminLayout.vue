<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { logoutAdmin } from '@/services/adminAuthService'
import { adminService } from '@/services/adminService'

const route = useRoute()
const auth = useAuthStore()
const pendingOrdersCount = ref(0)

const items = computed(() => [
  { label: 'Dashboard', to: '/admin/dashboard', icon: 'dashboard' },
  { label: 'Produk', to: '/admin/products', icon: 'products' },
  { label: 'Kategori', to: '/admin/categories', icon: 'categories' },
  { label: 'Ulasan', to: '/admin/reviews', icon: 'reviews' },
  {
    label: 'Pesanan',
    to: '/admin/orders',
    icon: 'orders',
    badge: pendingOrdersCount.value > 0 ? String(pendingOrdersCount.value) : null,
  },
  { label: 'Barang masuk', to: '/admin/inventory/incoming', icon: 'inventory' },
  { label: 'Penjualan DoD', to: '/admin/sales/direct', icon: 'sales' },
  { label: 'Rekap bulanan', to: '/admin/reports/monthly', icon: 'reports' },
  { label: 'Pengaturan toko', to: '/admin/settings', icon: 'settings' },
])
const section = computed(() => {
  const current = items.value.find((item) => route.path === item.to || route.path.startsWith(`${item.to}/`))
  return current?.label || 'Dashboard'
})

async function fetchPendingOrdersCount() {
  try {
    const res = await adminService.orders({ per_page: 1 })
    const count = res.data?.summary?.action_required_count ?? 0
    pendingOrdersCount.value = Number(count) || 0
  } catch {
    pendingOrdersCount.value = 0
  }
}

const isDropdownOpen = ref(false)
const showProfileModal = ref(false)
const dropdownRef = ref(null)

const userDisplayName = computed(() => {
  return auth.user?.name || auth.user?.username || 'Admin RiasKu'
})

const userInitial = computed(() => {
  const name = userDisplayName.value || 'A'
  return name.charAt(0).toUpperCase()
})

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

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  window.addEventListener('riasku-orders-updated', fetchPendingOrdersCount)
  fetchPendingOrdersCount()
})

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
  window.removeEventListener('riasku-orders-updated', fetchPendingOrdersCount)
})

async function signOut() {
  closeDropdown()
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
      <button class="admin-profile admin-profile-button" type="button" title="Profil Admin" @click="openProfileModal">
        {{ userInitial }}
      </button>
    </aside>

    <div class="admin-main-column">
      <header class="admin-topbar">
        <div class="admin-breadcrumb"><span>Admin</span><b>/</b><strong>{{ section }}</strong></div>
        
        <div class="admin-topbar-actions">
          <div class="admin-status-indicator" title="Sistem Aktif & Terhubung">
            <span class="admin-online-dot"></span>
            <span class="admin-status-label">Online</span>
          </div>

          <!-- Dropdown Profil Admin (Sama seperti navbar user) -->
          <div ref="dropdownRef" class="profile-dropdown-wrapper">
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
                  <small class="user-email-subtitle">{{ auth.user?.email || 'admin@riasku.com' }}</small>
                </div>
              </div>

              <div class="dropdown-divider"></div>

              <button class="dropdown-action-btn" type="button" @click="openProfileModal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Lihat Profil Admin</span>
              </button>

              <RouterLink to="/" class="dropdown-action-btn" @click="closeDropdown">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Lihat Toko Pelanggan</span>
              </RouterLink>

              <RouterLink to="/admin/settings" class="dropdown-action-btn" @click="closeDropdown">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>Pengaturan Toko</span>
              </RouterLink>

              <div class="dropdown-divider"></div>

              <button class="dropdown-action-btn logout-action" type="button" @click="signOut">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Keluar (Logout)</span>
              </button>
            </div>
          </div>
        </div>
      </header>
      <main class="admin-content"><RouterView /></main>
    </div>

    <!-- Modal Dialog: Lihat Profil Admin -->
    <div v-if="showProfileModal" class="modal-backdrop" role="dialog" aria-modal="true" @click.self="showProfileModal = false">
      <div class="profile-modal-card">
        <header class="profile-modal-header">
          <div class="header-title-box">
            <span class="eyebrow-badge">INFORMASI AKUN</span>
            <h2>Profil Administrator RiasKu</h2>
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
              <span class="badge-role">Administrator Utama</span>
            </div>
          </div>

          <div class="profile-details-list">
            <div class="detail-row">
              <span class="detail-label">Nama Administrator</span>
              <strong class="detail-val">{{ auth.user?.name || auth.user?.username || 'Admin RiasKu' }}</strong>
            </div>
            <div class="detail-row">
              <span class="detail-label">Alamat Email</span>
              <strong class="detail-val">{{ auth.user?.email || 'admin@riasku.com' }}</strong>
            </div>
            <div class="detail-row">
              <span class="detail-label">Peran &amp; Hak Akses</span>
              <span class="badge-verified">Kelola Produk, Pesanan, Inventaris &amp; Laporan</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Status Akun</span>
              <span class="badge-verified">✓ Aktif &amp; Terverifikasi</span>
            </div>
          </div>
        </div>

        <footer class="profile-modal-footer">
          <RouterLink to="/admin/settings" class="modal-btn-secondary" @click="showProfileModal = false">
            Pengaturan Toko
          </RouterLink>
          <button class="modal-btn-primary" type="button" @click="showProfileModal = false">
            Tutup
          </button>
        </footer>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-status-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 999px;
  background: #f4faf6;
  border: 1px solid #d4eedd;
}

.admin-status-label {
  font-size: 11px;
  font-weight: 600;
  color: #2e7d58;
}

.profile-dropdown-wrapper {
  position: relative;
  display: inline-block;
}

.profile-trigger-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #fdf2f8;
  border: 1px solid #fbcfe8;
  padding: 4px 12px 4px 5px;
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
  width: 28px;
  height: 28px;
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
  max-width: 130px;
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
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #be185d, #831843);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
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

.header-title-box {
  display: flex;
  flex-direction: column;
}

.eyebrow-badge {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #9d4c65;
  text-transform: uppercase;
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

.modal-btn-secondary {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid #e7cbd3;
  color: #8e4c63;
  background: #fff;
  font-size: 11px;
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.modal-btn-secondary:hover {
  background: #fdf5f8;
}

.modal-btn-primary {
  padding: 8px 16px;
  border-radius: 8px;
  border: none;
  color: #fff;
  background: #d98da4;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.modal-btn-primary:hover {
  background: #c97894;
}

@media (max-width: 640px) {
  .user-display-name,
  .admin-status-label {
    display: none;
  }
}
</style>
