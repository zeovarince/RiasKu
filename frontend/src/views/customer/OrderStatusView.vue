<script setup>
import { ref } from 'vue'
const orderCode = ref('')
const submitted = ref(false)
function checkOrder() { submitted.value = true }
</script>

<template>
  <section class="page-container inner-page narrow-page">
    <span class="eyebrow-badge">PELACAKAN PESANAN</span>
    <h1 class="page-title">Cek Status Pesanan</h1>
    <p class="page-subtitle">Masukkan kode pesanan untuk melihat perkembangan pesanan RiasKu.</p>
    <form class="surface-card track-form" @submit.prevent="checkOrder">
      <label>Kode pesanan<input v-model="orderCode" class="form-control" placeholder="Contoh: RSK-2026-0001" required /></label>
      <button class="button button-primary" type="submit">Cari Pesanan</button>
      <p v-if="submitted" class="notice-message">Pencarian status akan dihubungkan ke endpoint Laravel setelah backend tersedia.</p>
    </form>
  </section>
</template>
