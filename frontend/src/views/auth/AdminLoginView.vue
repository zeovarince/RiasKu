<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthPage from '@/components/AuthPage.vue'
import { loginAdmin } from '@/services/adminAuthService'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const fieldErrors = ref({})
const showPassword = ref(false)

async function submit() {
  isLoading.value = true
  errorMessage.value = ''
  fieldErrors.value = {}
  try {
    const sessionData = await loginAdmin({
      email: email.value,
      password: password.value,
    })
    auth.setSession(sessionData)
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/admin')
      ? route.query.redirect
      : '/admin/dashboard'
    await router.replace(redirect)
  } catch (error) {
    const errors = error.response?.data?.errors
    fieldErrors.value = errors || {}
    errorMessage.value = errors
      ? Object.values(errors).flat()[0]
      : (error.response?.data?.message || error.message || 'Gagal menghubungi server dashboard.')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <AuthPage
    :show-tabs="false"
    badge="PANEL PEMILIK"
    title="Masuk Dashboard"
    subtitle="Akses khusus pemilik dan pengelola katalog toko RiasKu."
  >
    <div v-if="errorMessage" class="alert show" role="alert">
      <span>{{ errorMessage }}</span>
    </div>

    <form class="auth-form" @submit.prevent="submit">
      <div class="field">
        <label for="adminEmail">Email</label>
        <div class="control">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>
          <input
            id="adminEmail"
            v-model.trim="email"
            name="email"
            type="email"
            inputmode="email"
            autocomplete="username"
            placeholder="admin@riasku.com"
            :aria-invalid="Boolean(fieldErrors.email)"
            aria-describedby="adminEmailErr"
            required
          />
        </div>
        <p id="adminEmailErr" class="err">{{ fieldErrors.email?.[0] || '' }}</p>
      </div>

      <div class="field">
        <label for="adminPass">Kata sandi</label>
        <div class="control">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input
            id="adminPass"
            v-model="password"
            name="password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            placeholder="••••••••"
            :aria-invalid="Boolean(fieldErrors.password)"
            aria-describedby="adminPassErr"
            required
          />
          <button
            class="toggle"
            type="button"
            :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
          </button>
        </div>
        <p id="adminPassErr" class="err">{{ fieldErrors.password?.[0] || '' }}</p>
      </div>

      <button class="btn" :class="{ loading: isLoading }" type="submit" :disabled="isLoading">
        <span v-if="isLoading" class="spinner" aria-hidden="true"></span>
        <span>{{ isLoading ? 'Memproses…' : 'Masuk Dashboard' }}</span>
      </button>
    </form>
  </AuthPage>
</template>
