<script setup>
import { ref, watch, onMounted } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'

const products = ref([])
const categories = ref([])
const loading = ref(false)
const message = ref('')
const messageType = ref('success')

// Filter state
const search = ref('')
const selectedCategory = ref('')
const selectedStockLabel = ref('')
const selectedActive = ref('')
const currentPage = ref(1)
const meta = ref({ current_page: 1, per_page: 15, total: 0, last_page: 1 })

async function fetchCategories() {
  try {
    const res = await adminService.getCategories()
    categories.value = res.data.data || []
  } catch (err) {
    console.error('Gagal mengambil kategori:', err)
  }
}

async function fetchProducts(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const params = {
      page,
      per_page: 15,
    }
    if (search.value.trim()) params.search = search.value.trim()
    if (selectedCategory.value) params.kategori_id = selectedCategory.value
    if (selectedStockLabel.value) params.stock_label = selectedStockLabel.value
    if (selectedActive.value !== '') params.is_active = selectedActive.value

    const res = await adminService.getProducts(params)
    products.value = res.data.data || []
    if (res.data.meta) {
      meta.value = res.data.meta
    }
  } catch (err) {
    message.value = err.response?.data?.message || 'Gagal memuat daftar produk.'
    messageType.value = 'danger'
  } finally {
    loading.value = false
  }
}

async function toggleActive(product) {
  const newStatus = !product.is_active
  const actionText = newStatus ? 'mengaktifkan' : 'menonaktifkan'
  if (!confirm(`Yakin ingin ${actionText} produk "${product.name}"?`)) {
    return
  }

  try {
    await adminService.toggleProductStatus(product.id, newStatus)
    product.is_active = newStatus
    message.value = `Produk "${product.name}" berhasil ${newStatus ? 'diaktifkan' : 'dinonaktifkan'}.`
    messageType.value = 'success'
  } catch (err) {
    message.value = err.response?.data?.message || 'Gagal mengubah status produk.'
    messageType.value = 'danger'
  }
}

function getTone(label) {
  if (label === 'Tersedia') return 'green'
  if (label === 'Pre-Order') return 'gold'
  return 'rose'
}

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchProducts(1)
  }, 400)
})

watch([selectedCategory, selectedStockLabel, selectedActive], () => {
  fetchProducts(1)
})

onMounted(() => {
  fetchCategories()
  fetchProducts(1)
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / KATALOG / PRODUK"
      title="Produk"
      description="Kelola katalog, harga, stok, varian, dan status produk toko RiasKu."
    >
      <template #actions>
        <RouterLink to="/admin/products/create" class="admin-btn admin-btn-primary">
          <AdminIcon name="plus" :size="16" /> Tambah Produk
        </RouterLink>
        <RouterLink to="/products" class="admin-btn admin-btn-outline">
          <AdminIcon name="external" :size="15" /> Lihat Toko
        </RouterLink>
      </template>
    </AdminPageHeader>

    <div v-if="message" :class="['admin-alert-banner', messageType]">
      <AdminIcon :name="messageType === 'success' ? 'check' : 'alert-triangle'" :size="18" />
      <span>{{ message }}</span>
      <button class="admin-alert-close" @click="message = ''">×</button>
    </div>

    <article class="admin-panel">
      <!-- Toolbar Filters -->
      <div class="admin-toolbar-expanded">
        <div class="admin-panel-title">
          <h2>Daftar Produk</h2>
          <AdminBadge tone="neutral">{{ meta.total }} produk</AdminBadge>
        </div>

        <div class="admin-filters-grid">
          <label class="admin-search">
            <AdminIcon name="search" :size="16" />
            <input v-model="search" placeholder="Cari nama, merek, atau SKU..." />
          </label>

          <select v-model="selectedCategory" class="admin-filter-select">
            <option value="">Semua Kategori</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>

          <select v-model="selectedStockLabel" class="admin-filter-select">
            <option value="">Semua Label Stok</option>
            <option value="Tersedia">Tersedia</option>
            <option value="Pre-Order">Pre-Order</option>
            <option value="Stok Habis">Stok Habis</option>
          </select>

          <select v-model="selectedActive" class="admin-filter-select">
            <option value="">Semua Status</option>
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>
      </div>

      <!-- Products Table -->
      <div class="admin-table-wrap">
        <table class="admin-table admin-products-table">
          <thead>
            <tr>
              <th>Produk</th>
              <th>Kategori</th>
              <th>Harga Modal</th>
              <th>Harga Eceran</th>
              <th>Harga Pack</th>
              <th>Stok</th>
              <th>Label Stok</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="admin-empty">Memuat produk...</td>
            </tr>
            <tr v-else-if="!products.length">
              <td colspan="9" class="admin-empty">Produk tidak ditemukan.</td>
            </tr>
            <tr v-for="product in products" :key="product.id" :class="{ 'row-inactive': !product.is_active }">
              <td>
                <div class="admin-product-cell">
                  <span class="admin-product-thumb">
                    <img
                      v-if="product.primary_foto?.image_url || (product.fotos && product.fotos[0]?.image_url)"
                      :src="product.primary_foto?.image_url || product.fotos[0]?.image_url"
                      alt="Thumbnail"
                      class="thumb-img"
                    />
                    <span v-else>R</span>
                  </span>
                  <span>
                    <strong>{{ product.name }}</strong>
                    <small>{{ product.brand }} &bull; {{ product.sku || 'Tanpa SKU' }}</small>
                  </span>
                </div>
              </td>
              <td>{{ product.kategori?.name || '-' }}</td>
              <td>Rp{{ Number(product.base_price).toLocaleString('id-ID') }}</td>
              <td>
                <strong>Rp{{ Number(product.retail_price).toLocaleString('id-ID') }}</strong>
              </td>
              <td>
                <span v-if="product.pack_price">
                  Rp{{ Number(product.pack_price).toLocaleString('id-ID') }}
                  <small class="pack-qty">({{ product.pack_quantity }} pcs)</small>
                </span>
                <span v-else class="text-muted">-</span>
              </td>
              <td>{{ product.total_stock }} pcs</td>
              <td>
                <AdminBadge :tone="getTone(product.stock_label)">
                  {{ product.stock_label }}
                </AdminBadge>
              </td>
              <td>
                <button
                  type="button"
                  class="admin-status-toggle"
                  :class="{ active: product.is_active }"
                  :title="product.is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan'"
                  @click="toggleActive(product)"
                >
                  <span class="toggle-dot"></span>
                  {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                </button>
              </td>
              <td>
                <div class="admin-row-actions">
                  <RouterLink
                    :to="`/admin/products/${product.id}/edit`"
                    class="admin-icon-button"
                    title="Ubah Produk"
                  >
                    <AdminIcon name="edit" :size="15" />
                  </RouterLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="admin-table-foot">
        <span>Menampilkan {{ products.length }} dari {{ meta.total }} produk</span>
        <div class="admin-pagination">
          <button
            :disabled="meta.current_page <= 1"
            @click="fetchProducts(meta.current_page - 1)"
          >
            ‹
          </button>
          <span class="pagination-page">{{ meta.current_page }} / {{ meta.last_page || 1 }}</span>
          <button
            :disabled="meta.current_page >= meta.last_page"
            @click="fetchProducts(meta.current_page + 1)"
          >
            ›
          </button>
        </div>
      </div>
    </article>
  </section>
</template>

<style scoped>
.admin-toolbar-expanded {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 20px;
}
.admin-filters-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 12px;
}
@media (max-width: 900px) {
  .admin-filters-grid {
    grid-template-columns: 1fr 1fr;
  }
}
.admin-filter-select {
  padding: 8px 12px;
  border-radius: var(--admin-radius-input, 12px);
  border: 1px solid var(--admin-border, #F0DEDB);
  background-color: #FFFFFF;
  color: var(--admin-text, #4A2C35);
  font-family: inherit;
  font-size: 0.9rem;
}
.thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 8px;
}
.row-inactive {
  opacity: 0.65;
  background-color: #FAF5F4;
}
.pack-qty {
  display: block;
  font-size: 0.78rem;
  color: var(--admin-text-muted, #8A6F76);
}
.admin-status-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: var(--admin-radius-pill, 999px);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--admin-border, #F0DEDB);
  background-color: #F5EAE7;
  color: #8A6F76;
  transition: all 0.2s ease;
}
.admin-status-toggle.active {
  background-color: #E6F4EA;
  color: #137333;
  border-color: #CEEAD6;
}
.toggle-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: currentColor;
}
.admin-alert-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: var(--admin-radius-input, 12px);
  margin-bottom: 20px;
  font-size: 0.92rem;
  font-weight: 500;
}
.admin-alert-banner.success {
  background-color: #E6F4EA;
  color: #137333;
  border: 1px solid #CEEAD6;
}
.admin-alert-banner.danger {
  background-color: #FDF1F2;
  color: #C53030;
  border: 1px solid #F8D7DA;
}
.admin-alert-close {
  margin-left: auto;
  background: transparent;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: inherit;
  line-height: 1;
}
.pagination-page {
  font-size: 0.88rem;
  font-weight: 500;
  padding: 0 8px;
}
</style>
