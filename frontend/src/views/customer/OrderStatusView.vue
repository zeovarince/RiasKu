<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PaymentModal from '@/components/PaymentModal.vue'
import { useAuthStore } from '@/stores/auth'
import { completeOrder, getMyOrders, getOrderStatus } from '@/services/checkoutService'
import { syncPaymentStatus } from '@/services/paymentService'
import { formatRupiah } from '@/utils/currency'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const isCustomerLoggedIn = computed(() => {
  return auth.isAuthenticated && auth.user?.account_type === 'pelanggan'
})

const orderCodeInput = ref('')
const isLoading = ref(false)
const order = ref(null)
const notFound = ref(false)
const errorMessage = ref('')
const showPaymentModal = ref(false)

// Daftar Pesanan Saya (Pelanggan Login)
const myOrders = ref([])
const loadingMyOrders = ref(false)
const myOrderStatusFilter = ref('all')
const myOrderIds = computed(() => new Set(myOrders.value.map((o) => o.order_code)))

const isCompletingOrder = ref(false)
const completeFeedback = ref('')
const copyResiFeedback = ref('')

const filteredMyOrders = computed(() => {
  if (myOrderStatusFilter.value === 'all') return myOrders.value
  return myOrders.value.filter((o) => resolveStatus(o) === myOrderStatusFilter.value)
})

function resolveStatus(target) {
  const raw = String(target?.order_status || target?.status || 'menunggu_pembayaran').toLowerCase()
  if (raw === 'pending') {
    return target?.payment_method === 'cod' ? 'diproses' : 'menunggu_pembayaran'
  }
  return raw
}

function formatStatusLabel(status) {
  const map = {
    menunggu_pembayaran: 'MENUNGGU PEMBAYARAN',
    diproses: 'DIPROSES',
    dikirim: 'DIKIRIM',
    selesai: 'SELESAI',
    dibatalkan: 'DIBATALKAN',
  }
  return map[status] || String(status || '').replaceAll('_', ' ').toUpperCase()
}

const effectiveStatus = computed(() => resolveStatus(order.value))

const isCod = computed(() => {
  return order.value?.payment_method === 'cod' || order.value?.payment_channel === 'cash'
})

const isPaid = computed(() => {
  return ['dibayar', 'lunas', 'settlement'].includes(order.value?.payment_status)
})

const isSurabaya = computed(() => {
  return String(order.value?.shipping_city || order.value?.destination_city || '').toLowerCase().includes('surabaya')
})

const totalProductAmount = computed(() => {
  return Number(order.value?.total_product_amount ?? order.value?.subtotal ?? 0)
})

const shippingCost = computed(() => {
  return Number(order.value?.shipping_cost ?? 0)
})

const totalAmount = computed(() => {
  return Number(order.value?.total_amount ?? order.value?.total ?? 0)
})

const canCompleteOrder = computed(() => {
  if (!isCustomerLoggedIn.value || !order.value) return false
  if (effectiveStatus.value !== 'dikirim') return false
  if (isCod.value && !isPaid.value) return false
  return myOrderIds.value.has(order.value.order_code) || order.value.pelanggan_id === auth.user?.id
})

const orderTimelineStep = computed(() => {
  if (!order.value) return 1
  const s = effectiveStatus.value
  if (s === 'dibatalkan') return 0
  if (s === 'menunggu_pembayaran') return isPaid.value ? 2 : 1
  if (s === 'diproses') return 3
  if (s === 'dikirim') return 4
  if (s === 'selesai') return 5
  return 2
})

const cancellationNote = computed(() => {
  const logs = order.value?.status_logs || []
  const cancelLog = [...logs].reverse().find((l) => l.to_status === 'dibatalkan')
  return cancelLog?.note || 'Pesanan ini telah dibatalkan.'
})

function formatDateTime(isoString) {
  if (!isoString) return '-'
  try {
    const d = new Date(isoString)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return isoString
  }
}

async function fetchMyOrders() {
  if (!isCustomerLoggedIn.value) {
    myOrders.value = []
    return
  }
  loadingMyOrders.value = true
  try {
    const res = await getMyOrders()
    myOrders.value = Array.isArray(res?.data) ? res.data : []
  } catch {
    myOrders.value = []
  } finally {
    loadingMyOrders.value = false
  }
}

async function searchOrder(code) {
  const queryCode = (code || orderCodeInput.value || '').trim()
  if (!queryCode) return

  isLoading.value = true
  notFound.value = false
  errorMessage.value = ''
  completeFeedback.value = ''
  order.value = null

  try {
    const res = await getOrderStatus(queryCode)
    const data = res.data?.data || res.data || res.order || res
    if (data && (data.order_code || data.code)) {
      order.value = {
        ...data,
        order_code: data.order_code || data.code,
        customer_name: data.customer_name || data.recipient_name,
        customer_phone: data.customer_phone || data.recipient_phone,
        shipping_city: data.shipping_city || data.destination_city,
        shipping_courier: data.shipping_courier || [data.courier, data.courier_service].filter(Boolean).join(' '),
        items: data.items || [],
        status_logs: data.status_logs || [],
        payment: data.payment || data.latest_pembayaran || null,
      }
      orderCodeInput.value = order.value.order_code

      if (route.query.code !== order.value.order_code) {
        router.replace({ query: { code: order.value.order_code } })
      }
    } else {
      notFound.value = true
    }
  } catch (err) {
    if (err.response?.status === 404) {
      notFound.value = true
    } else {
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat informasi pesanan.'
    }
  } finally {
    isLoading.value = false
  }
}

function handlePaymentSuccess() {
  showPaymentModal.value = false
  if (order.value?.order_code) {
    searchOrder(order.value.order_code)
  }
  fetchMyOrders()
}

const isSyncing = ref(false)
const syncFeedback = ref('')

async function handleSyncLiveStatus() {
  if (!order.value?.order_code || isSyncing.value) return
  isSyncing.value = true
  syncFeedback.value = ''
  try {
    const result = await syncPaymentStatus(order.value.order_code)
    syncFeedback.value = result.message || 'Status berhasil disinkronkan dari Midtrans.'
    await searchOrder(order.value.order_code)
    fetchMyOrders()
  } catch (err) {
    syncFeedback.value = err.response?.data?.message || 'Gagal memeriksa status ke Midtrans.'
  } finally {
    isSyncing.value = false
  }
}

async function handleCompleteOrder() {
  if (!order.value?.order_code || isCompletingOrder.value) return
  if (!confirm('Konfirmasi bahwa pesanan ini sudah Anda terima dengan baik?')) return

  isCompletingOrder.value = true
  completeFeedback.value = ''
  errorMessage.value = ''
  try {
    const res = await completeOrder(order.value.order_code)
    completeFeedback.value = res?.message || 'Pesanan telah ditandai selesai.'
    await Promise.all([searchOrder(order.value.order_code), fetchMyOrders()])
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal menyelesaikan pesanan.'
  } finally {
    isCompletingOrder.value = false
  }
}

async function copyTrackingNumber() {
  if (!order.value?.tracking_number) return
  try {
    await navigator.clipboard.writeText(order.value.tracking_number)
    copyResiFeedback.value = 'Nomor resi disalin!'
    setTimeout(() => { copyResiFeedback.value = '' }, 2000)
  } catch {
    copyResiFeedback.value = order.value.tracking_number
  }
}

onMounted(() => {
  fetchMyOrders()
  const codeFromQuery = route.query.code
  if (codeFromQuery) {
    orderCodeInput.value = String(codeFromQuery).trim()
    searchOrder(orderCodeInput.value)
  }
})

watch(() => route.query.code, (newCode) => {
  if (newCode && newCode !== orderCodeInput.value) {
    orderCodeInput.value = String(newCode).trim()
    searchOrder(orderCodeInput.value)
  }
})

watch(isCustomerLoggedIn, (loggedIn) => {
  if (loggedIn) fetchMyOrders()
})
</script>

<template>
  <section class="page-container inner-page narrow-page order-track-page">
    <header class="order-track-header">
      <span class="eyebrow-badge">PELACAKAN PESANAN</span>
      <h1 class="page-title">Cek Status Pesanan</h1>
      <p class="page-subtitle">Lacak progres pengemasan, nomor resi pengiriman, dan rincian pembayaran pesanan RiasKu Anda.</p>
    </header>

    <!-- Daftar Pesanan Saya (khusus pelanggan yang sedang login) -->
    <div v-if="isCustomerLoggedIn" class="surface-card my-orders-card">
      <div class="my-orders-header">
        <div>
          <h2>Pesanan Saya</h2>
          <small>Klik salah satu pesanan untuk melihat rincian &amp; nomor resi.</small>
        </div>
        <select v-model="myOrderStatusFilter" class="form-control my-orders-filter" aria-label="Filter status pesanan saya">
          <option value="all">Semua Status</option>
          <option value="menunggu_pembayaran">Menunggu Pembayaran</option>
          <option value="diproses">Diproses</option>
          <option value="dikirim">Dikirim</option>
          <option value="selesai">Selesai</option>
          <option value="dibatalkan">Dibatalkan</option>
        </select>
      </div>

      <div v-if="loadingMyOrders" class="my-orders-empty">Memuat riwayat pesanan Anda...</div>
      <div v-else-if="!filteredMyOrders.length" class="my-orders-empty">
        Belum ada pesanan{{ myOrderStatusFilter !== 'all' ? ' untuk status ini' : '' }}.
      </div>
      <div v-else class="my-orders-list">
        <button
          v-for="item in filteredMyOrders"
          :key="item.order_code"
          type="button"
          class="my-order-pill"
          :class="{ active: order?.order_code === item.order_code }"
          @click="searchOrder(item.order_code)"
        >
          <div>
            <strong>{{ item.order_code }}</strong>
            <small>{{ formatDateTime(item.created_at) }} · {{ formatRupiah(item.total_amount ?? item.total ?? 0) }}</small>
          </div>
          <span class="badge-status" :class="`status-${resolveStatus(item)}`">
            {{ formatStatusLabel(resolveStatus(item)) }}
          </span>
        </button>
      </div>
    </div>

    <!-- Form Pencarian Kode Pesanan -->
    <form class="surface-card track-form" @submit.prevent="searchOrder()">
      <div class="search-input-group">
        <label for="orderCode">Cari Berdasarkan Kode Pesanan</label>
        <div class="input-with-button">
          <input
            id="orderCode"
            v-model="orderCodeInput"
            class="form-control"
            placeholder="Contoh: RSK-20261121-ABC12"
            required
          />
          <button class="button button-primary" type="submit" :disabled="isLoading">
            {{ isLoading ? 'Mencari...' : 'Cari Pesanan' }}
          </button>
        </div>
      </div>
    </form>

    <!-- Pesan Loading -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Sedang memuat data pelacakan pesanan...</p>
    </div>

    <!-- Alert Not Found -->
    <div v-else-if="notFound" class="notice-message notice-error">
      ⚠️ Pesanan dengan kode <strong>"{{ orderCodeInput }}"</strong> tidak ditemukan.
      Mohon periksa kembali huruf dan nomor pada kode pesanan Anda.
    </div>

    <!-- Alert Error Lainnya -->
    <div v-else-if="errorMessage" class="notice-message notice-error">
      ⚠️ {{ errorMessage }}
    </div>

    <!-- Hasil Detail Pesanan -->
    <div v-else-if="order" class="order-detail-card surface-card">
      <header class="order-card-header">
        <div>
          <span class="order-code-badge">{{ order.order_code }}</span>
          <span v-if="order.has_preorder_items" class="po-order-tag">
            Pre-Order{{ order.preorder_estimate_days ? ` · Est. ${order.preorder_estimate_days} hari` : '' }}
          </span>
          <span class="order-date-text">Dibuat pada: {{ formatDateTime(order.created_at) }}</span>
        </div>
        <div class="status-badge-wrap">
          <span class="badge-status" :class="`status-${effectiveStatus}`">
            {{ formatStatusLabel(effectiveStatus) }}
          </span>
        </div>
      </header>

      <div v-if="completeFeedback" class="paid-helper-alert" style="margin-top: 1rem;">
        ✓ {{ completeFeedback }}
      </div>

      <!-- Banner Pembatalan Eksplisit -->
      <div v-if="effectiveStatus === 'dibatalkan'" class="cancelled-order-banner">
        <strong>✕ Pesanan Dibatalkan</strong>
        <p>{{ cancellationNote }}</p>
      </div>

      <!-- Timeline Progres Pesanan -->
      <div v-else class="tracking-timeline-box">
        <div class="timeline-steps">
          <div class="step-item" :class="{ completed: orderTimelineStep >= 1 }">
            <span class="step-dot">1</span>
            <span class="step-label">Pesanan Dibuat</span>
          </div>

          <div class="step-divider" :class="{ active: orderTimelineStep >= 2 }"></div>

          <div class="step-item" :class="{ completed: orderTimelineStep >= 2, current: orderTimelineStep === 2 }">
            <span class="step-dot">2</span>
            <span class="step-label">{{ isCod ? 'Konfirmasi COD' : 'Pembayaran' }}</span>
          </div>

          <div class="step-divider" :class="{ active: orderTimelineStep >= 3 }"></div>

          <div class="step-item" :class="{ completed: orderTimelineStep >= 3, current: orderTimelineStep === 3 }">
            <span class="step-dot">3</span>
            <span class="step-label">Sedang Diproses</span>
          </div>

          <div class="step-divider" :class="{ active: orderTimelineStep >= 4 }"></div>

          <div class="step-item" :class="{ completed: orderTimelineStep >= 4, current: orderTimelineStep === 4 }">
            <span class="step-dot">4</span>
            <span class="step-label">Pengiriman</span>
          </div>

          <div class="step-divider" :class="{ active: orderTimelineStep >= 5 }"></div>

          <div class="step-item" :class="{ completed: orderTimelineStep >= 5, current: orderTimelineStep === 5 }">
            <span class="step-dot">5</span>
            <span class="step-label">Selesai</span>
          </div>
        </div>
      </div>

      <!-- Aksi Konfirmasi Terima Paket oleh Pelanggan -->
      <div v-if="canCompleteOrder" class="complete-order-box">
        <p>Paket Anda berstatus <strong>Dikirim</strong>. Sudah menerima pesanan dengan baik?</p>
        <button
          class="button button-primary"
          type="button"
          :disabled="isCompletingOrder"
          @click="handleCompleteOrder"
        >
          {{ isCompletingOrder ? 'Memproses...' : '✓ Pesanan Sudah Diterima' }}
        </button>
      </div>

      <!-- Info Pengiriman, Resi & Penerima -->
      <section class="section-box delivery-info-box">
        <h3>Informasi Pengiriman</h3>
        <div class="info-grid">
          <div>
            <span class="info-label">Penerima</span>
            <strong>{{ order.customer_name || '-' }}</strong>
            <small style="display: block; color: #6b7280;">{{ order.customer_phone }}</small>
          </div>
          <div>
            <span class="info-label">Tujuan Pengiriman</span>
            <strong>{{ [order.shipping_district ? `Kec. ${order.shipping_district}` : '', order.shipping_city || order.destination_city, order.destination_province, order.shipping_postal_code].filter(Boolean).join(', ') || '-' }}</strong>
            <span v-if="isCod && isSurabaya" class="badge-sub-highlight">📍 Wilayah COD Toko Surabaya</span>
          </div>
          <div>
            <span class="info-label">Ekspedisi &amp; Nomor Resi</span>
            <strong>{{ order.shipping_courier || (isCod ? 'Kurir Toko RiasKu Surabaya' : 'Reguler Courier') }}</strong>
            <small v-if="order.shipping_etd" style="display: block; color: #6b7280;">Estimasi kirim: {{ order.shipping_etd }}</small>
            <small v-if="order.weight_grams" style="display: block; color: #6b7280;">Berat paket: {{ order.weight_grams }} g</small>
            <div v-if="order.tracking_number" class="resi-copy-row">
              <code>{{ order.tracking_number }}</code>
              <button type="button" class="copy-resi-btn" @click="copyTrackingNumber">Salin Resi</button>
              <small v-if="copyResiFeedback" style="color: #16a34a;">{{ copyResiFeedback }}</small>
            </div>
            <small v-else style="display: block; color: #6b7280;">
              {{ isCod ? 'Pengiriman kurir toko' : 'Nomor resi menyusul saat dikirim' }}
            </small>
          </div>
        </div>
      </section>

      <!-- Status Pembayaran (PPL-19 & PPL-20) -->
      <section class="section-box payment-status-box">
        <div class="payment-box-header">
          <div>
            <h3>Status Pembayaran</h3>
            <p v-if="isCod" class="method-sub">Metode: <strong>Cash on Delivery (COD Surabaya)</strong></p>
            <p v-else class="method-sub">Metode: <strong>Online ({{ (order.payment_channel || 'Midtrans').toUpperCase() }})</strong></p>
          </div>
          <span
            class="payment-pill"
            :class="{
              'pill-paid': isPaid,
              'pill-pending': !isPaid && effectiveStatus !== 'dibatalkan',
              'pill-cancelled': effectiveStatus === 'dibatalkan' || order.payment_status === 'batal' || order.payment_status === 'kadaluarsa'
            }"
          >
            {{ isPaid ? '✓ LUNAS / SETTLEMENT' : (effectiveStatus === 'dibatalkan' ? 'DIBATALKAN' : (isCod ? 'MENUNGGU KURIR (BAYAR DI TEMPAT)' : 'MENUNGGU PEMBAYARAN')) }}
          </span>
        </div>

        <div v-if="effectiveStatus === 'dibatalkan'" class="cod-helper-alert" style="background: #fef2f2; border-color: #fecaca; color: #991b1b;">
          <p style="margin: 0;">Transaksi telah dibatalkan. Tidak ada tagihan pembayaran yang aktif untuk pesanan ini.</p>
        </div>

        <div v-else-if="isCod && !isPaid" class="cod-helper-alert">
          <p>
            📍 <strong>Instruksi COD Surabaya:</strong> Pesanan Anda akan dikirim langsung oleh kurir toko RiasKu.
            Harap siapkan uang tunai pas sebesar <strong>{{ formatRupiah(totalAmount) }}</strong> saat serah terima paket di lokasi.
          </p>
        </div>

        <div v-else-if="!isPaid && effectiveStatus === 'menunggu_pembayaran'" class="online-helper-alert">
          <p>
            Pembayaran Anda belum terkonfirmasi. Silakan selesaikan pembayaran melalui channel yang Anda pilih agar pesanan segera diproses.
          </p>
          <div class="online-action-row" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.75rem;">
            <button
              v-if="order.payment"
              class="button button-primary button-reopen-pay"
              type="button"
              @click="showPaymentModal = true"
            >
              💳 Buka Instruksi Pembayaran Midtrans
            </button>
            <button
              class="button button-outline"
              type="button"
              :disabled="isSyncing"
              style="padding: 0.6rem 1rem; font-size: 0.85rem;"
              @click="handleSyncLiveStatus"
            >
              {{ isSyncing ? 'Memeriksa Midtrans...' : '🔄 Cek Status Midtrans' }}
            </button>
          </div>
          <p v-if="syncFeedback" class="sync-feedback-text" style="font-size: 0.8rem; margin-top: 0.5rem; color: #166534; font-weight: 600;">
            {{ syncFeedback }}
          </p>
        </div>

        <div v-else-if="isPaid" class="paid-helper-alert">
          <p>✓ Pembayaran telah diterima dan terverifikasi. Pesanan sedang dilanjutkan sesuai status terkini.</p>
        </div>
      </section>

      <!-- Rincian Produk Pesanan -->
      <section class="section-box items-list-box">
        <h3>Daftar Produk Pesanan</h3>
        <div class="items-table">
          <div v-for="item in order.items" :key="item.id" class="item-row">
            <div class="item-details">
              <strong>{{ item.product_name || item.produk?.name || 'Produk RiasKu' }}</strong>
              <small v-if="item.variant_name || item.varian?.name">
                Varian: {{ item.variant_name || item.varian?.name }}
              </small>
              <span v-if="item.is_preorder" class="po-order-tag" style="width: fit-content; margin-left: 0; margin-top: 2px;">
                Pre-Order{{ item.preorder_estimate_days ? ` · Est. ${item.preorder_estimate_days} hari` : '' }}
              </span>
              <small class="item-qty-tag">Jumlah: {{ item.quantity }} pcs</small>
            </div>
            <div class="item-price-calc">
              <span class="unit-price">{{ formatRupiah(item.unit_price ?? 0) }} × {{ item.quantity }}</span>
              <strong class="subtotal-price">{{ formatRupiah(item.subtotal ?? item.line_total ?? ((item.unit_price ?? 0) * item.quantity)) }}</strong>
            </div>
          </div>
        </div>

        <div class="total-breakdown">
          <div class="breakdown-row">
            <span>Subtotal Produk</span>
            <span>{{ formatRupiah(totalProductAmount) }}</span>
          </div>
          <div class="breakdown-row">
            <span>Biaya Pengiriman</span>
            <span v-if="shippingCost > 0">{{ formatRupiah(shippingCost) }}</span>
            <span v-else class="free-text">GRATIS (Promo / COD Surabaya)</span>
          </div>
          <div class="breakdown-row total-grand">
            <strong>Total Tagihan</strong>
            <strong class="total-amount">{{ formatRupiah(totalAmount) }}</strong>
          </div>
        </div>
      </section>

      <!-- Riwayat Log Status -->
      <section v-if="order.status_logs?.length" class="section-box">
        <h3>Riwayat Aktivitas Pesanan</h3>
        <div class="track-logs-list">
          <div v-for="log in order.status_logs" :key="log.id" class="track-log-item">
            <span class="badge-status" :class="`status-${log.to_status}`">
              {{ formatStatusLabel(log.to_status) }}
            </span>
            <div class="track-log-copy">
              <strong>{{ log.note }}</strong>
              <small>{{ formatDateTime(log.created_at) }}<template v-if="log.tracking_number"> · Resi: {{ log.tracking_number }}</template></small>
            </div>
          </div>
        </div>
      </section>

      <div class="back-link-wrap">
        <RouterLink to="/products" class="text-link">← Belanja Produk Lainnya</RouterLink>
      </div>
    </div>

    <PaymentModal
      v-if="showPaymentModal && order && order.payment"
      :order="order"
      :payment="order.payment"
      @close="showPaymentModal = false"
      @payment-success="handlePaymentSuccess"
    />
  </section>
</template>

<style scoped>
.order-track-page {
  max-width: 780px;
  margin: 0 auto;
  padding: 2rem 1rem 4rem;
}

.order-track-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.order-track-header .page-subtitle {
  margin-left: auto;
  margin-right: auto;
}

.my-orders-card {
  margin-top: 1.25rem;
  padding: 1.25rem 1.5rem;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

.my-orders-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.9rem;
}

.my-orders-header h2 {
  font-size: 1rem;
  margin: 0;
  color: #1f2937;
}

.my-orders-header small {
  font-size: 0.78rem;
  color: #6b7280;
}

.my-orders-filter {
  max-width: 210px;
  font-size: 0.85rem;
}

.my-orders-empty {
  padding: 0.85rem 0;
  color: #6b7280;
  font-size: 0.85rem;
}

.my-orders-list {
  display: grid;
  gap: 0.6rem;
  max-height: 240px;
  overflow-y: auto;
}

.my-order-pill {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  text-align: left;
  padding: 0.7rem 0.9rem;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafafa;
  cursor: pointer;
  transition: all 0.15s ease;
}

.my-order-pill:hover,
.my-order-pill.active {
  border-color: #8b5cf6;
  background: #f5f3ff;
}

.my-order-pill strong {
  display: block;
  font-size: 0.875rem;
  color: #111827;
}

.my-order-pill small {
  display: block;
  font-size: 0.75rem;
  color: #6b7280;
}

.track-form {
  width: 100%;
  max-width: 100%;
  margin: 1.25rem auto 0;
  padding: 1.5rem;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

.search-input-group label {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.input-with-button {
  display: flex;
  gap: 0.75rem;
}

.input-with-button input {
  flex: 1;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: #6b7280;
  gap: 0.75rem;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #f3f4f6;
  border-top-color: #8b5cf6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.order-detail-card {
  margin-top: 1.75rem;
  padding: 1.75rem;
  border-radius: 14px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

.order-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid #f3f4f6;
}

.order-code-badge {
  display: inline-block;
  background: #f5f3ff;
  color: #7c3aed;
  font-weight: 700;
  font-size: 1.125rem;
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
  border: 1px solid #ddd6fe;
}

.po-order-tag {
  display: inline-block;
  margin-left: 0.5rem;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  font-size: 0.72rem;
  font-weight: 700;
}

.order-date-text {
  display: block;
  font-size: 0.8125rem;
  color: #6b7280;
  margin-top: 0.35rem;
}

.badge-status {
  display: inline-block;
  padding: 0.35rem 0.85rem;
  border-radius: 9999px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.status-menunggu_pembayaran { background: #fef3c7; color: #b45309; }
.status-diproses { background: #ede9fe; color: #6d28d9; }
.status-dikirim { background: #e0f2fe; color: #0369a1; }
.status-selesai { background: #dcfce7; color: #15803d; }
.status-dibatalkan { background: #fee2e2; color: #b91c1c; }

.cancelled-order-banner {
  margin: 1.25rem 0;
  padding: 1rem 1.25rem;
  border-radius: 10px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.cancelled-order-banner p {
  margin: 0.35rem 0 0;
  font-size: 0.85rem;
}

.complete-order-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin: 1rem 0;
  padding: 1rem 1.25rem;
  border-radius: 10px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
  font-size: 0.85rem;
}

.complete-order-box p {
  margin: 0;
}

.resi-copy-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-top: 0.35rem;
}

.resi-copy-row code {
  background: #f3f4f6;
  padding: 0.2rem 0.5rem;
  border-radius: 5px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #1f2937;
}

.copy-resi-btn {
  border: 1px solid #ddd6fe;
  background: #f5f3ff;
  color: #6d28d9;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  cursor: pointer;
}

/* Timeline */
.tracking-timeline-box {
  margin: 1.5rem 0;
  padding: 1.25rem 0.5rem;
  background: #fafafa;
  border-radius: 10px;
}

.timeline-steps {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  width: 75px;
}

.step-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e5e7eb;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.75rem;
  margin-bottom: 0.35rem;
}

.step-item.completed .step-dot {
  background: #16a34a;
  color: #ffffff;
}

.step-item.current .step-dot {
  background: #7c3aed;
  color: #ffffff;
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
}

.step-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: #6b7280;
}

.step-item.completed .step-label,
.step-item.current .step-label {
  color: #111827;
}

.step-divider {
  flex: 1;
  height: 3px;
  background: #e5e7eb;
  margin: 0 4px 18px;
}

.step-divider.active {
  background: #16a34a;
}

.section-box {
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid #f3f4f6;
}

.section-box h3 {
  font-size: 0.95rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.75rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
}

.info-label {
  display: block;
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 0.2rem;
}

.badge-sub-highlight {
  display: inline-block;
  font-size: 0.7rem;
  background: #fef3c7;
  color: #92400e;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-weight: 600;
  margin-top: 0.25rem;
}

.payment-box-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.method-sub {
  font-size: 0.8125rem;
  color: #4b5563;
  margin: 0.25rem 0 0;
}

.payment-pill {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.35rem 0.85rem;
  border-radius: 6px;
}
.pill-paid { background: #dcfce7; color: #166534; }
.pill-pending { background: #fef9c3; color: #854d0e; }
.pill-cancelled { background: #fee2e2; color: #991b1b; }

.cod-helper-alert, .paid-helper-alert, .online-helper-alert {
  margin-top: 0.85rem;
  padding: 0.85rem 1rem;
  border-radius: 8px;
  font-size: 0.8125rem;
  line-height: 1.45;
}

.cod-helper-alert {
  background: #fefce8;
  border: 1px solid #fef08a;
  color: #854d0e;
}

.paid-helper-alert {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.online-helper-alert {
  background: #fdf4ff;
  border: 1px solid #f5d0fe;
  color: #86198f;
}

.button-reopen-pay {
  margin-top: 0.75rem;
  font-size: 0.8125rem;
  padding: 0.5rem 1rem;
}

.items-table {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.65rem 0;
  border-bottom: 1px dashed #e5e7eb;
}

.item-details {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.item-details strong {
  font-size: 0.875rem;
  color: #111827;
}

.item-details small {
  font-size: 0.75rem;
  color: #6b7280;
}

.item-qty-tag {
  color: #8b5cf6 !important;
  font-weight: 600;
}

.item-price-calc {
  text-align: right;
}

.unit-price {
  display: block;
  font-size: 0.75rem;
  color: #6b7280;
}

.subtotal-price {
  display: block;
  font-size: 0.875rem;
  color: #111827;
}

.total-breakdown {
  margin-top: 1rem;
  padding-top: 0.75rem;
  border-top: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.8125rem;
  color: #4b5563;
}

.free-text {
  color: #16a34a;
  font-weight: 600;
}

.total-grand {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid #e5e7eb;
  font-size: 1rem;
  color: #111827;
}

.total-amount {
  color: #7c3aed;
  font-size: 1.125rem;
}

.track-logs-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.track-log-item {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 0.65rem 0.85rem;
  background: #fafafa;
  border-radius: 8px;
  border: 1px solid #f3f4f6;
}

.track-log-copy strong {
  display: block;
  font-size: 0.82rem;
  color: #1f2937;
}

.track-log-copy small {
  display: block;
  font-size: 0.74rem;
  color: #6b7280;
  margin-top: 2px;
}

.back-link-wrap {
  margin-top: 1.5rem;
  text-align: center;
}

@media (max-width: 640px) {
  .my-orders-header,
  .complete-order-box,
  .input-with-button {
    flex-direction: column;
    align-items: stretch;
  }
  .my-orders-filter {
    max-width: none;
  }
  .step-divider {
    display: none;
  }
  .info-grid {
    grid-template-columns: 1fr !important;
    gap: 1rem;
  }
}
</style>
