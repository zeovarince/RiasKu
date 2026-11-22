<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { createOrder } from '@/services/checkoutService'
import { getRates, getZones } from '@/services/shippingService'
import { formatRupiah } from '@/utils/currency'
import PaymentModal from '@/components/PaymentModal.vue'

const cart = useCartStore()
const auth = useAuthStore()

const NO_DETAIL = '__none__'

const isSubmitting = ref(false)
const loadingZones = ref(true)
const loadingRates = ref(false)
const errorMessage = ref('')
const shippingError = ref('')
const activeOrder = ref(null)
const activePayment = ref(null)
const showPaymentModal = ref(false)

const zones = ref([])
const quote = ref(null)
const selectedOptionKey = ref('')
let quoteRequestId = 0

const errors = ref({
  name: '',
  phone: '',
  address: '',
  province: '',
  city: '',
  district: '',
  postalCode: '',
  service: '',
})

const method = ref('cod')
const onlineChannel = ref('qris')

const buyer = ref({
  name: auth.user?.username || auth.user?.name || '',
  phone: '',
  address: '',
  province: '',
  city: '',
  district: '',
  postalCode: '',
  notes: '',
})

// PPL-18: Hierarki zona pengiriman dinamis dari backend (/api/shipping/zones)
const provinces = computed(() => [...new Set(zones.value.map((z) => z.province))])

const cities = computed(() => [...new Set(
  zones.value
    .filter((z) => z.province === buyer.value.province)
    .map((z) => z.city),
)])

const districts = computed(() => [...new Set(
  zones.value
    .filter((z) => z.province === buyer.value.province && z.city === buyer.value.city)
    .map((z) => z.district || NO_DETAIL),
)])

const postalCodes = computed(() => [...new Set(
  zones.value
    .filter((z) =>
      z.province === buyer.value.province
      && z.city === buyer.value.city
      && (z.district || NO_DETAIL) === buyer.value.district)
    .map((z) => z.postal_code || NO_DETAIL),
)])

const selectedZone = computed(() => zones.value.find((z) =>
  z.province === buyer.value.province
  && z.city === buyer.value.city
  && (z.district || NO_DETAIL) === buyer.value.district
  && (z.postal_code || NO_DETAIL) === buyer.value.postalCode,
) || null)

const selectedRate = computed(() => quote.value?.options?.find(
  (opt) => `${opt.courier}|${opt.service}` === selectedOptionKey.value,
) || null)

// PPL-20: Deteksi apakah zona tujuan mendukung COD Surabaya
const isSurabaya = computed(() => {
  if (selectedZone.value) {
    return Boolean(selectedZone.value.cod_available) || String(selectedZone.value.city || '').toLowerCase().includes('surabaya')
  }
  return String(buyer.value.city || '').toLowerCase().includes('surabaya')
})

const hasPreorderItems = computed(() => cart.items.some((item) => Boolean(item.is_preorder_item)))
const maxPreorderDays = computed(() => {
  const days = cart.items
    .filter((item) => Boolean(item.is_preorder_item))
    .map((item) => Number(item.preorder_estimate_days || 0))
    .filter((d) => d > 0)
  return days.length ? Math.max(...days) : null
})

const subtotalAmount = computed(() => {
  if (quote.value?.subtotal !== undefined && quote.value?.subtotal !== null) {
    return Number(quote.value.subtotal)
  }
  return Number(cart.subtotal || 0)
})

const shippingCostAmount = computed(() => {
  if (selectedRate.value) {
    return Number(selectedRate.value.cost || 0)
  }
  return 0
})

const grandTotalAmount = computed(() => subtotalAmount.value + shippingCostAmount.value)

const rateItemPayload = computed(() => cart.items
  .filter((item) => item.id && (item.variantId ?? item.variant_id))
  .map((item) => ({
    product_id: Number(item.id),
    variant_id: Number(item.variantId ?? item.variant_id),
    quantity: Number(item.quantity || 1),
  })))

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value) || 0)
}

function formatWeightKg(weightGrams) {
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(
    (Number(weightGrams) || 0) / 1000,
  )
}

function handleProvinceChange() {
  errors.value.province = ''
  buyer.value.city = ''
  buyer.value.district = ''
  buyer.value.postalCode = ''
  if (cities.value.length === 1) {
    buyer.value.city = cities.value[0]
    handleCityChange()
  }
}

function handleCityChange() {
  errors.value.city = ''
  buyer.value.district = ''
  buyer.value.postalCode = ''
  if (districts.value.length === 1) {
    buyer.value.district = districts.value[0]
    handleDistrictChange()
  }
}

function handleDistrictChange() {
  errors.value.district = ''
  buyer.value.postalCode = ''
  if (postalCodes.value.length === 1) {
    buyer.value.postalCode = postalCodes.value[0]
  }
}

// Jika zona bukan Surabaya, otomatis alihkan dari COD ke Pembayaran Online
watch(isSurabaya, (supported) => {
  if (!supported && method.value === 'cod') {
    method.value = 'online'
  }
})

// PPL-18: Hitung ulang ongkos kirim otomatis saat zona atau isi keranjang berubah
async function loadShippingQuote() {
  const requestId = ++quoteRequestId
  quote.value = null
  selectedOptionKey.value = ''
  shippingError.value = ''
  loadingRates.value = false

  if (!selectedZone.value || rateItemPayload.value.length === 0) return

  loadingRates.value = true
  try {
    const result = await getRates({
      destination_zone_id: selectedZone.value.id,
      items: rateItemPayload.value,
    })
    if (requestId !== quoteRequestId || String(result.destination?.id) !== String(selectedZone.value?.id)) {
      return
    }
    quote.value = result
    const firstOption = result.options?.[0]
    if (firstOption) {
      selectedOptionKey.value = `${firstOption.courier}|${firstOption.service}`
    }
  } catch (err) {
    if (requestId !== quoteRequestId) return
    shippingError.value = err.response?.data?.message || 'Ongkos kirim belum dapat dihitung untuk zona ini.'
  } finally {
    if (requestId === quoteRequestId) {
      loadingRates.value = false
    }
  }
}

watch(
  () => `${selectedZone.value?.id ?? ''}|${rateItemPayload.value.map((i) => `${i.product_id}:${i.variant_id}:${i.quantity}`).join(',')}`,
  () => {
    loadShippingQuote()
  },
)

onMounted(async () => {
  loadingZones.value = true
  try {
    await cart.hydrateCart()
    const list = await getZones()
    zones.value = Array.isArray(list) ? list : []

    // Pre-select default Surabaya (Gubeng) jika tersedia agar pengalaman demo COD langsung siap
    const defaultZone = zones.value.find(
      (z) => String(z.city).toLowerCase() === 'surabaya' && String(z.district || '').toLowerCase() === 'gubeng',
    ) || zones.value.find(
      (z) => String(z.city).toLowerCase().includes('surabaya'),
    ) || zones.value[0]

    if (defaultZone) {
      buyer.value.province = defaultZone.province
      buyer.value.city = defaultZone.city
      buyer.value.district = defaultZone.district || NO_DETAIL
      buyer.value.postalCode = defaultZone.postal_code || NO_DETAIL
    }
  } catch (err) {
    shippingError.value = err.response?.data?.message || 'Daftar zona pengiriman belum dapat dimuat.'
  } finally {
    loadingZones.value = false
  }
})

function validateField(field) {
  if (field === 'name') {
    const val = (buyer.value.name || '').trim()
    if (!val) errors.value.name = 'Nama lengkap penerima wajib diisi.'
    else if (val.length < 3) errors.value.name = 'Nama penerima minimal 3 karakter.'
    else errors.value.name = ''
  }

  if (field === 'phone') {
    const clean = (buyer.value.phone || '').trim().replace(/[\s-]/g, '')
    if (!clean) errors.value.phone = 'Nomor WhatsApp aktif wajib diisi.'
    else if (!/^(\+62|62|0)8[0-9]{8,12}$/.test(clean)) {
      errors.value.phone = 'Nomor WhatsApp tidak valid (contoh: 081234567890, 10–14 digit angka).'
    } else errors.value.phone = ''
  }

  if (field === 'address') {
    const val = (buyer.value.address || '').trim()
    if (!val) errors.value.address = 'Alamat lengkap pengiriman wajib diisi.'
    else if (val.length < 8) {
      errors.value.address = 'Alamat terlalu singkat (sertakan nama jalan, RT/RW, dan nomor rumah).'
    } else errors.value.address = ''
  }

  if (field === 'province') {
    errors.value.province = buyer.value.province ? '' : 'Provinsi tujuan wajib dipilih.'
  }

  if (field === 'city') {
    errors.value.city = buyer.value.city ? '' : 'Kota tujuan pengiriman wajib dipilih.'
  }

  if (field === 'district') {
    errors.value.district = buyer.value.district ? '' : 'Kecamatan tujuan wajib dipilih.'
  }

  if (field === 'postalCode') {
    if (zones.value.length > 0) {
      errors.value.postalCode = buyer.value.postalCode ? '' : 'Kode pos tujuan wajib dipilih.'
    } else {
      const val = (buyer.value.postalCode || '').trim()
      errors.value.postalCode = val && !/^\d{5}$/.test(val) ? 'Kode pos harus terdiri dari 5 digit angka.' : ''
    }
  }

  if (field === 'service') {
    errors.value.service = selectedZone.value && !selectedRate.value
      ? 'Silakan pilih layanan kurir pengiriman terlebih dahulu.'
      : ''
  }
}

async function validateForm() {
  const orderedFields = ['name', 'phone', 'province', 'city', 'district', 'postalCode', 'address', 'service']
  orderedFields.forEach((f) => validateField(f))

  const firstInvalidKey = orderedFields.find((f) => Boolean(errors.value[f]))
  if (firstInvalidKey) {
    await nextTick()
    const targetId = `checkout-${firstInvalidKey}`
    document.getElementById(targetId)?.focus()
    return false
  }

  return true
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

  if (!(await validateForm())) {
    errorMessage.value = 'Mohon periksa kembali formulir Anda. Masih terdapat isian yang belum sesuai.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  const cleanDistrict = buyer.value.district === NO_DETAIL ? null : buyer.value.district
  const cleanPostal = buyer.value.postalCode === NO_DETAIL ? null : buyer.value.postalCode

  const payload = {
    customer_name: buyer.value.name.trim(),
    customer_phone: buyer.value.phone.trim(),
    shipping_address: buyer.value.address.trim(),
    shipping_city: selectedZone.value?.city || buyer.value.city,
    shipping_district: selectedZone.value?.district || cleanDistrict || 'Surabaya',
    shipping_postal_code: selectedZone.value?.postal_code || cleanPostal || null,
    ...(selectedZone.value && selectedRate.value ? {
      destination_zone_id: selectedZone.value.id,
      courier: selectedRate.value.courier,
      service: selectedRate.value.service,
    } : {}),
    payment_method: method.value,
    payment_channel: method.value === 'cod' ? 'cash' : onlineChannel.value,
    notes: buyer.value.notes ? buyer.value.notes.trim() : null,
    items: cart.items.map((item) => ({
      produk_id: item.id,
      produk_varian_id: item.variantId ?? item.variant_id ?? null,
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
      if (backendErrors.service || backendErrors.courier) {
        errors.value.service = (backendErrors.service || backendErrors.courier)[0]
      }

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
    <h1 class="page-title">Checkout, Ongkir &amp; Pembayaran</h1>
    <p class="page-subtitle">Lengkapi alamat pengiriman untuk menghitung ongkir otomatis, lalu pilih COD Surabaya atau Pembayaran Online Midtrans.</p>

    <div v-if="cart.items.length === 0 && !showPaymentModal" class="empty-state">
      <h2>Keranjang Anda masih kosong</h2>
      <p>Pilih produk terlebih dahulu di katalog kami sebelum melakukan checkout.</p>
      <RouterLink to="/products" class="button button-primary">Buka Katalog Produk</RouterLink>
    </div>

    <div v-else class="checkout-layout">
      <form class="surface-card checkout-form" novalidate @submit.prevent="submitCheckout">
        <h2>1. Informasi Penerima &amp; Alamat Tujuan</h2>

        <div class="form-row">
          <div class="form-group flex-1">
            <label for="checkout-name">Nama Lengkap Penerima <span class="req" aria-hidden="true">*</span></label>
            <input
              id="checkout-name"
              name="customer_name"
              v-model="buyer.name"
              type="text"
              autocomplete="name"
              required
              class="form-control"
              :class="{ 'input-error': errors.name }"
              :aria-invalid="errors.name ? 'true' : 'false'"
              :aria-describedby="errors.name ? 'checkout-name-error' : undefined"
              placeholder="Contoh: Nadia Safitri"
              @blur="validateField('name')"
              @input="errors.name = ''"
            />
            <span v-if="errors.name" id="checkout-name-error" class="field-error-text">{{ errors.name }}</span>
          </div>

          <div class="form-group flex-1">
            <label for="checkout-phone">Nomor WhatsApp Aktif <span class="req" aria-hidden="true">*</span></label>
            <input
              id="checkout-phone"
              name="customer_phone"
              v-model="buyer.phone"
              type="tel"
              inputmode="tel"
              autocomplete="tel"
              required
              class="form-control"
              :class="{ 'input-error': errors.phone }"
              :aria-invalid="errors.phone ? 'true' : 'false'"
              :aria-describedby="errors.phone ? 'checkout-phone-error' : undefined"
              placeholder="0813xxxxxxxx"
              @blur="validateField('phone')"
              @input="errors.phone = ''"
            />
            <span v-if="errors.phone" id="checkout-phone-error" class="field-error-text">{{ errors.phone }}</span>
          </div>
        </div>

        <!-- PPL-18: Zona Pengiriman Dinamis dari Server -->
        <div class="form-row">
          <div class="form-group flex-1">
            <label for="checkout-province">Provinsi <span class="req" aria-hidden="true">*</span></label>
            <select
              id="checkout-province"
              name="destination_province"
              v-model="buyer.province"
              required
              :disabled="loadingZones || !provinces.length"
              class="form-control"
              :class="{ 'input-error': errors.province }"
              :aria-invalid="errors.province ? 'true' : 'false'"
              :aria-describedby="errors.province ? 'checkout-province-error' : undefined"
              @change="handleProvinceChange"
            >
              <option value="" disabled>{{ loadingZones ? 'Memuat zona...' : 'Pilih provinsi' }}</option>
              <option v-for="prov in provinces" :key="prov" :value="prov">{{ prov }}</option>
            </select>
            <span v-if="errors.province" id="checkout-province-error" class="field-error-text">{{ errors.province }}</span>
          </div>

          <div class="form-group flex-1">
            <label for="checkout-city">Kota / Kabupaten <span class="req" aria-hidden="true">*</span></label>
            <select
              id="checkout-city"
              name="shipping_city"
              v-model="buyer.city"
              required
              :disabled="!buyer.province"
              class="form-control"
              :class="{ 'input-error': errors.city }"
              :aria-invalid="errors.city ? 'true' : 'false'"
              :aria-describedby="errors.city ? 'checkout-city-error' : undefined"
              @change="handleCityChange"
            >
              <option value="" disabled>Pilih kota / kabupaten</option>
              <option v-for="city in cities" :key="city" :value="city">
                {{ city }}{{ city.toLowerCase().includes('surabaya') ? ' (Tersedia COD)' : ' (Online Only)' }}
              </option>
            </select>
            <span v-if="errors.city" id="checkout-city-error" class="field-error-text">{{ errors.city }}</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group flex-1">
            <label for="checkout-district">Kecamatan <span class="req" aria-hidden="true">*</span></label>
            <select
              id="checkout-district"
              name="shipping_district"
              v-model="buyer.district"
              required
              :disabled="!buyer.city"
              class="form-control"
              :class="{ 'input-error': errors.district }"
              :aria-invalid="errors.district ? 'true' : 'false'"
              :aria-describedby="errors.district ? 'checkout-district-error' : undefined"
              @change="handleDistrictChange"
            >
              <option value="" disabled>Pilih kecamatan</option>
              <option v-for="dist in districts" :key="dist" :value="dist">
                {{ dist === NO_DETAIL ? 'Semua kecamatan' : dist }}
              </option>
            </select>
            <span v-if="errors.district" id="checkout-district-error" class="field-error-text">{{ errors.district }}</span>
          </div>

          <div class="form-group flex-1">
            <label for="checkout-postalCode">Kode Pos <span class="req" aria-hidden="true">*</span></label>
            <select
              id="checkout-postalCode"
              name="shipping_postal_code"
              v-model="buyer.postalCode"
              required
              :disabled="!buyer.district"
              class="form-control"
              :class="{ 'input-error': errors.postalCode }"
              :aria-invalid="errors.postalCode ? 'true' : 'false'"
              :aria-describedby="errors.postalCode ? 'checkout-postalCode-error' : undefined"
              @change="errors.postalCode = ''"
            >
              <option value="" disabled>Pilih kode pos</option>
              <option v-for="pc in postalCodes" :key="pc" :value="pc">
                {{ pc === NO_DETAIL ? 'Semua kode pos' : pc }}
              </option>
            </select>
            <span v-if="errors.postalCode" id="checkout-postalCode-error" class="field-error-text">{{ errors.postalCode }}</span>
          </div>
        </div>

        <div class="form-group">
          <label for="checkout-address">Alamat Lengkap (Jalan, RT/RW, No. Rumah) <span class="req" aria-hidden="true">*</span></label>
          <textarea
            id="checkout-address"
            name="shipping_address"
            v-model="buyer.address"
            autocomplete="street-address"
            required
            class="form-control"
            :class="{ 'input-error': errors.address }"
            :aria-invalid="errors.address ? 'true' : 'false'"
            :aria-describedby="errors.address ? 'checkout-address-error' : undefined"
            rows="2"
            placeholder="Jl. Manyar Kertajaya No. 45, RT 02 / RW 05"
            @blur="validateField('address')"
            @input="errors.address = ''"
          ></textarea>
          <span v-if="errors.address" id="checkout-address-error" class="field-error-text">{{ errors.address }}</span>
        </div>

        <div class="form-group">
          <label for="checkout-notes">Catatan untuk Penjual / Kurir (Opsional)</label>
          <input
            id="checkout-notes"
            name="notes"
            v-model="buyer.notes"
            type="text"
            class="form-control"
            placeholder="Contoh: Titipkan di pos satpam"
          />
        </div>

        <!-- PPL-18: Pilihan Kurir & Ongkos Kirim Otomatis -->
        <h2>2. Pilihan Kurir &amp; Ongkos Kirim</h2>

        <div class="shipping-card">
          <div class="form-group" style="margin-bottom: 0;">
            <label for="checkout-service">Layanan Pengiriman <span class="req" aria-hidden="true">*</span></label>
            <select
              id="checkout-service"
              name="service"
              v-model="selectedOptionKey"
              required
              :disabled="!selectedZone || loadingRates || !quote?.options?.length"
              class="form-control"
              :class="{ 'input-error': errors.service }"
              :aria-invalid="errors.service ? 'true' : 'false'"
              :aria-describedby="errors.service ? 'checkout-service-error' : undefined"
              @change="errors.service = ''"
            >
              <option value="" disabled>
                {{ loadingRates ? 'Menghitung ongkos kirim...' : (!selectedZone ? 'Pilih zona tujuan terlebih dahulu' : 'Pilih layanan kurir') }}
              </option>
              <option
                v-for="opt in quote?.options || []"
                :key="`${opt.courier}:${opt.service}`"
                :value="`${opt.courier}|${opt.service}`"
              >
                {{ opt.courier }} {{ opt.service }} — {{ formatRupiah(opt.cost) }} (Estimasi {{ opt.etd_min_days }}–{{ opt.etd_max_days }} hari · {{ opt.billable_weight_kg }} kg)
              </option>
            </select>
            <span v-if="errors.service" id="checkout-service-error" class="field-error-text">{{ errors.service }}</span>
          </div>

          <p v-if="loadingRates" class="shipping-hint" role="status">Menghitung berat paket dan tarif ongkir otomatis dari server...</p>
          <p v-else-if="shippingError" class="field-error-text" role="alert">{{ shippingError }}</p>
          <p v-else-if="selectedRate" class="shipping-meta-note">
            📦 Berat aktual: <strong>{{ formatNumber(quote?.weight_grams) }} g</strong> · Berat tagihan: <strong>{{ selectedRate.billable_weight_kg }} kg</strong> · Estimasi tiba: <strong>{{ selectedRate.etd_min_days }}–{{ selectedRate.etd_max_days }} hari</strong>
          </p>
        </div>

        <!-- PPL-19 & PPL-20: Pilihan Metode Pembayaran -->
        <h2>3. Pilihan Metode Pembayaran</h2>

        <!-- PPL-20: Opsi COD Surabaya -->
        <div class="payment-method-card" :class="{ 'disabled-method': !isSurabaya, active: method === 'cod' }">
          <label class="radio-label">
            <input v-model="method" name="payment_method" type="radio" value="cod" :disabled="!isSurabaya" />
            <div class="method-text">
              <div class="method-title-row">
                <strong>Cash on Delivery (COD Surabaya)</strong>
                <span class="badge-tag" :class="isSurabaya ? 'badge-green' : 'badge-gray'">
                  {{ isSurabaya ? '✓ Wilayah Surabaya Didukung' : 'Khusus Kota Surabaya' }}
                </span>
              </div>
              <p class="method-desc">Bayar tunai kepada kurir saat pesanan tiba di wilayah Kota Surabaya.</p>
              <p v-if="!isSurabaya" class="alert-warning" role="status">
                ⚠️ Pengiriman ke <strong>{{ buyer.city || 'wilayah ini' }}</strong> berada di luar jangkauan layanan COD RiasKu Surabaya. Silakan gunakan <strong>Pembayaran Online</strong>.
              </p>
            </div>
          </label>
        </div>

        <!-- PPL-19: Opsi Pembayaran Online Midtrans -->
        <div class="payment-method-card" :class="{ active: method === 'online' }">
          <label class="radio-label">
            <input v-model="method" name="payment_method" type="radio" value="online" />
            <div class="method-text">
              <div class="method-title-row">
                <strong>Pembayaran Online (Otomatis &amp; Real-time)</strong>
                <span class="badge-tag badge-purple">Midtrans Gateway</span>
              </div>
              <p class="method-desc">QRIS, Virtual Account Bank (BCA, BNI, BRI, Mandiri), dan E-Wallet.</p>
            </div>
          </label>

          <fieldset v-if="method === 'online'" class="online-channels-wrap">
            <legend class="sub-label">Pilih Channel Pembayaran:</legend>

            <div class="channel-grid">
              <label class="channel-option" :class="{ selected: onlineChannel === 'qris' }">
                <input v-model="onlineChannel" name="payment_channel" type="radio" value="qris" />
                <span class="channel-name">QRIS (Semua E-Wallet)</span>
                <small>GoPay, OVO, Dana, ShopeePay</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'bca_va' }">
                <input v-model="onlineChannel" name="payment_channel" type="radio" value="bca_va" />
                <span class="channel-name">BCA Virtual Account</span>
                <small>Verifikasi otomatis 24 jam</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'bni_va' }">
                <input v-model="onlineChannel" name="payment_channel" type="radio" value="bni_va" />
                <span class="channel-name">BNI Virtual Account</span>
                <small>ATM / Mobile Banking</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'bri_va' }">
                <input v-model="onlineChannel" name="payment_channel" type="radio" value="bri_va" />
                <span class="channel-name">BRI Virtual Account (BRIVA)</span>
                <small>BRImo &amp; ATM</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'mandiri_va' }">
                <input v-model="onlineChannel" name="payment_channel" type="radio" value="mandiri_va" />
                <span class="channel-name">Mandiri Bill Payment</span>
                <small>Livin' by Mandiri</small>
              </label>

              <label class="channel-option" :class="{ selected: onlineChannel === 'gopay' }">
                <input v-model="onlineChannel" name="payment_channel" type="radio" value="gopay" />
                <span class="channel-name">GoPay / GoPay Later</span>
                <small>Direct Deeplink</small>
              </label>
            </div>
          </fieldset>
        </div>

        <p v-if="errorMessage" class="notice-message notice-error" role="alert">{{ errorMessage }}</p>

        <button class="button button-primary button-checkout-submit" type="submit" :disabled="isSubmitting">
          {{ isSubmitting
            ? 'Memproses Pesanan & Pembayaran...'
            : (method === 'cod'
              ? `Konfirmasi Pesanan COD — ${formatRupiah(grandTotalAmount)}`
              : `Bayar Online — ${formatRupiah(grandTotalAmount)} →`)
          }}
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
              <span v-if="item.is_preorder_item" class="stock-pill pill-preorder" style="margin-top: 3px;">
                Pre-Order{{ item.preorder_estimate_days ? ` · Est. ${item.preorder_estimate_days} hari` : '' }}
              </span>
              <small class="item-qty">Qty: {{ item.quantity }} pcs</small>
            </div>
            <strong>{{ formatRupiah(item.price * item.quantity) }}</strong>
          </div>
        </div>

        <p v-if="hasPreorderItems" class="cart-po-summary-note" style="margin-bottom: 1rem;">
          ⏳ Pesanan ini memuat produk <strong>Pre-Order</strong> dengan estimasi penyiapan{{ maxPreorderDays ? ` hingga ${maxPreorderDays} hari` : '' }} sebelum pengiriman.
        </p>

        <div class="summary-calculation">
          <div class="summary-sub-line">
            <span>Subtotal Produk</span>
            <span>{{ formatRupiah(subtotalAmount) }}</span>
          </div>
          <div v-if="quote" class="summary-sub-line">
            <span>Berat Aktual</span>
            <span>{{ formatNumber(quote.weight_grams) }} g</span>
          </div>
          <div v-if="selectedRate" class="summary-sub-line">
            <span>Berat Tagihan ({{ selectedRate.courier }})</span>
            <span>{{ selectedRate.billable_weight_kg }} kg</span>
          </div>
          <div class="summary-sub-line">
            <span>Ongkos Kirim{{ selectedRate ? ` (${selectedRate.courier} ${selectedRate.service})` : '' }}</span>
            <strong v-if="selectedRate && shippingCostAmount > 0">{{ formatRupiah(shippingCostAmount) }}</strong>
            <span v-else-if="selectedRate && shippingCostAmount === 0" class="free-shipping">GRATIS</span>
            <span v-else>{{ loadingRates ? 'Menghitung...' : 'Pilih zona & kurir' }}</span>
          </div>
          <small v-if="selectedRate" class="weight-rule-note">
            Pembulatan per {{ formatWeightKg(selectedRate.rounding_step_grams) }} kg, min. {{ formatWeightKg(selectedRate.min_billable_weight_grams) }} kg.
          </small>
          <div class="summary-total">
            <span>Total Pembayaran</span>
            <strong>{{ formatRupiah(grandTotalAmount) }}</strong>
          </div>
        </div>

        <div class="security-note">
          <span>🔒 Transaksi Aman &amp; Terenkripsi</span>
          <small>Ongkir otomatis dihitung oleh server · Didukung Midtrans Gateway &amp; COD Surabaya</small>
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
  margin-bottom: 0.25rem;
}
.flex-1 { flex: 1; }
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
.form-group label {
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
  margin-top: 0.15rem;
  line-height: 1.3;
}
.shipping-card {
  background: #fdfafc;
  border: 1px solid #e6dce2;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1.25rem;
}
.shipping-hint {
  margin: 0.5rem 0 0;
  font-size: 0.8rem;
  color: #695861;
}
.shipping-meta-note {
  margin: 0.6rem 0 0;
  font-size: 0.8rem;
  color: #4b303d;
  background: #f5eef4;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
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
  flex-wrap: wrap;
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
  border: 0;
  margin: 1rem 0 0;
  padding: 0.85rem 0 0;
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
  gap: 0.45rem;
}
.summary-sub-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #55444e;
}
.weight-rule-note {
  font-size: 0.72rem;
  color: #7d6b75;
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

@media (max-width: 680px) {
  .form-row {
    flex-direction: column;
    gap: 0;
  }
  .payment-method-card {
    padding: 0.85rem;
  }
  .channel-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>
