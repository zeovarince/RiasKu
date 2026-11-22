<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'
import { formatRupiah } from '@/utils/currency'

const filterTabs = [
  { label: 'Semua', value: 'all', summaryKey: 'all' },
  { label: 'Menunggu Bayar', value: 'menunggu_pembayaran', summaryKey: 'menunggu_pembayaran' },
  { label: 'Diproses', value: 'diproses', summaryKey: 'diproses' },
  { label: 'Dikirim', value: 'dikirim', summaryKey: 'dikirim' },
  { label: 'Selesai', value: 'selesai', summaryKey: 'selesai' },
  { label: 'Dibatalkan', value: 'dibatalkan', summaryKey: 'dibatalkan' },
]

const activeStatus = ref('all')
const search = ref('')
const selectedPaymentMethod = ref('all')
const selectedOrderType = ref('all')

const orders = ref([])
const loading = ref(false)
const errorMessage = ref('')
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const summary = ref({
  all: 0,
  menunggu_pembayaran: 0,
  diproses: 0,
  dikirim: 0,
  selesai: 0,
  dibatalkan: 0,
  action_required_count: 0,
})

function resolveStatusValue(order) {
  const raw = String(order.order_status || order.status || 'menunggu_pembayaran').toLowerCase()
  if (raw === 'pending') {
    return order.payment_method === 'cod' ? 'diproses' : 'menunggu_pembayaran'
  }
  return raw
}

function mapTone(orderStatus) {
  switch (orderStatus) {
    case 'selesai': return 'green'
    case 'diproses': return 'lavender'
    case 'dikirim': return 'blue'
    case 'menunggu_pembayaran': return 'gold'
    case 'dibatalkan': return 'red'
    default: return 'neutral'
  }
}

function mapStatusLabel(orderStatus) {
  switch (orderStatus) {
    case 'menunggu_pembayaran': return 'Menunggu Bayar'
    case 'diproses': return 'Diproses'
    case 'dikirim': return 'Dikirim'
    case 'selesai': return 'Selesai'
    case 'dibatalkan': return 'Dibatalkan'
    default: return orderStatus || 'Menunggu'
  }
}

function formatPaymentLabel(order) {
  const isCod = order.payment_method === 'cod' || order.payment_channel === 'cash'
  if (isCod) {
    return 'COD (Uang Tunai)'
  }
  return `Online (${String(order.payment_channel || 'Midtrans').toUpperCase()})`
}

function formatDate(isoString) {
  if (!isoString) return '-'
  try {
    return new Date(isoString).toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    })
  } catch {
    return isoString
  }
}

let ordersRequestSeq = 0

async function fetchOrders(page = 1) {
  const seq = ++ordersRequestSeq
  loading.value = true
  errorMessage.value = ''
  try {
    const params = {
      page,
      per_page: 15,
    }
    if (search.value.trim()) params.search = search.value.trim()
    if (activeStatus.value !== 'all') params.status = activeStatus.value
    if (selectedPaymentMethod.value !== 'all') params.payment_method = selectedPaymentMethod.value
    if (selectedOrderType.value !== 'all') params.is_preorder = selectedOrderType.value

    const res = await adminService.orders(params)
    if (seq !== ordersRequestSeq) return
    const rows = Array.isArray(res.data?.data) ? res.data.data : []

    orders.value = rows.map((o) => {
      const effectiveStatus = resolveStatusValue(o)
      const itemCount = o.total_items_count ?? o.items?.length ?? 0
      const amount = o.total_amount ?? o.total ?? 0

      return {
        id: o.id,
        code: o.order_code,
        customer: o.customer_name || o.recipient_name || 'Pelanggan',
        phone: o.customer_phone || o.recipient_phone || '',
        itemLabel: `${itemCount} item`,
        total: formatRupiah(amount),
        payment: formatPaymentLabel(o),
        paymentPaid: o.payment_status === 'dibayar' || o.payment_status === 'lunas' || o.payment_status === 'settlement',
        status: mapStatusLabel(effectiveStatus),
        tone: mapTone(effectiveStatus),
        trackingNumber: o.tracking_number || null,
        hasPreorder: Boolean(o.has_preorder_items),
        preorderDays: o.preorder_estimate_days || null,
        date: formatDate(o.created_at),
      }
    })

    if (res.data?.meta) {
      meta.value = res.data.meta
    }
    if (res.data?.summary) {
      summary.value = res.data.summary
    }
  } catch (err) {
    if (seq !== ordersRequestSeq) return
    orders.value = []
    errorMessage.value = err.response?.data?.message || 'Gagal memuat daftar pesanan dari server.'
  } finally {
    if (seq === ordersRequestSeq) {
      loading.value = false
    }
  }
}

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchOrders(1)
  }, 350)
})

watch([activeStatus, selectedPaymentMethod, selectedOrderType], () => {
  fetchOrders(1)
})

onMounted(() => {
  fetchOrders(1)
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / TRANSAKSI / PESANAN"
      title="Pesanan"
      description="Pantau transaksi pembelian, nomor resi, dan kelola status pesanan pelanggan (COD &amp; Online Payment)."
    />

    <div v-if="errorMessage" class="admin-inline-error" style="margin-bottom: 1rem; color: #dc2626; background: #fef2f2; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem;">
      ⚠️ {{ errorMessage }}
    </div>

    <article class="admin-panel">
      <div class="admin-filter-tabs">
        <button
          v-for="tab in filterTabs"
          :key="tab.value"
          :class="{ active: activeStatus === tab.value }"
          @click="activeStatus = tab.value"
        >
          {{ tab.label }}
          <span>{{ summary[tab.summaryKey] ?? 0 }}</span>
        </button>
      </div>

      <div class="admin-toolbar">
        <label class="admin-search wide">
          <AdminIcon name="search" :size="16" />
          <input v-model="search" placeholder="Cari kode pesanan, nama, no. HP, atau no. resi..." />
        </label>
        <div class="admin-toolbar-actions">
          <select v-model="selectedPaymentMethod" class="admin-select" aria-label="Filter metode bayar">
            <option value="all">Semua metode bayar</option>
            <option value="cod">COD (Uang Tunai Surabaya)</option>
            <option value="online">Online (Midtrans / QRIS / VA)</option>
          </select>
          <select v-model="selectedOrderType" class="admin-select" aria-label="Filter jenis stok pesanan">
            <option value="all">Semua jenis pesanan</option>
            <option value="1">Pesanan Pre-Order</option>
            <option value="0">Ready Stock</option>
          </select>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table admin-orders-table">
          <thead>
            <tr>
              <th>Kode Pesanan</th>
              <th>Pelanggan &amp; Item</th>
              <th>Total Belanja</th>
              <th>Metode Bayar</th>
              <th>Status &amp; Resi</th>
              <th>Tanggal</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="admin-empty">Memuat daftar pesanan...</td>
            </tr>
            <tr v-else-if="!orders.length">
              <td colspan="7" class="admin-empty">Belum ada pesanan yang sesuai dengan filter ini.</td>
            </tr>
            <template v-else>
              <tr v-for="order in orders" :key="order.code">
                <td>
                  <strong class="admin-code">{{ order.code }}</strong>
                  <span v-if="order.hasPreorder" class="order-po-tag">
                    PO{{ order.preorderDays ? ` · ${order.preorderDays}h` : '' }}
                  </span>
                </td>
                <td>
                  <strong>{{ order.customer }}</strong>
                  <small>{{ order.itemLabel }}{{ order.phone ? ` · ${order.phone}` : '' }}</small>
                </td>
                <td>
                  <strong>{{ order.total }}</strong>
                </td>
                <td>
                  <span>{{ order.payment }}</span>
                  <small>{{ order.paymentPaid ? '✓ Lunas' : 'Belum Lunas' }}</small>
                </td>
                <td>
                  <AdminBadge :tone="order.tone">{{ order.status }}</AdminBadge>
                  <small v-if="order.trackingNumber" style="display: block; margin-top: 3px; color: #475569;">
                    Resi: {{ order.trackingNumber }}
                  </small>
                </td>
                <td>{{ order.date }}</td>
                <td>
                  <RouterLink :to="`/admin/orders/${order.code}`" class="admin-mini-link">
                    Detail
                  </RouterLink>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="admin-table-foot">
        <span>Menampilkan {{ orders.length }} dari {{ meta.total }} pesanan</span>
        <div class="admin-pagination">
          <button
            type="button"
            :disabled="meta.current_page <= 1 || loading"
            @click="fetchOrders(meta.current_page - 1)"
          >
            ‹
          </button>
          <span style="padding: 0 8px; font-size: 0.85rem; font-weight: 600;">
            {{ meta.current_page }} / {{ meta.last_page || 1 }}
          </span>
          <button
            type="button"
            :disabled="meta.current_page >= meta.last_page || loading"
            @click="fetchOrders(meta.current_page + 1)"
          >
            ›
          </button>
        </div>
      </div>
    </article>
  </section>
</template>

<style scoped>
.order-po-tag {
  display: inline-block;
  margin-left: 6px;
  padding: 2px 7px;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  font-size: 10px;
  font-weight: 700;
}
</style>
