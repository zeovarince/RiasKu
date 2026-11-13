<script setup>
import { ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const saved = ref(false)
const stockRows = [
  ['12 Nov 2026','Glow Velvet Lip Tint','+12 pcs','Rp28.000','Nadia / Supplier A'],
  ['10 Nov 2026','Dewy Cushion SPF 35','+8 pcs','Rp65.000','Distributor Kosmetik'],
  ['28 Okt 2026','Matte Powder Foundation','+15 pcs','Rp62.000','Grosir Kecantikan'],
]
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / INVENTARIS / BARANG MASUK" title="Barang Masuk" description="Catat stok baru yang diterima dan perbarui persediaan produk.">
      <template #actions><AdminBadge tone="gold">Inventaris</AdminBadge></template>
    </AdminPageHeader>
    <div class="admin-stock-layout">
      <div class="admin-form-main">
        <form class="admin-panel admin-form-panel" @submit.prevent="saved=true"><div class="admin-panel-heading"><div><h2>Form Barang Masuk</h2><p>Masukkan detail produk dan kuantitas stok yang diterima.</p></div></div><label class="admin-field">Pilih Produk <em>*</em><select><option>Pilih produk yang akan ditambahkan</option><option>Glow Velvet Lip Tint</option><option>Dewy Cushion SPF 35</option><option>Matte Powder Foundation</option></select></label><div class="admin-field-grid"><label class="admin-field">Nama / kode supplier<input placeholder="Supplier kosmetik"/></label><label class="admin-field">Jumlah Masuk (pcs) <em>*</em><input type="number" min="1" placeholder="12"/></label></div><div class="admin-field-grid"><label class="admin-field">Harga Modal Satuan (Rp)<input type="number" placeholder="28000"/></label><label class="admin-field">Tanggal Masuk<input type="date" value="2026-11-13"/></label></div><label class="admin-field">Catatan<textarea rows="3" placeholder="Catatan tambahan untuk stok masuk..."></textarea></label><button class="admin-btn admin-btn-primary"><AdminIcon name="check" :size="15"/> Simpan Barang Masuk</button><p v-if="saved" class="admin-inline-success">Data barang masuk siap dikirim ke endpoint inventaris.</p></form>
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Riwayat Barang Masuk</h2><p>Riwayat stok yang telah diterima.</p></div><AdminBadge>Terbaru</AdminBadge></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tanggal</th><th>Produk & Variasi</th><th>Qty Masuk</th><th>Harga Modal</th><th>Supplier</th></tr></thead><tbody><tr v-for="row in stockRows" :key="row[0]+row[1]"><td>{{ row[0] }}</td><td><strong>{{ row[1] }}</strong></td><td><AdminBadge tone="green">{{ row[2] }}</AdminBadge></td><td>{{ row[3] }}</td><td>{{ row[4] }}</td></tr></tbody></table></div></article>
      </div>
      <aside class="admin-panel admin-form-panel"><div class="admin-panel-heading"><h2>Segera Restock</h2><AdminBadge tone="gold">3 item</AdminBadge></div><p class="admin-panel-hint">Produk berikut memiliki persediaan rendah dan perlu diperhatikan.</p><div class="admin-restock-item"><span class="admin-product-thumb"><span>R</span></span><div><strong>Gentle Sunscreen Gel 50ml</strong><small>Stok tersisa 1 pcs</small><AdminBadge tone="rose">Stok kritis</AdminBadge></div></div><div class="admin-restock-item"><span class="admin-product-thumb"><span>R</span></span><div><strong>Dewy Cushion SPF 35</strong><small>Stok tersisa 3 pcs</small><AdminBadge tone="gold">Stok rendah</AdminBadge></div></div></aside>
    </div>
  </section>
</template>
