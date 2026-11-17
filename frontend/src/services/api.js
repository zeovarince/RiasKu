import axios from 'axios'

function createApiClient(baseURL) {
  const client = axios.create({
    baseURL,
    timeout: 15000,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  })

  client.interceptors.request.use((config) => {
    const token = localStorage.getItem('riasku_access_token') || sessionStorage.getItem('riasku_access_token')
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
  })

  client.interceptors.response.use((response) => response, (error) => {
    if (error.response?.status === 401 && error.config?.headers?.Authorization) {
      window.dispatchEvent(new CustomEvent('riasku:unauthorized'))
    }
    return Promise.reject(error)
  })

  return client
}

export const api = createApiClient(
  import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
)
export const dashboardApi = createApiClient(import.meta.env.VITE_DASHBOARD_API_URL || 'http://127.0.0.1:8000/api/admin')
