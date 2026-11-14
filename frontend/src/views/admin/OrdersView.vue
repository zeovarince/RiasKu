<script setup>
import { ref, computed } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
const activeFilter = ref('Semua')
const search = ref('')
const filters = ['Semua','Menunggu Bayar','Diproses','Dikirim','Selesai','Dibatalkan']
const orders = ref([
  {code:'RK-0014',customer:'Nadia Safitri',item:'2 item',total:'Rp76.000',payment:'COD (Uang Tunai)',paymentTone:'gold',status:'Diproses',tone:'lavender',date:'13 Nov 2026'},
  {code:'RK-0013',customer:'Megan Utami',item:'1 item',total:'Rp95.000',payment:'QRIS',paymentTone:'green',status:'Menunggu Bayar',tone:'gold',date:'12 Nov 2026'},
  {code:'RK-0012',customer:'Dinda Permata',item:'2 item',total:'Rp125.000',payment:'Transfer BCA',paymentTone:'green',status:'Lunas',tone:'green',date:'11 Nov 2026'},
  {code:'RK-0011',customer:'Rika Maulana',item:'1 item',total:'Rp85.000',payment:'Transfer Mandiri',paymentTone:'green',status:'Dikirim',tone:'blue',date:'10 Nov 2026'},
  {code:'RK-0010',customer:'Siti Nurhaliza',item:'3 item',total:'Rp160.000',payment:'QRIS',paymentTone:'green',status:'Selesai',tone:'green',date:'8 Nov 2026'},
])
const filtered = computed(() => orders.value.filter(o => (activeFilter.value==='Semua' || o.status===activeFilter.value || (activeFilter.value==='Diproses' && o.status==='Lunas')) && `${o.code} ${o.customer}`.toLowerCase().includes(search.value.toLowerCase())))
</script>
<template>
  <section class="admin-page">
    <AdminPageHeader eyebrow="ADMIN / TRANSAKSI / PESANAN" title="Pesanan" description="Pantau transaksi pembelian dan kelola status pesanan pelanggan." />
    <article class="admin-panel"><div class="admin-filter-tabs"><button v-for="filter in filters" :key="filter" :class="{active:activeFilter===filter}" @click="activeFilter=filter">{{ filter }} <span v-if="filter==='Semua'">{{ orders.length }}</span></button></div>
      <div class="admin-toolbar"><label class="admin-search wide"><AdminIcon name="search" :size="16"/><input v-model="search" placeholder="Cari kode pesanan, nama pelanggan..."/></label><div class="admin-toolbar-actions"><select class="admin-select"><option>Semua metode bayar</option><option>COD</option><option>QRIS</option></select><button class="admin-btn admin-btn-outline admin-btn-small"><AdminIcon name="filter" :size="15"/> Filter</button></div></div>
      <div class="admin-table-wrap"><table class="admin-table admin-orders-table"><thead><tr><th>Kode Pesanan</th><th>Pelanggan & Item</th><th>Total Belanja</th><th>Metode Bayar</th><th>Status Pesanan</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody><tr v-for="order in filtered" :key="order.code"><td><strong class="admin-code">{{ order.code }}</strong></td><td><strong>{{ order.customer }}</strong><small>{{ order.item }}</small></td><td><strong>{{ order.total }}</strong></td><td>{{ order.payment }}</td><td><AdminBadge :tone="order.tone">{{ order.status }}</AdminBadge></td><td>{{ order.date }}</td><td><RouterLink :to="`/admin/orders/${order.code}`" class="admin-mini-link">Detail</RouterLink></td></tr><tr v-if="!filtered.length"><td colspan="7" class="admin-empty">Tidak ada pesanan untuk filter ini.</td></tr></tbody></table></div>
      <div class="admin-table-foot"><span>Menampilkan {{ filtered.length }} dari {{ orders.length }} pesanan</span><div class="admin-pagination"><button disabled>‹</button><button class="current">1</button><button disabled>›</button></div></div>
    </article>
  </section>
</template>
