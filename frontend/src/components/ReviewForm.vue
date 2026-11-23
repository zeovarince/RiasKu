<script setup>
import { ref } from 'vue'
import RatingStars from '@/components/RatingStars.vue'

const props = defineProps({
  submitting: {
    type: Boolean,
    default: false
  },
  requireLogin: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submit', 'require-login'])

const userName = ref('')
const rating = ref(5)
const comment = ref('')
const errorMessage = ref('')

function handleSubmit() {
  errorMessage.value = ''

  if (props.requireLogin) {
    emit('require-login')
    return
  }

  if (rating.value < 1 || rating.value > 5) {
    errorMessage.value = 'Silakan pilih rating 1 sampai 5 bintang.'
    return
  }

  if (comment.value.trim().length < 5) {
    errorMessage.value = 'Ulasan minimal 5 karakter.'
    return
  }

  emit('submit', {
    user_name: userName.value.trim() || 'Pembeli RiasKu',
    rating: rating.value,
    comment: comment.value.trim()
  })

  userName.value = ''
  rating.value = 5
  comment.value = ''
}
</script>

<template>
  <form class="review-form" @submit.prevent="handleSubmit">
    <h3 class="review-form-title">Tulis Ulasan Kamu</h3>

    <p v-if="requireLogin" class="form-notice">Masuk ke akun RiasKu untuk menulis ulasan.</p>

    <div class="form-group">
      <label for="review-name">Nama (opsional)</label>
      <input
        id="review-name"
        v-model="userName"
        type="text"
        class="form-control"
        placeholder="Contoh: Habiba"
        :disabled="submitting || requireLogin"
      />
    </div>

    <div class="form-group">
      <label>Rating Produk</label>
      <div class="rating-picker">
        <RatingStars v-model="rating" interactive size="1.6rem" />
        <span class="rating-label">{{ rating }} / 5</span>
      </div>
    </div>

    <div class="form-group">
      <label for="review-comment">Ulasan</label>
      <textarea
        id="review-comment"
        v-model="comment"
        rows="3"
        class="form-control"
        placeholder="Bagikan pengalamanmu menggunakan produk ini..."
        :disabled="submitting || requireLogin"
        required
      ></textarea>
    </div>

    <p v-if="errorMessage" class="form-error">{{ errorMessage }}</p>

    <button type="submit" class="button button-primary" :disabled="submitting || requireLogin">
      {{ submitting ? 'Mengirim...' : 'Kirim Ulasan' }}
    </button>
  </form>
</template>

<style scoped>
.review-form {
  background: #fff;
  border: 1px solid #ece7e9;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.review-form-title {
  margin: 0;
  font-size: 1.1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.form-group label {
  font-size: 0.9rem;
  font-weight: 500;
}

.rating-picker {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.rating-label {
  font-size: 0.9rem;
  color: #6b7280;
  font-weight: 600;
}
</style>