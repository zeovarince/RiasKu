<script setup>
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const rows = [
  ['13 Nov 2026','Nadia Safitri','1 item','Rp38.000','Tunai langsung'],
  ['12 Nov 2026','Mita Griya','2 item','Rp95.000','QRIS'],
  ['11 Nov 2026','Ayu Lestari','1 item','Rp65.000','Tunai langsung'],
]
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / PENJUALAN / PENJUALAN DOD" title="Penjualan Langsung" description="Catat transaksi penjualan langsung di toko kepada pelanggan.">
      <template #actions><button class="admin-btn admin-btn-primary"><AdminIcon name="plus" :size="16"/> Catat Penjualan</button></template>
    </AdminPageHeader>
    <div class="admin-metric-grid three"><article class="admin-metric-card"><span>Total transaksi hari ini</span><strong>3</strong><small>Transaksi penjualan langsung</small></article><article class="admin-metric-card"><span>Penjualan hari ini</span><strong>Rp198.000</strong><small>Semua metode pembayaran</small></article><article class="admin-metric-card"><span>Rata-rata transaksi</span><strong>Rp66.000</strong><small>Nilai per transaksi</small></article></div>
    <article class="admin-panel"><div class="admin-toolbar"><div class="admin-panel-title"><h2>Riwayat Penjualan Langsung</h2><AdminBadge>3 transaksi</AdminBadge></div><div class="admin-toolbar-actions"><label class="admin-search"><AdminIcon name="search" :size="15"/><input placeholder="Cari pelanggan..."/></label><button class="admin-btn admin-btn-outline admin-btn-small"><AdminIcon name="calendar" :size="14"/> Periode</button></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tanggal Penjualan</th><th>Nama Pelanggan</th><th>Jumlah Item</th><th>Total</th><th>Cashier / Metode</th><th>Aksi</th></tr></thead><tbody><tr v-for="row in rows" :key="row[0]+row[1]"><td>{{ row[0] }}</td><td><strong>{{ row[1] }}</strong></td><td>{{ row[2] }}</td><td><strong>{{ row[3] }}</strong></td><td>{{ row[4] }}</td><td><button class="admin-mini-link">Lihat</button></td></tr></tbody></table></div></article>
  </section>
</template>
