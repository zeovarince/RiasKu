import { api } from './api'

const useMockData = import.meta.env.VITE_USE_MOCK_DATA !== 'false'

export async function login(credentials) {
  if (useMockData) {
    return {
      mock: true,
      user: { name: 'Pelanggan RiasKu', email: credentials.email },
    }
  }
  const response = await api.post('/auth/login', credentials)
  return response.data.data ?? response.data
}

export async function register(payload) {
  if (useMockData) {
    return {
      mock: true,
      user: { name: payload.name, email: payload.email },
    }
  }
  const response = await api.post('/auth/register', payload)
  return response.data.data ?? response.data
}

export async function logout() {
  if (useMockData) return { mock: true }
  const response = await api.post('/auth/logout')
  return response.data
}
