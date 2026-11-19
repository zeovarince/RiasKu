<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { createOrder } from '@/services/checkoutService'
import { formatRupiah } from '@/utils/currency'
import PaymentModal from '@/components/PaymentModal.vue'

const cart = useCartStore()
const auth = useAuthStore()
const router = useRouter()

const isSubmitting = ref(false)
const errorMessage = ref('')
const activeOrder = ref(null)
const activePayment = ref(null)
const showPaymentModal = ref(false)

const errors = ref({
  name: '',
  phone: '',
  address: '',
  city: '',
  district: '',
  postalCode: '',
})

const method = ref('cod')
const onlineChannel = ref('qris')

const surabayaDistricts = [
  'Gubeng', 'Wonokromo', 'Rungkut', 'Tegalsari', 'Genteng', 'Sukolilo',
  'Kenjeran', 'Mulyorejo', 'Sambikerep', 'Tandes', 'Sawahan', 'Krembangan',
  'Tambaksari', 'Dukuh Pakis', 'Jambangan', 'Gayungan', 'Wiyung', 'Lakarsantri'
]

const buyer = ref({
  name: auth.user?.username || '',
  phone: '',
  address: '',
  city: 'Kota Surabaya',
  district: 'Gubeng',
  postalCode: '60281',
  notes: '',
})

// PPL-20: Deteksi apakah kota yang dipilih adalah Surabaya
const isSurabaya = computed(() => {
  return String(buyer.value.city || '').toLowerCase().includes('surabaya')
})

// Jika user mengubah kota ke luar Surabaya, otomatis beralih dari COD ke Online
watch(() => buyer.value.city, (newCity) => {
  if (!String(newCity || '').toLowerCase().includes('surabaya') && method.value === 'cod') {
    method.value = 'online'
  }
})

function validateForm() {
  let valid = true
  errors.value = {
    name: '',
    phone: '',
    address: '',
    city: '',
    district: '',
    postalCode: '',
  }

  // 1. Validasi Nama
  const trimmedName = (buyer.value.name || '').trim()
  if (!trimmedName) {
    errors.value.name = 'Nama lengkap penerima wajib diisi.'
    valid = false
  } else if (trimmedName.length < 3) {
    errors.value.name = 'Nama penerima minimal 3 karakter.'
    valid = false
  }

  // 2. Validasi Nomor WhatsApp
  const cleanPhone = (buyer.value.phone || '').trim().replace(/[\s-]/g, '')
  if (!cleanPhone) {
    errors.value.phone = 'Nomor WhatsApp aktif wajib diisi.'
    valid = false
  } else if (!/^(\+62|62|0)8[0-9]{8,12}$/.test(cleanPhone)) {
    errors.value.phone = 'Nomor WhatsApp tidak valid (contoh: 081234567890, 10-14 digit angka).'
    valid = false
  }

  // 3. Validasi Alamat
  const trimmedAddress = (buyer.value.address || '').trim()
  if (!trimmedAddress) {
    errors.value.address = 'Alamat lengkap pengiriman wajib diisi.'
    valid = false
  } else if (trimmedAddress.length < 8) {
    errors.value.address = 'Alamat terlalu singkat (sertakan nama jalan, RT/RW, dan nomor rumah).'
    valid = false
  }

  // 4. Validasi Kota
  if (!buyer.value.city) {
    errors.value.city = 'Kota tujuan pengiriman wajib dipilih.'
    valid = false
  }

  // 5. Validasi Kecamatan
  const trimmedDistrict = (buyer.value.district || '').trim()
  if (!trimmedDistrict) {
    errors.value.district = 'Kecamatan tujuan pengiriman wajib diisi.'
    valid = false
  }

  // 6. Validasi Kode Pos (opsional tapi jika diisi wajib 5 digit)
  const trimmedPostal = (buyer.value.postalCode || '').trim()
  if (trimmedPostal && !/^\d{5}$/.test(trimmedPostal)) {
    errors.value.postalCode = 'Kode pos harus terdiri dari 5 digit angka.'
    valid = false
  }

  return valid
}

async function submitCheckout() {
  if (cart.items.length === 0) {
    errorMessage.value = 'Keranjang belanja Anda masih kosong.'
    return
  }

  // PPL-20: Validasi frontend COD Surabaya
  if (method.value === 'cod' && !isSurabaya.value) {
    errorMessage.value = 'Metode pembayaran COD hanya dapat digunakan untuk pengiriman wilayah Kota Surabaya.'
    return
  }

  // Validasi seluruh input form
  if (!validateForm()) {
    errorMessage.value = 'Mohon periksa kembali formulir Anda. Masih terdapat isian yang belum sesuai.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  const payload = {
    customer_name: buyer.value.name.trim(),
    customer_phone: buyer.value.phone.trim(),
    shipping_address: buyer.value.address.trim(),
    shipping_city: buyer.value.city,
    shipping_district: buyer.value.district,
    shipping_postal_code: buyer.value.postalCode,
    payment_method: method.value,
    payment_channel: method.value === 'cod' ? 'cash' : onlineChannel.value,
    notes: buyer.value.notes ? buyer.value.notes.trim() : null,
    items: cart.items.map((item) => ({
      produk_id: item.id,
      produk_varian_id: item.variantId || null,
      quantity: item.quantity,
    })),
  }

  try {
    const result = await createOrder(payload)
    activeOrder.value = result.order || result
    activePayment.value = result.payment || null

    // Bersihkan keranjang lokal setelah order berhasil dibuat
    cart.clearCart()

    // Buka modal instruksi pembayaran
    showPaymentModal.value = true
  } catch (err) {
    const backendMsg = err.response?.data?.message
    const backendErrors = err.response?.data?.errors
    if (backendErrors && typeof backendErrors === 'object') {
      if (backendErrors.customer_name) errors.value.name = backendErrors.customer_name[0]
      if (backendErrors.customer_phone) errors.value.phone = backendErrors.customer_phone[0]
      if (backendErrors.shipping_address) errors.value.address = backendErrors.shipping_address[0]
      if (backendErrors.shipping_city) errors.value.city = backendErrors.shipping_city[0]
      if (backendErrors.shipping_district) errors.value.district = backendErrors.shipping_district[0]
      if (backendErrors.shipping_postal_code) errors.value.postalCode = backendErrors.shipping_postal_code[0]

      const firstKey = Object.keys(backendErrors)[0]
      errorMessage.value = backendErrors[firstKey][0] || backendMsg || 'Gagal memproses checkout.'
    } else {
      errorMessage.value = backendMsg || err.message || 'Terjadi kesalahan saat memproses pesanan.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section class="page-container inner-page">
    <span class="eyebrow-badge">PENYELESAIAN PESANAN</span>
    <h1 class="page-title">Checkout &amp; Pembayaran</h1>
    <p class="page-subtitle">Lengkapi rincian pengiriman dan pilih metode pembayaran resmi RiasKu.</p>

    <div v-if="cart.items.length === 0 && !showPaymentModal" class="empty-state">
      <h2>Keranjang Anda masih kosong</h2>
      <p>Pilih produk terlebih dahulu di katalog kami sebelum melakukan checkout.</p>
      <RouterLink to="/products" class="button button-primary">Buka Katalog Produk</RouterLink>
    </div>

    <div v-else class="checkout-layout">
      <form class="surface-card checkout-form" @submit.prevent="submitCheckout">
        <h2>1. Informasi Penerima &amp; Alamat</h2>

        <div class="form-row">
          <label class="form-group flex-1">
            <span>Nama Lengkap Penerima <span class="req">*</span></span>
            <input
              v-model="buyer.name"
              class="form-control"
              :class="{ 'input-error': errors.name }"
              placeholder="Contoh: Nadia Safitri"
              @input="errors.name = ''"
            />
            <span v-if="errors.name" class="field-error-text">{{ errors.name }}</span>
          </label>

          <label class="form-group flex-1">
            <span>Nomor WhatsApp Aktif <span class="req">*</span></span>
            <input
              v-model="buyer.phone"
              class="form-control"
              :class="{ 'input-error': errors.phone }"
              type="tel"
              placeholder="0813xxxxxxxx"
              @input="errors.phone = ''"
            />
            <span v-if="errors.phone" class="field-error-text">{{ errors.phone }}</span>
          </label>
        </div>

        <div class="form-row">
          <label class="form-group flex-1">
            <span>Kota Tujuan Pengiriman <span class="req">*</span></span>
            <select
              v-model="buyer.city"
              class="form-control"
              :class="{ 'input-error': errors.city }"
              @change="errors.city = ''"
            >
              <option value="Kota Surabaya">Kota Surabaya (Tersedia COD)</option>
              <option value="Kabupaten Sidoarjo">Kabupaten Sidoarjo (Online Only)</option>
              <option value="Kabupaten Gresik">Kabupaten Gresik (Online Only)</option>
              <option value="Kota Malang">Kota Malang (Online Only)</option>
              <option value="Kota Jakarta Selatan">DKI Jakarta (Online Only)</option>
              <option value="Kota Bandung">Kota Bandung (Online Only)</option>
            </select>
            <span v-if="errors.city" class="field-error-text">{{ errors.city }}</span>
          </label>

          <label v-if="isSurabaya" class="form-group flex-1">
            <span>Kecamatan di Surabaya <span class="req">*</span></span>
            <select
              v-model="buyer.district"
              class="form-control"
              :class="{ 'input-error': errors.district }"
              @change="errors.district = ''"
            >
              <option v-for="d in surabayaDistricts" :key="d" :value="d">{{ d }}</option>
            </select>
            <span v-if="errors.district" class="field-error-text">{{ errors.district }}</span>
          </label>

          <label v-else class="form-group flex-1">
            <span>Kecamatan Tujuan <span class="req">*</span></span>
            <input
              v-model="buyer.district"
              class="form-control"
              :class="{ 'input-error': errors.district }"
              placeholder="Nama Kecamatan"
              @input="errors.district = ''"
            />
            <span v-if="errors.district" class="field-error-text">{{ errors.district }}</span>
          </label>
        </div>

        <label class="form-group">
          <span>Alamat Lengkap (Jalan, RT/RW, No. Rumah) <span class="req">*</span></span>
          <textarea
            v-model="buyer.address"
            class="form-control"
            :class="{ 'input-error': errors.address }"
            rows="2"
            placeholder="Jl. Manyar Kertajaya No. 45..."
            @input="errors.address = ''"
          ></textarea>
          <span v-if="errors.address" class="field-error-text">{{ errors.address }}</span>
        </label>

        <div class="form-row">
          <label class="form-group flex-1">
            <span>Kode Pos</span>
            <input
              v-model="buyer.postalCode"
              class="form-control"
              :class="{ 'input-error': errors.postalCode }"
              placeholder="60281"
              @input="errors.postalCode = ''"
            />
            <span v-if="errors.postalCode" class="field-error-text">{{ errors.postalCode }}</span>
          </label>

          <label class="form-group flex-2">
            <span>Catatan untuk Penjual / Kurir (Opsional)</span>
            <input v-model="buyer.notes" class="form-control" placeholder="Contoh: Titipkan di pos satpam" />
          </label>
        </div>

        <h2>2. Pilihan Metode Pembayaran</h2>

        <!-- PPL-20: Opsi COD Surabaya -->
        <div class="payment-method-card" :class="{ 'disabled-method': !isSurabaya, active: method === 'cod' }">
          <label class="radio-label">
            <input v-model="method" type="radio" value="cod" :disabled="!isSurabaya" />
            <div class="method-text">
              <div class="method-title-row">
                <strong>Cash on Delivery (COD Surabaya)</strong>
                <span class="badge-tag" :class="isSurabaya ? 'badge-green' : 'badge-gray'">
                  {{ isSurabaya ? '✓ Wilayah Surabaya Didukung' : 'Khusus Kota Surabaya' }}
                </span>
              </div>
              <p class="method-desc">Bayar tunai kepada kurir toko RiasKu saat pesanan tiba di rumah Anda.</p>
              <p v-if="!isSurabaya" class="alert-warning">
                ⚠️ Pengiriman Anda ke <strong>{{ buyer.city }}</strong> berada di luar jangkauan kurir COD RiasKu. Silakan pilih <strong>Pembayaran Online</strong>.
              </p>
            </div>
          </label>
        </div>

        <!-- PPL-19: Opsi Pembayaran Online Midtrans -->
        <div class="payment-method-card" :class="{ active: method === 'online' }">
          <label class="radio-label">
            <input v-model="method" type="radio" value="online" />
            <div class="method-text">
              <div class="method-title-row">
                <strong>Pembayaran Online (Otomatis &amp; Real-time)</strong>
                <span class="badge-tag badge-purple">Midtrans Gateway</span>
              </div>
              <p class="method-desc">QRIS, Virtual Account Bank (BCA, BNI, BRI, Mandiri), dan E-Wallet.</p>
            </div>
          </label>

          <!-- Pilihan Channel Online jika Online aktif -->
          <div v-if="method === 'online'" class="online-channels-wrap">
            <span class="sub-label">Pilih Channel Pembayaran:</span>

            <div class="channel-grid">
              <label class="channel-option" :class="{ selected: onlineChannel === 'qris' }">
                <input v-model="onlineChannel" type="radio" value="qris" />
                <span class="channel-name">QRIS (Semua E-Wallet)</span>
                <small>GoPay, OVO, Dana, ShopeePay</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'bca_va' }">
                <input v-model="onlineChannel" type="radio" value="bca_va" />
                <span class="channel-name">BCA Virtual Account</span>
                <small>Verifikasi otomatis 24 jam</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'bni_va' }">
                <input v-model="onlineChannel" type="radio" value="bni_va" />
                <span class="channel-name">BNI Virtual Account</span>
                <small>ATM / Mobile Banking</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'bri_va' }">
                <input v-model="onlineChannel" type="radio" value="bri_va" />
                <span class="channel-name">BRI Virtual Account (BRIVA)</span>
                <small>BRImo &amp; ATM</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'mandiri_va' }">
                <input v-model="onlineChannel" type="radio" value="mandiri_va" />
                <span class="channel-name">Mandiri Bill Payment</span>
                <small>Livin' by Mandiri</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'gopay' }">
                <input v-model="onlineChannel" type="radio" value="gopay" />
                <span class="channel-name">GoPay / GoPay Later</span>
                <small>Direct Deeplink</small>
              </label>
            </div>
          </div>
        </div>

        <p v-if="errorMessage" class="notice-message notice-error">{{ errorMessage }}</p>

        <button class="button button-primary button-checkout-submit" type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Memproses Pesanan & Pembayaran...' : (method === 'cod' ? 'Konfirmasi Pesanan COD' : 'Lanjut ke Pembayaran Online →') }}
        </button>
      </form>

      <aside class="surface-card order-summary">
        <h2>Ringkasan Pesanan</h2>
        <p class="summary-count">{{ cart.totalItems }} item dalam keranjang</p>

        <div class="summary-items-list">
          <div v-for="item in cart.items" :key="item.cartKey || item.id" class="summary-line">
            <div>
              <span class="item-name">{{ item.name }}</span>
              <small v-if="item.variantName" class="item-sub">Varian: {{ item.variantName }}</small>
              <small class="item-qty">Qty: {{ item.quantity }} pcs</small>
            </div>
            <strong>{{ formatRupiah(item.price * item.quantity) }}</strong>
          </div>
        </div>

        <div class="summary-calculation">
          <div class="summary-sub-line">
            <span>Subtotal Produk</span>
            <span>{{ formatRupiah(cart.subtotal) }}</span>
          </div>
          <div class="summary-sub-line">
            <span>Biaya Pengiriman</span>
            <span class="free-shipping">GRATIS (Promo RiasKu)</span>
          </div>
          <div class="summary-total">
            <span>Total Pembayaran</span>
            <strong>{{ formatRupiah(cart.subtotal) }}</strong>
          </div>
        </div>

        <div class="security-note">
          <span>🔒 Transaksi Aman &amp; Terenkripsi</span>
          <small>Didukung oleh Midtrans Sandbox Gateway &amp; Kurir Resmi RiasKu Surabaya</small>
        </div>
      </aside>
    </div>

    <!-- Modal Instruksi Pembayaran (PPL-19 & PPL-20) -->
    <PaymentModal
      v-if="showPaymentModal && activeOrder && activePayment"
      :order="activeOrder"
      :payment="activePayment"
      @close="showPaymentModal = false"
    />
  </section>
</template>

<style scoped>
.form-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.85rem;
}
.flex-1 { flex: 1; }
.flex-2 { flex: 2; }
.req { color: #c91b28; }
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #4b303d;
}
.input-error {
  border-color: #dc2626 !important;
  background-color: #fef2f2 !important;
  box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15) !important;
}
.field-error-text {
  display: block;
  color: #dc2626;
  font-size: 0.75rem;
  font-weight: 600;
  margin-top: 0.2rem;
  line-height: 1.3;
}
.payment-method-card {
  border: 1px solid #e6dce2;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1rem;
  background: #ffffff;
  transition: all 0.2s ease;
}
.payment-method-card.active {
  border-color: #8c2a5c;
  background: #fdfafc;
  box-shadow: 0 4px 12px rgba(140, 42, 92, 0.08);
}
.payment-method-card.disabled-method {
  background: #fafafa;
  border-color: #eee;
  opacity: 0.85;
}
.radio-label {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  cursor: pointer;
}
.method-text {
  flex: 1;
}
.method-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.method-desc {
  font-size: 0.85rem;
  color: #695861;
  margin: 0.25rem 0 0;
}
.badge-tag {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}
.badge-green { background: #eaf7ee; color: #1f8a42; }
.badge-gray { background: #f0f0f0; color: #777; }
.badge-purple { background: #f5eef4; color: #8c2a5c; }
.alert-warning {
  background: #fff8eb;
  border: 1px solid #ffe3b3;
  color: #92540c;
  font-size: 0.8rem;
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  margin-top: 0.5rem;
}
.online-channels-wrap {
  margin-top: 1rem;
  padding-top: 0.85rem;
  border-top: 1px solid #f0e6ec;
}
.sub-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: #55444e;
  margin-bottom: 0.5rem;
}
.channel-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.6rem;
}
.channel-option {
  border: 1px solid #d9cad3;
  border-radius: 8px;
  padding: 0.6rem 0.75rem;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  cursor: pointer;
  position: relative;
}
.channel-option input {
  position: absolute;
  top: 0.6rem;
  right: 0.6rem;
}
.channel-option.selected {
  border-color: #8c2a5c;
  background: #fcf5f8;
}
.channel-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: #3b1e2e;
}
.channel-option small {
  font-size: 0.7rem;
  color: #7d6b75;
}
.button-checkout-submit {
  width: 100%;
  padding: 0.85rem;
  font-size: 1rem;
  margin-top: 1rem;
}
.notice-error {
  background: #fdf2f2;
  border: 1px solid #f8b4b4;
  color: #9b1c1c;
  padding: 0.75rem;
  border-radius: 8px;
  margin-top: 0.75rem;
}
.summary-items-list {
  max-height: 250px;
  overflow-y: auto;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #eee;
}
.item-name {
  display: block;
  font-weight: 600;
  font-size: 0.85rem;
  color: #3b1e2e;
}
.item-sub, .item-qty {
  display: block;
  font-size: 0.75rem;
  color: #7a6e76;
}
.summary-calculation {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.summary-sub-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #55444e;
}
.free-shipping {
  color: #1f8a42;
  font-weight: 700;
}
.security-note {
  margin-top: 1.25rem;
  background: #fbfafc;
  border: 1px dashed #d9cad3;
  border-radius: 8px;
  padding: 0.75rem;
  text-align: center;
}
.security-note span {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: #4b303d;
}
.security-note small {
  font-size: 0.7rem;
  color: #7d6b75;
}
</style>
