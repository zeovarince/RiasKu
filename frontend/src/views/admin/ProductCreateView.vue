<script setup>
import { ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const form = ref({ name:'',category:'',sku:'',description:'',buyPrice:'',sellPrice:'',stock:'',preorder:false,active:true })
const saved = ref(false)
function saveProduct(){ saved.value = true }
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / PRODUK / TAMBAH" title="Tambah Produk" description="Lengkapi informasi produk agar tampil pada katalog RiasKu.">
      <template #actions><RouterLink to="/admin/products" class="admin-btn admin-btn-outline">Batal</RouterLink></template>
    </AdminPageHeader>
    <form class="admin-form-layout" @submit.prevent="saveProduct">
      <div class="admin-form-main">
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Informasi Produk</h2><p>Informasi utama produk yang akan ditampilkan kepada pelanggan.</p></div></div>
          <label class="admin-field">Nama Produk <em>*</em><input v-model="form.name" required placeholder="Contoh: Dewy Cushion SPF 35"/></label>
          <div class="admin-field-grid"><label class="admin-field">Kategori <em>*</em><select v-model="form.category" required><option value="" disabled>Pilih kategori</option><option>Bibir</option><option>Wajah</option><option>Skincare</option><option>Perawatan</option><option>Alat rias</option></select></label><label class="admin-field">SKU / Kode Produk <em>*</em><input v-model="form.sku" required placeholder="RSK-CUSH-01"/></label></div>
          <label class="admin-field">Deskripsi Produk<textarea v-model="form.description" rows="4" placeholder="Komposisi, manfaat, warna, dan detail produk..."></textarea></label>
          <div class="admin-field-grid"><label class="admin-field">Berat (gram) <em>*</em><input type="number" min="1" placeholder="50"/></label><label class="admin-field">Jumlah stok awal <em>*</em><input v-model="form.stock" type="number" min="0" required placeholder="10"/></label></div>
        </article>
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Harga & Keuntungan</h2><p>Tentukan harga modal dan harga jual produk.</p></div></div><div class="admin-field-grid"><label class="admin-field">Harga modal (Rp) <em>*</em><input v-model="form.buyPrice" type="number" min="0" required placeholder="28000"/></label><label class="admin-field">Harga jual (Rp) <em>*</em><input v-model="form.sellPrice" type="number" min="0" required placeholder="38000"/></label></div><div v-if="form.sellPrice && form.buyPrice" class="admin-profit-hint">Estimasi keuntungan: <strong>Rp{{ Math.max(0, Number(form.sellPrice)-Number(form.buyPrice)).toLocaleString('id-ID') }}</strong></div><label class="admin-switch-row"><span><strong>Jual per pack / grosir</strong><small>Aktifkan jika produk memiliki pilihan harga grosir.</small></span><input type="checkbox"/></label></article>
        <article class="admin-panel admin-form-panel"><div class="admin-panel-heading"><div><h2>Varian dan Stok</h2><p>Tambahkan pilihan ukuran, warna, atau varian produk.</p></div></div><div class="admin-field-grid"><label class="admin-field">Nama varian<input placeholder="Contoh: Shade / Warna"/></label><label class="admin-field">Nilai varian<input placeholder="Contoh: 01 Natural"/></label></div><button type="button" class="admin-btn admin-btn-outline admin-btn-small"><AdminIcon name="plus" :size="14"/> Tambah Varian</button></article>
      </div>
      <aside class="admin-form-aside">
        <article class="admin-panel admin-form-panel"><h2>Status Produk</h2><label class="admin-switch-row"><span><strong>Produk aktif</strong><small>Produk dapat dilihat pelanggan di katalog.</small></span><input v-model="form.active" type="checkbox" class="admin-toggle"/></label><label class="admin-switch-row"><span><strong>Pre-order</strong><small>Pelanggan dapat memesan sebelum stok tersedia.</small></span><input v-model="form.preorder" type="checkbox" class="admin-toggle"/></label></article>
        <article class="admin-panel admin-form-panel"><h2>Foto Produk</h2><div class="admin-upload-box"><span class="admin-product-thumb large"><span>R</span></span><button type="button" class="admin-btn admin-btn-outline admin-btn-small">Pilih foto</button><small>PNG atau JPG. Maksimal 2 MB.</small></div></article>
        <button class="admin-btn admin-btn-primary admin-submit" type="submit"><AdminIcon name="check" :size="16"/> Simpan Produk</button>
        <p v-if="saved" class="admin-inline-success">Form berhasil divalidasi. Integrasi penyimpanan akan dihubungkan ke API Laravel.</p>
      </aside>
    </form>
  </section>
</template>
