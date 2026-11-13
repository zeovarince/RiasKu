<script setup>
import { ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const settings = ref({ name:'RiasKu Official Store',phone:'081234567890',address:'Jl. Raya Surabaya, Jawa Timur',city:'Surabaya',minOrder:0,leadTime:24,cod:true,shipping:true,open:true })
const saved = ref(false)
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / PENGATURAN / TOKO" title="Pengaturan Toko" description="Kelola profil toko, kontak, wilayah layanan, dan operasional.">
      <template #actions><button class="admin-btn admin-btn-primary" @click="saved=true"><AdminIcon name="check" :size="15"/> Simpan Pengaturan</button></template>
    </AdminPageHeader>
    <form class="admin-settings-grid" @submit.prevent="saved=true">
      <div class="admin-settings-main">
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Profil Toko</h2><p>Informasi identitas toko yang ditampilkan kepada pelanggan.</p></div></div><label class="admin-field">URL / Nama Toko<input value="riasku.official"/></label><label class="admin-field">Nama Toko <em>*</em><input v-model="settings.name" required/></label><label class="admin-field">Nomor WhatsApp Admin <em>*</em><input v-model="settings.phone" required/></label><label class="admin-field">Alamat Asal Pengiriman<textarea v-model="settings.address" rows="3"></textarea></label><label class="admin-field">Kota Asal<select v-model="settings.city"><option>Surabaya</option><option>Bangkalan</option><option>Sidoarjo</option><option>Gresik</option></select></label></article>
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Wilayah Pengiriman</h2><p>Atur jangkauan pengiriman dan layanan pengantaran.</p></div></div><label class="admin-field">Ongkir Dalam Kota Surabaya<textarea rows="2" placeholder="Contoh: Gratis ongkir untuk area Surabaya Kota"></textarea></label><label class="admin-field">Wilayah Layanan<textarea rows="3" placeholder="Surabaya, Sidoarjo, Gresik, dan sekitarnya"></textarea></label><div class="admin-settings-actions"><span>Aktifkan layanan pengiriman toko</span><input v-model="settings.shipping" type="checkbox" class="admin-toggle"/></div></article>
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Aturan Operasional</h2><p>Ketentuan minimum pesanan dan batas waktu pembayaran.</p></div></div><div class="admin-field-grid"><label class="admin-field">Batas Stok Minimum (pcs) <em>*</em><input type="number" min="0" value="5"/></label><label class="admin-field">Batas Waktu Bayar (Jam) <em>*</em><input type="number" min="1" v-model="settings.leadTime"/></label></div><label class="admin-switch-row"><span><strong>Terima COD Surabaya</strong><small>Pelanggan dapat memilih pembayaran tunai saat pesanan diterima.</small></span><input v-model="settings.cod" type="checkbox" class="admin-toggle"/></label><label class="admin-switch-row"><span><strong>Toko sedang beroperasi</strong><small>Nonaktifkan untuk menyembunyikan tombol pembelian sementara.</small></span><input v-model="settings.open" type="checkbox" class="admin-toggle"/></label></article>
      </div>
      <aside class="admin-settings-aside"><article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><h2>Logo Toko</h2></div><div class="admin-store-logo-preview"><img src="/images/riasku-mark.png" alt="Logo RiasKu"/></div><p class="admin-panel-hint">Logo resmi RiasKu. File dapat diganti melalui pengelolaan aset.</p><button type="button" class="admin-btn admin-btn-outline admin-btn-small">Ganti Logo</button></article><article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><h2>Status Toko</h2></div><AdminBadge :tone="settings.open?'green':'rose'">{{ settings.open?'Toko aktif':'Toko tutup' }}</AdminBadge><p class="admin-panel-hint">Status toko menentukan apakah pelanggan dapat melakukan pemesanan.</p></article><button class="admin-btn admin-btn-primary admin-submit" type="submit">Simpan Pengaturan</button><p v-if="saved" class="admin-inline-success">Pengaturan berhasil divalidasi pada simulasi frontend.</p></aside>
    </form>
  </section>
</template>
