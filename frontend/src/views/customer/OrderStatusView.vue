<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getOrderStatus } from '@/services/checkoutService'
import { formatRupiah } from '@/utils/currency'

const route = useRoute()
const orderCode = ref('')
const order = ref(null)
const loading = ref(false)
const error = ref('')

async function checkOrder() {
  if (!orderCode.value.trim()) return
  loading.value = true
  error.value = ''
  order.value = null
  try {
    order.value = await getOrderStatus(orderCode.value.trim())
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Pesanan tidak ditemukan atau belum dapat dimuat.'
  } finally {
    loading.value = false
  }
}

watch(() => route.query.code, (code) => {
  if (typeof code === 'string') {
    orderCode.value = code
    checkOrder()
  }
})

onMounted(() => {
  if (typeof route.query.code === 'string') {
    orderCode.value = route.query.code
    checkOrder()
  }
})
</script>

<template>
  <section class="page-container inner-page narrow-page">
    <span class="eyebrow-badge">PELACAKAN PESANAN</span>
    <h1 class="page-title">Cek Status Pesanan</h1>
    <p class="page-subtitle">Masukkan kode pesanan untuk melihat perkembangan pesanan RiasKu.</p>
    <form class="surface-card track-form" @submit.prevent="checkOrder">
      <label>
        Kode pesanan
        <input v-model="orderCode" class="form-control" placeholder="Contoh: RSK-261117-AB12CD34" required />
      </label>
      <button class="button button-primary" type="submit" :disabled="loading">
        {{ loading ? 'Memuat pesanan...' : 'Cari Pesanan' }}
      </button>
      <p v-if="error" class="notice-message" role="alert">{{ error }}</p>
    </form>

    <article v-if="order" class="surface-card order-summary">
      <h2>Pesanan {{ order.order_code }}</h2>
      <p>Status: <strong>{{ order.status }}</strong></p>
      <p>Kurir: {{ order.courier }} / {{ order.courier_service }}</p>
      <p>Tujuan: {{ [order.destination_city, order.destination_district, order.destination_postal_code].filter(Boolean).join(', ') }}</p>
      <p>Nomor resi: {{ order.tracking_number || 'Belum tersedia' }}</p>
      <div v-for="item in order.items || []" :key="item.id" class="summary-line">
        <span>{{ item.product_name }}{{ item.variant_name ? ` (${item.variant_name})` : '' }} × {{ item.quantity }}</span>
        <strong>{{ formatRupiah(item.line_total) }}</strong>
      </div>
      <div class="summary-line"><span>Subtotal</span><strong>{{ formatRupiah(order.subtotal) }}</strong></div>
      <div class="summary-line"><span>Ongkos kirim</span><strong>{{ formatRupiah(order.shipping_cost) }}</strong></div>
      <div class="summary-total"><span>Total</span><strong>{{ formatRupiah(order.total) }}</strong></div>
    </article>
  </section>
</template>
