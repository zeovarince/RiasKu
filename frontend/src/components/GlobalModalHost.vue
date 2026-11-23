<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { login as loginRequest, register as registerRequest } from '@/services/authService'
import { createProductReview } from '@/services/reviewService'
import AiChatPanel from '@/components/AiChatPanel.vue'
import ProductArtwork from '@/components/ProductArtwork.vue'

const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()
const modal = computed(() => ui.activeModal)
const authMode = ref('login')
const authForm = ref({ name: '', email: '', password: '' })
const authError = ref('')
const authNotice = ref('')
const authLoading = ref(false)
const reviewRating = ref(5)
const reviewText = ref('')
const reviewTags = ref(['Tahan Seharian'])
const reviewSubmitted = ref(false)
const reviewSubmitting = ref(false)
const reviewError = ref('')

watch(modal, (value) => {
  if (value?.type === 'login' || value?.type === 'register') {
    authMode.value = value.type
    authError.value = ''
    authNotice.value = ''
  }
  if (value?.type === 'review') {
    reviewRating.value = 5
    reviewText.value = ''
    reviewTags.value = ['Tahan Seharian']
    reviewSubmitted.value = false
    reviewSubmitting.value = false
    reviewError.value = ''
  }
})

function close() {
  ui.closeModal()
}

function onKeydown(event) {
  if (event.key === 'Escape') close()
}

if (typeof window !== 'undefined') window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

function openAuth(mode) {
  authMode.value = mode
  authError.value = ''
  authNotice.value = ''
  ui.openModal(mode)
}

async function submitAuth() {
  authLoading.value = true
  authError.value = ''
  authNotice.value = ''
  try {
    const result = authMode.value === 'login'
      ? await loginRequest({ email: authForm.value.email, password: authForm.value.password })
      : await registerRequest(authForm.value)
    if (result?.mock) {
      authNotice.value = 'Simulasi berhasil. Hubungkan VITE_USE_MOCK_DATA=false saat endpoint Laravel siap.'
      return
    }
    close()
    await router.push('/')
  } catch (error) {
    authError.value = error?.response?.data?.message || error?.message || 'Autentikasi belum dapat diproses. Periksa data dan koneksi API.'
  } finally {
    authLoading.value = false
  }
}

function toggleReviewTag(tag) {
  if (reviewTags.value.includes(tag)) {
    reviewTags.value = reviewTags.value.filter((item) => item !== tag)
  } else {
    reviewTags.value = [...reviewTags.value, tag]
  }
}

async function submitReview() {
  if (!reviewText.value.trim()) return

  if (!auth.isAuthenticated) {
    ui.openModal('login')
    return
  }

  const productId = modal.value?.payload?.product?.id
  if (!productId) return

  reviewSubmitting.value = true
  reviewError.value = ''

  try {
    const note = reviewTags.value.length ? `\n\nPoin: ${reviewTags.value.join(', ')}` : ''
    await createProductReview(productId, {
      rating: reviewRating.value,
      comment: `${reviewText.value.trim()}${note}`,
    })

    reviewSubmitted.value = true
    window.dispatchEvent(new CustomEvent('riasku:review-created', {
      detail: { productId: String(productId) },
    }))
  } catch (error) {
    reviewError.value = error.message || 'Gagal mengirim ulasan.'
  } finally {
    reviewSubmitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modal" class="modal-backdrop" @click.self="close">
        <section class="modal-dialog" :class="`modal-${modal.type}`" role="dialog" aria-modal="true" :aria-label="modal.type === 'ai' ? 'Asisten RiasKu' : modal.type === 'review' ? 'Tulis ulasan' : 'Autentikasi RiasKu'">
          <button class="modal-close" type="button" aria-label="Tutup modal" @click="close">×</button>

          <template v-if="modal.type === 'ai'">
            <div class="modal-heading ai-modal-heading">
              <div class="assistant-avatar">✦</div>
              <div>
                <h2>Asisten Cerdas RiasKu</h2>
                <p><span class="online-dot"></span> AI Beauty Advisor · Multi-LLM &amp; Dikte Suara</p>
              </div>
            </div>
            <AiChatPanel
              mode="modal"
              :context-product-id="modal.payload?.productId || null"
              :initial-prompt="modal.payload?.initialPrompt || ''"
            />
          </template>

          <template v-else-if="modal.type === 'login' || modal.type === 'register'">
            <div class="auth-modal-brand">RiasKu<span>.</span></div>
            <span class="eyebrow-badge">{{ authMode === 'login' ? 'SELAMAT DATANG KEMBALI' : 'GABUNG BERSAMA RIASKU' }}</span>
            <h2 class="modal-title">{{ authMode === 'login' ? 'Masuk ke akun Anda' : 'Buat Akun RiasKu' }}</h2>
            <p class="modal-description">{{ authMode === 'login' ? 'Masuk untuk melihat pesanan dan menyimpan produk favorit.' : 'Daftar untuk menikmati pengalaman belanja dan layanan RiasKu.' }}</p>
            <form class="auth-card auth-modal-form" @submit.prevent="submitAuth">
              <label v-if="authMode === 'register'">Nama lengkap<input v-model="authForm.name" class="form-control" autocomplete="name" required /></label>
              <label>Email<input v-model="authForm.email" class="form-control" type="email" autocomplete="email" required /></label>
              <label>Kata sandi<input v-model="authForm.password" class="form-control" type="password" :autocomplete="authMode === 'login' ? 'current-password' : 'new-password'" minlength="8" required /></label>
              <p v-if="authError" class="form-error">{{ authError }}</p>
              <p v-if="authNotice" class="form-notice">{{ authNotice }}</p>
              <button class="button button-primary auth-submit" type="submit" :disabled="authLoading">{{ authLoading ? 'Memproses...' : authMode === 'login' ? 'Masuk' : 'Daftar Sekarang' }}</button>
            </form>
            <p class="auth-switch">{{ authMode === 'login' ? 'Belum punya akun?' : 'Sudah punya akun?' }} <button type="button" @click="openAuth(authMode === 'login' ? 'register' : 'login')">{{ authMode === 'login' ? 'Daftar sekarang' : 'Masuk' }}</button></p>
          </template>

          <template v-else-if="modal.type === 'review'">
            <span class="eyebrow-badge">PENGALAMAN PELANGGAN RIASKU</span>
            <h2 class="modal-title">Tulis Ulasan &amp; Penilaian Produk</h2>
            <p class="modal-description">Ulasan jujur Anda membantu calon pembeli memilih produk dengan lebih tepat.</p>
            <div class="review-product-line">
              <div class="review-product-art"><ProductArtwork :type="modal.payload.product?.art || 'cushion'" /></div>
              <div><strong>{{ modal.payload.product?.name || 'Produk RiasKu' }}</strong><small>RiasKu Official Store · Produk pilihan</small></div>
            </div>
            <template v-if="!reviewSubmitted">
              <div class="review-rating-picker" aria-label="Pilih rating">
                <button v-for="star in 5" :key="star" type="button" :class="{ selected: star <= reviewRating }" :aria-label="`${star} bintang`" @click="reviewRating = star">★</button>
              </div>
              <p class="rating-caption">{{ ['','Kurang Puas','Cukup Puas','Baik','Sangat Baik','Sangat Puas (Bintang 5)'][reviewRating] }}</p>
              <div class="review-tags">
                <span>Poin yang paling berkesan:</span>
                <button v-for="tag in ['Tahan Seharian','Tekstur Ringan','Shade Sesuai Kulit','Cocok untuk Kulit Sensitif','Packaging Bubble Tebal']" :key="tag" type="button" :class="{ selected: reviewTags.includes(tag) }" @click="toggleReviewTag(tag)">{{ tag }}</button>
              </div>
              <label class="review-text-label">Ceritakan pengalaman Anda<textarea v-model="reviewText" class="form-control" rows="4" maxlength="500" placeholder="Bagaimana produk ini saat digunakan? Ceritakan pengalaman Anda..."></textarea></label>
              <div class="review-count">{{ reviewText.length }}/500 karakter</div>
              <p v-if="reviewError" class="form-error">{{ reviewError }}</p>
              <div class="modal-actions"><button type="button" class="button button-outline" @click="close">Batal</button><button type="button" class="button button-primary" :disabled="!reviewText.trim() || reviewSubmitting" @click="submitReview">{{ reviewSubmitting ? 'Mengirim...' : 'Kirim Ulasan Sekarang' }}</button></div>
            </template>
            <div v-else class="review-success"><span>✓</span><strong>Terima kasih atas ulasan Anda!</strong><p>Ulasan Anda berhasil disimpan dan akan tampil pada halaman produk.</p><button class="button button-primary" type="button" @click="close">Selesai</button></div>
          </template>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
