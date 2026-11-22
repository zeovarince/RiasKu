<script setup>
import { computed } from 'vue'
import RatingStars from '@/components/RatingStars.vue'

const props = defineProps({
  reviews: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const averageRating = computed(() => {
  if (!props.reviews.length) return 0
  const total = props.reviews.reduce((sum, item) => sum + Number(item.rating || 0), 0)
  return Number((total / props.reviews.length).toFixed(1))
})

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date)
}
</script>

<template>
  <div class="review-list-wrapper">
    <div class="review-summary">
      <div class="summary-score">{{ averageRating || '0.0' }}</div>
      <div class="summary-meta">
        <RatingStars :model-value="averageRating" size="1.25rem" />
        <p>{{ reviews.length }} ulasan pembeli</p>
      </div>
    </div>

    <p v-if="loading" class="review-state">Memuat ulasan...</p>
    <p v-else-if="!reviews.length" class="review-state">
      Belum ada ulasan untuk produk ini. Jadilah yang pertama memberi ulasan!
    </p>

    <ul v-else class="review-items">
      <li v-for="review in reviews" :key="review.id" class="review-card">
        <div class="review-card-header">
          <strong>{{ review.user_name || 'Pembeli RiasKu' }}</strong>
          <span class="review-date">{{ formatDate(review.created_at) }}</span>
        </div>
        <RatingStars :model-value="Number(review.rating)" size="1rem" />
        <p class="review-comment">{{ review.comment }}</p>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.review-list-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.review-summary {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem;
  background: #faf7f8;
  border: 1px solid #ece7e9;
  border-radius: 12px;
}

.summary-score {
  font-size: 2.25rem;
  font-weight: 700;
  line-height: 1;
}

.summary-meta p {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
  color: #6b7280;
}

.review-state {
  color: #6b7280;
  font-size: 0.95rem;
  margin: 0;
}

.review-items {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.review-card {
  border: 1px solid #ece7e9;
  border-radius: 12px;
  padding: 1rem 1.25rem;
  background: #fff;
}

.review-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}

.review-date {
  font-size: 0.82rem;
  color: #6b7280;
}

.review-comment {
  margin: 0.6rem 0 0;
  line-height: 1.5;
}
</style>