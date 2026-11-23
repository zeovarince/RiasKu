<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminBadge from './AdminBadge.vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'

const now = new Date()
const jakartaDate = new Intl.DateTimeFormat('en-US', {
  timeZone: 'Asia/Jakarta',
  year: 'numeric',
  month: 'numeric',
}).formatToParts(now)
const currentMonth = Number(jakartaDate.find((part) => part.type === 'month')?.value)
const currentYear = Number(jakartaDate.find((part) => part.type === 'year')?.value)
const month = ref(currentMonth)
const year = ref(currentYear)
const search = ref('')
const report = ref(null)
const transactions = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 })
const loading = ref(false)
const exporting = ref(false)
const error = ref('')

const monthOptions = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const yearOptions = computed(() => {
  return Array.from({ length: 7 }, (_, index) => currentYear - 5 + index)
})
const summary = computed(() => report.value?.summary || {})
const dailyTrend = computed(() => report.value?.daily_trend || [])
const chartPoints = computed(() => {
  if (!dailyTrend.value.length) return ''
  const max = Math.max(1, ...dailyTrend.value.map((day) => Number(day.revenue) || 0))
  const lastIndex = Math.max(1, dailyTrend.value.length - 1)

  return dailyTrend.value.map((day, index) => {
    const x = (index / lastIndex) * 1000
    const y = 190 - ((Number(day.revenue) || 0) / max) * 160
    return String(x) + ',' + String(y)
  }).join(' ')
})
const monthTitle = computed(() => monthOptions[Number(month.value) - 1] + ' ' + year.value)
const startRow = computed(() => meta.value.total === 0 ? 0 : (meta.value.current_page - 1) * meta.value.per_page + 1)
const endRow = computed(() => Math.min(meta.value.current_page * meta.value.per_page, meta.value.total))

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value) || 0)
}

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
  }).format(date)
}

function transactionTone(row) {
  return row.type === 'Pendapatan' ? 'green' : 'gold'
}

async function loadReport(page = 1) {
  loading.value = true
  error.value = ''

  const params = {
    month: Number(month.value),
    year: Number(year.value),
  }

  try {
    const [summaryResponse, transactionsResponse] = await Promise.all([
      adminService.monthlyReport(params),
      adminService.monthlyTransactions({
        ...params,
        search: search.value.trim() || undefined,
        page,
        per_page: 15,
      }),
    ])

    report.value = summaryResponse.data?.data || null
    transactions.value = transactionsResponse.data?.data || []
    meta.value = transactionsResponse.data?.meta || { current_page: 1, last_page: 1, total: 0, per_page: 15 }
  } catch (requestError) {
    error.value = requestError.response?.data?.message || requestError.message || 'Rekap keuangan gagal dimuat.'
    report.value = null
    transactions.value = []
    meta.value = { current_page: 1, last_page: 1, total: 0, per_page: 15 }
  } finally {
    loading.value = false
  }
}

async function exportReport() {
  exporting.value = true
  error.value = ''

  try {
    const response = await adminService.exportMonthlyReport({
      month: Number(month.value),
      year: Number(year.value),
      search: search.value.trim() || undefined,
    })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = 'riasku-rekap-keuangan-' + year.value + '-' + String(month.value).padStart(2, '0') + '.csv'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (requestError) {
    error.value = requestError.response?.data?.message || requestError.message || 'Laporan gagal diunduh.'
  } finally {
    exporting.value = false
  }
}

function changePage(page) {
  if (page < 1 || page > meta.value.last_page || loading.value) return
  loadReport(page)
}

onMounted(() => loadReport())
</script>

<template>
  <section class="admin-page monthly-report-page">
    <AdminPageHeader
      eyebrow="ADMIN / LAPORAN / REKAP BULANAN"
      title="Rekap Keuangan"
      description="Pantau pendapatan, biaya stok masuk, dan estimasi laba bulanan."
    >
      <template #actions>
        <label class="admin-field monthly-period">
          <span>Bulan</span>
          <select v-model="month" class="admin-select" @change="loadReport()">
            <option v-for="(label, index) in monthOptions" :key="label" :value="index + 1">{{ label }}</option>
          </select>
        </label>
        <label class="admin-field monthly-period">
          <span>Tahun</span>
          <select v-model="year" class="admin-select" @change="loadReport()">
            <option v-for="option in yearOptions" :key="option" :value="option">{{ option }}</option>
          </select>
        </label>
        <button class="admin-btn admin-btn-primary" type="button" :disabled="exporting" @click="exportReport">
          <AdminIcon name="download" :size="15" />
          {{ exporting ? 'Menyiapkan CSV…' : 'Unduh CSV' }}
        </button>
      </template>
    </AdminPageHeader>

    <p v-if="error" class="monthly-report-error" role="alert">{{ error }}</p>
    <p v-if="loading && !report" class="admin-panel monthly-report-state">Memuat rekap {{ monthTitle }}…</p>

    <template v-if="report">
      <div class="admin-metric-grid monthly-metrics">
        <article class="admin-metric-card">
          <span>Total pendapatan</span>
          <strong>{{ formatCurrency(summary.gross_revenue) }}</strong>
          <small>Produk pada pesanan lunas atau selesai</small>
        </article>
        <article class="admin-metric-card">
          <span>Pesanan sukses</span>
          <strong>{{ summary.successful_orders || 0 }}</strong>
          <small>Rata-rata {{ formatCurrency(summary.average_order_value) }} per pesanan</small>
        </article>
        <article class="admin-metric-card">
          <span>Produk terjual</span>
          <strong>{{ summary.products_sold || 0 }} pcs</strong>
          <small>Total kuantitas pada pesanan sukses</small>
        </article>
        <article class="admin-metric-card">
          <span>Biaya barang masuk</span>
          <strong>{{ formatCurrency(summary.stock_expense) }}</strong>
          <small>Restock dengan harga modal tercatat</small>
        </article>
        <article class="admin-metric-card">
          <span>Estimasi laba kotor</span>
          <strong :class="{ 'monthly-negative': Number(summary.gross_profit) < 0 }">{{ formatCurrency(summary.gross_profit) }}</strong>
          <small>Omzet dikurangi estimasi modal terjual</small>
        </article>
      </div>

      <article class="admin-panel monthly-chart-panel">
        <div class="admin-panel-heading">
          <div>
            <h2>Tren pendapatan harian</h2>
            <p>{{ monthTitle }} · zona waktu Asia/Jakarta</p>
          </div>
          <AdminBadge tone="neutral">{{ formatCurrency(summary.gross_revenue) }}</AdminBadge>
        </div>
        <div class="monthly-chart-wrap" :class="{ 'is-loading': loading }">
          <svg viewBox="0 0 1000 220" preserveAspectRatio="none" role="img" :aria-label="'Tren pendapatan harian bulan ' + monthTitle">
            <line v-for="y in [30, 70, 110, 150, 190]" :key="y" x1="0" :y1="y" x2="1000" :y2="y" class="monthly-chart-grid" />
            <polyline v-if="chartPoints" :points="chartPoints" class="monthly-chart-line" />
            <text x="0" y="214" class="monthly-chart-label">1</text>
            <text x="500" y="214" class="monthly-chart-label">{{ Math.ceil(dailyTrend.length / 2) }}</text>
            <text x="985" y="214" text-anchor="end" class="monthly-chart-label">{{ dailyTrend.length }}</text>
          </svg>
          <p v-if="!loading && !Number(summary.gross_revenue)" class="monthly-chart-empty">Belum ada pendapatan pada bulan ini.</p>
        </div>
      </article>

      <article class="admin-panel monthly-transactions-panel">
        <div class="admin-toolbar">
          <div class="admin-panel-title">
            <div>
              <h2>Rincian transaksi</h2>
              <p class="monthly-panel-subtitle">Pendapatan pesanan dan pengeluaran barang masuk pada {{ monthTitle }}.</p>
            </div>
            <AdminBadge>{{ meta.total }} transaksi</AdminBadge>
          </div>
          <div class="admin-toolbar-actions">
            <label class="admin-search monthly-search">
              <AdminIcon name="search" :size="15" />
              <input
                v-model="search"
                type="search"
                placeholder="Cari pesanan, produk, supplier…"
                @keyup.enter="loadReport()"
              />
            </label>
            <button class="admin-btn admin-btn-outline admin-btn-small" type="button" @click="loadReport()">
              Cari
            </button>
          </div>
        </div>

        <p v-if="loading" class="admin-panel-hint">Memuat transaksi…</p>
        <div class="admin-table-wrap">
          <table class="admin-table monthly-transactions-table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>ID transaksi</th>
                <th>Keterangan</th>
                <th>Jenis</th>
                <th>Jumlah</th>
                <th>Nominal</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in transactions" :key="row.type + row.transaction_id">
                <td>{{ formatDate(row.date) }}</td>
                <td><strong class="admin-code">{{ row.transaction_id }}</strong></td>
                <td class="monthly-description">{{ row.description }}</td>
                <td><AdminBadge :tone="transactionTone(row)">{{ row.type }}</AdminBadge></td>
                <td>{{ row.quantity }} pcs</td>
                <td :class="row.type === 'Pendapatan' ? 'monthly-income' : 'monthly-expense'">
                  {{ row.amount === null ? '—' : formatCurrency(row.amount) }}
                </td>
                <td>{{ row.status }}</td>
              </tr>
              <tr v-if="!loading && transactions.length === 0">
                <td colspan="7" class="admin-empty">Tidak ada transaksi untuk periode dan pencarian ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="monthly-pagination">
          <span>Menampilkan {{ startRow }}–{{ endRow }} dari {{ meta.total }}</span>
          <div>
            <button class="admin-btn admin-btn-outline admin-btn-small" type="button" :disabled="meta.current_page <= 1 || loading" @click="changePage(meta.current_page - 1)">Sebelumnya</button>
            <span>Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
            <button class="admin-btn admin-btn-outline admin-btn-small" type="button" :disabled="meta.current_page >= meta.last_page || loading" @click="changePage(meta.current_page + 1)">Berikutnya</button>
          </div>
        </div>
      </article>

      <article class="admin-panel monthly-report-note">
        <div>
          <strong>Catatan perhitungan</strong>
          <p>Laba kotor memakai rata-rata harga modal barang masuk sampai akhir periode. Produk tanpa riwayat modal memakai harga modal produk saat ini. Biaya barang masuk hanya mencakup baris restock yang memiliki harga modal.</p>
        </div>
        <AdminBadge tone="gold">Estimasi modal</AdminBadge>
      </article>
    </template>
  </section>
</template>

<style scoped>
.monthly-report-page { display: flex; flex-direction: column; gap: 14px; }
.monthly-period { min-width: 105px; margin: 0; gap: 4px; }
.monthly-period span { color: #927e83; font-size: 8px; }
.monthly-period select { min-height: 34px; }
.monthly-metrics { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.monthly-metrics .admin-metric-card { min-width: 0; }
.monthly-metrics .admin-metric-card > strong { font-size: clamp(15px, 1.5vw, 21px); }
.monthly-chart-panel { padding: 18px; }
.monthly-chart-wrap { position: relative; height: 220px; margin-top: 16px; }
.monthly-chart-wrap.is-loading { opacity: .55; }
.monthly-chart-wrap svg { width: 100%; height: 100%; overflow: visible; }
.monthly-chart-grid { stroke: #f0e4e7; stroke-width: 1; }
.monthly-chart-line { fill: none; stroke: #a34d6b; stroke-linecap: round; stroke-linejoin: round; stroke-width: 4; vector-effect: non-scaling-stroke; }
.monthly-chart-label { fill: #a28e94; font-size: 11px; }
.monthly-chart-empty { position: absolute; inset: 35% 0 auto; margin: 0; color: #a28e94; font-size: 10px; text-align: center; }
.monthly-transactions-panel { min-width: 0; }
.monthly-panel-subtitle { margin: 4px 0 0; color: #a28e94; font-size: 8px; }
.monthly-search { width: min(280px, 45vw); }
.monthly-description { min-width: 170px; white-space: normal !important; }
.monthly-income { color: #3b8564 !important; font-weight: 700; }
.monthly-expense { color: #ad7651 !important; font-weight: 700; }
.monthly-negative { color: #b45360 !important; }
.monthly-pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding-top: 14px; color: #927e83; font-size: 8px; }
.monthly-pagination > div { display: flex; align-items: center; gap: 9px; }
.monthly-pagination button:disabled, .monthly-report-page button:disabled { opacity: .55; cursor: not-allowed; }
.monthly-report-error { margin: 0; padding: 10px 12px; border: 1px solid #edc7ce; border-radius: 8px; color: #9b3f50; background: #fff5f6; font-size: 10px; }
.monthly-report-state { color: #927e83; font-size: 10px; }
.monthly-report-note { display: flex; justify-content: space-between; gap: 14px; background: #fffaf1; border-color: #f0dfc5; }
.monthly-report-note strong { color: #74552d; font-size: 10px; }
.monthly-report-note p { margin: 4px 0 0; color: #9e8567; font-size: 8px; line-height: 1.6; }
@media (max-width: 1120px) {
  .monthly-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
  .monthly-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .monthly-period { min-width: 0; flex: 1; }
  .monthly-pagination { align-items: flex-start; flex-direction: column; }
  .monthly-pagination > div { flex-wrap: wrap; }
  .monthly-report-note { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 520px) {
  .monthly-metrics { grid-template-columns: 1fr 1fr; }
  .monthly-chart-panel { padding: 14px 12px; }
  .monthly-chart-wrap { height: 175px; }
  .monthly-transactions-panel .admin-toolbar-actions { align-items: stretch; }
  .monthly-search { width: 100%; }
}
</style>
