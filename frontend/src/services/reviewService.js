import { api } from './api'

const USE_MOCK = import.meta.env.VITE_USE_MOCK_DATA !== 'false'
const STORAGE_KEY = 'riasku_product_reviews_v1'

const DEFAULT_MOCK_REVIEWS = [
  {
    id: 101,
    product_id: '1',
    user_name: 'Siti Rahma',
    rating: 5,
    comment: 'Teksturnya ringan banget, tidak cakey, dan tahan seharian di kulit berminyak.',
    created_at: '2026-11-18T09:30:00Z'
  },
  {
    id: 102,
    product_id: '1',
    user_name: 'Nadia Putri',
    rating: 4,
    comment: 'Warnanya natural dan cepat merata. Packaging-nya juga rapi saat pengiriman.',
    created_at: '2026-11-19T14:15:00Z'
  }
]

function readMockReviews() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(DEFAULT_MOCK_REVIEWS))
      return [...DEFAULT_MOCK_REVIEWS]
    }
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : [...DEFAULT_MOCK_REVIEWS]
  } catch {
    return [...DEFAULT_MOCK_REVIEWS]
  }
}

function saveMockReviews(reviews) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(reviews))
}

export async function getProductReviews(productId) {
  if (USE_MOCK) {
    const allReviews = readMockReviews()
    return allReviews
      .filter((item) => String(item.product_id) === String(productId))
      .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  }

  const response = await api.get(`/products/${productId}/reviews`)
  return response.data?.data ?? []
}

export async function createProductReview(productId, payload) {
  if (USE_MOCK) {
    const allReviews = readMockReviews()
    const newReview = {
      id: Date.now(),
      product_id: String(productId),
      user_name: payload.user_name?.trim() || 'Pembeli RiasKu',
      rating: Number(payload.rating),
      comment: payload.comment.trim(),
      created_at: new Date().toISOString()
    }
    allReviews.unshift(newReview)
    saveMockReviews(allReviews)
    return newReview
  }

  const response = await api.post(`/products/${productId}/reviews`, {
    rating: Number(payload.rating),
    comment: payload.comment.trim()
  })
  return response.data?.data
}