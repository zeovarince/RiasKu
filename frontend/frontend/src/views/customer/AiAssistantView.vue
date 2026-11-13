<script setup>
import { ref } from 'vue'
const question = ref('')
const messages = ref([{ role: 'assistant', text: 'Halo! Aku asisten RiasKu. Ceritakan kebutuhan rias atau produk yang ingin kamu cari.' }])
function sendMessage() {
  const value = question.value.trim()
  if (!value) return
  messages.value.push({ role: 'user', text: value })
  messages.value.push({ role: 'assistant', text: 'Terima kasih. Fitur asisten AI akan dihubungkan ke layanan backend setelah endpoint AI disiapkan.' })
  question.value = ''
}
</script>

<template>
  <section class="page-container inner-page narrow-page">
    <span class="eyebrow-badge">RIASKU BEAUTY ASSISTANT</span>
    <h1 class="page-title">Tanya AI</h1>
    <p class="page-subtitle">Asisten untuk membantu menemukan produk dan layanan rias yang sesuai kebutuhanmu.</p>
    <div class="surface-card chat-panel">
      <div class="chat-messages">
        <div v-for="(message, index) in messages" :key="index" :class="['chat-bubble', message.role]">{{ message.text }}</div>
      </div>
      <form class="chat-composer" @submit.prevent="sendMessage">
        <input v-model="question" class="form-control" placeholder="Tulis pertanyaanmu..." aria-label="Pesan untuk asisten AI" />
        <button class="button button-primary" type="submit">Kirim</button>
      </form>
    </div>
  </section>
</template>
