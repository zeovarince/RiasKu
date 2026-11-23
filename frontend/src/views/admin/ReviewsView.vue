<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import { adminService } from '@/services/adminService'

const search = ref('')
const statusFilter = ref('all')
const reviews = ref([])
const loading = ref(false)
const error = ref('')
const notice = ref('')

const filtered = computed(() => reviews.value)

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function statusLabel(status) {
  if (status === 'approved') return 'Tampil'
  if (status === 'hidden') return 'Disembunyikan'
  return 'Ditinjau'
}

function statusTone(status) {
  return status === 'approved' ? 'green' : 'gold'
}

async function loadReviews() {
  loading.value = true
  error.value = ''

  try {
    const response = await adminService.reviews({
      q: search.value,
      status: statusFilter.value,
      per_page: 50,
    })

    reviews.value = (response.data?.data ?? []).map((review) => ({
      id: review.id,
      product: review.product_name || `Produk #${review.product_id}`,
      customer: review.customer,
      rating: Number(review.rating).toFixed(1),
      text: review.comment,
      photo: '—',
      date: formatDate(review.created_at),
      status: statusLabel(review.status),
      tone: statusTone(review.status),
      rawStatus: review.status,
    }))
  } catch (err) {
    error.value = err.response?.data?.message || err.message || 'Gagal memuat ulasan.'
    reviews.value = []
  } finally {
    loading.value = false
  }
}

async function toggleStatus(review) {
  error.value = ''
  notice.value = ''

  const nextStatus = review.rawStatus === 'approved' ? 'hidden' : 'approved'

  try {
    const response = await adminService.updateReview(review.id, { status: nextStatus })
    review.rawStatus = response.data?.data?.status ?? nextStatus
    review.status = statusLabel(review.rawStatus)
    review.tone = statusTone(review.rawStatus)
    notice.value = 'Status ulasan berhasil diperbarui.'
  } catch (err) {
    error.value = err.response?.data?.message || err.message || 'Gagal memperbarui status ulasan.'
  }
}

onMounted(loadReviews)
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / PELANGGAN / ULASAN" title="Ulasan" description="Tinjau dan moderasi ulasan produk dari pelanggan." />
    <article class="admin-panel">
      <div class="admin-toolbar">
        <div class="admin-panel-title"><h2>Daftar Ulasan</h2><AdminBadge>{{ reviews.length }} ulasan</AdminBadge></div>
        <div class="admin-toolbar-actions">
          <label class="admin-search"><input v-model="search" placeholder="Cari produk / pelanggan..." @change="loadReviews" @keyup.enter="loadReviews" /></label>
          <select v-model="statusFilter" class="admin-select" @change="loadReviews">
            <option value="all">Semua status</option>
            <option value="approved">Tampil</option>
            <option value="hidden">Disembunyikan</option>
          </select>
        </div>
      </div>

      <p v-if="loading" class="admin-panel-hint">Memuat ulasan...</p>
      <p v-if="error" class="form-error">{{ error }}</p>
      <p v-if="notice" class="admin-inline-success">{{ notice }}</p>

      <div class="admin-table-wrap">
        <table class="admin-table admin-review-table">
          <thead><tr><th>Produk & Variasi</th><th>Pemberi Ulasan</th><th>Rating</th><th>Komentar</th><th>Foto</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <tr v-for="review in filtered" :key="review.id">
              <td><strong>{{ review.product }}</strong></td>
              <td>{{ review.customer }}</td>
              <td><strong class="admin-rating">★ {{ review.rating }}</strong></td>
              <td class="review-comment">{{ review.text }}</td>
              <td>{{ review.photo }}</td>
              <td>{{ review.date }}</td>
              <td><AdminBadge :tone="review.tone">{{ review.status }}</AdminBadge></td>
              <td>
                <div class="admin-row-actions">
                  <button class="admin-mini-link" type="button" @click="toggleStatus(review)">
                    {{ review.rawStatus === 'approved' ? 'Sembunyikan' : 'Tampilkan' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && !filtered.length">
              <td colspan="8" class="admin-empty">Belum ada ulasan yang cocok.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>
