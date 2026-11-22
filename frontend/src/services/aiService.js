import { api } from './api'

export async function getAiStatus() {
  const response = await api.get('/ai/status')
  return response.data.data ?? response.data
}

export async function sendAiMessage(payload) {
  const response = await api.post('/ai/chat', payload, { timeout: 40000 })
  return response.data.data ?? response.data
}

export async function getAiHistory(sessionToken = null) {
  const params = sessionToken ? { session_token: sessionToken } : {}
  const response = await api.get('/ai/history', { params })
  return response.data.data ?? response.data
}

export async function getAiSessions(tokens = [], activeToken = null) {
  const cleanTokens = Array.isArray(tokens) ? tokens.filter(Boolean) : []
  const params = {
    ...(cleanTokens.length ? { tokens: cleanTokens.join(',') } : {}),
    ...(activeToken ? { active_token: activeToken } : {}),
  }
  const response = await api.get('/ai/sessions', { params })
  return response.data.data ?? []
}

export async function renameAiSession(sessionToken, title) {
  const response = await api.patch(`/ai/sessions/${encodeURIComponent(sessionToken)}`, { title })
  return response.data.data ?? response.data
}

export async function deleteAiSession(sessionToken) {
  if (!sessionToken) return { deleted: false }
  const response = await api.delete(`/ai/sessions/${encodeURIComponent(sessionToken)}`)
  return response.data.data ?? response.data
}

export async function clearAiHistory(sessionToken) {
  if (!sessionToken) return { cleared: false }
  const response = await api.delete('/ai/history', {
    params: { session_token: sessionToken },
  })
  return response.data.data ?? response.data
}

export async function getAiSuggestions() {
  const response = await api.get('/ai/suggestions')
  return response.data.data ?? []
}
