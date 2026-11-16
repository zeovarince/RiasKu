import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

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
    if (accessToken.value) localStorage.setItem('riasku_access_token', accessToken.value)
    if (user.value) localStorage.setItem('riasku_user', JSON.stringify(user.value))
  }

  function clearSession() {
    user.value = null
    accessToken.value = null
    localStorage.removeItem('riasku_access_token')
    localStorage.removeItem('riasku_user')
    sessionStorage.removeItem('riasku_access_token')
  }

  return { user, accessToken, isAuthenticated, setSession, clearSession }
})
