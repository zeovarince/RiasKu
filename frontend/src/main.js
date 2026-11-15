import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './assets/main.css'
import './assets/auth-login.css'
import './assets/admin.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

window.addEventListener('riasku:unauthorized', () => {
  const auth = useAuthStore(pinia)
  const adminArea = window.location.pathname.startsWith('/admin')
  auth.clearSession()
  router.replace(adminArea ? '/admin/login' : '/auth/login')
})

app.mount('#app')
