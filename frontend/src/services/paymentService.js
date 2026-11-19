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

/**
 * PPL-19: Live Sync Status dari Midtrans Sandbox.
 * Memanggil POST /api/payments/{orderNumber}/sync-status yang akan
 * menghubungi GET Midtrans /v2/{order_id}/status secara real-time.
 * Cocok untuk pengujian localhost tanpa ngrok.
 */
export async function syncPaymentStatus(orderNumber) {
  const response = await api.post(`/payments/${encodeURIComponent(orderNumber)}/sync-status`)
  return response.data.data ?? response.data
}
