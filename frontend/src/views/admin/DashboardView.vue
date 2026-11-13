<script setup>
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const metrics = [
  { label: 'Produk aktif', value: '24', note: '+3 produk bulan ini', icon: 'package', tone: 'rose' },
  { label: 'Menunggu pembayaran', value: '5', note: 'Perlu ditindaklanjuti', icon: 'wallet', tone: 'gold' },
  { label: 'Pesanan terbaru', value: '18', note: '7 pesanan diproses', icon: 'orders', tone: 'lavender' },
  { label: 'Penjualan bulan ini', value: 'Rp1.250.000', note: '+12,8% dari bulan lalu', icon: 'sales', tone: 'green' },
]
const orders = [
  ['RK-0014', 'Nadia Safitri', '2 item', 'Rp76.000', 'COD (Uang Tunai)', 'Diproses', 'rose'],
  ['RK-0013', 'Megan Utami', '1 item', 'Rp95.000', 'QRIS', 'Menunggu', 'gold'],
  ['RK-0012', 'Dinda Permata', '2 item', 'Rp125.000', 'Transfer BCA', 'Lunas', 'green'],
]
const stock = [
  ['Glow Velvet Lip Tint', 'RSK-LIP-01', '8 pcs', 'Stok aman', 'green'],
  ['Dewy Cushion SPF 35 PA+++ ', 'RSK-CUSH-02', '3 pcs', 'Stok menipis', 'gold'],
  ['Gentle Sunscreen Gel 50ml', 'RSK-SUN-01', '1 pcs', 'Segera restock', 'rose'],
]
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / RINGKASAN" title="Selamat datang kembali, Admin" description="Ringkasan aktivitas toko RiasKu hari ini.">
      <template #actions><RouterLink to="/admin/settings" class="admin-btn admin-btn-outline"><AdminIcon name="store" :size="15"/> Lihat toko</RouterLink></template>
    </AdminPageHeader>

    <div class="admin-metric-grid">
      <article v-for="metric in metrics" :key="metric.label" class="admin-metric-card">
        <div class="admin-metric-top"><span>{{ metric.label }}</span><span class="admin-metric-icon" :class="`metric-${metric.tone}`"><AdminIcon :name="metric.icon" :size="17"/></span></div>
        <strong>{{ metric.value }}</strong>
        <small>{{ metric.note }}</small>
      </article>
    </div>

    <div class="admin-dashboard-grid">
      <article class="admin-panel admin-chart-panel">
        <div class="admin-panel-heading"><div><h2>Grafik Penjualan</h2><p>Performa penjualan toko</p></div><select class="admin-select compact"><option>7 hari terakhir</option><option>30 hari terakhir</option></select></div>
        <div class="admin-chart-total"><strong>Rp757.000</strong><span><i></i> +8,4% minggu ini</span></div>
        <div class="admin-line-chart">
          <div class="admin-chart-y"><span>Rp800 rb</span><span>Rp600 rb</span><span>Rp400 rb</span><span>Rp200 rb</span><span>Rp0</span></div>
          <svg viewBox="0 0 640 190" preserveAspectRatio="none" role="img" aria-label="Grafik penjualan meningkat">
            <defs><linearGradient id="salesFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#c66e8b" stop-opacity=".26"/><stop offset="1" stop-color="#c66e8b" stop-opacity="0"/></linearGradient></defs>
            <path d="M0 153 C45 148 55 130 95 132 S154 118 190 122 S248 98 285 105 S345 91 380 96 S444 68 475 76 S535 55 565 60 S610 44 640 38 L640 190 L0 190Z" fill="url(#salesFill)"/>
            <path d="M0 153 C45 148 55 130 95 132 S154 118 190 122 S248 98 285 105 S345 91 380 96 S444 68 475 76 S535 55 565 60 S610 44 640 38" fill="none" stroke="#a34d6b" stroke-width="3" vector-effect="non-scaling-stroke"/>
            <g fill="#fff" stroke="#a34d6b" stroke-width="2"><circle cx="95" cy="132" r="4"/><circle cx="190" cy="122" r="4"/><circle cx="285" cy="105" r="4"/><circle cx="380" cy="96" r="4"/><circle cx="475" cy="76" r="4"/><circle cx="565" cy="60" r="4"/><circle cx="640" cy="38" r="4"/></g>
          </svg>
          <div class="admin-chart-x"><span>7 Nov</span><span>8 Nov</span><span>9 Nov</span><span>10 Nov</span><span>11 Nov</span><span>12 Nov</span><span>13 Nov</span></div>
        </div>
      </article>

      <article class="admin-panel admin-sales-panel">
        <div class="admin-panel-heading"><div><h2>Barang Terjual</h2><p>Distribusi berdasarkan kategori</p></div><button class="admin-icon-button" title="Lihat laporan"><AdminIcon name="external"/></button></div>
        <div class="admin-donut-wrap"><div class="admin-donut"><div><strong>24</strong><small>Produk</small></div></div></div>
        <ul class="admin-legend"><li><i class="legend-a"></i><span>Glow Velvet Lip Tint</span><b>40%</b></li><li><i class="legend-b"></i><span>Bedak & complexion</span><b>25%</b></li><li><i class="legend-c"></i><span>Skincare prep</span><b>20%</b></li><li><i class="legend-d"></i><span>Perlengkapan rias</span><b>15%</b></li></ul>
      </article>

      <article class="admin-panel admin-stock-panel">
        <div class="admin-panel-heading"><div><h2>Segera Restock</h2><p>Produk dengan persediaan terbatas</p></div><RouterLink to="/admin/inventory/incoming" class="admin-text-link">Lihat semua</RouterLink></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Produk & SKU</th><th>Stok tersedia</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in stock" :key="item[1]"><td><strong>{{ item[0] }}</strong><small>{{ item[1] }}</small></td><td>{{ item[2] }}</td><td><AdminBadge :tone="item[4]">{{ item[3] }}</AdminBadge></td><td><RouterLink to="/admin/inventory/incoming" class="admin-mini-link">Restock</RouterLink></td></tr></tbody></table></div>
      </article>

      <aside class="admin-side-feature"><span class="admin-feature-mark">R</span><div><AdminBadge tone="cream">Perlu perhatian</AdminBadge><h3>5 pesanan menunggu pembayaran</h3><p>Pastikan status pembayaran diperbarui sebelum pesanan diproses.</p><RouterLink to="/admin/orders" class="admin-btn admin-btn-light">Lihat pesanan <AdminIcon name="arrow-left" :size="14"/></RouterLink></div></aside>

      <article class="admin-panel admin-recent-panel">
        <div class="admin-panel-heading"><div><h2>Pesanan Terbaru</h2><p>Transaksi terbaru dari pelanggan</p></div><RouterLink to="/admin/orders" class="admin-text-link">Lihat semua</RouterLink></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID Pesanan</th><th>Pelanggan</th><th>Item</th><th>Total</th><th>Metode bayar</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr v-for="order in orders" :key="order[0]"><td><strong class="admin-code">{{ order[0] }}</strong></td><td>{{ order[1] }}</td><td>{{ order[2] }}</td><td><strong>{{ order[3] }}</strong></td><td>{{ order[4] }}</td><td><AdminBadge :tone="order[6]">{{ order[5] }}</AdminBadge></td><td><RouterLink :to="`/admin/orders/${order[0]}`" class="admin-mini-link">Detail</RouterLink></td></tr></tbody></table></div>
      </article>
    </div>
  </section>
</template>
