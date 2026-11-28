import { api } from './api'

const ZONES_CACHE_TTL_MS = 5 * 60 * 1000
let cachedZones = null
let cachedZonesAt = 0
let pendingZonesRequest = null

export async function getZones(q = '', { force = false } = {}) {
  const cleanQuery = String(q || '').trim()
  if (!cleanQuery && !force) {
    if (cachedZones && Date.now() - cachedZonesAt < ZONES_CACHE_TTL_MS) {
      return cachedZones
    }
    if (pendingZonesRequest) {
      return pendingZonesRequest
    }
  }

  const request = api.get('/shipping/zones', { params: cleanQuery ? { q: cleanQuery } : {} })
    .then((response) => {
      const list = response.data?.data ?? []
      if (!cleanQuery) {
        cachedZones = list
        cachedZonesAt = Date.now()
      }
      return list
    })
    .finally(() => {
      if (!cleanQuery) pendingZonesRequest = null
    })

  if (!cleanQuery) pendingZonesRequest = request
  return request
}

export async function getRates(payload) {
  const response = await api.post('/shipping/rates', payload)
  return response.data.data ?? response.data
}
