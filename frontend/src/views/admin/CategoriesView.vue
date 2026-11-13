<script setup>
import { ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const categories = ref([
  { name:'Bibir', count:1, date:'12 Okt 2026' },
  { name:'Wajah', count:7, date:'10 Okt 2026' },
  { name:'Perawatan', count:2, date:'11 Okt 2026' },
  { name:'Aksesoris', count:0, date:'28 Okt 2026' },
])
const showForm = ref(false)
const newCategory = ref('')
function addCategory(){ if(newCategory.value.trim()){ categories.value.push({name:newCategory.value.trim(),count:0,date:'13 Nov 2026'}); newCategory.value='';showForm.value=false } }
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / KATALOG / KATEGORI" title="Kategori" description="Pengelompokan produk untuk memudahkan pelanggan menemukan kebutuhan.">
      <template #actions><button class="admin-btn admin-btn-primary" @click="showForm = !showForm"><AdminIcon name="plus" :size="16"/> Tambah Kategori</button></template>
    </AdminPageHeader>
    <article v-if="showForm" class="admin-panel admin-add-inline"><label class="admin-field">Nama kategori baru<input v-model="newCategory" placeholder="Nama kategori" @keyup.enter="addCategory"/></label><button class="admin-btn admin-btn-primary" @click="addCategory">Simpan Kategori</button></article>
    <article class="admin-panel"><div class="admin-toolbar"><div class="admin-panel-title"><h2>Daftar Kategori</h2><AdminBadge>{{ categories.length }} kategori</AdminBadge></div><label class="admin-search"><AdminIcon name="search" :size="16"/><input placeholder="Cari kategori..."/></label></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Nama Kategori</th><th>Jumlah Produk</th><th>Tanggal Dibuat</th><th>Aksi</th></tr></thead><tbody><tr v-for="category in categories" :key="category.name"><td><div class="admin-category-name"><span class="admin-category-symbol">{{ category.name.slice(0,1) }}</span><strong>{{ category.name }}</strong></div></td><td>{{ category.count }} produk</td><td>{{ category.date }}</td><td><div class="admin-row-actions"><button class="admin-icon-button"><AdminIcon name="edit" :size="15"/></button><button class="admin-icon-button danger" @click="categories = categories.filter(c => c.name !== category.name)"><AdminIcon name="trash" :size="15"/></button></div></td></tr></tbody></table></div></article>
  </section>
</template>
