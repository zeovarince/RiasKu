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
  dashboard: (params = {}) => dashboardApi.get('/admin/dashboard', { params }),
  reviews: (params = {}) => dashboardApi.get('/admin/reviews', { params }),
  updateReview: (id, payload) => dashboardApi.patch(`/admin/reviews/${id}`, payload),
  orders: (params = {}) => dashboardApi.get('/admin/orders', { params }),
  orderDetail: (id) => dashboardApi.get(`/admin/orders/${id}`),
  updateOrderStatus: (id, payload) => dashboardApi.patch(`/admin/orders/${id}/status`, payload),
  incomingStock: (payload) => dashboardApi.post('/admin/inventory/incoming', payload),
  directSales: (params = {}) => dashboardApi.get('/admin/sales/direct', { params }),
  monthlyReport: (params = {}) => dashboardApi.get('/admin/reports/monthly', { params }),
  storeSettings: () => dashboardApi.get('/admin/settings/store'),
  updateStoreSettings: (payload) => dashboardApi.put('/admin/settings/store', payload),
}
