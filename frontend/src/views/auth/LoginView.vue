<script setup>
import { ref } from 'vue'
const email = ref('')
const password = ref('')
</script>

<template>
  <form class="surface-card auth-card" @submit.prevent>
    <span class="eyebrow-badge">SELAMAT DATANG KEMBALI</span>
    <h1>Masuk ke RiasKu</h1>
    <label>Email<input v-model="email" class="form-control" type="email" required /></label>
    <label>Kata sandi<input v-model="password" class="form-control" type="password" required /></label>
    <button class="button button-primary" type="submit">Masuk</button>
    <p class="auth-switch">Belum punya akun? <RouterLink to="/auth/register">Daftar sekarang</RouterLink></p>
  </form>
</template>
