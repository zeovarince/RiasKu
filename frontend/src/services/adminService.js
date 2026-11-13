import { api } from './api'

// Admin endpoints are prepared here. They intentionally do not assume a Laravel
// response schema yet; coordinate the final payload contract with the backend team.
export const adminService = {
  dashboard: (params = {}) => api.get('/admin/dashboard', { params }),
  products: (params = {}) => api.get('/admin/products', { params }),
  createProduct: (payload) => api.post('/admin/products', payload),
  categories: (params = {}) => api.get('/admin/categories', { params }),
  createCategory: (payload) => api.post('/admin/categories', payload),
  reviews: (params = {}) => api.get('/admin/reviews', { params }),
  updateReview: (id, payload) => api.patch(`/admin/reviews/${id}`, payload),
  orders: (params = {}) => api.get('/admin/orders', { params }),
  orderDetail: (id) => api.get(`/admin/orders/${id}`),
  updateOrderStatus: (id, payload) => api.patch(`/admin/orders/${id}/status`, payload),
  incomingStock: (payload) => api.post('/admin/inventory/incoming', payload),
  directSales: (params = {}) => api.get('/admin/sales/direct', { params }),
  monthlyReport: (params = {}) => api.get('/admin/reports/monthly', { params }),
  storeSettings: () => api.get('/admin/settings/store'),
  updateStoreSettings: (payload) => api.put('/admin/settings/store', payload),
}
