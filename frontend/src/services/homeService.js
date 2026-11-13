import { getCategories, getProducts } from './productService'

export async function getHomeData() {
  const [categoryRows, bestSellerRows] = await Promise.all([
    getCategories(),
    getProducts({ featured: 1, per_page: 4 }),
  ])

  return {
    categories: categoryRows,
    bestSellers: bestSellerRows.slice(0, 4),
  }
}
