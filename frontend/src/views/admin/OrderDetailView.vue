<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'
import { formatRupiah } from '@/utils/currency'

const route = useRoute()
const orderId = computed(() => route.params.id)

const loading = ref(true)
const savingStatus = ref(false)
const actionMessage = ref('')
const errorMessage = ref('')
const loadError = ref('')
const isConfirmingCod = ref(false)

const statusOptions = [
  { value: 'menunggu_pembayaran', label: 'Menunggu Pembayaran' },
  { value: 'diproses', label: 'Diproses' },
  { value: 'dikirim', label: 'Dikirim' },
  { value: 'selesai', label: 'Selesai' },
  { value: 'dibatalkan', label: 'Dibatalkan' },
]

const order = ref(null)
const selectedStatus = ref('menunggu_pembayaran')
const trackingNumber = ref('')
const shippingCourier = ref('')
const statusNote = ref('')

const customerName = computed(() => order.value?.customer_name || order.value?.recipient_name || 'Pelanggan')
const customerPhone = computed(() => order.value?.customer_phone || order.value?.recipient_phone || '-')
const shippingCity = computed(() => order.value?.shipping_city || order.value?.destination_city || '-')
const shippingDistrict = computed(() => order.value?.shipping_district || order.value?.destination_district || '')
const shippingPostalCode = computed(() => order.value?.shipping_postal_code || order.value?.destination_postal_code || '')
const shippingCourierLabel = computed(() => {
  if (!order.value) return '-'
  return order.value.shipping_courier || [order.value.courier, order.value.courier_service].filter(Boolean).join(' ') || (isCod.value ? 'Kurir Toko RiasKu Surabaya' : 'Kurir Reguler')
})
const totalProductAmount = computed(() => {
  if (!order.value) return 0
  return Number(order.value.total_product_amount ?? order.value.subtotal ?? 0)
})
const totalAmount = computed(() => {
  if (!order.value) return 0
  return Number(order.value.total_amount ?? order.value.total ?? 0)
})

const currentOrderStatus = computed(() => {
  const raw = String(order.value?.order_status || order.value?.status || 'menunggu_pembayaran').toLowerCase()
  if (raw === 'pending') {
    return isCod.value ? 'diproses' : 'menunggu_pembayaran'
  }
  return raw
})

const isCod = computed(() => order.value?.payment_method === 'cod' || order.value?.payment_channel === 'cash')
const isPaid = computed(() => ['dibayar', 'lunas', 'settlement'].includes(order.value?.payment_status))
const isTerminalStatus = computed(() => ['selesai', 'dibatalkan'].includes(currentOrderStatus.value))
const requiresManualPaymentNote = computed(() => {
  return currentOrderStatus.value === 'menunggu_pembayaran'
    && selectedStatus.value === 'diproses'
    && !isPaid.value
})

const allowedNextStatuses = computed(() => {
  if (Array.isArray(order.value?.allowed_next_statuses)) {
    return order.value.allowed_next_statuses
  }
  switch (currentOrderStatus.value) {
    case 'menunggu_pembayaran': return ['diproses', 'dibatalkan']
    case 'diproses': return ['dikirim', 'dibatalkan']
    case 'dikirim': return ['selesai']
    default: return []
  }
})

function isStatusOptionEnabled(optValue) {
  if (optValue === currentOrderStatus.value) {
    return optValue === 'dikirim'
  }
  return allowedNextStatuses.value.includes(optValue)
}

const statusTone = computed(() => {
  switch (currentOrderStatus.value) {
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
  if (['dibatalkan', 'batal', 'gagal', 'kadaluarsa'].includes(order.value?.payment_status)) return 'red'
  return 'gold'
})

const currentStepIndex = computed(() => {
  const s = currentOrderStatus.value
  if (s === 'dibatalkan') return 0
  if (s === 'menunggu_pembayaran') return isPaid.value ? 2 : 1
  if (s === 'diproses') return 3
  if (s === 'dikirim') return 4
  if (s === 'selesai') return 5
  return 1
})

function formatStatusText(status) {
  const map = {
    menunggu_pembayaran: 'Menunggu Pembayaran',
    diproses: 'Diproses',
    dikirim: 'Dikirim',
    selesai: 'Selesai',
    dibatalkan: 'Dibatalkan',
  }
  return map[status] || String(status || '').replaceAll('_', ' ')
}

function formatPaymentStatus(status) {
  const map = {
    dibayar: 'Lunas',
    lunas: 'Lunas',
    settlement: 'Lunas',
    menunggu_pembayaran: 'Menunggu Pembayaran',
    menunggu_pembayaran_cod: 'Menunggu Pembayaran COD',
    unpaid: 'Menunggu Pembayaran COD',
    menunggu: 'Menunggu Pembayaran',
    pending: 'Menunggu Pembayaran',
    batal: 'Dibatalkan',
    dibatalkan: 'Dibatalkan',
    gagal: 'Gagal',
    kadaluarsa: 'Kedaluwarsa',
  }
  const key = String(status || '').toLowerCase()
  return map[key] || key.replaceAll('_', ' ') || 'Menunggu Pembayaran'
}

function mapToneForLog(status) {
  switch (status) {
    case 'selesai': return 'green'
    case 'diproses': return 'lavender'
    case 'dikirim': return 'blue'
    case 'menunggu_pembayaran': return 'gold'
    case 'dibatalkan': return 'red'
    default: return 'neutral'
  }
}

function formatDateTime(iso) {
  if (!iso) return '-'
  try {
    return new Date(iso).toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return iso
  }
}

async function fetchOrderDetail() {
  loading.value = true
  loadError.value = ''
  errorMessage.value = ''
  try {
    const res = await adminService.orderDetail(orderId.value)
    const data = res.data?.data || res.data
    if (!data || !data.order_code) {
      loadError.value = `Pesanan "${orderId.value}" tidak ditemukan.`
      order.value = null
      return
    }

    order.value = {
      ...data,
      items: data.items || [],
      status_logs: data.status_logs || [],
      payment_method: data.payment_method || (data.payment_channel === 'cash' ? 'cod' : 'online'),
    }

    const resolved = currentOrderStatus.value
    const nextList = allowedNextStatuses.value
    selectedStatus.value = nextList[0] || resolved
    trackingNumber.value = data.tracking_number || ''
    shippingCourier.value = data.shipping_courier || data.courier || ''
    statusNote.value = ''
  } catch (err) {
    order.value = null
    loadError.value = err.response?.data?.message || `Gagal memuat detail pesanan "${orderId.value}".`
  } finally {
    loading.value = false
  }
}

async function handleSaveStatus() {
  if (!order.value || savingStatus.value) return

  if (selectedStatus.value === 'dikirim' && !isCod.value && !trackingNumber.value.trim()) {
    errorMessage.value = 'Nomor resi wajib diisi sebelum mengubah status ke Dikirim.'
    return
  }

  if (requiresManualPaymentNote.value && !statusNote.value.trim()) {
    errorMessage.value = 'Pembayaran belum terkonfirmasi. Isi catatan verifikasi pembayaran manual (mis. bukti transfer) sebelum mengubah status ke Diproses.'
    return
  }

  savingStatus.value = true
  errorMessage.value = ''
  actionMessage.value = ''
  try {
    const targetId = order.value.id || orderId.value
    const payload = {
      status: selectedStatus.value,
      ...(trackingNumber.value.trim() ? { tracking_number: trackingNumber.value.trim() } : {}),
      ...(shippingCourier.value.trim() ? { shipping_courier: shippingCourier.value.trim() } : {}),
      ...(statusNote.value.trim() ? { note: statusNote.value.trim() } : {}),
    }

    const res = await adminService.updateOrderStatus(targetId, payload)
    const updated = res.data?.data
    if (updated) {
      order.value = {
        ...updated,
        items: updated.items || order.value.items || [],
        status_logs: updated.status_logs || [],
      }
      const nextList = allowedNextStatuses.value
      selectedStatus.value = nextList[0] || currentOrderStatus.value
      trackingNumber.value = updated.tracking_number || trackingNumber.value
      statusNote.value = ''
    }

    actionMessage.value = res.data?.message || `Status pesanan berhasil diubah menjadi ${formatStatusText(selectedStatus.value)}.`
    window.dispatchEvent(new CustomEvent('riasku-orders-updated'))
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors && typeof errors === 'object') {
      const firstKey = Object.keys(errors)[0]
      errorMessage.value = errors[firstKey]?.[0] || err.response?.data?.message
    } else {
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memperbarui status pesanan.'
    }
  } finally {
    savingStatus.value = false
  }
}

async function handleConfirmCodPayment() {
  if (!order.value) return
  if (!confirm(`Konfirmasi pembayaran tunai COD untuk pesanan ${order.value.order_code}? Saldo tunai sebesar ${formatRupiah(totalAmount.value)} akan dicatat sebagai diterima.`)) {
    return
  }

  isConfirmingCod.value = true
  errorMessage.value = ''
  actionMessage.value = ''
  try {
    const targetId = order.value.id || orderId.value
    const res = await adminService.confirmCodPayment(targetId)
    if (res.data?.data) {
      order.value = res.data.data
      selectedStatus.value = 'selesai'
    } else {
      order.value.payment_status = 'dibayar'
      order.value.order_status = 'selesai'
    }
    actionMessage.value = res.data?.message || 'Uang tunai COD berhasil diverifikasi. Pesanan diselesaikan!'
    window.dispatchEvent(new CustomEvent('riasku-orders-updated'))
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.message || 'Gagal mengonfirmasi pembayaran COD.'
  } finally {
    isConfirmingCod.value = false
  }
}

onMounted(() => {
  fetchOrderDetail()
})

watch(orderId, (nextId, prevId) => {
  if (nextId && nextId !== prevId) {
    fetchOrderDetail()
  }
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / PESANAN / DETAIL"
      :title="`Pesanan ${order?.order_code || orderId}`"
      :description="order ? `Dibuat oleh ${customerName} · Wilayah ${shippingCity}` : 'Memuat rincian pesanan...'"
    >
      <template #actions>
        <RouterLink to="/admin/orders" class="admin-btn admin-btn-outline">
          <AdminIcon name="arrow-left" :size="15" /> Kembali ke daftar
        </RouterLink>
      </template>
    </AdminPageHeader>

    <div v-if="loading" class="admin-panel admin-form-panel" style="text-align: center; padding: 3rem;">
      Memuat detail pesanan...
    </div>

    <div v-else-if="loadError" class="admin-panel admin-form-panel" style="text-align: center; padding: 2.5rem;">
      <p style="color: #dc2626; font-weight: 600; margin-bottom: 1rem;">⚠️ {{ loadError }}</p>
      <RouterLink to="/admin/orders" class="admin-btn admin-btn-primary">
        Kembali ke Daftar Pesanan
      </RouterLink>
    </div>

    <template v-else-if="order">
      <div v-if="actionMessage" class="admin-inline-success" style="margin-bottom: 1rem; display: block;">
        ✓ {{ actionMessage }}
      </div>

      <div v-if="errorMessage" class="admin-inline-error" style="margin-bottom: 1rem; color: #dc2626; background: #fef2f2; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem;">
        ⚠️ {{ errorMessage }}
      </div>

      <div class="admin-order-detail-grid">
        <!-- Sisi Kiri: Rincian Produk, Pengiriman & Log -->
        <div class="admin-detail-main">
          <article class="admin-panel admin-form-panel">
            <div class="admin-panel-heading">
              <div>
                <h2>Item Pesanan</h2>
                <p>
                  Rincian produk yang dipesan pelanggan.
                  <span v-if="order.has_preorder_items" class="order-po-badge">
                    Mengandung Pre-Order{{ order.preorder_estimate_days ? ` (Est. ${order.preorder_estimate_days} hari)` : '' }}
                  </span>
                </p>
              </div>
              <AdminBadge :tone="statusTone">{{ formatStatusText(currentOrderStatus) }}</AdminBadge>
            </div>

            <div v-for="item in order.items" :key="item.id" class="admin-order-item">
              <span class="admin-product-thumb large">
                <img
                  v-if="item.produk?.primary_foto?.image_url || item.produk?.image_url"
                  :src="item.produk?.primary_foto?.image_url || item.produk?.image_url"
                  :alt="item.product_name"
                />
                <span v-else>R</span>
              </span>
              <div class="admin-order-item-name">
                <strong>{{ item.product_name || item.produk?.name || 'Produk RiasKu' }}</strong>
                <small>{{ item.variant_name || item.varian?.name || 'Standar' }}</small>
                <span v-if="item.is_preorder" class="order-po-badge" style="margin-top: 4px;">
                  Pre-Order{{ item.preorder_estimate_days ? ` · Est. ${item.preorder_estimate_days} hari` : '' }}
                </span>
              </div>
              <span>{{ item.quantity }} pcs</span>
              <strong>{{ formatRupiah(item.unit_price ?? 0) }}</strong>
              <strong>{{ formatRupiah(item.subtotal ?? item.line_total ?? ((item.unit_price ?? 0) * item.quantity)) }}</strong>
            </div>

            <div class="admin-order-totals">
              <div>
                <span>Subtotal Produk</span>
                <strong>{{ formatRupiah(totalProductAmount) }}</strong>
              </div>
              <div>
                <span>Ongkos Kirim ({{ shippingCourierLabel }})</span>
                <strong>{{ formatRupiah(order.shipping_cost || 0) }}</strong>
              </div>
              <div class="grand">
                <span>Total Bayar</span>
                <strong>{{ formatRupiah(totalAmount) }}</strong>
              </div>
            </div>
          </article>

          <article class="admin-panel admin-form-panel">
            <div class="admin-panel-heading">
              <div>
                <h2>Penerima dan Pengiriman</h2>
                <p>Informasi kontak, alamat tujuan, dan nomor resi.</p>
              </div>
              <AdminBadge v-if="isCod" tone="gold">📍 COD Khusus Surabaya</AdminBadge>
            </div>

            <div class="admin-info-grid">
              <div>
                <small>Nama Penerima</small>
                <strong>{{ customerName }}</strong>
              </div>
              <div>
                <small>Ekspedisi &amp; Layanan</small>
                <strong>{{ shippingCourierLabel }}</strong>
              </div>
              <div>
                <small>Nomor WhatsApp</small>
                <strong>{{ customerPhone }}</strong>
              </div>
              <div>
                <small>Nomor Resi Pengiriman</small>
                <strong>{{ order.tracking_number || (isCod ? 'Kurir Toko RiasKu (Tanpa Resi Ekspedisi)' : 'Belum diisi') }}</strong>
              </div>
              <div class="full">
                <small>Alamat Lengkap Tujuan</small>
                <strong>
                  {{ order.shipping_address }},
                  {{ shippingDistrict ? `${shippingDistrict}, ` : '' }}{{ shippingCity }} {{ shippingPostalCode }}
                </strong>
              </div>
              <div v-if="order.notes" class="full">
                <small>Catatan Tambahan Pelanggan</small>
                <em>"{{ order.notes }}"</em>
              </div>
            </div>
          </article>

          <!-- Riwayat Audit Perubahan Status -->
          <article class="admin-panel admin-form-panel">
            <div class="admin-panel-heading">
              <div>
                <h2>Riwayat Perubahan Status</h2>
                <p>Catatan kronologis progres pesanan dan pembaruan resi.</p>
              </div>
            </div>

            <div v-if="!order.status_logs?.length" class="admin-empty" style="padding: 1rem;">
              Belum ada catatan log tambahan untuk pesanan ini.
            </div>

            <div v-else class="status-logs-list">
              <div v-for="log in order.status_logs" :key="log.id" class="status-log-row">
                <div class="log-badge-col">
                  <AdminBadge :tone="mapToneForLog(log.to_status)">{{ formatStatusText(log.to_status) }}</AdminBadge>
                  <small>{{ formatDateTime(log.created_at) }}</small>
                </div>
                <div class="log-body-col">
                  <p style="margin: 0; font-weight: 600; font-size: 0.88rem; color: #3b1e2e;">
                    {{ log.note || `Perubahan status ke ${formatStatusText(log.to_status)}` }}
                  </p>
                  <small style="color: #6b7280;">
                    Oleh: {{ log.actor_name || log.actor_type }}
                    <template v-if="log.tracking_number"> · Resi: <strong>{{ log.tracking_number }}</strong></template>
                  </small>
                </div>
              </div>
            </div>
          </article>
        </div>

        <!-- Sisi Kanan: Status & Pembayaran -->
        <aside class="admin-detail-side">
          <article class="admin-panel admin-form-panel">
            <div class="admin-panel-heading">
              <div>
                <h2>Status Pesanan</h2>
              </div>
              <AdminBadge :tone="statusTone">{{ formatStatusText(currentOrderStatus) }}</AdminBadge>
            </div>

            <div v-if="currentOrderStatus === 'dibatalkan'" class="cancelled-side-banner">
              ✕ Pesanan ini telah dibatalkan. Stok produk non Pre-Order yang sebelumnya dipotong telah dipulihkan otomatis.
            </div>

            <div v-else class="admin-order-timeline">
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

            <template v-if="!isTerminalStatus">
              <label class="admin-field">
                Ubah Status Pesanan
                <select v-model="selectedStatus">
                  <option
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    :value="opt.value"
                    :disabled="!isStatusOptionEnabled(opt.value)"
                  >
                    {{ opt.label }}{{ opt.value === currentOrderStatus ? ' (Saat ini)' : (!isStatusOptionEnabled(opt.value) ? ' — Tidak diizinkan' : '') }}
                  </option>
                </select>
              </label>

              <template v-if="selectedStatus === 'dikirim'">
                <label class="admin-field">
                  Nomor Resi Pengiriman
                  <em v-if="!isCod"> *</em>
                  <small v-else style="font-weight: 400; color: #6b7280;"> (Opsional untuk COD)</small>
                  <input
                    v-model="trackingNumber"
                    placeholder="Contoh: JNE-0192837465"
                    :required="!isCod"
                  />
                </label>

                <label class="admin-field">
                  Nama Ekspedisi / Kurir (Opsional)
                  <input
                    v-model="shippingCourier"
                    placeholder="Contoh: JNE Regular / J&amp;T Express"
                  />
                </label>
              </template>

              <label class="admin-field">
                Catatan {{ requiresManualPaymentNote ? 'Verifikasi Pembayaran Manual' : 'Perubahan Status' }}
                <em v-if="requiresManualPaymentNote"> *</em>
                <small v-else style="font-weight: 400; color: #6b7280;"> (Opsional)</small>
                <textarea
                  v-model="statusNote"
                  rows="2"
                  :required="requiresManualPaymentNote"
                  :placeholder="requiresManualPaymentNote ? 'Wajib diisi: mis. Bukti transfer diverifikasi manual oleh admin' : 'Contoh: Paket diserahkan ke kurir / Alasan pembatalan...'"
                ></textarea>
              </label>

              <button
                class="admin-btn admin-btn-primary admin-submit"
                type="button"
                :disabled="savingStatus || !isStatusOptionEnabled(selectedStatus)"
                @click="handleSaveStatus"
              >
                {{ savingStatus ? 'Menyimpan...' : 'Simpan Perubahan Status' }}
              </button>
            </template>

            <div v-else class="admin-inline-success" style="display: block; text-align: center; margin-top: 0.75rem;">
              Status pesanan sudah final ({{ formatStatusText(currentOrderStatus) }}).
            </div>
          </article>

          <!-- Informasi Pembayaran (PPL-19 & PPL-20) -->
          <article class="admin-panel admin-form-panel">
            <div class="admin-panel-heading">
              <h2>Informasi Pembayaran</h2>
              <AdminBadge :tone="paymentTone">
                {{ isPaid ? 'Lunas' : formatPaymentStatus(order.payment_status) }}
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
                <strong>{{ formatRupiah(totalAmount) }}</strong>
              </div>
            </div>

            <!-- PPL-20: Tombol Konfirmasi Uang Tunai Diterima untuk COD Surabaya -->
            <div v-if="isCod && currentOrderStatus !== 'dibatalkan'" style="margin-top: 1rem;">
              <button
                v-if="!isPaid && ['dikirim', 'selesai'].includes(currentOrderStatus)"
                class="admin-btn admin-btn-primary admin-submit"
                type="button"
                :disabled="isConfirmingCod"
                style="background: #16a34a; border-color: #16a34a;"
                @click="handleConfirmCodPayment"
              >
                {{ isConfirmingCod ? 'Memproses Konfirmasi...' : '💵 Uang Tunai Diterima' }}
              </button>
              <p
                v-else-if="!isPaid"
                style="margin: 0; font-size: 0.8rem; color: #6b7280; background: #f9fafb; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px dashed #d1d5db;"
              >
                Ubah status pesanan ke <strong>Dikirim</strong> terlebih dahulu saat kurir berangkat, lalu konfirmasi penerimaan uang tunai COD.
              </p>
              <div v-else class="admin-inline-success" style="display: block; text-align: center; font-weight: 600;">
                ✓ Pembayaran COD Telah Selesai
              </div>
            </div>
          </article>
        </aside>
      </div>
    </template>
  </section>
</template>

<style scoped>
.order-po-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  font-size: 11px;
  font-weight: 700;
}
.cancelled-side-banner {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  line-height: 1.45;
  margin-bottom: 1rem;
}
.status-logs-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.status-log-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  padding: 0.75rem;
  border-radius: 8px;
  background: #faf7f8;
  border: 1px solid #f1e4e8;
}
.log-badge-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 145px;
}
.log-badge-col small {
  font-size: 0.75rem;
  color: #8a6f76;
}
.log-body-col {
  flex: 1;
}
</style>
