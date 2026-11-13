<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useUiStore } from '@/stores/ui'
import { useRouter } from 'vue-router'
import { login as loginRequest, register as registerRequest } from '@/services/authService'
import ProductArtwork from '@/components/ProductArtwork.vue'

const ui = useUiStore()
const router = useRouter()
const modal = computed(() => ui.activeModal)
const authMode = ref('login')
const authForm = ref({ name: '', email: '', password: '' })
const authError = ref('')
const authNotice = ref('')
const authLoading = ref(false)
const chatQuestion = ref('')
const chatMessages = ref([
  { role: 'assistant', text: 'Halo, selamat datang di RiasKu Studio. Mau kami bantu menemukan produk kosmetik atau layanan rias yang sesuai kebutuhanmu?' },
])
const reviewRating = ref(5)
const reviewText = ref('')
const reviewTags = ref(['Tahan Seharian'])
const reviewSubmitted = ref(false)

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

function sendChat() {
  const message = chatQuestion.value.trim()
  if (!message) return
  chatMessages.value.push({ role: 'user', text: message })
  chatMessages.value.push({ role: 'assistant', text: 'Terima kasih! Asisten RiasKu akan terhubung ke endpoint AI setelah layanan backend disiapkan. Untuk sementara, kamu bisa menjelajahi katalog produk kami.' })
  chatQuestion.value = ''
}

function toggleReviewTag(tag) {
  reviewTags.value = reviewTags.value.includes(tag)
    ? reviewTags.value.filter((item) => item !== tag)
    : [...reviewTags.value, tag]
}

function submitReview() {
  if (!reviewText.value.trim()) return
  reviewSubmitted.value = true
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
                <p><span class="online-dot"></span> AI Beauty Advisor Surabaya</p>
              </div>
            </div>
            <div class="chat-suggestions">
              <span>PERTANYAAN REKOMENDASI</span>
              <button type="button" @click="chatQuestion = 'Cari cushion untuk kulit kombinasi'">Cushion untuk kulit kombinasi</button>
              <button type="button" @click="chatQuestion = 'Rekomendasi rias wisuda'">Paket rias wisuda</button>
            </div>
            <div class="chat-messages modal-chat-messages" aria-live="polite">
              <div v-for="(message, index) in chatMessages" :key="index" :class="['chat-bubble', message.role]">{{ message.text }}</div>
            </div>
            <form class="chat-composer" @submit.prevent="sendChat">
              <input v-model="chatQuestion" class="form-control" placeholder="Ketik pertanyaan makeup / riasan..." aria-label="Pesan untuk asisten AI" />
              <button class="button button-primary" type="submit" aria-label="Kirim pesan">➤</button>
            </form>
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
              <div class="modal-actions"><button type="button" class="button button-outline" @click="close">Batal</button><button type="button" class="button button-primary" :disabled="!reviewText.trim()" @click="submitReview">Kirim Ulasan Sekarang</button></div>
            </template>
            <div v-else class="review-success"><span>✓</span><strong>Terima kasih atas ulasan Anda!</strong><p>Ulasan berhasil dicatat pada sesi demo frontend.</p><button class="button button-primary" type="button" @click="close">Selesai</button></div>
          </template>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
