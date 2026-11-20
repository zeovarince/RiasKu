<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useCartStore } from '@/stores/cart'
import { createOrder } from '@/services/checkoutService'
import { getRates, getZones } from '@/services/shippingService'
import { formatRupiah } from '@/utils/currency'

const cart = useCartStore()
const zones = ref([])
const quote = ref(null)
const selectedOptionKey = ref('')
const loadingZones = ref(true)
const loadingRates = ref(false)
const submitting = ref(false)
const errors = ref({})
const message = ref('')
const order = ref(null)
const noDetail = '__none__'
const buyer = reactive({
  name: '',
  phone: '',
  address: '',
  destinationProvince: '',
  destinationCity: '',
  destinationDistrict: '',
  destinationPostalCode: '',
  paymentMethod: 'online',
  notes: '',
})

const provinces = computed(() => [...new Set(zones.value.map((zone) => zone.province))])
const cities = computed(() => [...new Set(zones.value
  .filter((zone) => zone.province === buyer.destinationProvince)
  .map((zone) => zone.city))])
const districts = computed(() => [...new Set(zones.value
  .filter((zone) => zone.province === buyer.destinationProvince && zone.city === buyer.destinationCity)
  .map((zone) => zone.district || noDetail))])
const postalCodes = computed(() => [...new Set(zones.value
  .filter((zone) => zone.province === buyer.destinationProvince
    && zone.city === buyer.destinationCity
    && (zone.district || noDetail) === buyer.destinationDistrict)
  .map((zone) => zone.postal_code || noDetail))])
const selectedZone = computed(() => zones.value.find((zone) =>
  zone.province === buyer.destinationProvince
  && zone.city === buyer.destinationCity
  && (zone.district || noDetail) === buyer.destinationDistrict
  && (zone.postal_code || noDetail) === buyer.destinationPostalCode,
))
const selectedRate = computed(() => quote.value?.options?.find(
  (option) => `${option.courier}|${option.service}` === selectedOptionKey.value,
))
const total = computed(() => quote.value
  ? Number(quote.value.subtotal) + Number(selectedRate.value?.cost ?? 0)
  : null)
const unplacedErrors = computed(() => Object.entries(errors.value).filter(
  ([field]) => !['name', 'phone', 'address', 'destination_zone_id', 'service', 'payment_method', 'notes'].includes(field),
))
const itemPayload = computed(() => cart.items.map((item) => ({
  product_id: item.id,
  variant_id: item.variantId ?? item.variant_id,
  quantity: item.quantity,
})))
let quoteRequestId = 0

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value) || 0)
}

function formatWeightKg(weightGrams) {
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(
    (Number(weightGrams) || 0) / 1000,
  )
}

function resetAfterProvince() {
  buyer.destinationCity = ''
  buyer.destinationDistrict = ''
  buyer.destinationPostalCode = ''
}

function resetAfterCity() {
  buyer.destinationDistrict = ''
  buyer.destinationPostalCode = ''
}

function resetAfterDistrict() {
  buyer.destinationPostalCode = ''
  if (postalCodes.value.length === 1) {
    buyer.destinationPostalCode = postalCodes.value[0]
  }
}

function fieldError(field) {
  return errors.value[field]?.[0] || ''
}

function validationMessage(error, fallback) {
  const validationErrors = error.response?.data?.errors || {}
  if (error.response?.status === 422) {
    if (Object.keys(validationErrors).some((field) => field.startsWith('items.'))) {
      return 'Produk atau varian di keranjang tidak cocok dengan katalog server. Hapus item tersebut lalu tambahkan ulang dari katalog aktif.'
    }
    return 'Data checkout tidak valid. Periksa rincian kesalahan di bawah.'
  }

  return error.response?.data?.message || fallback
}

async function loadQuote() {
  const requestId = ++quoteRequestId
  quote.value = null
  selectedOptionKey.value = ''
  message.value = ''
  loadingRates.value = false
  if (!selectedZone.value || !itemPayload.value.length) return

  loadingRates.value = true
  try {
    const result = await getRates({
      destination_zone_id: selectedZone.value.id,
      items: itemPayload.value,
    })
    if (requestId !== quoteRequestId || String(result.destination?.id) !== String(selectedZone.value?.id)) return
    quote.value = result
    const firstOption = result.options?.[0]
    if (firstOption) {
      selectedOptionKey.value = `${firstOption.courier}|${firstOption.service}`
    }
    errors.value = {}
  } catch (error) {
    if (requestId !== quoteRequestId) return
    message.value = validationMessage(error, 'Ongkos kirim belum dapat dihitung.')
    errors.value = error.response?.data?.errors || {}
  } finally {
    if (requestId === quoteRequestId) loadingRates.value = false
  }
}

watch(
  () => `${selectedZone.value?.id ?? ''}|${itemPayload.value.map((item) => `${item.product_id}:${item.variant_id}:${item.quantity}`).join(',')}`,
  () => loadQuote(),
)

watch(selectedZone, (zone) => {
  if (buyer.paymentMethod === 'cod' && !zone?.cod_available) {
    buyer.paymentMethod = 'online'
  }
})

onMounted(async () => {
  cart.hydrateCart()
  try {
    zones.value = await getZones()
  } catch (error) {
    message.value = error.response?.data?.message || 'Daftar zona pengiriman gagal dimuat.'
  } finally {
    loadingZones.value = false
  }
})

async function submitCheckout() {
  errors.value = {}
  message.value = ''
  if (!selectedZone.value || !selectedRate.value || !quote.value) {
    message.value = 'Pilih zona dan layanan pengiriman terlebih dahulu.'
    return
  }

  submitting.value = true
  try {
    order.value = await createOrder({
      name: buyer.name,
      phone: buyer.phone,
      address: buyer.address,
      payment_method: buyer.paymentMethod,
      destination_zone_id: selectedZone.value.id,
      courier: selectedRate.value.courier,
      service: selectedRate.value.service,
      notes: buyer.notes || null,
      items: itemPayload.value,
    })
    cart.clearCart()
    quote.value = null
  } catch (error) {
    errors.value = error.response?.data?.errors || {}
    message.value = validationMessage(error, 'Pesanan belum dapat dibuat. Silakan coba kembali.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="page-container inner-page">
    <span class="eyebrow-badge">PENYELESAIAN PESANAN</span>
    <h1 class="page-title">Checkout &amp; Pengiriman</h1>

    <div v-if="order" class="surface-card checkout-success">
      <h2>Pesanan berhasil dibuat</h2>
      <p>Kode pesanan Anda: <strong>{{ order.order_code }}</strong></p>
      <p>Total pembayaran: <strong>{{ formatRupiah(order.total) }}</strong></p>
      <RouterLink
        class="button button-primary"
        :to="{ name: 'order-track', query: { code: order.order_code } }"
      >
        Lacak pesanan
      </RouterLink>
    </div>

    <div v-else class="checkout-layout">
      <form class="surface-card checkout-form" @submit.prevent="submitCheckout">
        <h2>Informasi Penerima</h2>
        <label>
          Nama lengkap
          <input v-model="buyer.name" class="form-control" autocomplete="name" required />
          <small v-if="fieldError('name')" class="form-error">{{ fieldError('name') }}</small>
        </label>
        <label>
          Nomor WhatsApp
          <input v-model="buyer.phone" class="form-control" type="tel" autocomplete="tel" required />
          <small v-if="fieldError('phone')" class="form-error">{{ fieldError('phone') }}</small>
        </label>
        <label>
          Alamat lengkap
          <textarea v-model="buyer.address" class="form-control" rows="3" autocomplete="street-address" required />
          <small v-if="fieldError('address')" class="form-error">{{ fieldError('address') }}</small>
        </label>

        <h2>Zona Tujuan</h2>
        <label>
          Provinsi
          <select v-model="buyer.destinationProvince" class="form-control" required :disabled="loadingZones" @change="resetAfterProvince">
            <option value="" disabled>{{ loadingZones ? 'Memuat zona...' : 'Pilih provinsi' }}</option>
            <option v-for="province in provinces" :key="province" :value="province">{{ province }}</option>
          </select>
        </label>
        <label>
          Kota / Kabupaten
          <select v-model="buyer.destinationCity" class="form-control" required :disabled="!buyer.destinationProvince" @change="resetAfterCity">
            <option value="" disabled>Pilih kota</option>
            <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
          </select>
        </label>
        <label>
          Kecamatan
          <select v-model="buyer.destinationDistrict" class="form-control" required :disabled="!buyer.destinationCity" @change="resetAfterDistrict">
            <option value="" disabled>Pilih kecamatan</option>
            <option v-for="district in districts" :key="district" :value="district">
              {{ district === noDetail ? 'Semua kecamatan' : district }}
            </option>
          </select>
        </label>
        <label>
          Kode pos
          <select v-model="buyer.destinationPostalCode" class="form-control" required :disabled="!buyer.destinationDistrict">
            <option value="" disabled>Pilih kode pos</option>
            <option v-for="postalCode in postalCodes" :key="postalCode" :value="postalCode">
              {{ postalCode === noDetail ? 'Semua kode pos' : postalCode }}
            </option>
          </select>
          <small v-if="fieldError('destination_zone_id')" class="form-error">{{ fieldError('destination_zone_id') }}</small>
        </label>

        <template v-if="selectedZone">
          <h2>Kurir dan Layanan</h2>
          <label>
            Layanan pengiriman
            <select
              class="form-control"
              v-model="selectedOptionKey"
              required
              :disabled="loadingRates || !quote?.options?.length"
            >
              <option value="" disabled>{{ loadingRates ? 'Menghitung ongkir...' : 'Pilih layanan' }}</option>
              <option
                v-for="option in quote?.options || []"
                :key="`${option.courier}:${option.service}`"
                :value="`${option.courier}|${option.service}`"
              >
                {{ option.courier }} / {{ option.service }} — {{ option.billable_weight_kg }} kg — {{ formatRupiah(option.cost) }} — {{ option.etd_min_days }}–{{ option.etd_max_days }} hari
              </option>
            </select>
            <small v-if="fieldError('service')" class="form-error">{{ fieldError('service') }}</small>
          </label>
          <p v-if="loadingRates" class="state-message">Menghitung berat dan ongkos kirim...</p>
          <p v-else-if="quote && !quote.options?.length" class="notice-message">Tidak ada layanan aktif untuk zona ini.</p>

          <h2>Metode Pembayaran</h2>
          <label class="radio-line">
            <input v-model="buyer.paymentMethod" type="radio" value="cod" :disabled="!selectedZone.cod_available" />
            COD (bayar saat pesanan diterima)
          </label>
          <p v-if="!selectedZone.cod_available" class="notice-message">
            COD belum tersedia untuk kota {{ selectedZone.city }}. Pilih pembayaran online.
          </p>
          <label class="radio-line">
            <input v-model="buyer.paymentMethod" type="radio" value="online" />
            Pembayaran online (QRIS / VA / E-Wallet)
          </label>
          <small v-if="fieldError('payment_method')" class="form-error">{{ fieldError('payment_method') }}</small>

          <label>
            Catatan untuk penjual (opsional)
            <textarea v-model="buyer.notes" class="form-control" rows="2" maxlength="2000" />
            <small v-if="fieldError('notes')" class="form-error">{{ fieldError('notes') }}</small>
          </label>
        </template>

        <p v-if="message" class="notice-message" role="alert">{{ message }}</p>
        <ul v-if="unplacedErrors.length" class="notice-message" role="alert">
          <li v-for="entry in unplacedErrors" :key="entry[0]">
            {{ entry[0] }}: {{ entry[1][0] }}
          </li>
        </ul>
        <button
          class="button button-primary"
          type="submit"
          :disabled="submitting || loadingZones || loadingRates || !cart.items.length || !selectedRate"
        >
          {{ submitting ? 'Membuat pesanan...' : 'Buat Pesanan' }}
        </button>
      </form>

      <aside class="surface-card order-summary">
        <h2>Ringkasan Pesanan</h2>
        <p>{{ cart.totalItems }} item dalam keranjang</p>
        <div v-for="item in cart.items" :key="item.cartKey || item.id" class="summary-line">
          <span>{{ item.name }}{{ item.variantName ? ` (${item.variantName})` : '' }} × {{ item.quantity }}</span>
        </div>
        <div class="summary-line">
          <span>Subtotal produk</span>
          <strong>{{ quote ? formatRupiah(quote.subtotal) : 'Menunggu kutipan' }}</strong>
        </div>
        <div class="summary-line">
          <span>Berat aktual</span>
          <strong>{{ quote ? `${formatNumber(quote.weight_grams)} g` : '—' }}</strong>
        </div>
        <small v-if="quote">Termasuk berat kemasan {{ formatNumber(quote.packaging_weight_grams) }} g.</small>
        <div class="summary-line">
          <span>Berat tagihan</span>
          <strong>{{ selectedRate ? `${selectedRate.billable_weight_kg} kg` : '—' }}</strong>
        </div>
        <small v-if="selectedRate">
          {{ selectedRate.courier }}: dibulatkan ke atas per {{ formatWeightKg(selectedRate.rounding_step_grams) }} kg,
          minimum {{ formatWeightKg(selectedRate.min_billable_weight_grams) }} kg.
        </small>
        <div class="summary-line">
          <span>Ongkos kirim</span>
          <strong>{{ selectedRate ? formatRupiah(selectedRate.cost) : '—' }}</strong>
        </div>
        <div class="summary-total">
          <span>Total</span>
          <strong>{{ total === null ? '—' : formatRupiah(total) }}</strong>
        </div>
        <small>Harga, berat, dan ongkos kirim akhir dihitung oleh server.</small>
      </aside>
    </div>
  </section>
</template>
