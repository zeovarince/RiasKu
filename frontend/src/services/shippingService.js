import { api } from './api'

export async function getZones(q = '') {
  const response = await api.get('/shipping/zones', { params: q ? { q } : {} })
  return response.data.data ?? []
}

export async function getRates(payload) {
  const response = await api.post('/shipping/rates', payload)
  return response.data.data ?? response.data
}
