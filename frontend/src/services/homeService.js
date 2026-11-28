import { getCategories, getBestSellers, getProducts } from './productService'

export async function getHomeData() {
  const [categoryRows, bestSellerRows] = await Promise.all([
    getCategories(),
    getBestSellers({ limit: 4 }),
  ])

  let featuredProducts = bestSellerRows.slice(0, 4)
  let isFallbackCatalog = false

  if (featuredProducts.length === 0) {
    const catalogRows = await getProducts({ per_page: 4 })
    featuredProducts = catalogRows.slice(0, 4)
    isFallbackCatalog = true
  }

  return {
    categories: categoryRows,
    bestSellers: featuredProducts,
    isFallbackCatalog,
  }
}
