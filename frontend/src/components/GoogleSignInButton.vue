<script setup>
import { onMounted, ref } from 'vue'

const emit = defineEmits(['credential'])
const props = defineProps({
  disabled: { type: Boolean, default: false },
  mode: { type: String, default: 'signin' },
})
const buttonHost = ref(null)
const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
const loadError = ref('')

function loadGoogleScript() {
  if (window.google?.accounts?.id) return Promise.resolve()
  if (window.riaskuGoogleScript) return window.riaskuGoogleScript
  window.riaskuGoogleScript = new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client?hl=id'
    script.async = true
    script.defer = true
    script.onload = resolve
    script.onerror = () => reject(new Error('Layanan Google tidak dapat dimuat.'))
    document.head.append(script)
  })
  return window.riaskuGoogleScript
}

onMounted(async () => {
  if (!clientId) {
    loadError.value = 'Client ID Google belum diatur di frontend/.env.'
    return
  }
  try {
    await loadGoogleScript()
    window.google.accounts.id.initialize({
      client_id: clientId,
      callback: (response) => emit('credential', response.credential),
    })
    window.google.accounts.id.renderButton(buttonHost.value, {
      type: 'standard', theme: 'outline', size: 'large', shape: 'pill',
      text: props.mode === 'signup' ? 'signup_with' : 'signin_with',
      width: 360,
    })
  } catch (error) {
    loadError.value = error.message
  }
})
</script>

<template>
  <div class="google-signin">
    <div ref="buttonHost" class="google-signin-host" :class="{ 'is-disabled': disabled }" :aria-disabled="disabled"></div>
    <p v-if="loadError" class="form-error" role="status">{{ loadError }}</p>
  </div>
</template>

<style scoped>
.google-signin {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin: 4px 0;
}
.google-signin-host {
  width: 100%;
  display: flex;
  justify-content: center;
  min-height: 44px;
}
.google-signin-host.is-disabled {
  pointer-events: none;
  opacity: 0.6;
}
.form-error {
  color: #b4233f;
  font-size: 12px;
  text-align: center;
  margin-top: 6px;
}
</style>
