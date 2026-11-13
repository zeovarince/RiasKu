<script setup>
defineProps({ type: { type: String, default: 'cushion' } })
</script>

<template>
  <div class="product-artwork" :class="`art-${type}`" aria-hidden="true">
    <svg v-if="type === 'lip'" viewBox="0 0 180 150" role="presentation">
      <ellipse cx="91" cy="132" rx="49" ry="8" class="art-shadow"/>
      <rect x="65" y="66" width="52" height="61" rx="9" class="art-case"/>
      <rect x="68" y="58" width="46" height="12" rx="3" class="art-metal"/>
      <path d="M72 58 77 25Q91 12 105 25L110 58Z" class="art-color"/>
      <path d="M79 29Q91 19 103 29" class="art-highlight"/>
      <path d="M74 84h34" class="art-detail"/>
    </svg>
    <svg v-else-if="type === 'powder'" viewBox="0 0 180 150" role="presentation">
      <ellipse cx="90" cy="132" rx="54" ry="8" class="art-shadow"/>
      <ellipse cx="90" cy="90" rx="49" ry="35" class="art-case"/>
      <path d="M41 88a49 35 0 0 1 98 0" class="art-lid"/>
      <ellipse cx="90" cy="88" rx="36" ry="24" class="art-powder"/>
      <path d="M57 89a33 21 0 0 0 66 0" class="art-detail"/>
      <path d="m74 63 32 0" class="art-highlight"/>
    </svg>
    <svg v-else-if="type === 'bridal'" viewBox="0 0 180 150" role="presentation">
      <ellipse cx="90" cy="132" rx="53" ry="8" class="art-shadow"/>
      <path d="M89 28c-17-23-52-2-34 22l35 38 35-38c18-24-17-45-36-22Z" class="art-color"/>
      <path d="M89 34c-10-12-26-2-18 10l18 20 18-20c8-12-8-22-18-10Z" class="art-highlight"/>
      <path d="M48 105q42 18 84 0" class="art-case"/>
      <path d="M68 112h44" class="art-detail"/>
    </svg>
    <svg v-else-if="type === 'serum'" viewBox="0 0 180 150" role="presentation">
      <ellipse cx="90" cy="132" rx="43" ry="8" class="art-shadow"/>
      <rect x="77" y="27" width="26" height="19" rx="4" class="art-metal"/>
      <rect x="84" y="13" width="12" height="17" rx="3" class="art-dropper"/>
      <rect x="63" y="44" width="54" height="82" rx="12" class="art-glass"/>
      <rect x="69" y="52" width="42" height="67" rx="8" class="art-liquid"/>
      <path d="M74 61v44" class="art-highlight"/>
      <path d="M76 82h28" class="art-detail"/>
    </svg>
    <svg v-else-if="type === 'brush'" viewBox="0 0 180 150" role="presentation">
      <ellipse cx="90" cy="132" rx="45" ry="8" class="art-shadow"/>
      <path d="m74 81 21-54q9-11 20-4l-23 62Z" class="art-bristle"/>
      <path d="m72 78 29 13-23 42q-4 5-10 2l-8-4q-5-3-2-9Z" class="art-case"/>
      <path d="m76 87 22 10" class="art-detail"/>
    </svg>
    <svg v-else viewBox="0 0 180 150" role="presentation">
      <ellipse cx="90" cy="132" rx="52" ry="8" class="art-shadow"/>
      <rect x="48" y="49" width="84" height="76" rx="18" class="art-case"/>
      <rect x="54" y="37" width="72" height="20" rx="9" class="art-metal"/>
      <rect x="56" y="64" width="68" height="49" rx="12" class="art-powder"/>
      <circle cx="90" cy="88" r="17" class="art-color"/>
      <circle cx="90" cy="88" r="10" class="art-highlight"/>
      <path d="M62 71q28-18 56 0" class="art-detail"/>
    </svg>
  </div>
</template>
