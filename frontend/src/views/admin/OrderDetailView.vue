<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const route = useRoute()
const status = ref('Diproses')
const saved = ref(false)
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / PESANAN / DETAIL" :title="`Pesanan ${route.params.id || 'RK-0014'}`" description="Dibuat pada 13 November 2026 · Nadia Safitri">
      <template #actions><RouterLink to="/admin/orders" class="admin-btn admin-btn-outline"><AdminIcon name="arrow-left" :size="15"/> Kembali ke daftar</RouterLink></template>
    </AdminPageHeader>
    <div class="admin-order-detail-grid">
      <div class="admin-detail-main">
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Item Pesanan</h2><p>Rincian produk yang dipesan pelanggan.</p></div><AdminBadge tone="lavender">{{ status }}</AdminBadge></div><div class="admin-order-item"><span class="admin-product-thumb large"><span>R</span></span><div class="admin-order-item-name"><strong>Glow Velvet Lip Tint</strong><small>01 Rose Bloom · Eceran</small></div><span>2 pcs</span><strong>Rp38.000</strong><strong>Rp76.000</strong></div><div class="admin-order-totals"><div><span>Subtotal Produk</span><strong>Rp76.000</strong></div><div><span>Ongkos Kirim (Diantar Penjual)</span><strong>Rp0</strong></div><div class="grand"><span>Total Bayar</span><strong>Rp76.000</strong></div></div></article>
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Penerima dan Pengiriman</h2><p>Informasi kontak dan alamat tujuan.</p></div></div><div class="admin-info-grid"><div><small>Nama Penerima</small><strong>Nadia Safitri</strong></div><div><small>Ekspedisi & Layanan</small><strong>Diantar Penjual · Same-day Kurir Toko</strong></div><div><small>Nomor WhatsApp</small><strong>081398765432 <AdminBadge tone="green">Terhubung</AdminBadge></strong></div><div><small>Nomor Resi</small><strong>Belum ada nomor resi</strong></div><div class="full"><small>Alamat Lengkap Tujuan</small><strong>Jl. Manyar Kertajaya No. 45, Gubeng, Kota Surabaya</strong></div></div></article>
      </div>
      <aside class="admin-detail-side">
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Status Pesanan</h2></div><AdminBadge tone="lavender">{{ status }}</AdminBadge></div><div class="admin-order-timeline"><div class="done"><i>✓</i><span>Menunggu<br/>Pembayaran</span></div><div class="done"><i>✓</i><span>Dibayar</span></div><div class="current"><i>3</i><span>Diproses</span></div><div><i>4</i><span>Dikirim</span></div><div><i>5</i><span>Selesai</span></div></div><label class="admin-field">Ubah Status Pesanan<select v-model="status"><option>Menunggu Pembayaran</option><option>Diproses</option><option>Dikirim</option><option>Selesai</option><option>Dibatalkan</option></select></label><button class="admin-btn admin-btn-primary admin-submit" @click="saved=true">Simpan Status</button><p v-if="saved" class="admin-inline-success">Status diperbarui pada simulasi frontend.</p></article>
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><h2>Informasi Pembayaran</h2><AdminBadge tone="gold">Menunggu</AdminBadge></div><div class="admin-info-stack"><div><small>Metode Pembayaran</small><strong>COD (Uang Tunai)</strong></div><div><small>Batas Waktu Bayar</small><strong>13 Nov 2026 · 18:00</strong></div><div><small>Waktu Dibayar</small><strong>Belum dibayar</strong></div></div><button class="admin-btn admin-btn-primary admin-submit">Uang Tunai Diterima</button></article>
      </aside>
    </div>
  </section>
</template>
