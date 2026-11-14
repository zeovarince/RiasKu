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
  const category = product.category && typeof product.category === 'object' ? product.category : null
  const price = Number(product.price ?? product.sale_price ?? 0)
  const variants = Array.isArray(product.variants) ? product.variants.map((variant) => ({
    ...variant,
    price: Number(variant.price ?? price),
    stock: Number(variant.stock ?? 0),
  })) : []

  return {
    ...product,
    id: product.id,
    code: product.code ?? product.sku ?? `PRODUK #${product.id}`,
    sku: product.sku ?? product.code ?? null,
    price,
    priceLabel: product.priceLabel ?? formatRupiah(price),
    image_url: product.image_url ?? product.image ?? null,
    category: category?.slug ?? product.category_slug ?? product.category ?? 'uncategorized',
    categoryLabel: category?.name ?? product.category_name ?? product.categoryLabel ?? 'Lainnya',
    rating: product.rating ?? product.average_rating ?? null,
    reviewCount: Number(product.reviewCount ?? product.review_count ?? product.reviews_count ?? 0),
    stock: Number(product.stock ?? product.stock_available ?? 0),
    variants,
    art: product.art ?? product.art_type ?? 'cushion',
  }
}

export async function getCategories() {
  if (useMockData) return categories

  const response = await api.get('/categories')
  return normalizeCollection(response.data?.data ?? response.data).map(normalizeCategory)
}

export async function getProducts(params = {}) {
  if (useMockData) {
    let result = [...products]
    const query = String(params.q ?? params.search ?? '').trim().toLowerCase()
    const category = String(params.category ?? '').trim().toLowerCase()
    const sort = String(params.sort ?? 'recommended')

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

    return result.map(normalizeProduct)
  }

  const response = await api.get('/products', { params })
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
