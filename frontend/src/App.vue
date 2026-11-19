<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const cart = useCartStore()

function handleUnauthorized() {
  cart.resetCart()
  auth.clearSession()
  const isAdminRoute = route.path.startsWith('/admin')
  const targetRoute = isAdminRoute ? 'admin-login' : 'login'
  router.push({ name: targetRoute, query: { redirect: route.fullPath } })
}

onMounted(() => {
  window.addEventListener('riasku:unauthorized', handleUnauthorized)
})

onUnmounted(() => {
  window.removeEventListener('riasku:unauthorized', handleUnauthorized)
})
</script>

<template>
  <RouterView />
</template>
