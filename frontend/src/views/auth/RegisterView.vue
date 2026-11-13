<script setup>
import { ref } from 'vue'
const form = ref({ name: '', email: '', password: '' })
</script>

<template>
  <form class="surface-card auth-card" @submit.prevent>
    <span class="eyebrow-badge">GABUNG BERSAMA KAMI</span>
    <h1>Buat Akun RiasKu</h1>
    <label>Nama lengkap<input v-model="form.name" class="form-control" required /></label>
    <label>Email<input v-model="form.email" class="form-control" type="email" required /></label>
    <label>Kata sandi<input v-model="form.password" class="form-control" type="password" minlength="8" required /></label>
    <button class="button button-primary" type="submit">Daftar</button>
    <p class="auth-switch">Sudah punya akun? <RouterLink to="/auth/login">Masuk</RouterLink></p>
  </form>
</template>
