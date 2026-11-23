import { api } from './api'

function reviewErrorMessage(error) {
  const errors = error.response?.data?.errors
  if (errors) {
    return Object.values(errors).flat().join(' ')
  }

  return error.response?.data?.message || error.message || 'Gagal memproses ulasan.'
}

export async function getProductReviews(productId) {
  try {
    const response = await api.get(`/products/${encodeURIComponent(productId)}/reviews`)
    return response.data?.data ?? []
  } catch (error) {
    throw new Error(reviewErrorMessage(error))
  }
}

export async function createProductReview(productId, payload) {
  try {
    const response = await api.post(`/products/${encodeURIComponent(productId)}/reviews`, {
      rating: Number(payload.rating),
      comment: payload.comment.trim(),
    })
    return response.data?.data
  } catch (error) {
    throw new Error(reviewErrorMessage(error))
  }
}
