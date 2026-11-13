<script setup>
import { ref, computed } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
const search = ref('')
const reviews = ref([
  { product:'Glow Velvet Lip Tint - 01 Rose Bloom',customer:'Dinda Permata',rating:'5.0',text:'Teksturnya ringan sekali di bibir, warnanya natural dan tahan lama. Sangat suka!',photo:'1 foto',date:'5 Nov 2026',status:'Tampil',tone:'green' },
  { product:'Dewy Cushion SPF 35 PA+++ - Light Sand',customer:'Siti Nurhaliza',rating:'4.8',text:'Coverage medium dan hasilnya bagus, tidak terlalu berat di wajah.',photo:'—',date:'4 Nov 2026',status:'Tampil',tone:'green' },
  { product:'Gentle Sunscreen Gel 50ml',customer:'Alya Rahmawati',rating:'4.9',text:'Ringan sekali dipakai dan tidak meninggalkan white cast.',photo:'—',date:'2 Nov 2026',status:'Ditinjau',tone:'gold' },
  { product:'Hydrating Barrier Serum',customer:'Nadia',rating:'5.0',text:'Kulit terasa lebih lembap setelah beberapa hari pemakaian.',photo:'—',date:'25 Okt 2026',status:'Tampil',tone:'green' },
])
const filtered = computed(() => reviews.value.filter(r => `${r.product} ${r.customer}`.toLowerCase().includes(search.value.toLowerCase())))
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / PELANGGAN / ULASAN" title="Ulasan" description="Tinjau dan moderasi ulasan produk dari pelanggan." />
    <article class="admin-panel"><div class="admin-toolbar"><div class="admin-panel-title"><h2>Daftar Ulasan</h2><AdminBadge>{{ reviews.length }} ulasan</AdminBadge></div><div class="admin-toolbar-actions"><label class="admin-search"><input v-model="search" placeholder="Cari produk / pelanggan..."/></label><select class="admin-select"><option>Semua status</option><option>Tampil</option><option>Ditinjau</option></select></div></div>
      <div class="admin-table-wrap"><table class="admin-table admin-review-table"><thead><tr><th>Produk & Variasi</th><th>Pemberi Ulasan</th><th>Rating</th><th>Komentar</th><th>Foto</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr v-for="review in filtered" :key="review.customer + review.product"><td><strong>{{ review.product }}</strong></td><td>{{ review.customer }}</td><td><strong class="admin-rating">★ {{ review.rating }}</strong></td><td class="review-comment">{{ review.text }}</td><td>{{ review.photo }}</td><td>{{ review.date }}</td><td><AdminBadge :tone="review.tone">{{ review.status }}</AdminBadge></td><td><div class="admin-row-actions"><button class="admin-mini-link">Lihat</button><button class="admin-mini-link" @click="review.status = review.status === 'Tampil' ? 'Disembunyikan' : 'Tampil'">{{ review.status === 'Tampil' ? 'Sembunyikan' : 'Tampilkan' }}</button></div></td></tr></tbody></table></div>
    </article>
  </section>
</template>
