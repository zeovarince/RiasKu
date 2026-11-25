<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthPage from '@/components/AuthPage.vue'
import { exchangeGoogleCode } from '@/services/authService'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const cart = useCartStore()
const errorMessage = ref('')
const mode = computed(() => route.query.mode === 'signup' ? 'signup' : 'signin')
const pageMode = computed(() => mode.value === 'signup' ? 'register' : 'login')
const retryRoute = computed(() => ({
  name: pageMode.value,
  query: isSafeRedirect(route.query.redirect) ? { redirect: route.query.redirect } : {},
}))

function isSafeRedirect(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !value.startsWith('/admin')
}

function messageForReason(reason) {
  const messages = {
    config_missing: 'Konfigurasi Google belum lengkap. Silakan hubungi pengelola toko.',
    state_mismatch: 'Sesi login Google tidak cocok. Silakan coba lagi.',
    access_denied: 'Login Google dibatalkan.',
    missing_code: 'Google tidak mengirim kode login. Silakan coba lagi.',
    token_exchange_failed: 'Google gagal menyelesaikan login. Silakan coba lagi.',
    identity_invalid: 'Identitas Google tidak valid atau email belum terverifikasi.',
    inactive: 'Akun pelanggan ini sedang dinonaktifkan.',
    account_conflict: 'Email ini sudah terhubung dengan akun Google lain.',
  }

  return messages[reason] || 'Login dengan Google gagal. Silakan coba lagi.'
}

async function finishGoogleLogin() {
  const reason = typeof route.query.error === 'string' ? route.query.error : ''
  const code = typeof route.query.code === 'string' ? route.query.code : ''

  if (reason) {
    errorMessage.value = messageForReason(reason)
    return
  }

  if (!code) {
    errorMessage.value = 'Kode login Google tidak ditemukan. Silakan mulai login kembali.'
    return
  }

  try {
    const session = await exchangeGoogleCode(code)
    auth.setSession(session)
    await cart.hydrateCart().catch(() => {})
    await router.replace(isSafeRedirect(route.query.redirect) ? route.query.redirect : '/')
  } catch (error) {
    errorMessage.value = error.response?.data?.message || error.message || 'Login dengan Google gagal. Silakan coba lagi.'
  }
}

onMounted(finishGoogleLogin)
</script>

<template>
  <AuthPage
    :active="pageMode"
    title="Menghubungkan akun Google"
    subtitle="Kami sedang menyelesaikan proses masuk ke RiasKu."
    :show-tabs="false"
  >
    <div v-if="errorMessage" class="alert show" role="alert">
      <span>{{ errorMessage }}</span>
    </div>
    <div v-else class="alert show ok" role="status">
      <span>Memverifikasi sesi Google...</span>
    </div>
    <p v-if="errorMessage" class="switch">
      <RouterLink class="link-btn" :to="retryRoute">Kembali dan coba lagi</RouterLink>
    </p>
  </AuthPage>
</template>
