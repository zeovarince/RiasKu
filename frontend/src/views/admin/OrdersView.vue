<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'
import { formatRupiah } from '@/utils/currency'

const activeFilter = ref('Semua')
const search = ref('')
const selectedPaymentMethod = ref('Semua metode bayar')
const filters = ['Semua', 'Menunggu Bayar', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan']

const defaultOrders = [
  { code: 'RK-0014', customer: 'Nadia Safitri', item: '2 item', total: 'Rp76.000', payment: 'COD (Uang Tunai)', paymentTone: 'gold', status: 'Diproses', tone: 'lavender', date: '13 Nov 2026' },
  { code: 'RK-0013', customer: 'Megan Utami', item: '1 item', total: 'Rp95.000', payment: 'QRIS', paymentTone: 'green', status: 'Menunggu Bayar', tone: 'gold', date: '12 Nov 2026' },
  { code: 'RK-0012', customer: 'Dinda Permata', item: '2 item', total: 'Rp125.000', payment: 'Transfer BCA', paymentTone: 'green', status: 'Lunas', tone: 'green', date: '11 Nov 2026' },
  { code: 'RK-0011', customer: 'Rika Maulana', item: '1 item', total: 'Rp85.000', payment: 'Transfer Mandiri', paymentTone: 'green', status: 'Dikirim', tone: 'blue', date: '10 Nov 2026' },
  { code: 'RK-0010', customer: 'Siti Nurhaliza', item: '3 item', total: 'Rp160.000', payment: 'QRIS', paymentTone: 'green', status: 'Selesai', tone: 'green', date: '8 Nov 2026' },
]

const orders = ref([...defaultOrders])

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
    default: return orderStatus || 'Diproses'
  }
}

function formatDate(isoString) {
  if (!isoString) return '-'
  try {
    const d = new Date(isoString)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
  } catch {
    return isoString
  }
}

async function fetchOrders() {
  try {
    const res = await adminService.orders()
    const apiOrders = res.data?.data || []
    if (apiOrders.length > 0) {
      const mapped = apiOrders.map((o) => ({
        code: o.order_code,
        customer: o.customer_name || o.recipient_name || 'Pelanggan',
        item: `${o.items?.length || 1} item`,
        total: formatRupiah(o.total_amount ?? o.total ?? 0),
        payment: o.payment_method === 'cod' ? 'COD (Uang Tunai)' : `Online (${(o.payment_channel || 'Midtrans').toUpperCase()})`,
        paymentTone: o.payment_status === 'dibayar' ? 'green' : 'gold',
        status: mapStatusLabel(o.order_status || o.status),
        tone: mapTone(o.order_status || o.status),
        date: formatDate(o.created_at),
        rawMethod: o.payment_method,
      }))
      orders.value = mapped
    }
  } catch (err) {
    console.warn('Gagal memuat pesanan admin dari API, tetap menggunakan data cadangan:', err)
  }
}

const filtered = computed(() =>
  orders.value.filter((o) => {
    const matchesFilter =
      activeFilter.value === 'Semua' ||
      o.status === activeFilter.value ||
      (activeFilter.value === 'Diproses' && o.status === 'Lunas')

    const matchesSearch = `${o.code} ${o.customer}`.toLowerCase().includes(search.value.toLowerCase())

    let matchesPayment = true
    if (selectedPaymentMethod.value === 'COD') {
      matchesPayment = o.payment.includes('COD')
    } else if (selectedPaymentMethod.value === 'Online / QRIS') {
      matchesPayment = !o.payment.includes('COD')
    }

    return matchesFilter && matchesSearch && matchesPayment
  })
)

onMounted(() => {
  fetchOrders()
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / TRANSAKSI / PESANAN"
      title="Pesanan"
      description="Pantau transaksi pembelian dan kelola status pesanan pelanggan (COD &amp; Online Payment)."
    />

    <article class="admin-panel">
      <div class="admin-filter-tabs">
        <button
          v-for="filter in filters"
          :key="filter"
          :class="{ active: activeFilter === filter }"
          @click="activeFilter = filter"
        >
          {{ filter }} <span v-if="filter === 'Semua'">{{ orders.length }}</span>
        </button>
      </div>

      <div class="admin-toolbar">
        <label class="admin-search wide">
          <AdminIcon name="search" :size="16" />
          <input v-model="search" placeholder="Cari kode pesanan, nama pelanggan..." />
        </label>
        <div class="admin-toolbar-actions">
          <select v-model="selectedPaymentMethod" class="admin-select">
            <option value="Semua metode bayar">Semua metode bayar</option>
            <option value="COD">COD (Uang Tunai Surabaya)</option>
            <option value="Online / QRIS">Online (Midtrans / QRIS / VA)</option>
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
              <th>Status Pesanan</th>
              <th>Tanggal</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in filtered" :key="order.code">
              <td>
                <strong class="admin-code">{{ order.code }}</strong>
              </td>
              <td>
                <strong>{{ order.customer }}</strong>
                <small>{{ order.item }}</small>
              </td>
              <td>
                <strong>{{ order.total }}</strong>
              </td>
              <td>
                <span>{{ order.payment }}</span>
              </td>
              <td>
                <AdminBadge :tone="order.tone">{{ order.status }}</AdminBadge>
              </td>
              <td>{{ order.date }}</td>
              <td>
                <RouterLink :to="`/admin/orders/${order.code}`" class="admin-mini-link">
                  Detail
                </RouterLink>
              </td>
            </tr>
            <tr v-if="!filtered.length">
              <td colspan="7" class="admin-empty">Tidak ada pesanan untuk filter ini.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="admin-table-foot">
        <span>Menampilkan {{ filtered.length }} dari {{ orders.length }} pesanan</span>
        <div class="admin-pagination">
          <button disabled>‹</button>
          <button class="current">1</button>
          <button disabled>›</button>
        </div>
      </div>
    </article>
  </section>
</template>
