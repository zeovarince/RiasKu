<script setup>
import { computed, onMounted } from 'vue'
import AppHeader from '@/components/AppHeader.vue'
import AppFooter from '@/components/AppFooter.vue'
import GlobalModalHost from '@/components/GlobalModalHost.vue'
import { useStoreSettingsStore } from '@/stores/storeSettings'
import { buildWhatsAppUrl } from '@/utils/whatsapp'

const storeSettings = useStoreSettingsStore()
const whatsappUrl = computed(() => buildWhatsAppUrl(storeSettings.settings))

onMounted(() => storeSettings.load())
</script>

<template>
  <div class="customer-app">
    <AppHeader />
    <main class="page-main">
      <RouterView />
    </main>
    <AppFooter />
    <a v-if="storeSettings.hasActiveWhatsApp && whatsappUrl" class="whatsapp-float" :href="whatsappUrl" target="_blank" rel="noopener noreferrer" :aria-label="`Hubungi ${storeSettings.settings.store_name} melalui WhatsApp`">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.7a8.4 8.4 0 0 1-12.4 7.4L3 20.4l1.4-4.8a8.4 8.4 0 1 1 16.1-3.9Z"/><path d="M8.8 8.2c.2-.4.4-.4.7-.4h.4c.2 0 .3.1.4.4l.7 1.6c.1.2.1.4-.1.6l-.5.6c-.2.2-.2.4 0 .6.5.9 1.2 1.6 2.1 2.1.2.1.4.1.6-.1l.7-.8c.2-.2.4-.2.6-.1l1.5.7c.3.1.4.3.4.5 0 .3-.2 1-.7 1.4-.5.5-1.2.7-1.9.6-1.1-.2-2.4-.8-3.7-2-1.4-1.3-2.2-2.8-2.4-3.8-.2-.8.1-1.4.4-1.9Z"/></svg>
    </a>
    <GlobalModalHost />
  </div>
</template>
