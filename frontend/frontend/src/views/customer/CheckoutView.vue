<script setup>
import { ref } from 'vue'
import { useCartStore } from '@/stores/cart'
import { formatRupiah } from '@/utils/currency'
const cart = useCartStore()
const method = ref('cod')
const buyer = ref({ name: '', phone: '', address: '' })
const message = ref('')

function submitPreview() {
  message.value = 'Form checkout sudah siap. Integrasi pembuatan pesanan menunggu endpoint Laravel.'
}
</script>

<template>
  <section class="page-container inner-page">
    <span class="eyebrow-badge">PENYELESAIAN PESANAN</span>
    <h1 class="page-title">Checkout &amp; COD</h1>
    <div class="checkout-layout">
      <form class="surface-card checkout-form" @submit.prevent="submitPreview">
        <h2>Informasi Penerima</h2>
        <label>Nama lengkap<input v-model="buyer.name" class="form-control" required /></label>
        <label>Nomor WhatsApp<input v-model="buyer.phone" class="form-control" type="tel" required /></label>
        <label>Alamat pengiriman<textarea v-model="buyer.address" class="form-control" rows="3" required /></label>
        <h2>Metode Pembayaran</h2>
        <label class="radio-line"><input v-model="method" type="radio" value="cod" /> COD khusus area Surabaya</label>
        <label class="radio-line"><input v-model="method" type="radio" value="online" /> Pembayaran online (QRIS / VA / E-Wallet)</label>
        <button class="button button-primary" type="submit">Konfirmasi Data Checkout</button>
        <p v-if="message" class="notice-message">{{ message }}</p>
      </form>
      <aside class="surface-card order-summary">
        <h2>Ringkasan Pesanan</h2>
        <p>{{ cart.totalItems }} item dalam keranjang</p>
        <div v-for="item in cart.items" :key="item.id" class="summary-line"><span>{{ item.name }} × {{ item.quantity }}</span><strong>{{ formatRupiah(item.price * item.quantity) }}</strong></div>
        <div class="summary-total"><span>Total sementara</span><strong>{{ formatRupiah(cart.subtotal) }}</strong></div>
      </aside>
    </div>
  </section>
</template>
