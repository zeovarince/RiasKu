<script setup>
import { computed, onMounted, ref } from 'vue'
import { useStoreSettingsStore } from '@/stores/storeSettings'
import { useUiStore } from '@/stores/ui'
import { normalizeWhatsAppNumber } from '@/utils/whatsapp'

const storeSettings = useStoreSettingsStore()
const ui = useUiStore()

const form = ref({ name: '', contact: '', service: 'Konsultasi shade', date: '', notes: '' })
const submitted = ref(false)

const activeWhatsAppNumber = computed(() => {
  if (!storeSettings.hasActiveWhatsApp) return ''
  return normalizeWhatsAppNumber(storeSettings.settings.whatsapp_number)
})

const consultationMessage = computed(() => {
  const lines = [
    `Halo ${storeSettings.settings.store_name || 'RiasKu'}, saya ingin mengajukan konsultasi.`,
    `Nama: ${form.value.name || '-'}`,
    `No. WhatsApp: ${form.value.contact || '-'}`,
    `Kebutuhan: ${form.value.service}`,
  ]
  if (form.value.date) lines.push(`Tanggal pilihan: ${form.value.date}`)
  if (form.value.notes.trim()) lines.push(`Catatan: ${form.value.notes.trim()}`)
  return lines.join('\n')
})

const whatsappConsultUrl = computed(() => {
  if (!activeWhatsAppNumber.value) return ''
  return `https://wa.me/${activeWhatsAppNumber.value}?text=${encodeURIComponent(consultationMessage.value)}`
})

function openAiConsultation() {
  const prompt = [
    `Halo Asisten RiasKu, saya membutuhkan ${form.value.service}.`,
    form.value.date ? `Rencana tanggal acara: ${form.value.date}.` : '',
    form.value.notes.trim() ? `Detail kebutuhan: ${form.value.notes.trim()}.` : '',
    'Mohon rekomendasikan produk atau persiapan rias yang sesuai.',
  ].filter(Boolean).join(' ')

  ui.openModal('ai', { initialPrompt: prompt })
}

function submit() {
  submitted.value = true
  if (whatsappConsultUrl.value) {
    window.open(whatsappConsultUrl.value, '_blank', 'noopener,noreferrer')
  }
}

onMounted(() => storeSettings.load())
</script>

<template>
  <section class="page-container inner-page narrow-page">
    <span class="eyebrow-badge">LAYANAN RIAS &amp; KONSULTASI</span>
    <h1 class="page-title">Konsultasi RiasKu</h1>
    <p class="page-subtitle">Ceritakan kebutuhanmu. Tim RiasKu melalui WhatsApp resmi atau Asisten AI siap membantu memilih produk dan layanan rias yang paling sesuai.</p>

    <form class="surface-card track-form" @submit.prevent="submit">
      <label for="consult-name">Nama
        <input id="consult-name" v-model.trim="form.name" class="form-control" autocomplete="name" required />
      </label>
      <label for="consult-phone">Nomor WhatsApp
        <input id="consult-phone" v-model.trim="form.contact" class="form-control" type="tel" autocomplete="tel" placeholder="0812xxxxxxxx" required />
      </label>
      <label for="consult-service">Kebutuhan
        <select id="consult-service" v-model="form.service" class="form-control">
          <option>Konsultasi shade</option>
          <option>Rias wisuda</option>
          <option>Rias lamaran</option>
          <option>Rias pernikahan</option>
        </select>
      </label>
      <label for="consult-date">Tanggal pilihan (opsional)
        <input id="consult-date" v-model="form.date" class="form-control" type="date" />
      </label>
      <label for="consult-notes">Catatan tambahan (opsional)
        <textarea id="consult-notes" v-model="form.notes" class="form-control" rows="3" placeholder="Contoh: kulit sawo matang berminyak, tema rias soft glam"></textarea>
      </label>

      <div class="consult-actions">
        <button v-if="whatsappConsultUrl" class="button button-primary" type="submit">
          Kirim via WhatsApp Resmi ↗
        </button>
        <button class="button" :class="whatsappConsultUrl ? 'button-outline' : 'button-primary'" type="button" @click="openAiConsultation">
          ✦ Tanya Asisten AI RiasKu
        </button>
      </div>

      <p v-if="submitted && whatsappConsultUrl" class="notice-message" role="status">
        Pesan konsultasi telah disiapkan untuk WhatsApp resmi {{ storeSettings.settings.store_name || 'RiasKu' }}.
        Jika tab WhatsApp tidak terbuka otomatis, <a :href="whatsappConsultUrl" target="_blank" rel="noopener noreferrer">klik di sini untuk membuka WhatsApp</a>.
      </p>
      <p v-else-if="!whatsappConsultUrl" class="notice-message" role="status">
        Kontak WhatsApp admin belum diaktifkan pada Pengaturan Toko. Anda tetap dapat berkonsultasi langsung secara instan menggunakan <strong>Asisten AI RiasKu</strong> di atas.
      </p>
    </form>
  </section>
</template>

<style scoped>
.consult-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 0.5rem;
}
</style>
