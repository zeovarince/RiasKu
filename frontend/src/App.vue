<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

function handleUnauthorized() {
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
