import { api } from './api'

export async function createPaymentIntent(orderId, method) {
  const response = await api.post(`/orders/${orderId}/payments`, { method })
  return response.data.data ?? response.data
}
