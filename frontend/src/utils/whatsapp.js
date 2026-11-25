export function normalizeWhatsAppNumber(value) {
  const compact = String(value ?? '').trim().replace(/[\s().-]+/g, '')
  if (!compact || !/^\+?(?:62\d{8,12}|0\d{9,12}|8\d{9,12})$/.test(compact)) return null

  let digits = compact.replace(/^\+/, '')
  if (digits.startsWith('0')) digits = `62${digits.slice(1)}`
  else if (digits.startsWith('8')) digits = `62${digits}`

  return /^628\d{7,12}$/.test(digits) ? digits : null
}

export function getWhatsAppMessage(settings = {}, { productName = '', orderId = '' } = {}) {
  const template = settings.whatsapp_default_message || 'Halo RiasKu, saya ingin bertanya.'
  const hasProductToken = template.includes('{product_name}')
  const hasOrderToken = template.includes('{order_id}')
  let message = template
    .replaceAll('{product_name}', productName || 'produk yang tersedia')
    .replaceAll('{order_id}', orderId || 'pesanan saya')
    .trim()

  if (productName && !hasProductToken) message = `${message}\n\nSaya ingin bertanya tentang produk: ${productName}.`
  if (orderId && !hasOrderToken) message = `${message}\n\nSaya ingin bertanya tentang pesanan: ${orderId}.`

  return message
}

export function buildWhatsAppUrl(settings, context = {}) {
  if (!settings?.is_whatsapp_active) return ''
  const number = normalizeWhatsAppNumber(settings.whatsapp_number)
  if (!number) return ''

  return `https://wa.me/${number}?text=${encodeURIComponent(getWhatsAppMessage(settings, context))}`
}
