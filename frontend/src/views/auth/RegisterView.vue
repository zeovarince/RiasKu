<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthPage from '@/components/AuthPage.vue'
import GoogleSignInButton from '@/components/GoogleSignInButton.vue'
import { register } from '@/services/authService'

const router = useRouter()
const route = useRoute()
const form = ref({ username: '', email: '', password: '', password_confirmation: '' })
const isLoading = ref(false)
const errorMessage = ref('')
const fieldErrors = ref({})
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

async function submit() {
  errorMessage.value = ''
  fieldErrors.value = {}

  if (form.value.password !== form.value.password_confirmation) {
    fieldErrors.value = { password_confirmation: ['Konfirmasi kata sandi tidak cocok.'] }
    errorMessage.value = 'Konfirmasi kata sandi tidak cocok.'
    return
  }

  isLoading.value = true
  try {
    await register(form.value)
    await router.replace({ name: 'login', query: { ...route.query, registered: '1' } })
  } catch (error) {
    const errors = error.response?.data?.errors
    fieldErrors.value = errors || {}
    errorMessage.value = errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || error.message || 'Pendaftaran gagal.')
  } finally {
    isLoading.value = false
  }
}

</script>

<template>
  <AuthPage active="register" title="Buat akun RiasKu" subtitle="Daftar gratis supaya checkout lebih cepat dan status pesanan mudah dipantau.">
    <div v-if="errorMessage" class="alert show" role="alert"><span>{{ errorMessage }}</span></div>

    <form class="auth-form" @submit.prevent="submit">
      <div class="field">
        <label for="regUsername">Username</label>
        <div class="control">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input id="regUsername" v-model.trim="form.username" name="username" type="text" autocomplete="username" minlength="2" maxlength="80" placeholder="Nama pengguna" :aria-invalid="Boolean(fieldErrors.username)" aria-describedby="regUsernameErr" required />
        </div>
        <p id="regUsernameErr" class="err">{{ fieldErrors.username?.[0] || '' }}</p>
      </div>

      <div class="field">
        <label for="regEmail">Email</label>
        <div class="control">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>
          <input id="regEmail" v-model.trim="form.email" name="email" type="email" inputmode="email" autocomplete="email" placeholder="nama@email.com" :aria-invalid="Boolean(fieldErrors.email)" aria-describedby="regEmailErr" required />
        </div>
        <p id="regEmailErr" class="err">{{ fieldErrors.email?.[0] || '' }}</p>
      </div>

      <div class="field">
        <label for="regPass">Kata sandi</label>
        <div class="control">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input id="regPass" v-model="form.password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" minlength="8" placeholder="Minimal 8 karakter" :aria-invalid="Boolean(fieldErrors.password)" aria-describedby="regPassErr" required />
          <button class="toggle" type="button" :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" :aria-pressed="showPassword" @click="showPassword = !showPassword">
            <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
          </button>
        </div>
        <p id="regPassErr" class="err">{{ fieldErrors.password?.[0] || '' }}</p>
      </div>

      <div class="field">
        <label for="regPassConfirm">Konfirmasi kata sandi</label>
        <div class="control">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input
            id="regPassConfirm"
            v-model="form.password_confirmation"
            name="password_confirmation"
            :type="showPasswordConfirmation ? 'text' : 'password'"
            autocomplete="new-password"
            minlength="8"
            placeholder="Ulangi kata sandi"
            :aria-invalid="Boolean(fieldErrors.password_confirmation)"
            aria-describedby="regPassConfirmErr"
            required
          />
          <button
            class="toggle"
            type="button"
            :aria-label="showPasswordConfirmation ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
            :aria-pressed="showPasswordConfirmation"
            @click="showPasswordConfirmation = !showPasswordConfirmation"
          >
            <svg v-if="!showPasswordConfirmation" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
          </button>
        </div>
        <p id="regPassConfirmErr" class="err">{{ fieldErrors.password_confirmation?.[0] || '' }}</p>
      </div>

      <button class="btn" :class="{ loading: isLoading }" type="submit" :disabled="isLoading">
        <span v-if="isLoading" class="spinner" aria-hidden="true"></span>
        <span>{{ isLoading ? 'Mendaftarkan…' : 'Daftar' }}</span>
      </button>
      <p class="terms">Dengan mendaftar, kamu menyetujui ketentuan dan kebijakan privasi RiasKu.</p>
    </form>

    <div class="social-auth">
      <div class="social-divider"><span>atau daftar lewat</span></div>
      <GoogleSignInButton mode="signup" :disabled="isLoading" />
    </div>
    <p class="switch">Sudah punya akun? <RouterLink class="link-btn" :to="{ name: 'login', query: route.query }">Masuk</RouterLink></p>
  </AuthPage>
</template>
