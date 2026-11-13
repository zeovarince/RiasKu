<script setup>
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
const best = [
  ['Glow Velvet Lip Tint 01 Rose Bloom',38,'Rp1.444.000'],
  ['Dewy Cushion SPF 35 PA+++ ',25,'Rp2.375.000'],
  ['Gentle Sunscreen Gel 50ml',19,'Rp1.995.000'],
  ['Matte Powder Foundation',14,'Rp1.190.000'],
]
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / LAPORAN / REKAP BULANAN" title="Rekap Bulanan" description="Laporan performa penjualan, produk terlaris, dan ringkasan toko.">
      <template #actions><select class="admin-select"><option>November 2026</option><option>Oktober 2026</option></select><button class="admin-btn admin-btn-outline">Unduh PDF / Excel</button></template>
    </AdminPageHeader>
    <div class="admin-metric-grid four"><article class="admin-metric-card"><span>Penjualan kotor</span><strong>Rp1.250.000</strong><small>Total transaksi bulan ini</small></article><article class="admin-metric-card"><span>Penjualan bersih</span><strong>Rp1.200.000</strong><small>Setelah potongan</small></article><article class="admin-metric-card"><span>Barang masuk</span><strong>Rp890.000</strong><small>Modal stok diterima</small></article><article class="admin-metric-card"><span>Laba estimasi</span><strong class="positive">Rp780.000</strong><small>Margin 62,4%</small></article></div>
    <div class="admin-report-grid"><article class="admin-panel"><div class="admin-panel-heading"><div><h2>Produk Terlaris</h2><p>Produk dengan jumlah penjualan tertinggi</p></div><AdminBadge tone="neutral">Bulan ini</AdminBadge></div><div class="admin-best-list"><div v-for="(item,index) in best" :key="item[0]" class="admin-best-row"><span class="admin-rank">{{ index+1 }}</span><span class="admin-product-thumb mini"><span>R</span></span><div class="admin-best-info"><strong>{{ item[0] }}</strong><div class="admin-progress"><i :style="{width:`${item[1]/38*100}%`}"></i></div></div><div class="admin-best-value"><strong>{{ item[1] }} pcs</strong><small>{{ item[2] }}</small></div></div></div></article>
      <article class="admin-panel"><div class="admin-panel-heading"><h2>Ringkasan Bulan</h2></div><div class="admin-summary-lines"><div><span>Jumlah pesanan dibuat</span><strong>18 pesanan</strong></div><div><span>Jumlah pelanggan langsung</span><strong>12 transaksi</strong></div><div><span>Total unit terjual</span><strong>87 pcs</strong></div><div><span>Rata-rata nilai order</span><strong>Rp69.444</strong></div><div><span>Produk terlaris</span><strong>Glow Velvet Lip Tint</strong></div><div><span>Pertumbuhan dibanding bulan lalu</span><AdminBadge tone="green">+12,8%</AdminBadge></div></div></article></div>
    <article class="admin-panel admin-report-note"><div><strong>Catatan laporan</strong><p>Ringkasan ini menggunakan data simulasi untuk persiapan integrasi. Nilai final akan dihitung oleh API laporan Laravel.</p></div><AdminBadge tone="gold">Data demo</AdminBadge></article>
  </section>
</template>
