import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const activeModal = ref(null)

  function openModal(type, payload = {}) {
    activeModal.value = { type, payload }
  }

  function closeModal() {
    activeModal.value = null
  }

  return { activeModal, openModal, closeModal }
})
