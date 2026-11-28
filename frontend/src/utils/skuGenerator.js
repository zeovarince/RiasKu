/**
 * Generate a RiasKu-formatted SKU derived from the product name letters.
 * Matches backend BarcodeProductLookupService::buildSkuPrefixFromName().
 *
 * Examples:
 * - "Cushion" -> "RSK-CUSH-01"
 * - "Dewy Cushion" -> "RSK-DECU-01"
 * - "Velvet Matte Lip Cream" -> "RSK-VMLC-01"
 */
export function buildSkuPrefixFromName(name) {
  const cleaned = String(name || '')
    .replace(/[^A-Za-z0-9\s]+/g, ' ')
    .trim()
    .toUpperCase()

  const words = cleaned.split(/\s+/).filter(Boolean)
  if (words.length === 0) return ''
  if (words.length === 1) return words[0].slice(0, 4)
  if (words.length === 2) return `${words[0].slice(0, 2)}${words[1].slice(0, 2)}`

  return words
    .slice(0, 4)
    .map((word) => word[0])
    .join('')
}

export function generateSkuFromName(name, suffix = '01') {
  const prefix = buildSkuPrefixFromName(name)
  if (!prefix) return ''
  const cleanSuffix = String(suffix || '01').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 4) || '01'
  return `RSK-${prefix}-${cleanSuffix}`
}

export function generateRandomSkuSuffix() {
  const num = Math.floor(10 + Math.random() * 89)
  return String(num)
}
