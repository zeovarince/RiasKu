<script setup>
import AiChatPanel from '@/components/AiChatPanel.vue'
</script>

<template>
  <section class="ai-workspace-page">
    <AiChatPanel mode="page" />
  </section>
</template>

<style scoped>
.ai-workspace-page {
  width: 100%;
  background: #fffdfb;
  min-height: calc(100vh - 74px);
  display: flex;
  flex-direction: column;
}
</style>
