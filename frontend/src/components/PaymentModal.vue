<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { simulatePaymentSuccess } from '@/services/paymentService'
import { formatRupiah } from '@/utils/currency'

const props = defineProps({
  order: { type: Object, required: true },
  payment: { type: Object, required: true },
})

const emit = defineEmits(['close', 'payment-success'])
const router = useRouter()

const isSimulating = ref(false)
const copied = ref(false)
const isPaid = ref(props.payment?.transaction_status === 'settlement' || props.order?.payment_status === 'dibayar')
const simulationMessage = ref('')

const isCod = computed(() => props.order?.payment_method === 'cod' || props.payment?.payment_type === 'cod_cash')
const isQris = computed(() => props.payment?.payment_type === 'qris')
const isVa = computed(() => props.payment?.payment_type === 'bank_transfer' || props.payment?.payment_type === 'echannel')

const bankName = computed(() => {
  const bank = props.payment?.bank || ''
  return bank ? bank.toUpperCase() : 'VIRTUAL ACCOUNT'
})

function copyToClipboard(text) {
  if (!text) return
  navigator.clipboard.writeText(String(text))
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

async function handleSimulatePay() {
  if (!props.payment?.id) return
  isSimulating.value = true
  try {
    const result = await simulatePaymentSuccess(props.payment.id)
    isPaid.value = true
    simulationMessage.value = result.message || 'Pembayaran berhasil dikonfirmasi!'
    emit('payment-success', result)
  } catch (err) {
    simulationMessage.value = err.response?.data?.message || err.message || 'Gagal melakukan simulasi pembayaran.'
  } finally {
    isSimulating.value = false
  }
}

function goToTracking() {
  emit('close')
  router.push({ name: 'order-track', query: { code: props.order.order_code } })
}
</script>

<template>
  <div class="modal-backdrop" role="dialog" aria-modal="true" @click.self="emit('close')">
    <div class="modal-card payment-modal">
      <header class="modal-header">
        <div>
          <span class="eyebrow-badge">{{ isCod ? 'KONFIRMASI COD SURABAYA' : 'INSTRUKSI PEMBAYARAN' }}</span>
          <h2 class="modal-title">Pesanan {{ order.order_code }}</h2>
        </div>
        <button class="modal-close-btn" type="button" aria-label="Tutup modal" @click="emit('close')">×</button>
      </header>

      <div class="modal-body">
        <!-- Status Banner Berhasil -->
        <div v-if="isPaid" class="payment-success-card">
          <div class="success-icon" aria-hidden="true">✓</div>
          <h3>Pembayaran Berhasil!</h3>
          <p>Terima kasih, pesanan Anda telah lunas dan sedang segera dipersiapkan oleh tim RiasKu.</p>
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

          <!-- PPL-19: Tampilan QRIS -->
          <div v-else-if="isQris" class="qris-card">
            <div class="qris-header">
              <span class="qris-logo">QRIS</span>
              <span>National QR Standard</span>
            </div>
            <div class="qris-visual">
              <img v-if="payment.qr_url" :src="payment.qr_url" alt="QR Code QRIS RiasKu" class="qris-image" />
              <div v-else class="qr-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="120" height="120"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>{{ payment.qr_string ? 'Scan QRIS RiasKu' : 'QRIS Dinamis' }}</span>
              </div>
            </div>
            <p class="qris-note">Mendukung GoPay, OVO, DANA, ShopeePay, BCA Mobile, Livin' Mandiri, dan semua e-wallet / mobile banking berlogo QRIS.</p>
          </div>

          <!-- PPL-19: Tampilan Virtual Account -->
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

          <!-- Petunjuk Langkah Pembayaran -->
          <div v-if="payment.instructions?.length" class="instructions-accordion">
            <h4>Panduan Cara Pembayaran:</h4>
            <ol>
              <li v-for="(step, idx) in payment.instructions" :key="idx">{{ step }}</li>
            </ol>
          </div>

          <!-- Simulator Pembayaran untuk Pengujian Lokal / Penilaian Praktikum -->
          <div class="simulator-box">
            <span class="simulator-title">⚡ FITUR SIMULATOR PENGUJIAN LOKAL (TESTER)</span>
            <p>Untuk menguji alur pembayaran secara instan di localhost tanpa membuka mobile banking asli, klik tombol di bawah:</p>
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
.qris-visual {
  display: flex;
  justify-content: center;
  margin: 0.5rem 0;
}
.qris-image {
  max-width: 200px;
  width: 100%;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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
.simulator-box {
  background: #fff8eb;
  border: 1px solid #ffd480;
  border-radius: 10px;
  padding: 0.9rem;
  margin-top: 1rem;
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
