<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PaymentModal from '@/components/PaymentModal.vue'
import { getOrderStatus } from '@/services/checkoutService'
import { formatRupiah } from '@/utils/currency'

const route = useRoute()
const router = useRouter()

const orderCodeInput = ref('')
const isLoading = ref(false)
const order = ref(null)
const notFound = ref(false)
const errorMessage = ref('')
const showPaymentModal = ref(false)

const isCod = computed(() => {
  return order.value?.payment_method === 'cod' || order.value?.payment_channel === 'cash'
})

const isPaid = computed(() => {
  return order.value?.payment_status === 'dibayar' || order.value?.payment_status === 'settlement' || order.value?.payment_status === 'lunas'
})

const orderTimelineStep = computed(() => {
  if (!order.value) return 1
  const s = order.value.order_status
  if (s === 'menunggu_pembayaran') return isPaid.value ? 2 : 1
  if (s === 'diproses') return 3
  if (s === 'dikirim') return 4
  if (s === 'selesai') return 5
  if (s === 'dibatalkan') return 0
  return 2
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

async function searchOrder(code) {
  const queryCode = (code || orderCodeInput.value || '').trim()
  if (!queryCode) return

  isLoading.value = true
  notFound.value = false
  errorMessage.value = ''
  order.value = null

  try {
    const res = await getOrderStatus(queryCode)
    const data = res.data?.data || res.data || res.order || res
    if (data && (data.order_code || data.code)) {
      order.value = {
        ...data,
        order_code: data.order_code || data.code,
        items: data.items || [],
        payment: data.payment || data.latest_pembayaran || null,
      }
      orderCodeInput.value = order.value.order_code

      // Sinkronkan query parameter di URL tanpa reload
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
}

onMounted(() => {
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
</script>

<template>
  <section class="page-container inner-page narrow-page order-track-page">
    <span class="eyebrow-badge">PELACAKAN PESANAN</span>
    <h1 class="page-title">Cek Status Pesanan</h1>
    <p class="page-subtitle">Lacak status proses, pengiriman, dan rincian pembayaran pesanan RiasKu Anda.</p>

    <!-- Form Pencarian Kode Pesanan -->
    <form class="surface-card track-form" @submit.prevent="searchOrder()">
      <div class="search-input-group">
        <label for="orderCode">Kode Pesanan</label>
        <div class="input-with-button">
          <input
            id="orderCode"
            v-model="orderCodeInput"
            class="form-control"
            placeholder="Contoh: RSK-20261117-0001"
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
      <!-- Header Pesanan -->
      <header class="order-card-header">
        <div>
          <span class="order-code-badge">{{ order.order_code }}</span>
          <span class="order-date-text">Dibuat pada: {{ formatDateTime(order.created_at) }}</span>
        </div>
        <div class="status-badge-wrap">
          <span class="badge-status" :class="`status-${order.order_status}`">
            {{ order.order_status.replace('_', ' ').toUpperCase() }}
          </span>
        </div>
      </header>

      <!-- Timeline Progres Pesanan -->
      <div class="tracking-timeline-box">
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

      <!-- Info Pengiriman & Penerima -->
      <section class="section-box delivery-info-box">
        <h3>Informasi Pengiriman</h3>
        <div class="info-grid">
          <div>
            <span class="info-label">Penerima</span>
            <strong>{{ order.customer_name }}</strong>
            <small>{{ order.customer_phone }}</small>
          </div>
          <div>
            <span class="info-label">Kota Tujuan</span>
            <strong>{{ order.shipping_city }}</strong>
            <span v-if="order.shipping_city === 'Kota Surabaya'" class="badge-sub-highlight">📍 Wilayah COD Toko Surabaya</span>
          </div>
          <div>
            <span class="info-label">Ekspedisi / Pengantar</span>
            <strong>{{ order.shipping_courier || (isCod ? 'Kurir Toko RiasKu Surabaya' : 'Reguler Courier') }}</strong>
          </div>
        </div>
      </section>

      <!-- Status Pembayaran (PPL-19 Midtrans & PPL-20 COD) -->
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
              'pill-pending': !isPaid && order.payment_status !== 'batal',
              'pill-cancelled': order.payment_status === 'batal'
            }"
          >
            {{ isPaid ? '✓ LUNAS / SETTLEMENT' : (isCod ? 'MENUNGGU KURIR (BAYAR DI TEMPAT)' : 'MENUNGGU PEMBAYARAN') }}
          </span>
        </div>

        <!-- Jika COD dan belum lunas -->
        <div v-if="isCod && !isPaid" class="cod-helper-alert">
          <p>
            📍 <strong>Instruksi COD Surabaya:</strong> Pesanan Anda akan dikirim langsung oleh kurir toko RiasKu.
            Harap siapkan uang tunai pas sebesar <strong>{{ formatRupiah(order.total_amount) }}</strong> saat serah terima paket di lokasi.
          </p>
        </div>

        <!-- Jika Online dan belum lunas -->
        <div v-else-if="!isPaid" class="online-helper-alert">
          <p>
            Pembayaran Anda belum terkonfirmasi. Silakan selesaikan pembayaran melalui channel yang Anda pilih agar pesanan segera diproses.
          </p>
          <button
            v-if="order.payment"
            class="button button-primary button-reopen-pay"
            type="button"
            @click="showPaymentModal = true"
          >
            💳 Buka Instruksi / Simulasikan Pembayaran
          </button>
        </div>

        <!-- Jika sudah lunas -->
        <div v-else class="paid-helper-alert">
          <p>✓ Pembayaran telah diterima dan terverifikasi secara otomatis. Tim RiasKu sedang memproses produk pesanan Anda.</p>
        </div>
      </section>

      <!-- Rincian Produk Pesanan -->
      <section class="section-box items-list-box">
        <h3>Daftar Produk Pesanan</h3>
        <div class="items-table">
          <div v-for="item in order.items" :key="item.id" class="item-row">
            <div class="item-details">
              <strong>{{ item.product_name || item.produk?.nama_produk || 'Produk RiasKu' }}</strong>
              <small v-if="item.variant_name || item.varian?.nama_varian">
                Varian: {{ item.variant_name || item.varian?.nama_varian }}
              </small>
              <small class="item-qty-tag">Jumlah: {{ item.quantity }} pcs</small>
            </div>
            <div class="item-price-calc">
              <span class="unit-price">{{ formatRupiah(item.price) }} × {{ item.quantity }}</span>
              <strong class="subtotal-price">{{ formatRupiah(item.subtotal || item.price * item.quantity) }}</strong>
            </div>
          </div>
        </div>

        <div class="total-breakdown">
          <div class="breakdown-row">
            <span>Biaya Pengiriman</span>
            <span class="free-text">GRATIS (Promo RiasKu)</span>
          </div>
          <div class="breakdown-row total-grand">
            <strong>Total Tagihan</strong>
            <strong class="total-amount">{{ formatRupiah(order.total_amount) }}</strong>
          </div>
        </div>
      </section>

      <div class="back-link-wrap">
        <RouterLink to="/products" class="text-link">← Belanja Produk Lainnya</RouterLink>
      </div>
    </div>

    <!-- Modal Instruksi Pembayaran (Re-usable PPL-19 & PPL-20) -->
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
  max-width: 760px;
  margin: 0 auto;
  padding: 2rem 1rem 4rem;
}

.track-form {
  margin-top: 1.5rem;
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
  margin-top: 2rem;
  padding: 1.75rem;
  border-radius: 14px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

.order-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
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
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.status-menunggu_pembayaran { background: #fef3c7; color: #b45309; }
.status-diproses { background: #ede9fe; color: #6d28d9; }
.status-dikirim { background: #e0f2fe; color: #0369a1; }
.status-selesai { background: #dcfce7; color: #15803d; }
.status-dibatalkan { background: #fee2e2; color: #b91c1c; }

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
  transition: all 0.2s ease;
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

/* Sections */
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

/* Payment box */
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

/* Items List */
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

.back-link-wrap {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
