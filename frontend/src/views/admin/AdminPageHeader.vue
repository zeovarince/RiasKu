<script setup>
defineProps({ eyebrow: String, title: String, description: String })
</script>
<template>
  <div class="admin-page-heading">
    <div>
      <span class="admin-eyebrow">{{ eyebrow || 'ADMIN / RIASKU' }}</span>
      <h1>{{ title }}</h1>
      <p v-if="description">{{ description }}</p>
    </div>
    <div class="admin-page-actions"><slot name="actions" /></div>
  </div>
</template>
