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

  const rawVariants = Array.isArray(product.variants) ? product.variants : (Array.isArray(product.varians) ? product.varians : [])
  const variants = rawVariants.map((variant) => ({
    ...variant,
    name: variant.name ?? variant.nama ?? 'Standar',
    price: Number(variant.price ?? price),
    stock: Number(variant.stock ?? variant.stok ?? 0),
  }))

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
