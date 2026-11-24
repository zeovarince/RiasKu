import { getCategories, getBestSellers } from './productService'

export async function getHomeData() {
  const [categoryRows, bestSellerRows] = await Promise.all([
    getCategories(),
    getBestSellers({ limit: 4 }),
  ])

  return {
    categories: categoryRows,
    bestSellers: bestSellerRows.slice(0, 4),
  }
}
