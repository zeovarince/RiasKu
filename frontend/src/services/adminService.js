import { dashboardApi } from './api'

export const adminService = {
  // Kategori
  getCategories: () => dashboardApi.get('/kategori'),
  createCategory: (payload) => dashboardApi.post('/kategori', payload),
  updateCategory: (id, payload) => dashboardApi.put(`/kategori/${id}`, payload),
  deleteCategory: (id) => dashboardApi.delete(`/kategori/${id}`),

  // Produk
  getProducts: (params = {}) => dashboardApi.get('/produk', { params }),
  getProduct: (id) => dashboardApi.get(`/produk/${id}`),
  createProduct: (payload) => dashboardApi.post('/produk', payload),
  updateProduct: (id, payload) => dashboardApi.put(`/produk/${id}`, payload),
  toggleProductStatus: (id, isActive) => dashboardApi.patch(`/produk/${id}/status`, { is_active: isActive }),

  // Varian
  addVariant: (productId, payload) => dashboardApi.post(`/produk/${productId}/varian`, payload),
  updateVariant: (variantId, payload) => dashboardApi.put(`/varian/${variantId}`, payload),
  toggleVariantStatus: (variantId, isActive) => dashboardApi.patch(`/varian/${variantId}/status`, { is_active: isActive }),

  // Foto
  uploadPhoto: (productId, formData) => dashboardApi.post(`/produk/${productId}/foto`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  updatePhoto: (photoId, payload) => dashboardApi.patch(`/foto/${photoId}`, payload),
  deletePhoto: (photoId) => dashboardApi.delete(`/foto/${photoId}`),

  // Dashboard & Other
  dashboard: (params = {}) => dashboardApi.get('/dashboard', { params }),
  reviews: (params = {}) => dashboardApi.get('/reviews', { params }),
  updateReview: (id, payload) => dashboardApi.patch(`/reviews/${id}`, payload),
  orders: (params = {}) => dashboardApi.get('/orders', { params }),
  orderDetail: (id) => dashboardApi.get(`/orders/${id}`),
  updateOrderStatus: (id, payload) => dashboardApi.patch(`/orders/${id}/status`, payload),
  confirmCodPayment: (id, payload = {}) => dashboardApi.post(`/orders/${id}/confirm-cod-payment`, payload),
  // Barang masuk & peringatan restock (PPL-23)
  getIncomingStocks: (params = {}) => dashboardApi.get('/inventory/incoming', { params }),
  createIncomingStock: (payload) => dashboardApi.post('/inventory/incoming', payload),
  getRestockAlerts: (params = {}) => dashboardApi.get('/inventory/restock', { params }),
  incomingStock: (payload) => dashboardApi.post('/inventory/incoming', payload),
  directSales: (params = {}) => dashboardApi.get('/sales/direct', { params }),
  monthlyReport: (params = {}) => dashboardApi.get('/reports/monthly', { params }),
  monthlyTransactions: (params = {}) => dashboardApi.get('/reports/monthly/transactions', { params }),
  exportMonthlyReport: (params = {}) => dashboardApi.get('/reports/monthly/export', {
    params,
    responseType: 'blob',
  }),
  storeSettings: () => dashboardApi.get('/settings/store'),
  updateStoreSettings: (payload) => dashboardApi.put('/settings/store', payload),
}
