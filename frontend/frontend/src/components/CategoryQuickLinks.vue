<script setup>
defineProps({ categories: { type: Array, default: () => [] } })
</script>

<template>
  <section class="category-section" aria-label="Kategori produk">
    <div class="category-list">
      <RouterLink v-for="category in categories" :key="category.slug" :to="{ name: 'products', query: { category: category.slug } }" class="category-item">
        <span class="category-icon" aria-hidden="true">
          <svg v-if="category.icon === 'lip'" viewBox="0 0 32 32"><path d="M8 19c2.7-2 5.3-2 8 0s5.3 2 8 0M9 19c.4 5.1 4.1 7 7 7s6.6-1.9 7-7"/><path d="M12 13c1.8-2.4 6.2-2.4 8 0"/></svg>
          <svg v-else-if="category.icon === 'eye'" viewBox="0 0 32 32"><path d="M4 16s4.2-7 12-7 12 7 12 7-4.2 7-12 7S4 16 4 16Z"/><circle cx="16" cy="16" r="3"/></svg>
          <svg v-else-if="category.icon === 'heart'" viewBox="0 0 32 32"><path d="M16 26 5.8 16.4C-.2 9.2 8.8 3.8 16 11c7.2-7.2 16.2-1.8 10.2 5.4L16 26Z"/></svg>
          <svg v-else-if="category.icon === 'brush'" viewBox="0 0 32 32"><path d="m8 22 13-13 4 4-13 13-5 1 1-5Z"/><path d="m18 12 4 4"/></svg>
          <svg v-else-if="category.icon === 'crown'" viewBox="0 0 32 32"><path d="m5 11 6 5 5-9 5 9 6-5-2 14H7L5 11Z"/><path d="M8 28h16"/></svg>
          <svg v-else-if="category.icon === 'ring'" viewBox="0 0 32 32"><circle cx="16" cy="18" r="8"/><path d="m12 8 4-4 4 4-4 3-4-3Z"/></svg>
          <svg v-else viewBox="0 0 32 32"><circle cx="16" cy="16" r="5"/></svg>
        </span>
        <span>{{ category.name }}</span>
      </RouterLink>
    </div>
  </section>
</template>
