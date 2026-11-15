import { dashboardApi } from './api'

export async function loginAdmin(credentials) {
  const response = await dashboardApi.post('/auth/login', credentials)
  return response.data.data ?? response.data
}

export async function logoutAdmin() {
  const response = await dashboardApi.post('/auth/logout')
  return response.data
}
