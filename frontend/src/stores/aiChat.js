import { ref } from 'vue'
import { defineStore } from 'pinia'
import {
  clearAiHistory,
  deleteAiSession,
  getAiHistory,
  getAiSessions,
  getAiStatus,
  getAiSuggestions,
  renameAiSession,
  sendAiMessage,
} from '@/services/aiService'

const SESSION_STORAGE_KEY = 'riasku_ai_session_token'
const GUEST_TOKENS_STORAGE_KEY = 'riasku_ai_session_tokens'

const WELCOME_MESSAGE = {
  id: 'welcome',
  role: 'assistant',
  content: 'Halo! Aku **Asisten Kecantikan RiasKu**. Ceritakan jenis kulit, produk yang kamu cari, kebutuhan rias acara, atau tanyakan seputar **Pre-Order**, **COD Surabaya**, dan **pelacakan resi**.',
  recommended_products: [],
  follow_up_suggestions: [],
  provider: null,
  model: null,
}

function readStoredToken() {
  try {
    return localStorage.getItem(SESSION_STORAGE_KEY) || null
  } catch {
    return null
  }
}

function saveStoredToken(token) {
  try {
    if (token) {
      localStorage.setItem(SESSION_STORAGE_KEY, token)
    } else {
      localStorage.removeItem(SESSION_STORAGE_KEY)
    }
  } catch {
    // Ignore storage unavailability
  }
}

function readGuestTokens() {
  try {
    const raw = JSON.parse(localStorage.getItem(GUEST_TOKENS_STORAGE_KEY) || '[]')
    return Array.isArray(raw) ? raw.filter((t) => typeof t === 'string' && t.length > 0).slice(0, 30) : []
  } catch {
    return []
  }
}

function writeGuestTokens(tokens) {
  try {
    localStorage.setItem(GUEST_TOKENS_STORAGE_KEY, JSON.stringify(tokens.slice(0, 30)))
  } catch {
    // Ignore storage unavailability
  }
}

function addGuestToken(token) {
  if (!token) return readGuestTokens()
  const existing = readGuestTokens().filter((t) => t !== token)
  const next = [token, ...existing].slice(0, 30)
  writeGuestTokens(next)
  return next
}

function removeGuestToken(token) {
  if (!token) return readGuestTokens()
  const next = readGuestTokens().filter((t) => t !== token)
  writeGuestTokens(next)
  return next
}

export const useAiChatStore = defineStore('aiChat', () => {
  const sessionToken = ref(readStoredToken())
  const activeSessionTitle = ref('')
  const messages = ref([{ ...WELCOME_MESSAGE }])
  const sessions = ref([])
  const aiStatus = ref({
    primary_provider: 'gemini',
    active_chain: ['gemini', 'groq', 'openrouter'],
    configured_providers: [],
    has_real_llm: false,
    models: {},
  })
  const suggestions = ref([
    'Cari cushion untuk kulit kombinasi',
    'Rekomendasi paket rias pengantin atau wisuda',
    'Produk apa saja yang sedang Pre-Order?',
    'Bagaimana cara bayar COD Surabaya & cek resi?',
  ])
  const isLoading = ref(false)
  const isHydrating = ref(false)
  const isLoadingSessions = ref(false)
  const isInitialized = ref(false)
  const error = ref('')
  const lastProvider = ref(null)
  const lastModel = ref(null)

  let mutationVersion = 0
  let logoutVersion = 0
  let inFlightInit = null
  const clearedSessionTokens = new Set()

  async function fetchSessions() {
    isLoadingSessions.value = true
    try {
      const tokens = readGuestTokens()
      const list = await getAiSessions(tokens, sessionToken.value)
      if (Array.isArray(list)) {
        sessions.value = list
      }
    } catch {
      // Keep current cached session list if transient error occurs
    } finally {
      isLoadingSessions.value = false
    }
  }

  async function initSession(force = false) {
    if (isInitialized.value && !force) return
    if (inFlightInit && !force) return inFlightInit

    const startVersion = mutationVersion
    const startLogoutVersion = logoutVersion
    const tokenAtStart = sessionToken.value
    const tokensAtStart = readGuestTokens()
    isHydrating.value = true
    error.value = ''

    const task = (async () => {
      try {
        const [suggestionList, historyData, sessionList, statusData] = await Promise.all([
          getAiSuggestions().catch(() => null),
          tokenAtStart ? getAiHistory(tokenAtStart).catch(() => null) : Promise.resolve(null),
          getAiSessions(tokensAtStart, tokenAtStart).catch(() => null),
          getAiStatus().catch(() => null),
        ])

        // Abaikan seluruh hasil jika terjadi logout saat init berjalan agar sesi privat tidak bocor kembali.
        if (logoutVersion !== startLogoutVersion) {
          return
        }

        if (Array.isArray(suggestionList) && suggestionList.length > 0) {
          suggestions.value = suggestionList
        }

        if (statusData && typeof statusData === 'object') {
          aiStatus.value = statusData
        }

        // Jangan menimpa percakapan atau daftar sesi jika pengguna sudah mengirim/mereset/pindah sesi saat init berjalan.
        if (mutationVersion === startVersion) {
          if (Array.isArray(sessionList)) {
            sessions.value = sessionList
          }

          if (historyData?.session_token) {
            sessionToken.value = historyData.session_token
            saveStoredToken(historyData.session_token)
            addGuestToken(historyData.session_token)
          }

          activeSessionTitle.value = historyData?.session_title || ''

          if (Array.isArray(historyData?.messages) && historyData.messages.length > 0) {
            messages.value = historyData.messages
            const lastAi = [...historyData.messages].reverse().find((m) => m.role === 'assistant')
            if (lastAi?.provider) lastProvider.value = lastAi.provider
          } else if (force || messages.value.length === 0) {
            messages.value = [{ ...WELCOME_MESSAGE }]
          }
        }

        isInitialized.value = true
      } finally {
        if (mutationVersion === startVersion && logoutVersion === startLogoutVersion) {
          isHydrating.value = false
        }
        if (inFlightInit === task) {
          inFlightInit = null
        }
      }
    })()

    inFlightInit = task
    return task
  }

  function invalidateInit() {
    isInitialized.value = false
    inFlightInit = null
  }

  function startNewChat() {
    mutationVersion += 1
    isLoading.value = false
    error.value = ''
    sessionToken.value = null
    activeSessionTitle.value = ''
    lastProvider.value = null
    lastModel.value = null
    saveStoredToken(null)
    messages.value = [{ ...WELCOME_MESSAGE }]
    isInitialized.value = true
  }

  function clearAllSessionsLocal() {
    mutationVersion += 1
    logoutVersion += 1
    isLoading.value = false
    error.value = ''
    sessionToken.value = null
    activeSessionTitle.value = ''
    sessions.value = []
    clearedSessionTokens.clear()
    lastProvider.value = null
    lastModel.value = null
    saveStoredToken(null)
    writeGuestTokens([])
    messages.value = [{ ...WELCOME_MESSAGE }]
    isInitialized.value = false
  }

  async function selectSession(targetToken) {
    if (!targetToken) return false
    const selectVersion = ++mutationVersion
    isLoading.value = false
    isHydrating.value = true
    error.value = ''

    try {
      const historyData = await getAiHistory(targetToken)
      if (selectVersion !== mutationVersion) {
        return false
      }
      sessionToken.value = historyData?.session_token || targetToken
      saveStoredToken(sessionToken.value)
      addGuestToken(sessionToken.value)
      activeSessionTitle.value = historyData?.session_title || ''

      if (Array.isArray(historyData?.messages) && historyData.messages.length > 0) {
        messages.value = historyData.messages
        const lastAi = [...historyData.messages].reverse().find((m) => m.role === 'assistant')
        lastProvider.value = lastAi?.provider || null
        lastModel.value = null
      } else {
        messages.value = [{ ...WELCOME_MESSAGE }]
      }
      isInitialized.value = true
      return true
    } catch (err) {
      if (selectVersion === mutationVersion) {
        error.value = err.response?.data?.message || 'Gagal memuat sesi percakapan tersebut.'
      }
      return false
    } finally {
      if (selectVersion === mutationVersion) {
        isHydrating.value = false
      }
    }
  }

  async function renameSession(targetToken, title) {
    const clean = String(title || '').trim()
    if (!targetToken || !clean) return false
    try {
      const updated = await renameAiSession(targetToken, clean)
      const nextTitle = updated?.title || clean
      sessions.value = sessions.value.map((s) => (
        s.session_token === targetToken ? { ...s, title: nextTitle } : s
      ))
      if (sessionToken.value === targetToken) {
        activeSessionTitle.value = nextTitle
      }
      return true
    } catch {
      return false
    }
  }

  async function deleteSession(targetToken) {
    if (!targetToken) return false
    error.value = ''
    try {
      await deleteAiSession(targetToken)
      clearedSessionTokens.add(targetToken)
      removeGuestToken(targetToken)
      sessions.value = sessions.value.filter((s) => s.session_token !== targetToken)
      if (sessionToken.value === targetToken) {
        startNewChat()
      }
      return true
    } catch (err) {
      if (err.response?.status === 404) {
        clearedSessionTokens.add(targetToken)
        removeGuestToken(targetToken)
        sessions.value = sessions.value.filter((s) => s.session_token !== targetToken)
        if (sessionToken.value === targetToken) {
          startNewChat()
        }
        return true
      }
      error.value = err.response?.data?.message || 'Gagal menghapus sesi percakapan. Silakan coba lagi.'
      return false
    }
  }

  function upsertLocalSessionSummary(token, title, preview, messageObj) {
    if (!token) return
    const existing = sessions.value.find((s) => s.session_token === token)
    const entry = {
      session_token: token,
      title: title || existing?.title || 'Percakapan RiasKu',
      message_count: existing ? (existing.message_count || 0) + 2 : 2,
      last_message_preview: String(preview || '').replace(/\s+/g, ' ').slice(0, 85),
      last_provider: messageObj?.provider || existing?.last_provider || null,
      last_message_at: new Date().toISOString(),
      created_at: existing?.created_at || new Date().toISOString(),
    }
    sessions.value = [entry, ...sessions.value.filter((s) => s.session_token !== token)]
  }

  async function sendMessage(rawText, options = {}) {
    const text = String(rawText || '').trim()
    if (text.length < 2 || isLoading.value) {
      return null
    }

    const sendVersion = ++mutationVersion
    const sendLogoutVersion = logoutVersion
    const tokenAtSend = sessionToken.value
    error.value = ''
    isLoading.value = true

    const tempId = `temp-${Date.now()}`
    messages.value.push({
      id: tempId,
      role: 'user',
      content: text,
      recommended_products: [],
      follow_up_suggestions: [],
    })

    try {
      const result = await sendAiMessage({
        ...(tokenAtSend ? { session_token: tokenAtSend } : {}),
        message: text,
        ...(options.contextProductId ? { context_product_id: Number(options.contextProductId) } : {}),
      })

      // Jika terjadi logout saat request berjalan, abaikan total agar data & token tidak menulis ulang localStorage (M7).
      if (sendLogoutVersion !== logoutVersion) {
        return result?.message || true
      }

      const resolvedToken = result?.session_token || tokenAtSend
      const wasCleared = resolvedToken && clearedSessionTokens.has(resolvedToken)

      // Untuk pindah sesi atau "+ Obrolan Baru" (non-logout), tetap catat token & ringkasan sesi baru di sidebar (N-L3).
      if (!wasCleared && resolvedToken) {
        addGuestToken(resolvedToken)
        upsertLocalSessionSummary(
          resolvedToken,
          result?.session_title || text.slice(0, 110),
          result?.message?.content || text,
          result?.message,
        )
      }

      // Abaikan pembaruan stage aktif bila pengguna sudah beralih/mereset sesi saat request berlangsung (M6).
      if (sendVersion !== mutationVersion) {
        return result?.message || true
      }

      if (result?.session_token) {
        sessionToken.value = result.session_token
        saveStoredToken(result.session_token)
      }

      if (result?.session_title) {
        activeSessionTitle.value = result.session_title
      } else if (!activeSessionTitle.value) {
        activeSessionTitle.value = text.slice(0, 110)
      }

      if (result?.message) {
        messages.value.push(result.message)
        lastProvider.value = result.message.provider || null
        lastModel.value = result.message.model || null
      }

      isInitialized.value = true
      return result?.message || true
    } catch (err) {
      if (sendVersion !== mutationVersion) {
        return null
      }
      if (err.response?.status === 429) {
        error.value = 'Terlalu banyak pertanyaan dalam satu menit. Mohon tunggu sebentar lalu coba lagi.'
      } else if (err.response?.status === 403 && sessionToken.value) {
        removeGuestToken(sessionToken.value)
        sessionToken.value = null
        saveStoredToken(null)
        error.value = 'Sesi sebelumnya telah berakhir. Silakan kirim pertanyaanmu kembali.'
      } else {
        error.value = err.response?.data?.message || 'Asisten AI sedang mengalami gangguan koneksi. Silakan coba beberapa saat lagi.'
      }
      messages.value = messages.value.filter((m) => m.id !== tempId)
      return null
    } finally {
      if (sendVersion === mutationVersion) {
        isLoading.value = false
      }
    }
  }

  async function resetSession() {
    mutationVersion += 1
    isLoading.value = false
    error.value = ''
    const currentToken = sessionToken.value
    sessionToken.value = null
    activeSessionTitle.value = ''
    lastProvider.value = null
    lastModel.value = null
    saveStoredToken(null)
    messages.value = [{ ...WELCOME_MESSAGE }]

    if (currentToken) {
      clearedSessionTokens.add(currentToken)
      removeGuestToken(currentToken)
      sessions.value = sessions.value.filter((s) => s.session_token !== currentToken)
      try {
        await clearAiHistory(currentToken)
      } catch {
        // Sesi lokal tetap bersih walau server mengembalikan 404/403
      }
    }
  }

  return {
    sessionToken,
    activeSessionTitle,
    messages,
    sessions,
    aiStatus,
    suggestions,
    isLoading,
    isHydrating,
    isLoadingSessions,
    error,
    lastProvider,
    lastModel,
    initSession,
    invalidateInit,
    fetchSessions,
    startNewChat,
    clearAllSessionsLocal,
    selectSession,
    renameSession,
    deleteSession,
    sendMessage,
    resetSession,
  }
})
