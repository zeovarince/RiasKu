import { api } from './api'

function unwrap(response) {
  return response.data.data ?? response.data
}

export async function login(credentials) {
  return unwrap(await api.post('/auth/login', credentials))
}

export async function register(payload) {
  return unwrap(await api.post('/auth/register', payload))
}

export async function loginWithGoogle(credential) {
  return unwrap(await api.post('/auth/google', { credential }))
}

export async function exchangeGoogleCode(code) {
  return unwrap(await api.post('/auth/google/exchange', { code }))
}

export async function logout() {
  const response = await api.post('/auth/logout')
  return response.data
}
