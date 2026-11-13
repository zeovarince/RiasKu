export const categories = [
  { slug: 'complexion', name: 'Complexion', icon: 'complexion' },
  { slug: 'lip-care', name: 'Lip Care', icon: 'lip' },
  { slug: 'eye-makeup', name: 'Eye Makeup', icon: 'eye' },
  { slug: 'skincare-prep', name: 'Skincare Prep', icon: 'complexion' },
  { slug: 'rias-pengantin', name: 'Rias Pengantin', icon: 'heart' },
  { slug: 'rias-wisuda', name: 'Rias Wisuda', icon: 'crown' },
  { slug: 'alat-rias', name: 'Alat Rias', icon: 'brush' },
]

export const products = [
  {
    id: 1, code: 'KODE: RSK-CUSH-01', name: 'Velvet Matte Cushion SPF 35', tag: 'BEST SELLER #1',
    category: 'complexion', categoryLabel: 'Complexion', art: 'cushion', price: 185000,
    priceLabel: 'Rp 185.000', rating: '4.9', reviewCount: 184,
    description: 'Cushion dengan hasil velvet matte untuk tampilan rias natural sehari-hari.',
  },
  {
    id: 2, code: 'KODE: RSK-LIP-04', name: 'Glaze Tinted Lip Oil Butter', tag: 'READY STOCK',
    category: 'lip-care', categoryLabel: 'Lipstick', art: 'lip', price: 95000,
    priceLabel: 'Rp 95.000', rating: '4.8', reviewCount: 92,
    description: 'Lip oil berwarna dengan sentuhan lembap dan kilau natural.',
  },
  {
    id: 3, code: 'KODE: RSK-POWD-02', name: 'Translucent HD Setting Powder', tag: 'SISA 3 PCS',
    category: 'complexion', categoryLabel: 'Complexion', art: 'powder', price: 135000,
    priceLabel: 'Rp 135.000', rating: '4.9', reviewCount: 64,
    description: 'Bedak tabur translucent untuk membantu mengunci hasil makeup.',
  },
  {
    id: 4, code: 'JASA: RSK-BRV-010', name: 'Paket Rias Pengantin Modern', tag: 'COD SURABAYA',
    category: 'rias-pengantin', categoryLabel: 'Paket Jasa Rias', art: 'bridal', type: 'service', price: 1850000,
    priceLabel: 'Rp 1.850.000', rating: '5.0', reviewCount: 42, reviewLabel: 'Booking',
    description: 'Paket layanan rias pengantin modern bersama makeup artist RiasKu.',
  },
]
