import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const accessToken = ref(null)
  const isAuthenticated = computed(() => Boolean(accessToken.value))

  function setSession(payload) {
    user.value = payload.user ?? null
    accessToken.value = payload.token ?? null
  }

  function clearSession() {
    user.value = null
    accessToken.value = null
  }

  return { user, accessToken, isAuthenticated, setSession, clearSession }
})
