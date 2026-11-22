import { api } from './api'

export async function createOrder(payload) {
  const response = await api.post('/checkout', payload)
  return response.data.data ?? response.data
}

export async function getOrderStatus(orderCode) {
  try {
    const response = await api.get(`/orders/${encodeURIComponent(orderCode)}/track`)
    return response.data.data ?? response.data
  } catch (err) {
    if (err.response?.status === 404) {
      const token = localStorage.getItem('riasku_access_token') || sessionStorage.getItem('riasku_access_token')
      if (!token) throw err
      const response = await api.get(`/orders/${encodeURIComponent(orderCode)}`)
      return response.data.data ?? response.data
    }
    throw err
  }
}

export async function getMyOrders(params = {}) {
  const response = await api.get('/orders', { params })
  return response.data
}

export async function getMyOrderDetail(idOrCode) {
  const response = await api.get(`/orders/${encodeURIComponent(idOrCode)}`)
  return response.data.data ?? response.data
}

export async function completeOrder(idOrCode) {
  const response = await api.post(`/orders/${encodeURIComponent(idOrCode)}/complete`)
  return response.data
}
