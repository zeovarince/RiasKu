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
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0, behavior: 'smooth' }
  },
})

export default router
