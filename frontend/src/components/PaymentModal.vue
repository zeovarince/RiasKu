<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { simulatePaymentSuccess, syncPaymentStatus } from '@/services/paymentService'
import { formatRupiah } from '@/utils/currency'

const props = defineProps({
  order: { type: Object, required: true },
  payment: { type: Object, required: true },
})

const emit = defineEmits(['close', 'payment-success'])
const router = useRouter()

const dialogRef = ref(null)
const isSimulating = ref(false)
const isCheckingStatus = ref(false)
const copyStatus = ref('')
const copyFailed = ref(false)
const localQrDataUrl = ref('')
const remoteQrFailed = ref(false)
const isPaid = ref(
  props.payment?.transaction_status === 'settlement' ||
  props.order?.payment_status === 'dibayar' ||
  props.order?.payment_status === 'lunas'
)
const simulationMessage = ref('')
const syncMessage = ref('')
let pollTimer = null
let pollDelayMs = 8000
let isPollingActive = false
let copyTimer = null
let lastCopyAnnouncement = ''
let isPollInFlight = false
let pressedOutsideDialog = false

const isCod = computed(() => props.order?.payment_method === 'cod' || props.payment?.payment_type === 'cod_cash')
const isVa = computed(() => props.payment?.payment_type === 'bank_transfer' || props.payment?.payment_type === 'echannel')
const ewalletChannel = computed(() => {
  const type = String(props.payment?.payment_type || '').toLowerCase()
  const payloadChannel = String(props.payment?.payload_response?.channel || '').toLowerCase()
  const orderChannel = String(props.order?.payment_channel || '').toLowerCase()
  for (const candidate of [type, payloadChannel, orderChannel]) {
    if (['gopay', 'shopeepay', 'dana'].includes(candidate)) return candidate
  }
  return ''
})
const isEwallet = computed(() => Boolean(ewalletChannel.value) || Boolean(props.payment?.deeplink_url))
const isQris = computed(() => props.payment?.payment_type === 'qris' && !isEwallet.value)
const ewalletDisplayLabel = computed(() => {
  if (ewalletChannel.value === 'gopay') return 'GOPAY'
  if (ewalletChannel.value === 'shopeepay') return 'SHOPEEPAY'
  if (ewalletChannel.value === 'dana') return 'DANA'
  return 'E-WALLET'
})

const paymentMode = computed(() => {
  const mode = props.payment?.midtrans_mode || props.payment?.payload_response?.mode || ''
  if (mode) return String(mode).toLowerCase()
  if (String(props.payment?.transaction_id || '').startsWith('MOCK-')) return 'local_simulation'
  return import.meta.env.DEV ? 'live_sandbox' : 'live_production'
})

const isLocalSimulation = computed(() => paymentMode.value === 'local_simulation')
const isSandboxMode = computed(() => paymentMode.value === 'live_sandbox')
const canSimulate = computed(() => Boolean(props.payment?.can_simulate) || isLocalSimulation.value)

const bankName = computed(() => {
  const bank = props.payment?.bank || ''
  return bank ? bank.toUpperCase() : 'VIRTUAL ACCOUNT'
})

const qrImageSrc = computed(() => {
  if (localQrDataUrl.value) return localQrDataUrl.value
  if (!remoteQrFailed.value && props.payment?.qr_url) return props.payment.qr_url
  return null
})

const midtransSimulatorInfo = computed(() => {
  const bank = String(props.payment?.bank || '').toLowerCase()
  const type = String(props.payment?.payment_type || '').toLowerCase()
  if (type === 'qris') {
    return {
      url: 'https://simulator.sandbox.midtrans.com/qris/index',
      label: 'Buka Midtrans QRIS Simulator ↗',
      hint: 'Salin URL QR Code di bawah, lalu tempel pada halaman Midtrans QRIS Simulator.',
    }
  }
  if (type === 'bank_transfer' && ['bca', 'bni', 'bri'].includes(bank)) {
    return {
      url: `https://simulator.sandbox.midtrans.com/${bank}/va/index`,
      label: `Buka Midtrans ${bank.toUpperCase()} VA Simulator ↗`,
      hint: `Salin nomor Virtual Account ${bank.toUpperCase()} di atas, lalu tempel di simulator Midtrans.`,
    }
  }
  if (type === 'echannel') {
    return {
      url: 'https://simulator.sandbox.midtrans.com/mandiri/bill/index',
      label: 'Buka Midtrans Mandiri Bill Simulator ↗',
      hint: 'Masukkan Kode Perusahaan & Bill Key di atas pada simulator Mandiri Bill Midtrans.',
    }
  }
  if (type === 'gopay') {
    return {
      url: 'https://simulator.sandbox.midtrans.com/gopay/ui/index',
      label: 'Buka Midtrans GoPay Simulator ↗',
      hint: 'Salin URL QR Code GoPay di bawah atau klik tombol pembayaran GoPay.',
    }
  }
  return {
    url: 'https://simulator.sandbox.midtrans.com/',
    label: 'Buka Midtrans Payment Simulator ↗',
    hint: 'Gunakan simulator resmi Midtrans untuk menguji pelunasan transaksi sandbox.',
  }
})

async function renderLocalQr(qrString) {
  remoteQrFailed.value = false
  if (!qrString) {
    localQrDataUrl.value = ''
    return
  }
  try {
    const mod = await import('qrcode')
    const toDataURL = mod.toDataURL || mod.default?.toDataURL
    if (typeof toDataURL === 'function') {
      localQrDataUrl.value = await toDataURL(String(qrString), {
        width: 240,
        margin: 2,
        errorCorrectionLevel: 'M',
        color: { dark: '#1f1620', light: '#ffffff' },
      })
    }
  } catch {
    localQrDataUrl.value = ''
  }
}

watch(
  () => props.payment?.qr_string,
  (qrString) => {
    renderLocalQr(qrString)
  },
  { immediate: true }
)

watch(
  () => props.payment?.id,
  () => {
    isPaid.value = (
      props.payment?.transaction_status === 'settlement' ||
      props.order?.payment_status === 'dibayar' ||
      props.order?.payment_status === 'lunas'
    )
    simulationMessage.value = ''
    syncMessage.value = ''
    remoteQrFailed.value = false
    pollDelayMs = 8000
  }
)

function legacyCopy(text) {
  const previousFocus = document.activeElement
  const field = Object.assign(document.createElement('textarea'), { value: text })
  field.setAttribute('readonly', '')
  field.style.cssText = 'position:fixed;inset-block-start:0;opacity:0'
  document.body.append(field)
  field.select()
  try {
    return document.execCommand('copy')
  } finally {
    field.remove()
    previousFocus?.focus?.({ preventScroll: true })
  }
}

function announceCopy(ok, label = 'Nomor VA') {
  if (copyTimer) clearTimeout(copyTimer)
  copyFailed.value = !ok
  let message = ok ? `${label} berhasil disalin` : `Gagal menyalin ${label}`
  if (message === lastCopyAnnouncement) message += '\u00A0'
  lastCopyAnnouncement = message
  copyStatus.value = ''
  requestAnimationFrame(() => {
    copyStatus.value = message
  })
  copyTimer = setTimeout(() => {
    copyStatus.value = ''
    copyFailed.value = false
  }, 2200)
}

async function copyToClipboard(text, label = 'Nomor VA') {
  const value = String(text || '').trim()
  if (!value) return
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value)
    } else if (!legacyCopy(value)) {
      throw new Error('Copy failed')
    }
    announceCopy(true, label)
  } catch {
    if (legacyCopy(value)) {
      announceCopy(true, label)
    } else {
      announceCopy(false, label)
    }
  }
}

function closeModal() {
  stopPolling()
  if (dialogRef.value?.open) {
    dialogRef.value.close()
  }
  emit('close')
}

function handleDialogClose() {
  stopPolling()
  emit('close')
}

function isPointerOutsideDialog(event, dialog) {
  const rect = dialog.getBoundingClientRect()
  return event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom
}

function handlePointerDown(event) {
  if ('closedBy' in HTMLDialogElement.prototype || !dialogRef.value) return
  pressedOutsideDialog = isPointerOutsideDialog(event, dialogRef.value)
}

function handlePointerUp(event) {
  if ('closedBy' in HTMLDialogElement.prototype || !dialogRef.value) return
  if (pressedOutsideDialog && isPointerOutsideDialog(event, dialogRef.value)) {
    closeModal()
  }
  pressedOutsideDialog = false
}

/**
 * PPL-19: Live Sync status ke backend & Midtrans.
 */
async function handleCheckStatus(manual = false) {
  if (isPaid.value || isCod.value) return
  if (!manual && (document.hidden || isPollInFlight)) return

  isPollInFlight = true
  if (manual) isCheckingStatus.value = true

  try {
    const orderCode = props.order.order_code || props.order.id
    const result = await syncPaymentStatus(orderCode)
    pollDelayMs = 8000

    const orderPayStatus = String(result?.order?.payment_status || '').toLowerCase()
    const trxStatus = String(result?.midtrans_status || result?.payment?.transaction_status || '').toLowerCase()

    if (['dibayar', 'lunas', 'settlement', 'capture'].includes(orderPayStatus) || ['settlement', 'capture'].includes(trxStatus)) {
      isPaid.value = true
      stopPolling()
      syncMessage.value = '✓ Pembayaran berhasil terverifikasi lunas secara real-time!'
      emit('payment-success', result)
    } else if (['expire', 'cancel', 'deny', 'kadaluarsa', 'dibatalkan'].includes(trxStatus) || ['kadaluarsa', 'dibatalkan'].includes(orderPayStatus)) {
      stopPolling()
      syncMessage.value = `⚠️ Transaksi ${trxStatus || orderPayStatus}. Stok pesanan telah dikembalikan.`
    } else if (manual) {
      syncMessage.value = result?.message || `Status terkini: ${trxStatus || orderPayStatus || 'Menunggu Pembayaran'}`
    }
  } catch (err) {
    pollDelayMs = Math.min(20000, Math.round(pollDelayMs * 1.5))
    if (manual) {
      syncMessage.value = err.response?.data?.message || 'Gagal memeriksa status ke server.'
    }
  } finally {
    isPollInFlight = false
    if (manual) isCheckingStatus.value = false
  }
}

/**
 * Auto-polling adaptif (mulai 8 detik, backoff hingga 20 detik bila Midtrans lambat)
 * agar tidak membebani server lokal saat modal terbuka.
 */
function scheduleNextPoll() {
  if (!isPollingActive || isPaid.value || isCod.value || isLocalSimulation.value) return
  pollTimer = setTimeout(async () => {
    await handleCheckStatus(false)
    scheduleNextPoll()
  }, pollDelayMs)
}

function startPolling() {
  if (isPaid.value || isCod.value || isLocalSimulation.value) return
  stopPolling()
  pollDelayMs = 8000
  isPollingActive = true
  scheduleNextPoll()
}

function stopPolling() {
  isPollingActive = false
  if (pollTimer) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
}

async function handleSimulatePay() {
  if (!props.payment?.id) return
  isSimulating.value = true
  simulationMessage.value = ''
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
  if (dialogRef.value?.open) {
    dialogRef.value.close()
  }
  emit('close')
  router.push({ name: 'order-track', query: { code: props.order.order_code } })
}

onMounted(() => {
  if (dialogRef.value && !dialogRef.value.open) {
    dialogRef.value.showModal()
  }
  startPolling()
})

onUnmounted(() => {
  stopPolling()
  if (copyTimer) clearTimeout(copyTimer)
  if (dialogRef.value?.open) {
    dialogRef.value.close()
  }
})
</script>

<template>
  <dialog
    ref="dialogRef"
    class="payment-dialog"
    closedby="any"
    aria-labelledby="payment-modal-title"
    @close="handleDialogClose"
    @pointerdown="handlePointerDown"
    @pointerup="handlePointerUp"
  >
    <div class="payment-modal">
      <header class="modal-header">
        <div>
          <span class="eyebrow-badge">{{ isCod ? 'KONFIRMASI COD SURABAYA' : 'INSTRUKSI PEMBAYARAN MIDTRANS' }}</span>
          <h1 id="payment-modal-title" class="modal-title">Pesanan {{ order.order_code }}</h1>
        </div>
        <button class="modal-close-btn" type="button" aria-label="Tutup modal pembayaran" @click="closeModal">×</button>
      </header>

      <div class="modal-body">
        <!-- Status Banner Berhasil -->
        <div v-if="isPaid" class="payment-success-card">
          <div class="success-icon" aria-hidden="true">✓</div>
          <h2>Pembayaran Berhasil!</h2>
          <p>Terima kasih, pembayaran pesanan Anda telah diverifikasi lunas secara real-time dan sedang segera dipersiapkan oleh tim RiasKu.</p>
          <button class="button button-primary" type="button" autofocus @click="goToTracking">Lihat Status Pesanan →</button>
        </div>

        <div v-else class="payment-instructions-wrap">
          <!-- Total Tagihan -->
          <div class="payment-amount-box">
            <span>Total Tagihan Pembayaran</span>
            <strong>{{ formatRupiah(order.total_amount ?? order.total ?? 0) }}</strong>
            <small v-if="!isCod">Batas waktu bayar: 24 Jam dari pemesanan · Keranjang aman hingga lunas</small>
            <small v-else>Bayar tunai kepada kurir saat barang tiba di Kota Surabaya</small>
          </div>

          <!-- PPL-20: Tampilan Khusus COD Surabaya -->
          <div v-if="isCod" class="cod-instruction-card">
            <div class="cod-badge-row">
              <span class="badge-cod-highlight">📍 COD KHUSUS KOTA SURABAYA</span>
            </div>
            <p><strong>Penerima:</strong> {{ order.customer_name || order.recipient_name }} ({{ order.customer_phone || order.recipient_phone }})</p>
            <p><strong>Alamat Pengiriman:</strong> {{ order.shipping_address }}, {{ order.shipping_city || order.destination_city }}</p>
            <p v-if="order.shipping_courier || order.courier"><strong>Pengiriman:</strong> {{ order.shipping_courier || `${order.courier} ${order.courier_service || ''}`.trim() }}</p>
            <div class="notice-info">
              ✓ Siapkan uang pas <strong>{{ formatRupiah(order.total_amount ?? order.total ?? 0) }}</strong> saat kurir toko RiasKu tiba.<br/>
              ✓ Buka dan periksa paket Anda bersama kurir sebelum melakukan serah terima tunai.
            </div>
          </div>

          <!-- PPL-19: Tampilan QRIS & E-Wallet QR (GoPay / ShopeePay) Midtrans -->
          <div v-else-if="isQris || (isEwallet && (qrImageSrc || payment.qr_string || payment.qr_url))" class="qris-card">
            <div class="qris-header">
              <span class="qris-logo">{{ isQris ? 'QRIS' : ewalletDisplayLabel }}</span>
              <span class="qris-badge-midtrans">Midtrans Core API</span>
            </div>
            <div class="qris-visual">
              <img
                v-if="qrImageSrc"
                :src="qrImageSrc"
                :alt="`QR Code ${isQris ? 'QRIS' : ewalletDisplayLabel} RiasKu`"
                class="qris-image"
                width="220"
                height="220"
                @error="remoteQrFailed = true"
              />
              <div v-else class="qr-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="96" height="96" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>{{ isQris ? 'Menyiapkan QRIS Dinamis...' : `Menyiapkan QR ${ewalletDisplayLabel}...` }}</span>
              </div>
            </div>
            <p class="qris-note">
              {{ isQris
                ? "Mendukung GoPay, OVO, DANA, ShopeePay, BCA Mobile, Livin' Mandiri, dan semua e-wallet / mobile banking berlogo QRIS."
                : 'Scan QR Code ini menggunakan aplikasi e-wallet Anda atau klik tombol pembayaran langsung di bawah.'
              }}
            </p>
          </div>

          <!-- PPL-19: Tampilan Virtual Account Midtrans -->
          <div v-else-if="isVa" class="va-card">
            <div class="va-badge">{{ bankName }}</div>
            <div class="va-number-box">
              <span class="va-label">{{ payment.biller_code ? 'Nomor Pelanggan / Bill Key' : 'Nomor Virtual Account' }}</span>
              <div class="va-copy-row">
                <span id="va-code-value" class="va-code">{{ payment.va_number || payment.bill_key }}</span>
                <span class="copy-control">
                  <button
                    class="button button-soft button-copy"
                    type="button"
                    @click="copyToClipboard(payment.va_number || payment.bill_key, payment.biller_code ? 'Bill Key' : 'Nomor VA')"
                  >
                    Salin
                  </button>
                  <span
                    class="copy-live-status"
                    :class="{ visible: Boolean(copyStatus), failed: copyFailed }"
                    role="status"
                  >{{ copyStatus }}</span>
                </span>
              </div>
              <div v-if="payment.biller_code" class="biller-info">
                <span>Kode Perusahaan Mandiri: <strong>{{ payment.biller_code }}</strong> (RiasKu)</span>
              </div>
            </div>
          </div>

          <!-- PPL-19: Tampilan E-Wallet Deeplink (GoPay / ShopeePay) -->
          <div v-if="payment.deeplink_url" class="ewallet-action-box">
            <a :href="payment.deeplink_url" target="_blank" rel="noopener noreferrer" class="button button-primary button-deeplink">
              Buka Pembayaran E-Wallet ↗
            </a>
          </div>

          <!-- Simulator Pembayaran Instan (Tampil di Sandbox/Lokal jika diizinkan, selalu disembunyikan di Production) -->
          <div v-if="!isCod && canSimulate" class="simulator-box">
            <span class="simulator-title">
              {{ isSandboxMode ? '⚡ SIMULATOR MIDTRANS SANDBOX (1-KLIK SUKSES)' : '⚡ FITUR SIMULATOR INSTAN (MODE LOKAL)' }}
            </span>
            <p>
              {{ isSandboxMode
                ? 'Klik tombol di bawah untuk langsung menyimulasikan pembayaran berhasil di mode Sandbox tanpa menunggu webhook:'
                : 'Klik tombol di bawah untuk melunasi langsung secara lokal tanpa membuka simulator Midtrans:'
              }}
            </p>
            <button
              class="button button-primary button-simulate"
              type="button"
              :disabled="isSimulating"
              @click="handleSimulatePay"
            >
              {{ isSimulating ? 'Memproses Simulasi...' : '✓ Simulasikan Pembayaran Sukses Sekarang' }}
            </button>
            <p v-if="simulationMessage" class="notice-message" role="status">{{ simulationMessage }}</p>
          </div>

          <!-- Petunjuk Langkah Pembayaran -->
          <div v-if="payment.instructions?.length" class="instructions-accordion">
            <h2>Panduan Cara Pembayaran:</h2>
            <ol>
              <li v-for="(step, idx) in payment.instructions" :key="idx">{{ step }}</li>
            </ol>
          </div>

          <!-- Polling & Live Status Check (Khusus mode Midtrans Sandbox / Production) -->
          <div v-if="!isCod && !isLocalSimulation" class="realtime-status-box">
            <div class="status-poll-indicator">
              <span class="poll-dot" aria-hidden="true"></span>
              <small>Auto-sync aktif: memantau pelunasan dari Midtrans secara berkala...</small>
            </div>
            <button
              class="button button-outline button-check-status"
              type="button"
              :disabled="isCheckingStatus"
              @click="handleCheckStatus(true)"
            >
              {{ isCheckingStatus ? 'Memeriksa ke Midtrans...' : '🔄 Cek Status Pembayaran Sekarang' }}
            </button>
            <p v-if="syncMessage" class="notice-message sync-notice" role="status">{{ syncMessage }}</p>
          </div>

          <!-- Link Bantuan Midtrans Simulator Resmi (Hanya tampil pada mode Sandbox, tidak di Production) -->
          <div v-if="!isCod && isSandboxMode" class="sandbox-helper-box">
            <span class="helper-label">🛠️ MIDTRANS OFFICIAL SANDBOX SIMULATOR</span>
            <p>{{ midtransSimulatorInfo.hint }}</p>
            <div class="sandbox-actions-row">
              <a
                :href="midtransSimulatorInfo.url"
                target="_blank"
                rel="noopener noreferrer"
                class="simulator-external-link"
              >
                {{ midtransSimulatorInfo.label }}
              </a>
              <span v-if="payment.qr_url" class="copy-control">
                <button
                  class="button button-soft button-copy"
                  type="button"
                  @click="copyToClipboard(payment.qr_url, 'URL QR Code')"
                >
                  Salin URL QR
                </button>
                <span
                  v-if="!isVa"
                  class="copy-live-status"
                  :class="{ visible: Boolean(copyStatus), failed: copyFailed }"
                  role="status"
                >{{ copyStatus }}</span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <footer class="modal-footer">
        <button class="button button-outline" type="button" @click="goToTracking">Cek Pelacakan Pesanan</button>
        <button class="button button-secondary" type="button" @click="closeModal">Tutup</button>
      </footer>
    </div>
  </dialog>
</template>

<style scoped>
.payment-dialog {
  border: none;
  padding: 0;
  background: transparent;
  width: min(520px, calc(100vw - 2rem));
  max-width: 520px;
  max-height: 90vh;
  margin: auto;
  overflow: visible;
}
.payment-dialog:modal {
  overscroll-behavior: contain;
}
.payment-dialog::backdrop {
  background: rgba(18, 14, 20, 0.75);
  backdrop-filter: blur(4px);
  overflow: hidden;
  overscroll-behavior: contain;
}
.payment-modal {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
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
  min-inline-size: 32px;
  min-block-size: 32px;
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
  width: 220px;
  height: 220px;
  max-width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: contain;
  display: block;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  background: #ffffff;
  padding: 0.5rem;
}
.qr-placeholder {
  width: 220px;
  height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #8c717e;
  background: #ffffff;
  border: 1px dashed #d9cad3;
  border-radius: 8px;
  font-size: 0.78rem;
}
.sandbox-actions-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
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
  gap: 0.75rem;
  background: #ffffff;
  border: 1px solid #d9cad3;
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  margin-top: 0.35rem;
}
.va-code {
  font-family: monospace;
  font-size: 1.2rem;
  font-weight: 700;
  color: #3b1e2e;
  letter-spacing: 1px;
  word-break: break-all;
}
.copy-control {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  flex-shrink: 0;
}
.button-copy {
  min-inline-size: 56px;
  min-block-size: 32px;
  padding: 0.3rem 0.8rem;
  font-size: 0.8rem;
}
.copy-live-status {
  position: absolute;
  bottom: calc(100% + 6px);
  right: 0;
  white-space: nowrap;
  font-size: 0.72rem;
  font-weight: 600;
  color: #ffffff;
  background: #166534;
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease;
}
.copy-live-status.visible {
  opacity: 1;
}
.copy-live-status.failed {
  background: #b91c1c;
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
.instructions-accordion h2 {
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
@media (prefers-reduced-motion: reduce) {
  .poll-dot {
    animation: none;
  }
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
.payment-success-card h2 {
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
