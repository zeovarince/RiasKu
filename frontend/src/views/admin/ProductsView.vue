<script setup>
import { ref, computed } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const search = ref('')
const products = ref([
  { sku:'RSK-LIP-01',name:'Glow Velvet Lip Tint',category:'Bibir',buy:28000,price:38000,stock:42,status:'Tersedia',tone:'green' },
  { sku:'RSK-CUSH-02',name:'Dewy Cushion SPF 35 PA+++',category:'Wajah',buy:65000,price:95000,stock:3,status:'Tersedia',tone:'green' },
  { sku:'RSK-SUN-01',name:'Gentle Sunscreen Gel 50ml',category:'Skincare',buy:78000,price:105000,stock:8,status:'Pre-Order',tone:'gold' },
  { sku:'RSK-SER-03',name:'Hydrating Serum Serum',category:'Perawatan',buy:69000,price:99000,stock:0,status:'Stok habis',tone:'rose' },
  { sku:'RSK-PWD-04',name:'Matte Powder Foundation',category:'Wajah',buy:62000,price:85000,stock:16,status:'Tersedia',tone:'green' },
])
const filtered = computed(() => products.value.filter(p => `${p.name} ${p.sku} ${p.category}`.toLowerCase().includes(search.value.toLowerCase())))
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / KATALOG / PRODUK" title="Produk" description="Kelola katalog, harga, stok, dan status produk toko RiasKu.">
      <template #actions><RouterLink to="/admin/products/create" class="admin-btn admin-btn-primary"><AdminIcon name="plus" :size="16"/> Tambah Produk</RouterLink><RouterLink to="/" class="admin-btn admin-btn-outline"><AdminIcon name="external" :size="15"/> Lihat Toko</RouterLink></template>
    </AdminPageHeader>
    <article class="admin-panel">
      <div class="admin-toolbar"><div class="admin-panel-title"><h2>Daftar Produk</h2><AdminBadge tone="neutral">{{ filtered.length }} produk</AdminBadge></div><label class="admin-search"><AdminIcon name="search" :size="16"/><input v-model="search" placeholder="Cari nama atau SKU produk..." /></label></div>
      <div class="admin-table-wrap"><table class="admin-table admin-products-table"><thead><tr><th>Produk</th><th>Kategori</th><th>Harga beli</th><th>Harga jual</th><th>Stok</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr v-for="product in filtered" :key="product.sku"><td><div class="admin-product-cell"><span class="admin-product-thumb"><span>R</span></span><span><strong>{{ product.name }}</strong><small>{{ product.sku }}</small></span></div></td><td>{{ product.category }}</td><td>Rp{{ product.buy.toLocaleString('id-ID') }}</td><td><strong>Rp{{ product.price.toLocaleString('id-ID') }}</strong></td><td>{{ product.stock }} pcs</td><td><AdminBadge :tone="product.tone">{{ product.status }}</AdminBadge></td><td><div class="admin-row-actions"><button class="admin-icon-button" title="Lihat"><AdminIcon name="eye" :size="15"/></button><button class="admin-icon-button" title="Edit"><AdminIcon name="edit" :size="15"/></button><button class="admin-icon-button danger" title="Hapus" @click="products = products.filter(p => p.sku !== product.sku)"><AdminIcon name="trash" :size="15"/></button></div></td></tr><tr v-if="!filtered.length"><td colspan="7" class="admin-empty">Produk tidak ditemukan.</td></tr></tbody></table></div>
      <div class="admin-table-foot"><span>Menampilkan {{ filtered.length }} dari {{ products.length }} produk</span><div class="admin-pagination"><button disabled>‹</button><button class="current">1</button><button disabled>›</button></div></div>
    </article>
  </section>
</template>
