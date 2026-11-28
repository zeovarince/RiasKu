<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'
import { formatRupiah } from '@/utils/currency'

const chartDays = ref(7)
const loadingDashboard = ref(true)
const dashboardError = ref('')
const dashboardData = ref(null)

const stockRows = ref([])
const loadingRestock = ref(false)
const restockError = ref('')
const bestSellerRows = ref([])
const bestSellerMeta = ref({})
const loadingBestSellers = ref(false)
const bestSellerError = ref('')

const metrics = computed(() => {
  const m = dashboardData.value?.metrics
  const growthText = m?.revenue_growth_pct === null || m?.revenue_growth_pct === undefined
    ? 'Belum ada data bulan lalu'
    : `${m.revenue_growth_pct >= 0 ? '+' : ''}${m.revenue_growth_pct}% dari bulan lalu`

  return [
    {
      label: 'Produk aktif',
      value: m ? String(m.active_products) : '—',
      note: m ? `+${m.new_products_this_month} produk bulan ini` : 'Memuat data...',
      icon: 'package',
      tone: 'rose',
    },
    {
      label: 'Menunggu pembayaran',
      value: m ? String(m.pending_payment_orders) : '—',
      note: m?.pending_payment_orders > 0 ? 'Perlu ditindaklanjuti' : 'Semua pembayaran lancar',
      icon: 'wallet',
      tone: 'gold',
    },
    {
      label: 'Pesanan bulan ini',
      value: m ? String(m.total_orders_this_month) : '—',
      note: m ? `${m.processing_orders} pesanan sedang diproses` : 'Memuat data...',
      icon: 'orders',
      tone: 'lavender',
    },
    {
      label: 'Penjualan bulan ini',
      value: m ? formatRupiah(m.monthly_revenue) : '—',
      note: m ? growthText : 'Memuat data...',
      icon: 'sales',
      tone: 'green',
    },
  ]
})

const chartPoints = computed(() => dashboardData.value?.sales_chart?.points || [])
const chartTotalRevenue = computed(() => Number(dashboardData.value?.sales_chart?.total_revenue || 0))
const chartMaxValue = computed(() => {
  const maxVal = Math.max(0, ...chartPoints.value.map((p) => Number(p.revenue) || 0))
  return maxVal > 0 ? maxVal : 100000
})

const chartCoords = computed(() => {
  const pts = chartPoints.value
  if (!pts.length) return []
  const maxVal = chartMaxValue.value
  const lastIdx = Math.max(1, pts.length - 1)
  return pts.map((point, idx) => {
    const x = (idx / lastIdx) * 640
    const y = 178 - ((Number(point.revenue) || 0) / maxVal) * 150
    return { x: Number(x.toFixed(1)), y: Number(y.toFixed(1)), ...point }
  })
})

const chartLinePath = computed(() => {
  if (!chartCoords.value.length) return ''
  return chartCoords.value.map((pt, i) => `${i === 0 ? 'M' : 'L'}${pt.x} ${pt.y}`).join(' ')
})

const chartAreaPath = computed(() => {
  if (!chartCoords.value.length) return ''
  const first = chartCoords.value[0]
  const last = chartCoords.value[chartCoords.value.length - 1]
  return `${chartLinePath.value} L${last.x} 190 L${first.x} 190 Z`
})

const chartYAxisTicks = computed(() => {
  const maxVal = chartMaxValue.value
  return [1, 0.75, 0.5, 0.25, 0].map((ratio) => {
    const val = Math.round(maxVal * ratio)
    if (val >= 1000000) return `Rp${(val / 1000000).toFixed(1)} jt`
    if (val >= 1000) return `Rp${Math.round(val / 1000)} rb`
    return `Rp${val}`
  })
})

const chartXAxisLabels = computed(() => {
  const pts = chartPoints.value
  if (pts.length <= 7) return pts.map((p) => p.label)
  const step = Math.ceil((pts.length - 1) / 6)
  const picked = []
  for (let i = 0; i < pts.length; i += step) {
    picked.push(pts[i].label)
  }
  const lastLabel = pts[pts.length - 1]?.label
  if (lastLabel && picked[picked.length - 1] !== lastLabel) {
    picked[picked.length - 1] = lastLabel
  }
  return picked
})

const categoryDist = computed(() => dashboardData.value?.category_distribution || { total_units: 0, items: [] })
const recentOrders = computed(() => dashboardData.value?.recent_orders || [])
const pendingCount = computed(() => Number(dashboardData.value?.metrics?.pending_payment_orders || 0))
const legendClasses = ['legend-a', 'legend-b', 'legend-c', 'legend-d']

function restockTone(status) {
  if (status === 'Stok Habis') return 'rose'
  if (status === 'Stok Menipis') return 'gold'
  return 'green'
}

function rankTone(rank) {
  if (rank === 1) return 'cream'
  if (rank === 2) return 'lavender'
  if (rank === 3) return 'gold'
  return 'neutral'
}

function orderStatusLabel(status) {
  const map = {
    menunggu_pembayaran: 'Menunggu Bayar',
    diproses: 'Diproses',
    dikirim: 'Dikirim',
    selesai: 'Selesai',
    dibatalkan: 'Dibatalkan',
  }
  return map[status] || status || 'Diproses'
}

function orderStatusTone(status) {
  if (status === 'selesai') return 'green'
  if (status === 'dikirim') return 'lavender'
  if (status === 'diproses') return 'rose'
  if (status === 'dibatalkan') return 'neutral'
  return 'gold'
}

function formatPaymentLabel(order) {
  if (order.payment_method === 'cod') return 'COD (Uang Tunai)'
  const ch = String(order.payment_channel || 'online').toUpperCase().replaceAll('_', ' ')
  return ch
}

function formatScore(score) {
  return Number(score || 0).toFixed(1)
}

async function loadDashboard() {
  loadingDashboard.value = true
  dashboardError.value = ''
  try {
    const res = await adminService.dashboard({ days: Number(chartDays.value) })
    dashboardData.value = res.data?.data || null
  } catch (err) {
    dashboardError.value = err.response?.data?.message || 'Gagal memuat ringkasan dashboard.'
  } finally {
    loadingDashboard.value = false
  }
}

async function loadRestock() {
  loadingRestock.value = true
  restockError.value = ''
  try {
    const res = await adminService.getRestockAlerts({ per_page: 5 })
    stockRows.value = res.data?.data || []
  } catch (err) {
    restockError.value = err.response?.data?.message || 'Gagal memuat peringatan restock.'
  } finally {
    loadingRestock.value = false
  }
}

async function loadBestSellers() {
  loadingBestSellers.value = true
  bestSellerError.value = ''
  try {
    const res = await adminService.getBestSellers({ limit: 5 })
    bestSellerRows.value = res.data?.data || []
    bestSellerMeta.value = res.data?.meta || {}
  } catch (err) {
    bestSellerError.value = err.response?.data?.message || 'Gagal memuat produk best seller.'
  } finally {
    loadingBestSellers.value = false
  }
}

onMounted(() => {
  loadDashboard()
  loadRestock()
  loadBestSellers()
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / RINGKASAN" title="Selamat datang kembali, Admin" description="Ringkasan aktivitas dan performa toko RiasKu secara real-time.">
      <template #actions>
        <RouterLink to="/" class="admin-btn admin-btn-outline"><AdminIcon name="store" :size="15"/> Lihat toko</RouterLink>
      </template>
    </AdminPageHeader>

    <div v-if="dashboardError" class="admin-bestseller-error" role="alert">
      <AdminIcon name="alert-triangle" :size="18" />
      <span>{{ dashboardError }}</span>
    </div>

    <div class="admin-metric-grid">
      <article v-for="metric in metrics" :key="metric.label" class="admin-metric-card">
        <div class="admin-metric-top"><span>{{ metric.label }}</span><span class="admin-metric-icon" :class="`metric-${metric.tone}`"><AdminIcon :name="metric.icon" :size="17"/></span></div>
        <strong>{{ metric.value }}</strong>
        <small>{{ metric.note }}</small>
      </article>
    </div>

    <div class="admin-dashboard-grid">
      <article class="admin-panel admin-chart-panel">
        <div class="admin-panel-heading">
          <div>
            <h2>Grafik Penjualan</h2>
            <p>Omzet produk lunas &amp; selesai ({{ chartDays }} hari terakhir)</p>
          </div>
          <select v-model="chartDays" class="admin-select compact" aria-label="Rentang grafik penjualan" @change="loadDashboard">
            <option :value="7">7 hari terakhir</option>
            <option :value="30">30 hari terakhir</option>
          </select>
        </div>
        <div class="admin-chart-total">
          <strong>{{ formatRupiah(chartTotalRevenue) }}</strong>
          <span><i></i> Total periode {{ chartDays }} hari</span>
        </div>
        <div class="admin-line-chart">
          <div class="admin-chart-y">
            <span v-for="(tick, idx) in chartYAxisTicks" :key="idx">{{ tick }}</span>
          </div>
          <svg viewBox="0 0 640 190" preserveAspectRatio="none" role="img" :aria-label="`Grafik penjualan ${chartDays} hari terakhir`">
            <defs>
              <linearGradient id="salesFill" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0" stop-color="#c66e8b" stop-opacity=".26"/>
                <stop offset="1" stop-color="#c66e8b" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path v-if="chartAreaPath" :d="chartAreaPath" fill="url(#salesFill)"/>
            <path v-if="chartLinePath" :d="chartLinePath" fill="none" stroke="#a34d6b" stroke-width="3" vector-effect="non-scaling-stroke"/>
            <g v-if="chartCoords.length <= 10" fill="#fff" stroke="#a34d6b" stroke-width="2">
              <circle v-for="pt in chartCoords" :key="pt.date" :cx="pt.x" :cy="pt.y" r="4"/>
            </g>
          </svg>
          <div class="admin-chart-x">
            <span v-for="(label, idx) in chartXAxisLabels" :key="idx">{{ label }}</span>
          </div>
        </div>
      </article>

      <article class="admin-panel admin-sales-panel">
        <div class="admin-panel-heading">
          <div>
            <h2>Barang Terjual</h2>
            <p>Distribusi unit terjual 90 hari terakhir</p>
          </div>
          <RouterLink to="/admin/reports/monthly" class="admin-icon-button" title="Lihat laporan bulanan" aria-label="Lihat laporan bulanan">
            <AdminIcon name="external"/>
          </RouterLink>
        </div>
        <div class="admin-donut-wrap">
          <div class="admin-donut">
            <div>
              <strong>{{ categoryDist.total_units }}</strong>
              <small>Unit terjual</small>
            </div>
          </div>
        </div>
        <ul v-if="categoryDist.items.length" class="admin-legend">
          <li v-for="(item, idx) in categoryDist.items" :key="item.category">
            <i :class="legendClasses[idx % legendClasses.length]"></i>
            <span>{{ item.category }}</span>
            <b>{{ item.percentage }}%</b>
          </li>
        </ul>
        <p v-else class="admin-empty" style="margin-top: 8px; text-align: center;">Belum ada produk terjual dalam 90 hari terakhir.</p>
      </article>

      <article class="admin-panel admin-stock-panel">
        <div class="admin-panel-heading"><div><h2>Segera Restock</h2><p>Produk dengan persediaan pada atau di bawah batas minimum</p></div><RouterLink to="/admin/inventory/incoming" class="admin-text-link">Lihat semua</RouterLink></div>
        <div v-if="restockError" class="admin-bestseller-error" role="alert">
          <AdminIcon name="alert-triangle" :size="16" />
          <span>{{ restockError }}</span>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Produk &amp; SKU</th><th>Stok tersedia</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              <tr v-if="loadingRestock"><td colspan="4" class="admin-empty">Memuat peringatan restock...</td></tr>
              <tr v-else-if="!stockRows.length"><td colspan="4" class="admin-empty">Semua produk memiliki stok aman.</td></tr>
              <tr v-for="product in stockRows" :key="product.id">
                <td><strong>{{ product.name }}</strong><small>{{ product.sku }}</small></td>
                <td>{{ product.total_stock }} pcs <small>min. {{ product.min_stock }} pcs</small></td>
                <td><AdminBadge :tone="restockTone(product.restock_status)">{{ product.restock_status }}</AdminBadge></td>
                <td><RouterLink :to="{ path: '/admin/inventory/incoming', query: { product_id: product.id } }" class="admin-mini-link">Restock</RouterLink></td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <aside class="admin-side-feature">
        <span class="admin-feature-mark">R</span>
        <div>
          <AdminBadge tone="cream">{{ pendingCount > 0 ? 'Perlu perhatian' : 'Terkendali' }}</AdminBadge>
          <h3>
            {{ pendingCount > 0
              ? `${pendingCount} pesanan menunggu pembayaran`
              : 'Tidak ada pesanan menunggu pembayaran'
            }}
          </h3>
          <p>
            {{ pendingCount > 0
              ? 'Pastikan status pembayaran diperbarui sebelum pesanan diproses.'
              : 'Semua pesanan aktif telah terverifikasi atau selesai diproses.'
            }}
          </p>
          <RouterLink to="/admin/orders" class="admin-btn admin-btn-light">Lihat pesanan <AdminIcon name="arrow-left" :size="14"/></RouterLink>
        </div>
      </aside>

      <article class="admin-panel admin-recent-panel">
        <div class="admin-panel-heading"><div><h2>Pesanan Terbaru</h2><p>Transaksi terbaru dari pelanggan</p></div><RouterLink to="/admin/orders" class="admin-text-link">Lihat semua</RouterLink></div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>ID Pesanan</th><th>Pelanggan</th><th>Item</th><th>Total</th><th>Metode bayar</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              <tr v-if="loadingDashboard"><td colspan="7" class="admin-empty">Memuat pesanan terbaru...</td></tr>
              <tr v-else-if="!recentOrders.length"><td colspan="7" class="admin-empty">Belum ada transaksi pesanan.</td></tr>
              <tr v-for="order in recentOrders" :key="order.id">
                <td><strong class="admin-code">{{ order.order_code }}</strong></td>
                <td>{{ order.customer_name }}</td>
                <td>{{ order.items_count }} item</td>
                <td><strong>{{ formatRupiah(order.total_amount) }}</strong></td>
                <td>{{ formatPaymentLabel(order) }}</td>
                <td><AdminBadge :tone="orderStatusTone(order.order_status)">{{ orderStatusLabel(order.order_status) }}</AdminBadge></td>
                <td><RouterLink :to="`/admin/orders/${order.order_code}`" class="admin-mini-link">Detail</RouterLink></td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </div>

    <article class="admin-panel admin-bestseller-panel">
      <div class="admin-panel-heading">
        <div>
          <h2>Produk Best Seller</h2>
          <p>Peringkat utama dari frekuensi pesanan {{ bestSellerMeta.window_days || 90 }} hari terakhir, dengan unit terjual dan rating sebagai penilaian tambahan.</p>
        </div>
        <RouterLink to="/admin/products" class="admin-text-link">Kelola produk</RouterLink>
      </div>
      <div v-if="bestSellerError" class="admin-bestseller-error">
        <AdminIcon name="alert-triangle" :size="18" />
        <span>{{ bestSellerError }}</span>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Peringkat</th>
              <th>Produk &amp; SKU</th>
              <th>Pesanan</th>
              <th>Total Terjual</th>
              <th>Rating</th>
              <th>Skor</th>
              <th>Stok</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingBestSellers"><td colspan="8" class="admin-empty">Memuat produk best seller...</td></tr>
            <tr v-else-if="!bestSellerRows.length"><td colspan="8" class="admin-empty">Belum ada penjualan lunas dalam {{ bestSellerMeta.window_days || 90 }} hari terakhir.</td></tr>
            <tr v-for="item in bestSellerRows" :key="item.product_id">
              <td><AdminBadge :tone="rankTone(item.rank)">#{{ item.rank }}</AdminBadge></td>
              <td><strong>{{ item.name }}</strong><small>{{ item.brand }} &bull; {{ item.sku || 'Tanpa SKU' }}</small></td>
              <td>{{ item.orders_count }} pesanan</td>
              <td><strong>{{ item.units_sold }} pcs</strong></td>
              <td>{{ item.rating || '-' }} <small>{{ item.review_count }} ulasan</small></td>
              <td><strong>{{ formatScore(item.best_seller_score) }}</strong> <small>/ 100</small></td>
              <td>
                {{ item.total_stock }} pcs <small>min. {{ item.min_stock }} pcs</small>
                <AdminBadge :tone="restockTone(item.restock_status)">{{ item.restock_status }}</AdminBadge>
              </td>
              <td>
                <RouterLink
                  v-if="item.needs_restock"
                  :to="{ path: '/admin/inventory/incoming', query: { product_id: item.product_id } }"
                  class="admin-mini-link"
                >Restock</RouterLink>
                <RouterLink v-else :to="`/admin/products/${item.product_id}/edit`" class="admin-mini-link">Detail</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>

<style scoped>
.admin-bestseller-panel { margin-top: 14px; }
.admin-bestseller-error {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  padding: 10px 12px;
  border: 1px solid #f8d7da;
  border-radius: 8px;
  color: #c53030;
  background: #fdf1f2;
  font-size: 10px;
}
</style>
