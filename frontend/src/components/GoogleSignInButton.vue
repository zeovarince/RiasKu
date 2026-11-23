<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  mode: { type: String, default: 'signin' },
  redirect: { type: String, default: '' },
})

const route = useRoute()

// Alur redirect OAuth 2.0 (server-side). Ini tidak bergantung pada iframe
// accounts.google.com, sehingga tetap berfungsi saat browser memblokir
// storage/cookie pihak ketiga (Edge Tracking Prevention, mode privat, dll).
const googleAuthUrl = computed(() => {
  const base = import.meta.env.VITE_GOOGLE_AUTH_URL || 'http://127.0.0.1:8000/auth/google/redirect'

  try {
    const url = new URL(base)
    url.searchParams.set('mode', props.mode === 'signup' ? 'signup' : 'signin')

    const queryRedirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    const target = props.redirect || queryRedirect

    if (target.startsWith('/') && !target.startsWith('//') && !target.startsWith('/admin')) {
      url.searchParams.set('redirect', target)
    }

    return url.toString()
  } catch {
    return '#'
  }
})

const label = computed(() => (props.mode === 'signup' ? 'Daftar dengan Google' : 'Masuk dengan Google'))
</script>

<template>
  <a
    class="google-signin-button"
    :class="{ 'is-disabled': disabled }"
    :href="disabled ? undefined : googleAuthUrl"
    :aria-disabled="disabled ? 'true' : 'false'"
    :tabindex="disabled ? -1 : 0"
    @click="disabled && $event.preventDefault()"
  >
    <svg class="google-signin-logo" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
    </svg>
    <span>{{ label }}</span>
  </a>
</template>

<style scoped>
.google-signin-button {
  width: 100%;
  max-width: 360px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 10px 18px;
  border: 1px solid #dadce0;
  border-radius: 999px;
  background: #fff;
  color: #3c4043;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.google-signin-button:hover {
  background: #f8f9fa;
  box-shadow: 0 1px 3px rgba(60, 64, 67, 0.24);
  transform: translateY(-1px);
}

.google-signin-button:focus-visible {
  outline: 2px solid #4285f4;
  outline-offset: 2px;
}

.google-signin-button.is-disabled {
  pointer-events: none;
  opacity: 0.6;
}

.google-signin-logo {
  width: 18px;
  height: 18px;
  flex: none;
}
</style>
