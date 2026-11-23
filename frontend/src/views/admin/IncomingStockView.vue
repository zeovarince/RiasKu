<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'
import { formatRupiah } from '@/utils/currency'

const route = useRoute()

const products = ref([])
const history = ref([])
const restockProducts = ref([])
const meta = ref({ current_page: 1, per_page: 15, total: 0, last_page: 1 })
const loading = ref(false)
const saving = ref(false)
const message = ref('')
const messageType = ref('success')
const errors = ref({})

const filters = reactive({ produk_id: '', date_from: '', date_to: '' })
const form = reactive({
  produk_id: '',
  produk_varian_id: '',
  quantity: 1,
  tanggal_masuk: new Date().toISOString().slice(0, 10),
  supplier: '',
  unit_cost: '',
  notes: '',
})

const variants = computed(() => {
  const product = products.value.find((item) => String(item.id) === String(form.produk_id))

  return product?.variants || product?.varians || []
})

function restockTone(status) {
  if (status === 'Stok Habis') return 'rose'
  if (status === 'Stok Menipis') return 'gold'
  return 'green'
}

function formatTanggal(value) {
  if (!value) return '-'

  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function loadProducts() {
  try {
    const res = await adminService.getProducts({ per_page: 100, is_active: 1 })
    products.value = res.data.data || []
  } catch (err) {
    message.value = err.response?.data?.message || 'Gagal memuat daftar produk.'
    messageType.value = 'danger'
  }
}

async function loadHistory(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (filters.produk_id) params.produk_id = filters.produk_id
    if (filters.date_from) params.date_from = filters.date_from
    if (filters.date_to) params.date_to = filters.date_to

    const res = await adminService.getIncomingStocks(params)
    history.value = res.data.data || []
    meta.value = res.data.meta || meta.value
  } catch (err) {
    message.value = err.response?.data?.message || 'Gagal memuat riwayat barang masuk.'
    messageType.value = 'danger'
  } finally {
    loading.value = false
  }
}

async function loadRestock() {
  try {
    const res = await adminService.getRestockAlerts({ per_page: 5 })
    restockProducts.value = res.data.data || []
  } catch (err) {
    message.value = err.response?.data?.message || 'Gagal memuat peringatan restock.'
    messageType.value = 'danger'
  }
}

function applyQuery() {
  const productId = route.query.product_id
  if (!productId) return

  form.produk_id = String(productId)
  const selected = products.value.find((item) => String(item.id) === String(productId))
  const requestedVariant = route.query.variant_id
  const available = selected?.variants || selected?.varians || []
  form.produk_varian_id = available.some((item) => String(item.id) === String(requestedVariant))
    ? String(requestedVariant)
    : (available[0]?.id ?? '')
}

function selectProductForRestock(product) {
  form.produk_id = String(product.id)
  const available = product.variants || product.varians || []
  form.produk_varian_id = available[0]?.id ?? ''
  message.value = `Produk "${product.name}" dipilih. Isi jumlah barang masuk lalu simpan.`
  messageType.value = 'success'
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function saveStockIn() {
  errors.value = {}
  saving.value = true

  try {
    const payload = {
      produk_id: Number(form.produk_id),
      produk_varian_id: Number(form.produk_varian_id),
      quantity: Number(form.quantity),
      tanggal_masuk: form.tanggal_masuk,
      supplier: form.supplier || null,
      notes: form.notes || null,
    }
    if (form.unit_cost !== '' && form.unit_cost !== null) {
      payload.unit_cost = Number(form.unit_cost)
    }

    const res = await adminService.createIncomingStock(payload)
    message.value = res.data.message || 'Barang masuk berhasil dicatat.'
    messageType.value = 'success'
    form.quantity = 1
    form.notes = ''

    await Promise.all([loadProducts(), loadHistory(meta.value.current_page), loadRestock()])
  } catch (err) {
    message.value = err.response?.data?.message || 'Gagal menyimpan barang masuk.'
    messageType.value = 'danger'
    errors.value = err.response?.data?.errors || {}
  } finally {
    saving.value = false
  }
}

watch(() => form.produk_id, () => {
  const available = variants.value
  if (!available.some((item) => String(item.id) === String(form.produk_varian_id))) {
    form.produk_varian_id = available[0]?.id ?? ''
  }
})

watch([() => filters.produk_id, () => filters.date_from, () => filters.date_to], () => {
  loadHistory(1)
})

watch(() => route.query.product_id, () => {
  applyQuery()
})

onMounted(async () => {
  await loadProducts()
  applyQuery()
  await Promise.all([loadHistory(1), loadRestock()])
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / INVENTARIS / BARANG MASUK"
      title="Barang Masuk"
      description="Catat stok baru yang diterima dan perbarui persediaan produk secara otomatis."
    >
      <template #actions><AdminBadge tone="gold">Inventaris</AdminBadge></template>
    </AdminPageHeader>

    <div v-if="message" :class="['admin-alert-banner', messageType]">
      <AdminIcon :name="messageType === 'success' ? 'check' : 'alert-triangle'" :size="18" />
      <span>{{ message }}</span>
      <button class="admin-alert-close" type="button" @click="message = ''">×</button>
    </div>

    <div class="admin-stock-layout">
      <div class="admin-form-main">
        <form class="admin-panel admin-form-panel" @submit.prevent="saveStockIn">
          <div class="admin-panel-heading">
            <div>
              <h2>Form Barang Masuk</h2>
              <p>Masukkan detail produk dan kuantitas stok yang diterima.</p>
            </div>
          </div>

          <label class="admin-field">
            Pilih Produk <em>*</em>
            <select v-model="form.produk_id" :class="{ 'is-invalid': errors.produk_id }" required>
              <option value="">Pilih produk yang akan ditambahkan</option>
              <option v-for="product in products" :key="product.id" :value="product.id">
                {{ product.name }} ({{ product.total_stock }} pcs)
              </option>
            </select>
            <small v-if="errors.produk_id" class="admin-error-text">{{ errors.produk_id[0] }}</small>
          </label>

          <div class="admin-field-grid">
            <label class="admin-field">
              Varian Produk <em>*</em>
              <select v-model="form.produk_varian_id" :class="{ 'is-invalid': errors.produk_varian_id }" required>
                <option value="">Pilih varian</option>
                <option v-for="variant in variants" :key="variant.id" :value="variant.id">
                  {{ variant.name }} (stok {{ variant.stock }} pcs)
                </option>
              </select>
              <small v-if="errors.produk_varian_id" class="admin-error-text">{{ errors.produk_varian_id[0] }}</small>
            </label>
            <label class="admin-field">
              Jumlah Masuk (pcs) <em>*</em>
              <input v-model.number="form.quantity" type="number" min="1" :class="{ 'is-invalid': errors.quantity }" required />
              <small v-if="errors.quantity" class="admin-error-text">{{ errors.quantity[0] }}</small>
            </label>
          </div>

          <div class="admin-field-grid">
            <label class="admin-field">
              Nama / kode supplier
              <input v-model="form.supplier" placeholder="Supplier kosmetik" :class="{ 'is-invalid': errors.supplier }" />
              <small v-if="errors.supplier" class="admin-error-text">{{ errors.supplier[0] }}</small>
            </label>
            <label class="admin-field">
              Harga Modal Satuan (Rp)
              <input v-model.number="form.unit_cost" type="number" min="0" placeholder="28000" :class="{ 'is-invalid': errors.unit_cost }" />
              <small v-if="errors.unit_cost" class="admin-error-text">{{ errors.unit_cost[0] }}</small>
            </label>
          </div>

          <div class="admin-field-grid">
            <label class="admin-field">
              Tanggal Masuk
              <input v-model="form.tanggal_masuk" type="date" :class="{ 'is-invalid': errors.tanggal_masuk }" />
              <small v-if="errors.tanggal_masuk" class="admin-error-text">{{ errors.tanggal_masuk[0] }}</small>
            </label>
            <label class="admin-field">
              Catatan (faktur / batch)
              <input v-model="form.notes" placeholder="Contoh: INV-2026-113" :class="{ 'is-invalid': errors.notes }" />
              <small v-if="errors.notes" class="admin-error-text">{{ errors.notes[0] }}</small>
            </label>
          </div>

          <button class="admin-btn admin-btn-primary" type="submit" :disabled="saving || !form.produk_id || !form.produk_varian_id">
            <AdminIcon name="check" :size="15" />
            {{ saving ? 'Menyimpan...' : 'Simpan Barang Masuk' }}
          </button>
        </form>

        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Riwayat Barang Masuk</h2>
              <p>Riwayat stok yang telah diterima dan dicatat oleh admin.</p>
            </div>
            <AdminBadge tone="neutral">{{ meta.total }} transaksi</AdminBadge>
          </div>

          <div class="admin-field-grid stock-filters">
            <label class="admin-field">
              Filter produk
              <select v-model="filters.produk_id">
                <option value="">Semua produk</option>
                <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
              </select>
            </label>
            <label class="admin-field">
              Rentang tanggal
              <div class="stock-date-range">
                <input v-model="filters.date_from" type="date" />
                <span>s/d</span>
                <input v-model="filters.date_to" type="date" />
              </div>
            </label>
          </div>

          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Produk & Variasi</th>
                  <th>Qty Masuk</th>
                  <th>Harga Modal</th>
                  <th>Supplier / Catatan</th>
                  <th>Dicatat Oleh</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading"><td colspan="6" class="admin-empty">Memuat riwayat barang masuk...</td></tr>
                <tr v-else-if="!history.length"><td colspan="6" class="admin-empty">Belum ada transaksi barang masuk.</td></tr>
                <tr v-for="row in history" :key="row.id">
                  <td>
                    <strong>{{ formatTanggal(row.tanggal_masuk) }}</strong>
                    <small>{{ row.created_at ? formatTanggal(row.created_at) : '-' }}</small>
                  </td>
                  <td>
                    <strong>{{ row.produk?.name || '-' }}</strong>
                    <small>{{ row.varian?.name || '-' }}</small>
                  </td>
                  <td><AdminBadge tone="green">+{{ row.quantity }} pcs</AdminBadge></td>
                  <td>{{ row.unit_cost ? formatRupiah(row.unit_cost) : '-' }}</td>
                  <td>
                    <strong>{{ row.supplier || '-' }}</strong>
                    <small v-if="row.notes">{{ row.notes }}</small>
                  </td>
                  <td>{{ row.recorded_by?.name || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="admin-table-foot">
            <span>Menampilkan {{ history.length }} dari {{ meta.total }} transaksi</span>
            <div class="admin-pagination">
              <button type="button" :disabled="meta.current_page <= 1" @click="loadHistory(meta.current_page - 1)">‹</button>
              <span class="pagination-page">{{ meta.current_page }} / {{ meta.last_page || 1 }}</span>
              <button type="button" :disabled="meta.current_page >= meta.last_page" @click="loadHistory(meta.current_page + 1)">›</button>
            </div>
          </div>
        </article>
      </div>

      <aside class="admin-panel admin-form-panel">
        <div class="admin-panel-heading">
          <h2>Segera Restock</h2>
          <AdminBadge tone="gold">{{ restockProducts.length }} item</AdminBadge>
        </div>
        <p class="admin-panel-hint">Produk dengan stok pada atau di bawah batas minimum.</p>
        <p v-if="!restockProducts.length" class="admin-panel-hint">Semua produk memiliki stok aman.</p>
        <div v-for="product in restockProducts" :key="product.id" class="admin-restock-item">
          <span class="admin-product-thumb"><span>R</span></span>
          <div>
            <strong>{{ product.name }}</strong>
            <small>Stok {{ product.total_stock }} pcs · min. {{ product.min_stock }} pcs</small>
            <AdminBadge :tone="restockTone(product.restock_status)">{{ product.restock_status }}</AdminBadge>
            <button type="button" class="admin-mini-link" @click="selectProductForRestock(product)">Catat barang masuk</button>
          </div>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
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
.admin-error-text {
  color: #C53030;
  font-size: 0.78rem;
  font-weight: 400;
}
.is-invalid {
  border-color: #C53030 !important;
}
.stock-filters {
  margin-bottom: 14px;
}
.stock-date-range {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pagination-page {
  font-size: 0.88rem;
  font-weight: 500;
  padding: 0 8px;
}
</style>
