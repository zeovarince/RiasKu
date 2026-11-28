import { api } from './api'
import { categories, products } from './mockData'
import { formatRupiah } from '@/utils/currency'

const useMockData = import.meta.env.VITE_USE_MOCK_DATA !== 'false'

function normalizeCollection(payload) {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  return []
}

function normalizeCategory(category) {
  return {
    ...category,
    slug: category.slug ?? String(category.id ?? category.name).toLowerCase().replace(/[^a-z0-9]+/g, '-'),
    name: category.name ?? category.label ?? 'Kategori',
    icon: category.icon ?? 'complexion',
  }
}

export function normalizeProduct(product) {
  const category = product.category && typeof product.category === 'object' ? product.category : (product.kategori ?? null)
  const price = Number(product.price ?? product.retail_price ?? product.sale_price ?? 0)
  const packPrice = product.pack_price ? Number(product.pack_price) : null
  const packQuantity = product.pack_quantity ? Number(product.pack_quantity) : null
  const isPreorder = Boolean(product.is_preorder ?? product.bisa_preorder)
  const preorderEstimateDays = product.preorder_estimate_days ?? null

  const rawVariants = Array.isArray(product.variants)
    ? product.variants
    : Array.isArray(product.varians)
      ? product.varians
      : Array.isArray(product.active_varians)
        ? product.active_varians
        : []
  const variants = rawVariants.map((variant) => {
    const vStock = Number(variant.stock ?? variant.stok ?? 0)
    const vActive = variant.is_active !== undefined ? Boolean(variant.is_active) : true
    const vIsPreorderItem = variant.is_preorder_item !== undefined
      ? Boolean(variant.is_preorder_item)
      : (vActive && vStock <= 0 && isPreorder)
    const vStockLabel = variant.stock_label ?? (
      !vActive ? 'Nonaktif' : vStock > 0 ? 'Tersedia' : isPreorder ? 'Pre-Order' : 'Stok Habis'
    )

    return {
      ...variant,
      name: variant.name ?? variant.nama ?? 'Standar',
      price: Number(variant.price ?? price),
      stock: vStock,
      is_active: vActive,
      stock_label: vStockLabel,
      is_preorder_item: vIsPreorderItem,
      preorder_estimate_days: vIsPreorderItem ? (variant.preorder_estimate_days ?? preorderEstimateDays) : null,
    }
  })

  const stock = Number(
    product.stock ?? product.total_stock ?? product.stock_available ??
    (variants.length > 0 ? variants.reduce((sum, v) => sum + (v.stock || 0), 0) : 0)
  )

  let stockLabel = product.stock_label || product.label_stok
  if (!stockLabel) {
    if (stock > 0) stockLabel = 'Tersedia'
    else if (isPreorder) stockLabel = 'Pre-Order'
    else stockLabel = 'Stok Habis'
  }

  const primaryPhoto = product.primary_foto?.file_path || product.primaryFoto?.file_path || product.fotos?.[0]?.file_path
  const imageUrl = product.image_url ?? product.image ?? (primaryPhoto ? `/storage/${primaryPhoto}` : null)
  const bestSellerScore = Number(product.best_seller_score ?? product.bestSellerScore ?? 0)
  const bestSellerUnitsSold = Number(product.best_seller_units_sold ?? product.bestSellerUnitsSold ?? 0)
  const bestSellerOrdersCount = Number(product.best_seller_orders_count ?? product.bestSellerOrdersCount ?? 0)
  const rawBestSellerRank = product.best_seller_rank ?? product.bestSellerRank
  const bestSellerRank = rawBestSellerRank == null ? null : Number(rawBestSellerRank)
  const isBestSeller = Boolean(product.is_best_seller ?? product.isBestSeller ?? bestSellerScore > 0)

  return {
    ...product,
    id: product.id,
    code: product.code ?? product.sku ?? `PRODUK #${product.id}`,
    sku: product.sku ?? product.code ?? null,
    price,
    priceLabel: product.priceLabel ?? formatRupiah(price),
    pack_price: packPrice,
    pack_quantity: packQuantity,
    packPriceLabel: packPrice ? formatRupiah(packPrice) : null,
    image_url: imageUrl,
    category: category?.slug ?? product.category_slug ?? product.category ?? 'uncategorized',
    categoryLabel: category?.name ?? product.category_name ?? product.categoryLabel ?? 'Lainnya',
    rating: product.rating ?? product.average_rating ?? null,
    reviewCount: Number(product.reviewCount ?? product.review_count ?? product.reviews_count ?? 0),
    stock,
    stock_label: stockLabel,
    is_preorder: isPreorder,
    preorder_estimate_days: preorderEstimateDays,
    variants,
    art: product.art ?? product.art_type ?? 'cushion',
    bestSellerScore,
    bestSellerUnitsSold,
    bestSellerOrdersCount,
    bestSellerRank,
    isBestSeller,
  }
}

const CATEGORIES_CACHE_TTL_MS = 60 * 1000
let cachedCategories = null
let cachedCategoriesAt = 0
let pendingCategoriesRequest = null

export async function getCategories({ force = false } = {}) {
  if (useMockData) return categories

  if (!force) {
    if (cachedCategories && Date.now() - cachedCategoriesAt < CATEGORIES_CACHE_TTL_MS) {
      return cachedCategories
    }
    if (pendingCategoriesRequest) {
      return pendingCategoriesRequest
    }
  }

  pendingCategoriesRequest = api.get('/categories')
    .then((response) => {
      const rows = normalizeCollection(response.data?.data ?? response.data).map(normalizeCategory)
      cachedCategories = rows
      cachedCategoriesAt = Date.now()
      return rows
    })
    .finally(() => {
      pendingCategoriesRequest = null
    })

  return pendingCategoriesRequest
}

export async function getProducts(params = {}) {
  if (useMockData) {
    let result = [...products]
    const query = String(params.q ?? params.search ?? '').trim().toLowerCase()
    const category = String(params.category ?? '').trim().toLowerCase()
    const sort = String(params.sort ?? 'recommended')
    const isBestSellerSort = ['best_seller', 'best-seller', 'terlaris'].includes(sort)

    if (query) {
      result = result.filter((product) =>
        [product.name, product.code, product.description, product.categoryLabel]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(query)),
      )
    }

    if (category && category !== 'all') {
      result = result.filter((product) =>
        String(product.category).toLowerCase() === category ||
        String(product.categoryLabel).toLowerCase() === category,
      )
    }

    if (sort === 'price-asc') result.sort((a, b) => a.price - b.price)
    if (sort === 'price-desc') result.sort((a, b) => b.price - a.price)
    if (sort === 'rating') result.sort((a, b) => Number(b.rating) - Number(a.rating))
    if (sort === 'name') result.sort((a, b) => a.name.localeCompare(b.name, 'id'))
    if (isBestSellerSort) {
      result.sort((a, b) =>
        Number(b.best_seller_orders_count || 0) - Number(a.best_seller_orders_count || 0) ||
        Number(b.best_seller_units_sold || 0) - Number(a.best_seller_units_sold || 0),
      )
    }

    let bestSellerRank = 0

    return result.map((product) => {
      const hasPurchases = Number(product.best_seller_orders_count || 0) > 0
      const rank = isBestSellerSort && hasPurchases ? ++bestSellerRank : null

      return normalizeProduct({
        ...product,
        ...(rank ? { best_seller_rank: rank } : {}),
      })
    })
  }

  const response = await api.get('/products', { params })
  return normalizeCollection(response.data?.data ?? response.data).map(normalizeProduct)
}

export async function getProductsPaginated(params = {}) {
  const page = Math.max(1, Number(params.page) || 1)
  const perPage = Math.max(1, min100(Number(params.per_page) || 12))

  if (useMockData) {
    let result = await getProducts(params)
    const stockFilter = String(params.stock_label ?? '').trim().toLowerCase()
    if (stockFilter && stockFilter !== 'all') {
      result = result.filter((p) => String(p.stock_label || '').toLowerCase() === stockFilter)
    }
    const total = result.length
    const lastPage = Math.max(1, Math.ceil(total / perPage))
    const safePage = Math.min(page, lastPage)
    const start = (safePage - 1) * perPage
    return {
      items: result.slice(start, start + perPage),
      meta: { current_page: safePage, last_page: lastPage, per_page: perPage, total },
    }
  }

  const cleanParams = { page, per_page: perPage }
  if (params.q && String(params.q).trim()) cleanParams.q = String(params.q).trim()
  if (params.category && params.category !== 'all') cleanParams.category = params.category
  if (params.stock_label && params.stock_label !== 'all') cleanParams.stock_label = params.stock_label
  if (params.sort && params.sort !== 'recommended') cleanParams.sort = params.sort

  const response = await api.get('/products', { params: cleanParams })
  const items = normalizeCollection(response.data?.data ?? response.data).map(normalizeProduct)
  const rawMeta = response.data?.meta
  const meta = rawMeta
    ? {
      current_page: Number(rawMeta.current_page) || page,
      last_page: Math.max(1, Number(rawMeta.last_page) || 1),
      per_page: Number(rawMeta.per_page) || perPage,
      total: Number(rawMeta.total) ?? items.length,
    }
    : { current_page: 1, last_page: 1, per_page: items.length || perPage, total: items.length }

  return { items, meta }
}

function min100(value) {
  return Math.min(100, value)
}

export async function getBestSellers(params = {}) {
  const limit = Math.max(1, Math.min(50, Number(params.limit ?? params.per_page ?? 8) || 8))

  if (useMockData) {
    const mockProducts = products.filter((product) => product.type !== 'service')
    const maxOrders = Math.max(0, ...mockProducts.map((product) => Number(product.best_seller_orders_count || 0)))
    const maxUnits = Math.max(0, ...mockProducts.map((product) => Number(product.best_seller_units_sold || 0)))

    return mockProducts
      .map((product) => {
        const orders = Number(product.best_seller_orders_count || 0)
        const units = Number(product.best_seller_units_sold || 0)
        const rating = Number(product.rating || 0)
        const score = maxOrders > 0 && orders > 0
          ? 100 * (
            0.6 * orders / maxOrders +
            0.25 * (maxUnits > 0 ? units / maxUnits : 0) +
            0.15 * Math.min(1, rating / 5)
          )
          : 0

        return { ...product, best_seller_score: Number(score.toFixed(4)) }
      })
      .sort((a, b) =>
        Number(b.best_seller_orders_count || 0) - Number(a.best_seller_orders_count || 0) ||
        Number(b.best_seller_score || 0) - Number(a.best_seller_score || 0) ||
        Number(b.best_seller_units_sold || 0) - Number(a.best_seller_units_sold || 0),
      )
      .slice(0, limit)
      .map((product, index) => normalizeProduct({
        ...product,
        best_seller_rank: index + 1,
      }))
  }

  const response = await api.get('/products/best-sellers', { params: { ...params, limit } })
  return normalizeCollection(response.data?.data ?? response.data).map(normalizeProduct)
}

export async function getProductById(id) {
  if (useMockData) {
    const product = products.find((item) => String(item.id) === String(id))
    if (!product) throw new Error('Produk tidak ditemukan.')
    return normalizeProduct(product)
  }

  const response = await api.get(`/products/${encodeURIComponent(id)}`)
  return normalizeProduct(response.data?.data ?? response.data)
}
