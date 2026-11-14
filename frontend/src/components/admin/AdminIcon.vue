<script setup>
const props = defineProps({ name: { type: String, required: true }, size: { type: Number, default: 19 } })
</script>
<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <template v-if="name === 'dashboard'"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/></template>
    <template v-else-if="name === 'products'"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="M4 7v10l8 4 8-4V7M12 11v10"/></template>
    <template v-else-if="name === 'categories'"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></template>
    <template v-else-if="name === 'reviews'"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></template>
    <template v-else-if="name === 'orders'"><path d="M5 4h14v17H5z"/><path d="M8 8h8M8 12h8M8 16h5M9 4V2h6v2"/></template>
    <template v-else-if="name === 'inventory'"><path d="M3 7h18v13H3zM3 7l2-4h14l2 4M8 11h8M8 15h5"/><path d="M12 3v4"/></template>
    <template v-else-if="name === 'sales'"><path d="M4 19V5M4 19h17"/><path d="m7 15 4-4 3 2 6-7"/><path d="M16 6h4v4"/></template>
    <template v-else-if="name === 'reports'"><path d="M4 20V4h16v16H4Z"/><path d="M8 16v-4M12 16V8M16 16v-6"/></template>
    <template v-else-if="name === 'settings'"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.2.9-1.2 2.1-1.4-.5a8 8 0 0 1-1.6.9l-.2 1.5h-2.5l-.3-1.5a8 8 0 0 1-1.6-.9l-1.4.5-1.2-2.1 1.2-.9a7 7 0 0 1 0-1.8l-1.2-.9 1.2-2.1 1.4.5a8 8 0 0 1 1.6-.9l.3-1.5h2.5l.2 1.5a8 8 0 0 1 1.6.9l1.4-.5 1.2 2.1-1.2.9a7 7 0 0 1 0 1.8Z" transform="translate(-1 -1) scale(1.08)"/></template>
    <template v-else-if="name === 'store'"><path d="M4 10v10h16V10M3 10l2-6h14l2 6"/><path d="M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M9 20v-6h6v6"/></template>
    <template v-else-if="name === 'search'"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></template>
    <template v-else-if="name === 'plus'"><path d="M12 5v14M5 12h14"/></template>
    <template v-else-if="name === 'arrow-left'"><path d="m14 5-7 7 7 7M7 12h14"/></template>
    <template v-else-if="name === 'external'"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></template>
    <template v-else-if="name === 'calendar'"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></template>
    <template v-else-if="name === 'filter'"><path d="M4 5h16M7 12h10m-7 7h4"/></template>
    <template v-else-if="name === 'eye'"><path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></template>
    <template v-else-if="name === 'edit'"><path d="m4 16.5-.8 4.3 4.3-.8L19 8.5a2.8 2.8 0 0 0-4-4L4 16.5Z"/><path d="m13.5 6 4 4"/></template>
    <template v-else-if="name === 'trash'"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"/></template>
    <template v-else-if="name === 'check'"><path d="m5 12 4 4L19 6"/></template>
    <template v-else-if="name === 'chevron-down'"><path d="m6 9 6 6 6-6"/></template>
    <template v-else-if="name === 'user'"><circle cx="12" cy="8" r="3.5"/><path d="M4 21a8 8 0 0 1 16 0"/></template>
    <template v-else-if="name === 'wallet'"><rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9h18M16 14h2"/></template>
    <template v-else-if="name === 'package'"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="M3 8v9l9 5 9-5V8M12 13v9"/></template>
    <template v-else><circle cx="12" cy="12" r="8"/><path d="M12 8v8M8 12h8"/></template>
  </svg>
</template>
