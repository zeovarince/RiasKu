import { api } from './api'
import { products } from './mockData'

const useMockData = import.meta.env.VITE_USE_MOCK_DATA !== 'false'

export async function getProducts(params = {}) {
  if (useMockData) {
    const query = String(params.q ?? '').toLowerCase()
    return products.filter((product) => product.name.toLowerCase().includes(query))
  }

  const response = await api.get('/products', { params })
  return response.data.data ?? response.data
}

export async function getProductById(id) {
  if (useMockData) {
    const product = products.find((item) => String(item.id) === String(id))
    if (!product) throw new Error('Produk tidak ditemukan.')
    return product
  }

  const response = await api.get(`/products/${id}`)
  return response.data.data ?? response.data
}
