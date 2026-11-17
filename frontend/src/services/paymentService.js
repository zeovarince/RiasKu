import { api } from './api'

export async function createPaymentIntent(orderId, channel = 'qris') {
  const response = await api.post(`/orders/${orderId}/payments`, { channel })
  return response.data.data ?? response.data
}

export async function getPaymentStatus(paymentId) {
  const response = await api.get(`/payments/${paymentId}`)
  return response.data.data ?? response.data
}

export async function simulatePaymentSuccess(paymentId) {
  const response = await api.post(`/payments/${paymentId}/simulate-pay`)
  return response.data.data ?? response.data
}
