import { api } from './api'

export async function createOrder(payload) {
  const response = await api.post('/orders', payload)
  return response.data.data ?? response.data
}

export async function getOrderStatus(orderCode) {
  const response = await api.get(`/orders/${encodeURIComponent(orderCode)}`)
  return response.data.data ?? response.data
}
