import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { useAiChatStore } from '@/stores/aiChat'

export const useAuthStore = defineStore('auth', () => {
  function readUser() {
    try {
      return JSON.parse(localStorage.getItem('riasku_user') || 'null')
    } catch {
      return null
    }
  }

  const user = ref(readUser())
  const accessToken = ref(localStorage.getItem('riasku_access_token') || sessionStorage.getItem('riasku_access_token'))
  const isAuthenticated = computed(() => Boolean(accessToken.value))

  function setSession(payload) {
    user.value = payload.user ?? null
    accessToken.value = payload.token ?? payload.accessToken ?? null
    try {
      if (accessToken.value) localStorage.setItem('riasku_access_token', accessToken.value)
      if (user.value) localStorage.setItem('riasku_user', JSON.stringify(user.value))
    } catch {
      // Ignore storage unavailability
    }
    useAiChatStore().invalidateInit()
  }

  function clearSession() {
    user.value = null
    accessToken.value = null
    try {
      localStorage.removeItem('riasku_access_token')
      localStorage.removeItem('riasku_user')
      sessionStorage.removeItem('riasku_access_token')
      localStorage.removeItem('riasku_cart')
      localStorage.removeItem('riasku_ai_session_token')
      localStorage.removeItem('riasku_ai_session_tokens')
    } catch {
      // Ignore storage unavailability
    }
    useAiChatStore().clearAllSessionsLocal()
  }

  return { user, accessToken, isAuthenticated, setSession, clearSession }
})
