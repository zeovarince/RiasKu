<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'
import { formatRupiah } from '@/utils/currency'

const route = useRoute()
const orderId = route.params.id || 'RK-0014'

const loading = ref(false)
const saved = ref(false)
const actionMessage = ref('')
const errorMessage = ref('')
const isConfirmingCod = ref(false)

const statusOptions = [
  { value: 'menunggu_pembayaran', label: 'Menunggu Pembayaran' },
  { value: 'diproses', label: 'Diproses' },
  { value: 'dikirim', label: 'Dikirim' },
  { value: 'selesai', label: 'Selesai' },
  { value: 'dibatalkan', label: 'Dibatalkan' },
]

// Default order state (with fallback data)
const order = ref({
  id: 1,
  order_code: orderId,
  customer_name: 'Nadia Safitri',
  customer_phone: '081398765432',
  shipping_address: 'Jl. Manyar Kertajaya No. 45',
  shipping_city: 'Kota Surabaya',
  shipping_district: 'Gubeng',
  shipping_postal_code: '60281',
  shipping_courier: 'Kurir Toko RiasKu (Same-day)',
  shipping_resi: null,
  payment_method: 'cod',
  payment_channel: 'cash',
  payment_status: 'menunggu',
  order_status: 'diproses',
  total_amount: 76000,
  shipping_cost: 0,
  created_at: new Date().toISOString(),
  items: [
    {
      id: 1,
      product_name: 'Glow Velvet Lip Tint',
      variant_name: '01 Rose Bloom · Eceran',
      quantity: 2,
      price: 38000,
      subtotal: 76000,
    },
  ],
  latest_pembayaran: null,
})

const selectedStatus = ref('diproses')

const isCod = computed(() => order.value.payment_method === 'cod' || order.value.payment_channel === 'cash')
const isPaid = computed(() => order.value.payment_status === 'dibayar' || order.value.payment_status === 'lunas')

const statusTone = computed(() => {
  switch (selectedStatus.value) {
    case 'selesai': return 'green'
    case 'diproses': return 'lavender'
    case 'dikirim': return 'blue'
    case 'menunggu_pembayaran': return 'gold'
    case 'dibatalkan': return 'red'
    default: return 'neutral'
  }
})

const paymentTone = computed(() => {
  if (isPaid.value) return 'green'
  if (order.value.payment_status === 'dibatalkan' || order.value.payment_status === 'gagal') return 'red'
  return 'gold'
})

const currentStepIndex = computed(() => {
  if (selectedStatus.value === 'menunggu_pembayaran') return 1
  if (isPaid.value && selectedStatus.value === 'diproses') return 3
  if (selectedStatus.value === 'diproses') return 3
  if (selectedStatus.value === 'dikirim') return 4
  if (selectedStatus.value === 'selesai') return 5
  return 1
})

async function fetchOrderDetail() {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await adminService.orderDetail(orderId)
    const data = res.data?.data || res.data
    if (data) {
      order.value = {
        ...data,
        customer_name: data.customer_name || data.recipient_name || 'Pelanggan',
        customer_phone: data.customer_phone || data.recipient_phone || '-',
        shipping_city: data.shipping_city || data.destination_city || 'Kota Surabaya',
        shipping_district: data.shipping_district || data.destination_district || '',
        shipping_postal_code: data.shipping_postal_code || data.destination_postal_code || '',
        shipping_courier: data.shipping_courier || [data.courier, data.courier_service].filter(Boolean).join(' '),
        shipping_resi: data.tracking_number || data.shipping_resi || null,
        total_amount: Number(data.total_amount ?? data.total ?? 0),
        shipping_cost: Number(data.shipping_cost ?? 0),
        items: data.items || [],
        payment_method: data.payment_method || (data.payment_channel === 'cash' ? 'cod' : 'online'),
      }
      selectedStatus.value = data.order_status || data.status || 'diproses'
    }
  } catch (err) {
    // Fallback gracefully to default template data if mock or route id doesn't exist yet
    console.warn('Gagal memuat detail pesanan dari API, menggunakan template data:', err)
  } finally {
    loading.value = false
  }
}

async function handleSaveStatus() {
  saved.value = false
  errorMessage.value = ''
  actionMessage.value = ''
  try {
    const targetId = order.value.id || orderId
    await adminService.updateOrderStatus(targetId, { status: selectedStatus.value })
    order.value.order_status = selectedStatus.value
    saved.value = true
    actionMessage.value = `Status pesanan berhasil diubah menjadi: ${selectedStatus.value}`
    setTimeout(() => { saved.value = false }, 3500)
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.message || 'Gagal memperbarui status pesanan.'
  }
}

async function handleConfirmCodPayment() {
  if (!confirm(`Konfirmasi pembayaran tunai COD untuk pesanan ${order.value.order_code}? Saldo tunai sebesar ${formatRupiah(order.value.total_amount)} akan dicatat sebagai diterima.`)) {
    return
  }

  isConfirmingCod.value = true
  errorMessage.value = ''
  actionMessage.value = ''
  try {
    const targetId = order.value.id || orderId
    const res = await adminService.confirmCodPayment(targetId)
    order.value.payment_status = 'dibayar'
    order.value.order_status = 'selesai'
    selectedStatus.value = 'selesai'
    saved.value = true
    actionMessage.value = res.data?.message || 'Uang tunai COD berhasil diverifikasi. Pesanan diselesaikan!'
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.message || 'Gagal mengonfirmasi pembayaran COD.'
  } finally {
    isConfirmingCod.value = false
  }
}

onMounted(() => {
  fetchOrderDetail()
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / PESANAN / DETAIL"
      :title="`Pesanan ${order.order_code}`"
      :description="`Dibuat oleh ${order.customer_name} · Wilayah ${order.shipping_city || 'Kota Surabaya'}`"
    >
      <template #actions>
        <RouterLink to="/admin/orders" class="admin-btn admin-btn-outline">
          <AdminIcon name="arrow-left" :size="15" /> Kembali ke daftar
        </RouterLink>
      </template>
    </AdminPageHeader>

    <div v-if="actionMessage" class="admin-inline-success" style="margin-bottom: 1rem; display: block;">
      ✓ {{ actionMessage }}
    </div>

    <div v-if="errorMessage" class="admin-inline-error" style="margin-bottom: 1rem; color: #dc2626; background: #fef2f2; padding: 0.75rem; border-radius: 8px; font-size: 0.875rem;">
      ⚠️ {{ errorMessage }}
    </div>

    <div class="admin-order-detail-grid">
      <!-- Sisi Kiri: Rincian Produk & Pengiriman -->
      <div class="admin-detail-main">
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Item Pesanan</h2>
              <p>Rincian produk yang dipesan pelanggan.</p>
            </div>
            <AdminBadge :tone="statusTone">{{ selectedStatus }}</AdminBadge>
          </div>

          <div v-for="item in order.items" :key="item.id" class="admin-order-item">
            <span class="admin-product-thumb large">
              <img v-if="item.produk?.primary_foto?.image_url || item.produk?.image_url" :src="item.produk?.primary_foto?.image_url || item.produk?.image_url" :alt="item.product_name" />
              <span v-else>R</span>
            </span>
            <div class="admin-order-item-name">
              <strong>{{ item.product_name || item.produk?.name }}</strong>
              <small>{{ item.variant_name || item.varian?.name || 'Standar' }}</small>
            </div>
            <span>{{ item.quantity }} pcs</span>
            <strong>{{ formatRupiah(item.unit_price ?? item.price ?? 0) }}</strong>
            <strong>{{ formatRupiah(item.subtotal ?? item.line_total ?? ((item.unit_price ?? item.price ?? 0) * item.quantity)) }}</strong>
          </div>

          <div class="admin-order-totals">
            <div>
              <span>Subtotal Produk</span>
              <strong>{{ formatRupiah(order.total_amount - (order.shipping_cost || 0)) }}</strong>
            </div>
            <div>
              <span>Ongkos Kirim ({{ isCod ? 'Kurir Toko COD Surabaya' : 'Kurir Standar' }})</span>
              <strong>{{ formatRupiah(order.shipping_cost || 0) }}</strong>
            </div>
            <div class="grand">
              <span>Total Bayar</span>
              <strong>{{ formatRupiah(order.total_amount) }}</strong>
            </div>
          </div>
        </article>

        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Penerima dan Pengiriman</h2>
              <p>Informasi kontak dan alamat tujuan.</p>
            </div>
            <AdminBadge v-if="isCod" tone="gold">📍 COD Khusus Surabaya</AdminBadge>
          </div>

          <div class="admin-info-grid">
            <div>
              <small>Nama Penerima</small>
              <strong>{{ order.customer_name }}</strong>
            </div>
            <div>
              <small>Ekspedisi &amp; Layanan</small>
              <strong>{{ order.shipping_courier || (isCod ? 'Diantar Kurir Toko Surabaya (Same-day)' : 'Kurir Reguler') }}</strong>
            </div>
            <div>
              <small>Nomor WhatsApp</small>
              <strong>{{ order.customer_phone }} <AdminBadge tone="green">Terhubung</AdminBadge></strong>
            </div>
            <div>
              <small>Nomor Resi / Bukti</small>
              <strong>{{ order.shipping_resi || 'Pengiriman Langsung Toko' }}</strong>
            </div>
            <div class="full">
              <small>Alamat Lengkap Tujuan</small>
              <strong>{{ order.shipping_address }}, {{ order.shipping_district ? order.shipping_district + ', ' : '' }}{{ order.shipping_city }} {{ order.shipping_postal_code || '' }}</strong>
            </div>
            <div v-if="order.notes" class="full">
              <small>Catatan Tambahan Pelanggan</small>
              <em>"{{ order.notes }}"</em>
            </div>
          </div>
        </article>
      </div>

      <!-- Sisi Kanan: Status & Manajemen Pembayaran (PPL-19 & PPL-20) -->
      <aside class="admin-detail-side">
        <!-- Status Pesanan -->
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Status Pesanan</h2>
            </div>
            <AdminBadge :tone="statusTone">{{ selectedStatus }}</AdminBadge>
          </div>

          <div class="admin-order-timeline">
            <div :class="{ done: currentStepIndex >= 1 }">
              <i>{{ currentStepIndex > 1 ? '✓' : '1' }}</i>
              <span>Menunggu<br />Pembayaran</span>
            </div>
            <div :class="{ done: isPaid || currentStepIndex >= 2 }">
              <i>{{ isPaid ? '✓' : '2' }}</i>
              <span>Dibayar</span>
            </div>
            <div :class="{ done: currentStepIndex > 3, current: currentStepIndex === 3 }">
              <i>{{ currentStepIndex > 3 ? '✓' : '3' }}</i>
              <span>Diproses</span>
            </div>
            <div :class="{ done: currentStepIndex > 4, current: currentStepIndex === 4 }">
              <i>{{ currentStepIndex > 4 ? '✓' : '4' }}</i>
              <span>Dikirim</span>
            </div>
            <div :class="{ done: currentStepIndex >= 5, current: currentStepIndex === 5 }">
              <i>{{ currentStepIndex >= 5 ? '✓' : '5' }}</i>
              <span>Selesai</span>
            </div>
          </div>

          <label class="admin-field">
            Ubah Status Pesanan
            <select v-model="selectedStatus">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </label>

          <button class="admin-btn admin-btn-primary admin-submit" type="button" @click="handleSaveStatus">
            Simpan Status
          </button>
        </article>

        <!-- Informasi Pembayaran (PPL-19 & PPL-20) -->
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <h2>Informasi Pembayaran</h2>
            <AdminBadge :tone="paymentTone">
              {{ isPaid ? 'Lunas' : (order.payment_status === 'menunggu' ? 'Menunggu' : order.payment_status) }}
            </AdminBadge>
          </div>

          <div class="admin-info-stack">
            <div>
              <small>Metode Pembayaran</small>
              <strong>{{ isCod ? 'COD (Uang Tunai Surabaya)' : `Online (${order.payment_channel ? order.payment_channel.toUpperCase() : 'Midtrans'})` }}</strong>
            </div>
            <div>
              <small>Status Transaksi</small>
              <strong>{{ isPaid ? 'Lunas / Settlement' : 'Menunggu Pelunasan' }}</strong>
            </div>
            <div>
              <small>Total yang Harus Diterima</small>
              <strong>{{ formatRupiah(order.total_amount) }}</strong>
            </div>
          </div>

          <!-- PPL-20: Tombol Konfirmasi Uang Tunai Diterima untuk COD Surabaya -->
          <div v-if="isCod" style="margin-top: 1rem;">
            <button
              v-if="!isPaid"
              class="admin-btn admin-btn-primary admin-submit"
              type="button"
              :disabled="isConfirmingCod"
              style="background: #16a34a; border-color: #16a34a;"
              @click="handleConfirmCodPayment"
            >
              {{ isConfirmingCod ? 'Memproses Konfirmasi...' : '💵 Uang Tunai Diterima' }}
            </button>
            <div v-else class="admin-inline-success" style="display: block; text-align: center; font-weight: 600;">
              ✓ Pembayaran COD Telah Selesai
            </div>
          </div>
        </article>
      </aside>
    </div>
  </section>
</template>
