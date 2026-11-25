import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/services/api'
import { normalizeWhatsAppNumber } from '@/utils/whatsapp'

const defaults = {
  store_name: 'RiasKu',
  store_tagline: '',
  store_logo: null,
  store_address: '',
  operational_hours: '',
  whatsapp_number: null,
  whatsapp_default_message: 'Halo RiasKu, saya ingin bertanya.',
  is_whatsapp_active: false,
}

export const useStoreSettingsStore = defineStore('store-settings', () => {
  const settings = ref({ ...defaults })
  const loaded = ref(false)
  let pendingRequest = null

  const hasActiveWhatsApp = computed(() => (
    Boolean(settings.value.is_whatsapp_active)
    && Boolean(normalizeWhatsAppNumber(settings.value.whatsapp_number))
  ))

  function setSettings(value) {
    settings.value = { ...defaults, ...(value || {}) }
    loaded.value = true
  }

  async function load(force = false) {
    if (loaded.value && !force) return settings.value
    if (pendingRequest && !force) return pendingRequest

    pendingRequest = api.get('/store-settings')
      .then(({ data }) => {
        setSettings(data?.data || data)
        return settings.value
      })
      .catch(() => {
        loaded.value = true
        return settings.value
      })
      .finally(() => {
        pendingRequest = null
      })

    return pendingRequest
  }

  return { settings, loaded, hasActiveWhatsApp, load, setSettings }
})
