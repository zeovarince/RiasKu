<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { loginAdmin } from '@/services/adminAuthService'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const errorMessage = ref('')

async function submit() {
  isLoading.value = true
  errorMessage.value = ''
  try {
    auth.setSession(await loginAdmin({ email: email.value, password: password.value }))
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/admin')
      ? route.query.redirect
      : '/admin/dashboard'
    await router.replace(redirect)
  } catch (error) {
    const errors = error.response?.data?.errors
    errorMessage.value = errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || error.message || 'Gagal menghubungi server dashboard.')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <form class="surface-card auth-card" @submit.prevent="submit">
    <span class="eyebrow-badge">PANEL PEMILIK</span>
    <h1>Masuk Dashboard</h1>
    <p v-if="errorMessage" class="form-error" role="alert">{{ errorMessage }}</p>
    <label>Email<input v-model.trim="email" class="form-control" type="email" autocomplete="username" required /></label>
    <label>Kata sandi<input v-model="password" class="form-control" type="password" autocomplete="current-password" required /></label>
    <button class="button button-primary" type="submit" :disabled="isLoading">{{ isLoading ? 'Memproses…' : 'Masuk' }}</button>
  </form>
</template>
