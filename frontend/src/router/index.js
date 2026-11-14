import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    component: () => import('@/layouts/CustomerLayout.vue'),
    children: [
      { path: '', name: 'home', component: () => import('@/views/customer/HomeView.vue') },
      { path: 'products', name: 'products', component: () => import('@/views/customer/ProductListView.vue') },
      { path: 'products/:id', name: 'product-detail', component: () => import('@/views/customer/ProductDetailView.vue') },
      { path: 'cart', name: 'cart', component: () => import('@/views/customer/CartView.vue') },
      { path: 'checkout', name: 'checkout', component: () => import('@/views/customer/CheckoutView.vue') },
      { path: 'orders/track', name: 'order-track', component: () => import('@/views/customer/OrderStatusView.vue') },
      { path: 'consultation', name: 'consultation', component: () => import('@/views/customer/ConsultationView.vue') },
      { path: 'ai-assistant', name: 'ai-assistant', component: () => import('@/views/customer/AiAssistantView.vue') },
    ],
  },
  {
    path: '/auth',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      { path: 'login', name: 'login', component: () => import('@/views/auth/LoginView.vue') },
      { path: 'register', name: 'register', component: () => import('@/views/auth/RegisterView.vue') },
    ],
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    children: [
      { path: '', redirect: '/admin/dashboard' },
      { path: 'dashboard', name: 'admin-dashboard', component: () => import('@/views/admin/DashboardView.vue') },
      { path: 'products', name: 'admin-products', component: () => import('@/views/admin/ProductsView.vue') },
      { path: 'products/create', name: 'admin-product-create', component: () => import('@/views/admin/ProductCreateView.vue') },
      { path: 'categories', name: 'admin-categories', component: () => import('@/views/admin/CategoriesView.vue') },
      { path: 'reviews', name: 'admin-reviews', component: () => import('@/views/admin/ReviewsView.vue') },
      { path: 'orders', name: 'admin-orders', component: () => import('@/views/admin/OrdersView.vue') },
      { path: 'orders/:id', name: 'admin-order-detail', component: () => import('@/views/admin/OrderDetailView.vue') },
      { path: 'inventory/incoming', name: 'admin-incoming-stock', component: () => import('@/views/admin/IncomingStockView.vue') },
      { path: 'sales/direct', name: 'admin-direct-sales', component: () => import('@/views/admin/DirectSalesView.vue') },
      { path: 'reports/monthly', name: 'admin-monthly-report', component: () => import('@/views/admin/MonthlyReportView.vue') },
      { path: 'settings', name: 'admin-settings', component: () => import('@/views/admin/StoreSettingsView.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() { return { top: 0, behavior: 'smooth' } },
})

export default router
