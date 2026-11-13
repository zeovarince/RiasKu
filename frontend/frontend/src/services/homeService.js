import { api } from './api'
import { categories, products } from './mockData'

const useMockData = import.meta.env.VITE_USE_MOCK_DATA !== 'false'

export async function getHomeData() {
  if (useMockData) return { categories, bestSellers: products }

  const [categoryResponse, productResponse] = await Promise.all([
    api.get('/categories'),
    api.get('/products', { params: { featured: 1, per_page: 4 } }),
  ])

  return {
    categories: categoryResponse.data.data ?? categoryResponse.data,
    bestSellers: productResponse.data.data ?? productResponse.data,
  }
}
