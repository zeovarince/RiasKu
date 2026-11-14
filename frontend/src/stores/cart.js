import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import {
  addCartItem,
  clearRemoteCart,
  getCart,
  removeCartItem,
  updateCartItem,
} from '@/services/cartService'

const STORAGE_KEY = 'riasku_cart'

function makeCartKey(productId, variantId = null) {
  return `${productId}:${variantId ?? 'default'}`
}

function readSavedCart() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
    if (!Array.isArray(saved)) return []
    return saved.map((item) => ({
      ...item,
      cartKey: item.cartKey || makeCartKey(item.id, item.variantId),
      quantity: Math.max(1, Number(item.quantity) || 1),
    }))
  } catch {
    return []
  }
}

function remoteCartEnabled() {
  return import.meta.env.VITE_CART_API_ENABLED === 'true' &&
    Boolean(sessionStorage.getItem('riasku_access_token'))
}

export const useCartStore = defineStore('cart', () => {
  const items = ref(readSavedCart())
  const syncError = ref('')
  const isSyncing = ref(false)

  watch(items, (value) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
    } catch (error) {
      console.warn('Keranjang tidak dapat disimpan pada browser ini.', error)
    }
  }, { deep: true })

  const totalItems = computed(() => items.value.reduce(
    (total, item) => total + Number(item.quantity || 0), 0,
  ))
  const subtotal = computed(() => items.value.reduce(
    (total, item) => total + Number(item.price || 0) * Number(item.quantity || 0), 0,
  ))

  function setSyncError(error) {
    syncError.value = error?.message || 'Sinkronisasi keranjang ke server gagal.'
  }

  async function hydrateCart() {
    if (!remoteCartEnabled()) return
    isSyncing.value = true
    syncError.value = ''
    try {
      const result = await getCart()
      const rows = Array.isArray(result?.items) ? result.items : Array.isArray(result) ? result : []
      items.value = rows.map((row) => {
        const product = row.product || row
        const variant = row.variant || null
        return {
          ...product,
          id: product.id,
          price: Number(row.unit_price ?? variant?.price ?? product.price ?? 0),
          quantity: Math.max(1, Number(row.quantity) || 1),
          variantId: variant?.id ?? row.variant_id ?? null,
          variantName: variant?.name ?? row.variant_name ?? null,
          serverCartItemId: row.id,
          cartKey: makeCartKey(product.id, variant?.id ?? row.variant_id ?? null),
        }
      })
    } catch (error) {
      setSyncError(error)
    } finally {
      isSyncing.value = false
    }
  }

  function addItem(product, quantity = 1, variant = null) {
    const variantId = variant?.id ?? null
    const cartKey = makeCartKey(product.id, variantId)
    const requestedQuantity = Math.max(1, Number(quantity) || 1)
    const stock = Number(variant?.stock ?? product.stock ?? 9999)
    const existing = items.value.find((item) => item.cartKey === cartKey)

    if (existing) {
      existing.quantity = Math.min(stock, existing.quantity + requestedQuantity)
    } else {
      items.value.push({
        ...product,
        price: Number(variant?.price ?? product.price ?? 0),
        quantity: Math.min(stock, requestedQuantity),
        stock,
        variantId,
        variantName: variant?.name ?? null,
        cartKey,
      })
    }

    if (remoteCartEnabled()) {
      const current = items.value.find((item) => item.cartKey === cartKey)
      const payload = { product_id: product.id, variant_id: variantId, quantity: requestedQuantity }
      addCartItem(payload)
        .then((result) => {
          if (current && result?.id) current.serverCartItemId = result.id
          syncError.value = ''
        })
        .catch(setSyncError)
    }

    return items.value.find((item) => item.cartKey === cartKey)
  }

  function updateQuantity(cartKey, quantity) {
    const item = items.value.find((entry) => entry.cartKey === cartKey || String(entry.id) === String(cartKey))
    if (!item) return
    const nextQuantity = Number(quantity)
    if (!Number.isFinite(nextQuantity) || nextQuantity <= 0) return removeItem(item.cartKey)

    item.quantity = Math.min(Number(item.stock ?? 9999), Math.floor(nextQuantity))
    if (remoteCartEnabled() && item.serverCartItemId) {
      updateCartItem(item.serverCartItemId, { quantity: item.quantity })
        .then(() => { syncError.value = '' })
        .catch(setSyncError)
    }
  }

  function removeItem(cartKey) {
    const item = items.value.find((entry) => entry.cartKey === cartKey || String(entry.id) === String(cartKey))
    if (!item) return
    items.value = items.value.filter((entry) => entry.cartKey !== item.cartKey)
    if (remoteCartEnabled() && item.serverCartItemId) {
      removeCartItem(item.serverCartItemId).catch(setSyncError)
    }
  }

  function clearCart() {
    items.value = []
    if (remoteCartEnabled()) clearRemoteCart().catch(setSyncError)
  }

  return {
    items,
    totalItems,
    subtotal,
    syncError,
    isSyncing,
    hydrateCart,
    addItem,
    updateQuantity,
    removeItem,
    clearCart,
  }
})
