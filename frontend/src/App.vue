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

async function dismissPreloader() {
  const preloader = document.getElementById('riasku-preloader')
  if (!preloader || preloader.dataset.dismissed === 'true') {
    document.documentElement.dataset.appReady = 'true'
    return
  }

  const prefersReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
  const minDisplayMs = prefersReduced ? 120 : 560
  const bootStart = typeof window.__RIASKU_BOOT_START__ === 'number' ? window.__RIASKU_BOOT_START__ : 0

  const fontWait = document.fonts?.ready
    ? Promise.race([
        document.fonts.ready,
        new Promise((resolve) => window.setTimeout(resolve, 420)),
      ])
    : Promise.resolve()

  await fontWait

  const elapsed = performance.now() - bootStart
  const remaining = Math.max(0, minDisplayMs - elapsed)
  if (remaining > 0) {
    await new Promise((resolve) => window.setTimeout(resolve, remaining))
  }

  if (preloader.dataset.dismissed === 'true') {
    document.documentElement.dataset.appReady = 'true'
    return
  }

  preloader.dataset.dismissed = 'true'
  preloader.classList.add('is-exiting')
  document.documentElement.dataset.appReady = 'true'

  const cleanup = () => {
    preloader.remove()
    document.getElementById('riasku-preloader-style')?.remove()
  }

  const runningAnimations = typeof preloader.getAnimations === 'function' ? preloader.getAnimations() : []
  if (runningAnimations.length === 0) {
    cleanup()
    return
  }

  let finished = false
  const finishOnce = () => {
    if (finished) return
    finished = true
    cleanup()
  }

  preloader.addEventListener('transitionend', finishOnce, { once: true })
  window.setTimeout(finishOnce, 560)
}

onMounted(() => {
  window.addEventListener('riasku:unauthorized', handleUnauthorized)
  dismissPreloader()
})

onUnmounted(() => {
  window.removeEventListener('riasku:unauthorized', handleUnauthorized)
})
</script>

<template>
  <RouterView />
</template>
