<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { simulatePaymentSuccess, syncPaymentStatus } from '@/services/paymentService'
import { formatRupiah } from '@/utils/currency'

const props = defineProps({
  order: { type: Object, required: true },
  payment: { type: Object, required: true },
})

const emit = defineEmits(['close', 'payment-success'])
const router = useRouter()

const isSimulating = ref(false)
const isCheckingStatus = ref(false)
const copied = ref(false)
const isPaid = ref(
  props.payment?.transaction_status === 'settlement' ||
  props.order?.payment_status === 'dibayar' ||
  props.order?.payment_status === 'lunas'
)
const simulationMessage = ref('')
const syncMessage = ref('')
let pollTimer = null

const isCod = computed(() => props.order?.payment_method === 'cod' || props.payment?.payment_type === 'cod_cash')
const isQris = computed(() => props.payment?.payment_type === 'qris')
const isVa = computed(() => props.payment?.payment_type === 'bank_transfer' || props.payment?.payment_type === 'echannel')
const isEwallet = computed(() => ['gopay', 'shopeepay', 'dana'].includes(props.payment?.payment_type) || Boolean(props.payment?.deeplink_url))

const bankName = computed(() => {
  const bank = props.payment?.bank || ''
  return bank ? bank.toUpperCase() : 'VIRTUAL ACCOUNT'
})

const qrImageSrc = computed(() => {
  if (props.payment?.qr_url) return props.payment.qr_url
  if (props.payment?.qr_string) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(props.payment.qr_string)}`
  }
  return null
})

function copyToClipboard(text) {
  if (!text) return
  navigator.clipboard.writeText(String(text))
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

/**
 * PPL-19: Live Sync status ke backend & Midtrans Sandbox.
 */
async function handleCheckStatus(manual = false) {
  if (isPaid.value || isCod.value) return

  if (manual) isCheckingStatus.value = true

  try {
    const orderCode = props.order.order_code || props.order.id
    const result = await syncPaymentStatus(orderCode)

    const paymentStatus = result?.order?.payment_status || result?.payment?.transaction_status
    if (paymentStatus === 'dibayar' || paymentStatus === 'settlement' || result?.synced === true) {
      isPaid.value = true
      stopPolling()
      syncMessage.value = '✓ Pembayaran berhasil terverifikasi lunas secara real-time!'
      emit('payment-success', result)
    } else if (manual) {
      syncMessage.value = `Status terkini: ${result?.midtrans_status || result?.payment?.transaction_status || 'Menunggu Pembayaran'}`
    }
  } catch (err) {
    if (manual) {
      syncMessage.value = err.response?.data?.message || 'Gagal memeriksa status ke server.'
    }
  } finally {
    if (manual) isCheckingStatus.value = false
  }
}

/**
 * Auto-polling setiap 5 detik selama belum lunas.
 */
function startPolling() {
  if (isPaid.value || isCod.value) return
  stopPolling()
  pollTimer = setInterval(() => {
    handleCheckStatus(false)
  }, 5000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

async function handleSimulatePay() {
  if (!props.payment?.id) return
  isSimulating.value = true
  try {
    const result = await simulatePaymentSuccess(props.payment.id)
    isPaid.value = true
    stopPolling()
    simulationMessage.value = result.message || 'Pembayaran berhasil dikonfirmasi!'
    emit('payment-success', result)
  } catch (err) {
    simulationMessage.value = err.response?.data?.message || err.message || 'Gagal melakukan simulasi pembayaran.'
  } finally {
    isSimulating.value = false
  }
}

function goToTracking() {
  stopPolling()
  emit('close')
  router.push({ name: 'order-track', query: { code: props.order.order_code } })
}

onMounted(() => {
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="modal-backdrop" role="dialog" aria-modal="true" @click.self="emit('close')">
    <div class="modal-card payment-modal">
      <header class="modal-header">
        <div>
          <span class="eyebrow-badge">{{ isCod ? 'KONFIRMASI COD SURABAYA' : 'INSTRUKSI PEMBAYARAN MIDTRANS' }}</span>
          <h2 class="modal-title">Pesanan {{ order.order_code }}</h2>
        </div>
        <button class="modal-close-btn" type="button" aria-label="Tutup modal" @click="emit('close')">×</button>
      </header>

      <div class="modal-body">
        <!-- Status Banner Berhasil -->
        <div v-if="isPaid" class="payment-success-card">
          <div class="success-icon" aria-hidden="true">✓</div>
          <h3>Pembayaran Berhasil!</h3>
          <p>Terima kasih, pembayaran pesanan Anda telah diverifikasi lunas secara real-time dan sedang segera dipersiapkan oleh tim RiasKu.</p>
          <button class="button button-primary" type="button" @click="goToTracking">Lihat Status Pesanan →</button>
        </div>

        <div v-else class="payment-instructions-wrap">
          <!-- Total Tagihan -->
          <div class="payment-amount-box">
            <span>Total Tagihan Pembayaran</span>
            <strong>{{ formatRupiah(order.total_amount) }}</strong>
            <small v-if="!isCod">Batas waktu bayar: 24 Jam dari pemesanan</small>
            <small v-else>Bayar tunai kepada kurir saat barang tiba di Kota Surabaya</small>
          </div>

          <!-- PPL-20: Tampilan Khusus COD Surabaya -->
          <div v-if="isCod" class="cod-instruction-card">
            <div class="cod-badge-row">
              <span class="badge-cod-highlight">📍 COD KHUSUS KOTA SURABAYA</span>
            </div>
            <p><strong>Penerima:</strong> {{ order.customer_name }} ({{ order.customer_phone }})</p>
            <p><strong>Alamat Pengiriman:</strong> {{ order.shipping_address }}, {{ order.shipping_city }}</p>
            <div class="notice-info">
              ✓ Siapkan uang pas <strong>{{ formatRupiah(order.total_amount) }}</strong> saat kurir toko RiasKu tiba.<br/>
              ✓ Buka dan periksa paket Anda bersama kurir sebelum melakukan serah terima tunai.
            </div>
          </div>

          <!-- PPL-19: Tampilan QRIS Midtrans -->
          <div v-else-if="isQris" class="qris-card">
            <div class="qris-header">
              <span class="qris-logo">QRIS</span>
              <span class="qris-badge-midtrans">Midtrans Core API</span>
            </div>
            <div class="qris-visual">
              <img v-if="qrImageSrc" :src="qrImageSrc" alt="QR Code QRIS RiasKu" class="qris-image" />
              <div v-else class="qr-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="120" height="120"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>QRIS Dinamis</span>
              </div>
            </div>
            <p class="qris-note">Mendukung GoPay, OVO, DANA, ShopeePay, BCA Mobile, Livin' Mandiri, dan semua e-wallet / mobile banking berlogo QRIS.</p>
          </div>

          <!-- PPL-19: Tampilan Virtual Account Midtrans -->
          <div v-else-if="isVa" class="va-card">
            <div class="va-badge">{{ bankName }}</div>
            <div class="va-number-box">
              <span class="va-label">Nomor Virtual Account</span>
              <div class="va-copy-row">
                <span class="va-code">{{ payment.va_number || payment.bill_key }}</span>
                <button class="button button-soft button-copy" type="button" @click="copyToClipboard(payment.va_number || payment.bill_key)">
                  {{ copied ? 'Tersalin ✓' : 'Salin' }}
                </button>
              </div>
              <div v-if="payment.biller_code" class="biller-info">
                <span>Kode Perusahaan Mandiri: <strong>{{ payment.biller_code }}</strong> (RiasKu)</span>
              </div>
            </div>
          </div>

          <!-- PPL-19: Tampilan E-Wallet (GoPay / ShopeePay) -->
          <div v-if="payment.deeplink_url" class="ewallet-action-box">
            <a :href="payment.deeplink_url" target="_blank" rel="noopener noreferrer" class="button button-primary button-deeplink">
              Buka Pembayaran E-Wallet / Midtrans Simulator ↗
            </a>
          </div>

          <!-- Petunjuk Langkah Pembayaran -->
          <div v-if="payment.instructions?.length" class="instructions-accordion">
            <h4>Panduan Cara Pembayaran:</h4>
            <ol>
              <li v-for="(step, idx) in payment.instructions" :key="idx">{{ step }}</li>
            </ol>
          </div>

          <!-- Polling & Live Status Check (Real-time Midtrans Sandbox) -->
          <div v-if="!isCod" class="realtime-status-box">
            <div class="status-poll-indicator">
              <span class="poll-dot"></span>
              <small>Auto-sync aktif: memantau pelunasan dari Midtrans setiap 5 detik...</small>
            </div>
            <button
              class="button button-outline button-check-status"
              type="button"
              :disabled="isCheckingStatus"
              @click="handleCheckStatus(true)"
            >
              {{ isCheckingStatus ? 'Memeriksa ke Midtrans...' : '🔄 Cek Status Pembayaran Sekarang' }}
            </button>
            <p v-if="syncMessage" class="notice-message sync-notice">{{ syncMessage }}</p>
          </div>

          <!-- Link Bantuan Midtrans Simulator (Sandbox Mode) -->
          <div v-if="!isCod" class="sandbox-helper-box">
            <span class="helper-label">🛠️ PENGUJIAN MIDTRANS SANDBOX</span>
            <p>Untuk menguji pelunasan nomor VA atau QRIS ini via Midtrans Simulator resmi:</p>
            <a
              href="https://simulator.sandbox.midtrans.com/"
              target="_blank"
              rel="noopener noreferrer"
              class="simulator-external-link"
            >
              Buka Midtrans Payment Simulator ↗
            </a>
            <small>Salin nomor VA di atas, lalu tempel di simulator bank Midtrans untuk membayar.</small>
          </div>

          <!-- Simulator Pembayaran Lokal Cepat (Fallback Praktikum) -->
          <div class="simulator-box">
            <span class="simulator-title">⚡ FITUR SIMULATOR INSTAN (OFFLINE/LOCAL TEST)</span>
            <p>Klik tombol di bawah untuk melunasi langsung secara lokal tanpa membuka simulator Midtrans:</p>
            <button
              class="button button-primary button-simulate"
              type="button"
              :disabled="isSimulating"
              @click="handleSimulatePay"
            >
              {{ isSimulating ? 'Memproses Simulasi...' : '✓ Simulasikan Pembayaran Sukses Sekarang' }}
            </button>
            <p v-if="simulationMessage" class="notice-message">{{ simulationMessage }}</p>
          </div>
        </div>
      </div>

      <footer class="modal-footer">
        <button class="button button-outline" type="button" @click="goToTracking">Cek Pelacakan Pesanan</button>
        <button class="button button-secondary" type="button" @click="emit('close')">Tutup</button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(18, 14, 20, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 1rem;
}
.payment-modal {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}
.modal-header {
  padding: 1.25rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid #f0e6ec;
}
.modal-title {
  font-size: 1.25rem;
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
.modal-body {
  padding: 1.25rem 1.5rem;
  overflow-y: auto;
}
.payment-amount-box {
  background: #fdf5f8;
  border: 1px solid #f3d4e2;
  border-radius: 12px;
  padding: 1rem;
  text-align: center;
  margin-bottom: 1.25rem;
}
.payment-amount-box span {
  display: block;
  font-size: 0.85rem;
  color: #7d5a6c;
}
.payment-amount-box strong {
  display: block;
  font-size: 1.75rem;
  color: #8c2a5c;
  margin: 0.25rem 0;
}
.payment-amount-box small {
  color: #a46d88;
  font-size: 0.8rem;
}
.cod-instruction-card {
  background: #fcfbf9;
  border: 1px dashed #d99a26;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1rem;
}
.badge-cod-highlight {
  display: inline-block;
  background: #fef3d6;
  color: #92540c;
  font-weight: 700;
  font-size: 0.75rem;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  margin-bottom: 0.5rem;
}
.notice-info {
  background: #f6fcf8;
  border: 1px solid #c7ecd2;
  border-radius: 8px;
  padding: 0.75rem;
  font-size: 0.85rem;
  color: #1e6338;
  line-height: 1.5;
  margin-top: 0.5rem;
}
.qris-card {
  text-align: center;
  background: #fcfcfd;
  border: 1px solid #e8e3e7;
  border-radius: 12px;
  padding: 1.25rem;
  margin-bottom: 1rem;
}
.qris-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
  font-weight: 600;
  color: #60525a;
  margin-bottom: 0.75rem;
}
.qris-logo {
  font-size: 1.1rem;
  font-weight: 800;
  color: #c91b28;
}
.qris-badge-midtrans {
  background: #ede9fe;
  color: #6d28d9;
  font-size: 0.7rem;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}
.qris-visual {
  display: flex;
  justify-content: center;
  margin: 0.5rem 0;
}
.qris-image {
  max-width: 220px;
  width: 100%;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  background: #ffffff;
  padding: 0.5rem;
}
.qris-note {
  font-size: 0.75rem;
  color: #7a6e76;
  margin-top: 0.75rem;
  line-height: 1.4;
}
.va-card {
  background: #fcfbfd;
  border: 1px solid #e9e1ea;
  border-radius: 12px;
  padding: 1.25rem;
  margin-bottom: 1rem;
}
.va-badge {
  display: inline-block;
  background: #8c2a5c;
  color: #ffffff;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  margin-bottom: 0.75rem;
}
.va-copy-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #ffffff;
  border: 1px solid #d9cad3;
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  margin-top: 0.35rem;
}
.va-code {
  font-family: monospace;
  font-size: 1.25rem;
  font-weight: 700;
  color: #3b1e2e;
  letter-spacing: 1px;
}
.button-copy {
  padding: 0.3rem 0.8rem;
  font-size: 0.8rem;
}
.biller-info {
  margin-top: 0.5rem;
  font-size: 0.85rem;
  color: #695861;
}
.ewallet-action-box {
  margin-bottom: 1rem;
}
.button-deeplink {
  display: block;
  text-align: center;
  text-decoration: none;
  background: #0081a7;
  color: #ffffff;
  padding: 0.65rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
}
.instructions-accordion {
  margin: 1rem 0;
  background: #f9f7f8;
  padding: 0.85rem 1rem;
  border-radius: 8px;
}
.instructions-accordion h4 {
  font-size: 0.85rem;
  margin: 0 0 0.5rem;
  color: #4b303d;
}
.instructions-accordion ol {
  margin: 0;
  padding-left: 1.2rem;
  font-size: 0.8rem;
  color: #5c4753;
  line-height: 1.5;
}
.realtime-status-box {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 0.85rem;
  margin-bottom: 1rem;
}
.status-poll-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}
.poll-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #16a34a;
  animation: pulse 1.5s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}
.button-check-status {
  width: 100%;
  padding: 0.5rem;
  font-size: 0.85rem;
  border-color: #86efac;
  color: #166534;
}
.sync-notice {
  margin-top: 0.5rem;
  font-size: 0.8rem;
  color: #15803d;
  font-weight: 600;
}
.sandbox-helper-box {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  padding: 0.85rem;
  margin-bottom: 1rem;
}
.helper-label {
  display: block;
  font-size: 0.72rem;
  font-weight: 800;
  color: #475569;
  letter-spacing: 0.5px;
  margin-bottom: 0.25rem;
}
.sandbox-helper-box p {
  font-size: 0.78rem;
  color: #475569;
  margin: 0 0 0.5rem;
}
.simulator-external-link {
  display: inline-block;
  background: #3b82f6;
  color: #ffffff;
  padding: 0.4rem 0.8rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  text-decoration: none;
  margin-bottom: 0.35rem;
}
.sandbox-helper-box small {
  display: block;
  font-size: 0.72rem;
  color: #64748b;
}
.simulator-box {
  background: #fff8eb;
  border: 1px solid #ffd480;
  border-radius: 10px;
  padding: 0.9rem;
  margin-top: 0.75rem;
}
.simulator-title {
  display: block;
  font-size: 0.75rem;
  font-weight: 800;
  color: #92540c;
  letter-spacing: 0.5px;
  margin-bottom: 0.35rem;
}
.simulator-box p {
  font-size: 0.75rem;
  color: #78480e;
  margin: 0 0 0.6rem;
}
.button-simulate {
  width: 100%;
  background: #92540c;
  color: #ffffff;
  border: none;
  padding: 0.6rem;
  font-size: 0.85rem;
  font-weight: 700;
  border-radius: 8px;
  cursor: pointer;
}
.button-simulate:hover:not(:disabled) {
  background: #754209;
}
.payment-success-card {
  text-align: center;
  padding: 2rem 1rem;
}
.success-icon {
  width: 56px;
  height: 56px;
  line-height: 56px;
  border-radius: 50%;
  background: #e6f7ec;
  color: #1f8a42;
  font-size: 2rem;
  font-weight: bold;
  margin: 0 auto 1rem;
}
.payment-success-card h3 {
  color: #1f8a42;
  font-size: 1.4rem;
  margin: 0 0 0.5rem;
}
.payment-success-card p {
  color: #5a4b53;
  margin-bottom: 1.5rem;
}
.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #f0e6ec;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  background: #faf8fa;
}
</style>
