<script setup>
import { ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: Number,
    default: 0
  },
  interactive: {
    type: Boolean,
    default: false
  },
  size: {
    type: String,
    default: '1.1rem'
  }
})

const emit = defineEmits(['update:modelValue'])
const hoverValue = ref(0)

function selectStar(star) {
  if (!props.interactive) return
  emit('update:modelValue', star)
}
</script>

<template>
  <div
    class="rating-stars"
    :class="{ 'is-interactive': interactive }"
    :style="{ fontSize: size }"
    role="img"
    :aria-label="`Rating ${modelValue} dari 5 bintang`"
  >
    <button
      v-for="star in 5"
      :key="star"
      type="button"
      class="star-btn"
      :class="{ filled: star <= (hoverValue || Math.round(modelValue)) }"
      :disabled="!interactive"
      :aria-label="`Beri ${star} bintang`"
      @mouseenter="interactive && (hoverValue = star)"
      @mouseleave="interactive && (hoverValue = 0)"
      @click="selectStar(star)"
    >
      ★
    </button>
  </div>
</template>

<style scoped>
.rating-stars {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
}

.star-btn {
  background: none;
  border: none;
  padding: 0;
  line-height: 1;
  color: #d4d4d8;
  cursor: default;
  font-size: inherit;
  transition: transform 0.15s ease, color 0.15s ease;
}

.star-btn.filled {
  color: #e5a93c;
}

.is-interactive .star-btn {
  cursor: pointer;
}

.is-interactive .star-btn:hover {
  transform: scale(1.15);
}
</style>