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
const GUEST_STORAGE_KEY = 'riasku_guest_cart'

function makeCartKey(productId, variantId = null) {
  return `${productId}:${variantId ?? 'default'}`
}

function readAuthSnapshot() {
  const token = localStorage.getItem('riasku_access_token') || sessionStorage.getItem('riasku_access_token')
  let user = null
  try {
    user = JSON.parse(localStorage.getItem('riasku_user') || 'null')
  } catch {
    user = null
  }
  return { token, user }
}

function isCustomerSession() {
  const { token, user } = readAuthSnapshot()
  return Boolean(token) && user?.account_type === 'pelanggan'
}

function isAdminSession() {
  const { token, user } = readAuthSnapshot()
  return Boolean(token) && user?.account_type === 'admin'
}

function remoteCartEnabled() {
  return import.meta.env.VITE_CART_API_ENABLED === 'true' && isCustomerSession()
}

function normalizeStoredRows(raw) {
  if (!Array.isArray(raw)) return []
  return raw
    .filter((item) => item && item.id)
    .map((item) => ({
      ...item,
      cartKey: item.cartKey || makeCartKey(item.id, item.variantId ?? item.variant_id ?? null),
      quantity: Math.max(1, Number(item.quantity) || 1),
    }))
}

function readJsonStorage(key) {
  try {
    return normalizeStoredRows(JSON.parse(localStorage.getItem(key) || '[]'))
  } catch {
    return []
  }
}

function writeJsonStorage(key, value) {
  try {
    if (Array.isArray(value) && value.length > 0) {
      localStorage.setItem(key, JSON.stringify(value))
    } else {
      localStorage.removeItem(key)
    }
  } catch (error) {
    console.warn('Keranjang tidak dapat disimpan pada browser ini.', error)
  }
}

function readSavedCart() {
  if (isAdminSession()) return []
  if (isCustomerSession()) {
    const customerItems = readJsonStorage(STORAGE_KEY)
    const guestItems = readJsonStorage(GUEST_STORAGE_KEY)
    if (guestItems.length === 0) return customerItems
    const merged = [...customerItems]
    for (const guestItem of guestItems) {
      if (!merged.some((existing) => existing.cartKey === guestItem.cartKey)) {
        merged.push(guestItem)
      }
    }
    return merged
  }
  return readJsonStorage(GUEST_STORAGE_KEY)
}

function resolveCartStockMeta(product, variant = null, row = null) {
  const stock = Number(variant?.stock ?? product?.stock ?? product?.total_stock ?? 9999)
  const isProductPreorder = Boolean(
    product?.is_preorder || product?.stock_label === 'Pre-Order' || row?.is_preorder_item,
  )
  const isPreorderItem = Boolean(
    row?.is_preorder_item ?? variant?.is_preorder_item ?? (stock <= 0 && isProductPreorder),
  )
  const estimateDays = isPreorderItem
    ? (row?.preorder_estimate_days ?? variant?.preorder_estimate_days ?? product?.preorder_estimate_days ?? null)
    : null
  const stockLabel = row?.stock_label ?? variant?.stock_label ?? (
    stock > 0 ? 'Tersedia' : isPreorderItem ? 'Pre-Order' : 'Stok Habis'
  )

  return {
    stock,
    is_preorder_item: isPreorderItem,
    preorder_estimate_days: estimateDays,
    stock_label: stockLabel,
    maxQuantity: isPreorderItem ? 99 : stock,
  }
}

function mapServerRow(row) {
  const product = row.product || row
  const variant = row.variant || null
  const variantId = variant?.id ?? row.variant_id ?? null
  const stockMeta = resolveCartStockMeta(product, variant, row)

  return {
    ...product,
    id: product.id,
    price: Number(row.unit_price ?? variant?.price ?? product.price ?? 0),
    quantity: Math.max(1, Number(row.quantity) || 1),
    stock: stockMeta.stock,
    is_preorder_item: stockMeta.is_preorder_item,
    preorder_estimate_days: stockMeta.preorder_estimate_days,
    stock_label: stockMeta.stock_label,
    variantId,
    variantName: variant?.name ?? row.variant_name ?? null,
    serverCartItemId: row.id,
    cartKey: makeCartKey(product.id, variantId),
  }
}

export const useCartStore = defineStore('cart', () => {
  const items = ref(readSavedCart())
  const syncError = ref('')
  const isSyncing = ref(false)
  const pendingAddPromises = new Map()
  let pendingHydratePromise = null
  let lastHydratedAt = 0

  watch(items, (value) => {
    if (isAdminSession()) return
    if (isCustomerSession()) {
      writeJsonStorage(STORAGE_KEY, value)
    } else {
      writeJsonStorage(GUEST_STORAGE_KEY, value)
    }
  }, { deep: true })

  const totalItems = computed(() => items.value.reduce(
    (total, item) => total + Number(item.quantity || 0), 0,
  ))
  const subtotal = computed(() => items.value.reduce(
    (total, item) => total + Number(item.price || 0) * Number(item.quantity || 0), 0,
  ))

  function setSyncError(error) {
    syncError.value = error?.response?.data?.message || error?.message || 'Sinkronisasi keranjang ke server gagal.'
  }

  /**
   * PPL-16: Sinkronisasi keranjang pelanggan.
   * 1. Jika ada item guest yang ditambahkan sebelum login (termasuk melewati redirect Google OAuth),
   *    unggah ke server lalu bersihkan riasku_guest_cart.
   * 2. Ambil daftar keranjang resmi dari server sebagai single source of truth sehingga item yang
   *    sudah dihapus/checkout di tab lain tidak hidup kembali.
   */
  async function hydrateCart(force = false) {
    if (!remoteCartEnabled()) return
    const guestItems = readJsonStorage(GUEST_STORAGE_KEY)

    if (!force && guestItems.length === 0) {
      if (pendingHydratePromise) return pendingHydratePromise
      if (lastHydratedAt > 0 && Date.now() - lastHydratedAt < 2500) return
    }

    isSyncing.value = true
    syncError.value = ''
    pendingHydratePromise = (async () => {
      try {
        if (guestItems.length > 0) {
          for (const guestItem of guestItems) {
            try {
              await addCartItem({
                product_id: guestItem.id,
                variant_id: guestItem.variantId ?? guestItem.variant_id ?? null,
                quantity: guestItem.quantity,
              })
            } catch {
              // Abaikan item guest yang stoknya sudah habis / tidak valid di server
            }
          }
          try {
            localStorage.removeItem(GUEST_STORAGE_KEY)
          } catch {}
        }

        const result = await getCart()
        const rows = Array.isArray(result?.items) ? result.items : Array.isArray(result) ? result : []
        items.value = rows.map(mapServerRow)
        lastHydratedAt = Date.now()
      } catch (error) {
        setSyncError(error)
      } finally {
        isSyncing.value = false
        pendingHydratePromise = null
      }
    })()

    return pendingHydratePromise
  }

  function addItem(product, quantity = 1, variant = null) {
    const variantId = variant?.id ?? null
    const cartKey = makeCartKey(product.id, variantId)
    const requestedQuantity = Math.max(1, Number(quantity) || 1)
    const stockMeta = resolveCartStockMeta(product, variant)

    if (!stockMeta.is_preorder_item && stockMeta.stock <= 0) {
      return null
    }

    const existing = items.value.find((item) => item.cartKey === cartKey)

    if (existing) {
      existing.stock = stockMeta.stock
      existing.is_preorder_item = stockMeta.is_preorder_item
      existing.preorder_estimate_days = stockMeta.preorder_estimate_days
      existing.stock_label = stockMeta.stock_label
      existing.quantity = Math.min(stockMeta.maxQuantity, existing.quantity + requestedQuantity)
    } else {
      items.value.push({
        ...product,
        price: Number(variant?.price ?? product.price ?? 0),
        quantity: Math.min(stockMeta.maxQuantity, requestedQuantity),
        stock: stockMeta.stock,
        is_preorder_item: stockMeta.is_preorder_item,
        preorder_estimate_days: stockMeta.preorder_estimate_days,
        stock_label: stockMeta.stock_label,
        variantId,
        variantName: variant?.name ?? null,
        cartKey,
      })
    }

    if (remoteCartEnabled()) {
      const payload = { product_id: product.id, variant_id: variantId, quantity: requestedQuantity }
      const syncPromise = addCartItem(payload)
        .then((result) => {
          const current = items.value.find((item) => item.cartKey === cartKey)
          if (current && result?.id) {
            current.serverCartItemId = result.id
            if (result.variant_id !== undefined && result.variant_id !== null) {
              const resolvedKey = makeCartKey(product.id, result.variant_id)
              current.variantId = result.variant_id
              current.variantName = result.variant?.name ?? current.variantName
              current.cartKey = resolvedKey
            }
            if (result.is_preorder_item !== undefined) current.is_preorder_item = Boolean(result.is_preorder_item)
            if (result.preorder_estimate_days !== undefined) current.preorder_estimate_days = result.preorder_estimate_days
            if (result.stock_label) current.stock_label = result.stock_label
            if (result.variant?.stock !== undefined) current.stock = Number(result.variant.stock)
          }
          syncError.value = ''
          return result
        })
        .catch((error) => {
          setSyncError(error)
          return null
        })
        .finally(() => {
          if (pendingAddPromises.get(cartKey) === syncPromise) {
            pendingAddPromises.delete(cartKey)
          }
        })

      pendingAddPromises.set(cartKey, syncPromise)
    }

    return items.value.find((item) => item.cartKey === cartKey)
  }

  async function updateQuantity(cartKey, quantity) {
    const item = items.value.find((entry) => entry.cartKey === cartKey || String(entry.id) === String(cartKey))
    if (!item) return
    const nextQuantity = Number(quantity)
    if (!Number.isFinite(nextQuantity) || nextQuantity <= 0) return removeItem(item.cartKey)

    const maxQty = item.is_preorder_item ? 99 : Number(item.stock ?? 9999)
    item.quantity = Math.max(1, Math.min(maxQty, Math.floor(nextQuantity)))

    if (!remoteCartEnabled()) return

    if (!item.serverCartItemId && pendingAddPromises.has(item.cartKey)) {
      await pendingAddPromises.get(item.cartKey)
    }

    if (item.serverCartItemId) {
      updateCartItem(item.serverCartItemId, { quantity: item.quantity })
        .then(() => { syncError.value = '' })
        .catch(setSyncError)
    }
  }

  async function removeItem(cartKey) {
    const item = items.value.find((entry) => entry.cartKey === cartKey || String(entry.id) === String(cartKey))
    if (!item) return

    const pending = !item.serverCartItemId ? pendingAddPromises.get(item.cartKey) : null
    items.value = items.value.filter((entry) => entry.cartKey !== item.cartKey)

    if (!remoteCartEnabled()) return

    let serverId = item.serverCartItemId
    if (!serverId && pending) {
      const created = await pending
      serverId = created?.id ?? null
    }

    if (serverId) {
      removeCartItem(serverId).catch(setSyncError)
    }
  }

  function clearCart() {
    items.value = []
    if (remoteCartEnabled()) clearRemoteCart().catch(setSyncError)
  }

  function resetCart() {
    items.value = []
    syncError.value = ''
    lastHydratedAt = 0
    pendingAddPromises.clear()
    try {
      localStorage.removeItem(STORAGE_KEY)
      localStorage.removeItem(GUEST_STORAGE_KEY)
    } catch {}
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
    resetCart,
  }
})
