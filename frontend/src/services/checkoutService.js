import { api } from './api'

export async function createOrder(payload) {
  const response = await api.post('/checkout', payload)
  return response.data.data ?? response.data
}

export async function getOrderStatus(orderCode) {
  try {
    const response = await api.get(`/orders/${encodeURIComponent(orderCode)}/track`)
    return response.data.data ?? response.data
  } catch {
    const response = await api.get(`/orders/${encodeURIComponent(orderCode)}`)
    return response.data.data ?? response.data
  }
}
