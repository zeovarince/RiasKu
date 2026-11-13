import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'

function readSavedCart() {
  try {
    return JSON.parse(localStorage.getItem('riasku_cart') || '[]')
  } catch {
    return []
  }
}

export const useCartStore = defineStore('cart', () => {
  const items = ref(readSavedCart())

  watch(items, (value) => {
    try {
      localStorage.setItem('riasku_cart', JSON.stringify(value))
    } catch (error) {
      console.warn('Keranjang tidak dapat disimpan pada browser ini.', error)
    }
  }, { deep: true })

  const totalItems = computed(() => items.value.reduce((total, item) => total + item.quantity, 0))
  const subtotal = computed(() => items.value.reduce((total, item) => total + item.price * item.quantity, 0))

  function addItem(product) {
    const existing = items.value.find((item) => String(item.id) === String(product.id))
    if (existing) existing.quantity += 1
    else items.value.push({ ...product, quantity: 1 })
  }

  function updateQuantity(id, quantity) {
    if (quantity <= 0) return removeItem(id)
    const item = items.value.find((entry) => String(entry.id) === String(id))
    if (item) item.quantity = quantity
  }

  function removeItem(id) {
    items.value = items.value.filter((item) => String(item.id) !== String(id))
  }

  function clearCart() {
    items.value = []
  }

  return { items, totalItems, subtotal, addItem, updateQuantity, removeItem, clearCart }
})
