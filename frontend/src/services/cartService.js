import { api } from './api'

// Dipakai ketika backend mengaktifkan API cart dan user sudah login.
export async function getCart() {
  const response = await api.get('/cart')
  return response.data?.data ?? response.data
}

export async function addCartItem(payload) {
  const response = await api.post('/cart/items', payload)
  return response.data?.data ?? response.data
}

export async function updateCartItem(itemId, payload) {
  const response = await api.patch(`/cart/items/${encodeURIComponent(itemId)}`, payload)
  return response.data?.data ?? response.data
}

export async function removeCartItem(itemId) {
  const response = await api.delete(`/cart/items/${encodeURIComponent(itemId)}`)
  return response.data?.data ?? response.data
}

export async function clearRemoteCart() {
  const response = await api.delete('/cart')
  return response.data?.data ?? response.data
}
