<script setup>
import { ref } from 'vue'
const form = ref({ name: '', contact: '', service: 'Konsultasi shade', date: '' })
const submitted = ref(false)
function submit() { submitted.value = true }
</script>

<template>
  <section class="page-container inner-page narrow-page">
    <span class="eyebrow-badge">LAYANAN RIAS &amp; KONSULTASI</span>
    <h1 class="page-title">Konsultasi RiasKu</h1>
    <p class="page-subtitle">Ceritakan kebutuhanmu. Tim RiasKu akan membantu memilih layanan atau produk yang sesuai.</p>
    <form class="surface-card track-form" @submit.prevent="submit">
      <label>Nama<input v-model="form.name" class="form-control" required /></label>
      <label>Nomor WhatsApp<input v-model="form.contact" class="form-control" type="tel" required /></label>
      <label>Kebutuhan<select v-model="form.service" class="form-control"><option>Konsultasi shade</option><option>Rias wisuda</option><option>Rias lamaran</option><option>Rias pernikahan</option></select></label>
      <label>Tanggal pilihan<input v-model="form.date" class="form-control" type="date" /></label>
      <button class="button button-primary" type="submit">Kirim Permintaan</button>
      <p v-if="submitted" class="notice-message">Form berhasil disiapkan. Pengiriman ke Laravel API akan diaktifkan setelah backend siap.</p>
    </form>
  </section>
</template>
