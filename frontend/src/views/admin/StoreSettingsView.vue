<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'
import { useStoreSettingsStore } from '@/stores/storeSettings'
import { getWhatsAppMessage, normalizeWhatsAppNumber } from '@/utils/whatsapp'

const store = useStoreSettingsStore()
const settings = reactive({
  store_name: 'RiasKu',
  store_tagline: '',
  store_logo: '',
  store_address: '',
  operational_hours: '',
  whatsapp_number: '',
  whatsapp_default_message: 'Halo RiasKu, saya ingin bertanya.',
  is_whatsapp_active: false,
})
const loading = ref(true)
const saving = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const fieldErrors = ref({})

const normalizedNumber = computed(() => normalizeWhatsAppNumber(settings.whatsapp_number))
const previewMessage = computed(() => getWhatsAppMessage(settings, {
  productName: 'Lip Tint Velvet',
  orderId: 'RSK-2026-001',
}))

function applySettings(value) {
  Object.assign(settings, {
    store_name: value.store_name || 'RiasKu',
    store_tagline: value.store_tagline || '',
    store_logo: value.store_logo || '',
    store_address: value.store_address || '',
    operational_hours: value.operational_hours || '',
    whatsapp_number: value.whatsapp_number || '',
    whatsapp_default_message: value.whatsapp_default_message || 'Halo RiasKu, saya ingin bertanya.',
    is_whatsapp_active: Boolean(value.is_whatsapp_active),
  })
}

async function loadSettings() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await adminService.storeSettings()
    const data = response.data?.data || response.data
    applySettings(data)
  } catch {
    errorMessage.value = 'Pengaturan toko gagal dimuat. Muat ulang halaman atau coba lagi.'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  successMessage.value = ''
  errorMessage.value = ''
  fieldErrors.value = {}

  if (settings.is_whatsapp_active && !normalizedNumber.value) {
    fieldErrors.value = { whatsapp_number: ['Masukkan nomor WhatsApp Indonesia yang valid sebelum mengaktifkan tombol.'] }
    errorMessage.value = 'Periksa kembali nomor WhatsApp.'
    return
  }

  saving.value = true
  try {
    const response = await adminService.updateStoreSettings({ ...settings })
    const data = response.data?.data || response.data
    applySettings(data)
    store.setSettings(data)
    successMessage.value = 'Pengaturan toko berhasil disimpan.'
  } catch (error) {
    fieldErrors.value = error.response?.data?.errors || {}
    errorMessage.value = error.response?.data?.message || 'Pengaturan toko gagal disimpan. Periksa data lalu coba lagi.'
  } finally {
    saving.value = false
  }
}

onMounted(loadSettings)
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / PENGATURAN / TOKO"
      title="Pengaturan Toko"
      description="Kelola identitas toko dan tombol WhatsApp yang dilihat pelanggan."
    >
      <template #actions>
        <button class="admin-btn admin-btn-primary" type="submit" form="store-settings-form" :disabled="saving || loading">
          <AdminIcon name="check" :size="15" />
          {{ saving ? 'Menyimpan...' : 'Simpan Pengaturan' }}
        </button>
      </template>
    </AdminPageHeader>

    <p v-if="successMessage" class="admin-inline-success" role="status">{{ successMessage }}</p>
    <p v-if="errorMessage" class="admin-inline-error" role="alert">{{ errorMessage }}</p>
    <p v-if="loading" class="admin-panel admin-panel-hint" role="status">Memuat pengaturan toko...</p>

    <form v-else id="store-settings-form" class="admin-settings-grid" @submit.prevent="saveSettings">
      <div class="admin-settings-main">
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div><h2>Profil Toko</h2><p>Informasi toko ditampilkan di area publik RiasKu.</p></div>
          </div>
          <label class="admin-field">Nama Toko <em>*</em>
            <input v-model.trim="settings.store_name" required maxlength="120" autocomplete="organization" />
            <small v-if="fieldErrors.store_name" class="admin-field-error">{{ fieldErrors.store_name[0] }}</small>
          </label>
          <label class="admin-field">Deskripsi Singkat
            <input v-model.trim="settings.store_tagline" maxlength="255" placeholder="Kosmetik pilihan untuk setiap hari" />
            <small v-if="fieldErrors.store_tagline" class="admin-field-error">{{ fieldErrors.store_tagline[0] }}</small>
          </label>
          <label class="admin-field">URL Logo Toko
            <input v-model.trim="settings.store_logo" type="url" maxlength="2048" placeholder="https://..." />
            <small v-if="fieldErrors.store_logo" class="admin-field-error">{{ fieldErrors.store_logo[0] }}</small>
          </label>
          <label class="admin-field">Alamat Toko / Pengiriman
            <textarea v-model.trim="settings.store_address" rows="3" maxlength="2000" placeholder="Alamat yang ingin ditampilkan kepada pelanggan" />
            <small v-if="fieldErrors.store_address" class="admin-field-error">{{ fieldErrors.store_address[0] }}</small>
          </label>
          <label class="admin-field">Jam Operasional
            <input v-model.trim="settings.operational_hours" maxlength="500" placeholder="Contoh: Senin–Sabtu, 09.00–17.00 WIB" />
            <small v-if="fieldErrors.operational_hours" class="admin-field-error">{{ fieldErrors.operational_hours[0] }}</small>
          </label>
        </article>

        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div><h2>Kontak WhatsApp</h2><p>Nomor lokal 08... akan disimpan dalam format internasional 628....</p></div>
          </div>
          <label class="admin-field">Nomor WhatsApp Admin
            <input v-model.trim="settings.whatsapp_number" type="tel" maxlength="30" autocomplete="tel" placeholder="081234567890 atau 6281234567890" />
            <small v-if="fieldErrors.whatsapp_number" class="admin-field-error">{{ fieldErrors.whatsapp_number[0] }}</small>
            <small v-else-if="normalizedNumber">Nomor tersimpan: {{ normalizedNumber }}</small>
          </label>
          <label class="admin-field">Template Pesan Otomatis
            <textarea v-model.trim="settings.whatsapp_default_message" rows="4" maxlength="500" placeholder="Halo RiasKu, saya ingin bertanya tentang {product_name}." />
            <small>Gunakan <code>{product_name}</code> atau <code>{order_id}</code> untuk pesan kontekstual.</small>
            <small v-if="fieldErrors.whatsapp_default_message" class="admin-field-error">{{ fieldErrors.whatsapp_default_message[0] }}</small>
          </label>
          <div class="admin-message-preview">
            <strong>Pratinjau pesan</strong>
            <p>{{ previewMessage }}</p>
          </div>
          <label class="admin-switch-row">
            <span><strong>Aktifkan tombol WhatsApp</strong><small>Tombol melayang dan kontak WhatsApp tampil jika nomor valid.</small></span>
            <input v-model="settings.is_whatsapp_active" type="checkbox" class="admin-toggle" />
          </label>
          <small v-if="fieldErrors.is_whatsapp_active" class="admin-field-error">{{ fieldErrors.is_whatsapp_active[0] }}</small>
        </article>
      </div>

      <aside class="admin-settings-aside">
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading"><h2>Pratinjau Identitas</h2></div>
          <div class="admin-store-logo-preview">
            <img v-if="settings.store_logo" :src="settings.store_logo" :alt="`Logo ${settings.store_name}`" />
            <img v-else src="/images/riasku-mark.png" alt="Logo RiasKu" />
          </div>
          <strong>{{ settings.store_name || 'RiasKu' }}</strong>
          <p v-if="settings.store_tagline" class="admin-panel-hint">{{ settings.store_tagline }}</p>
          <p v-if="settings.store_address" class="admin-panel-hint">{{ settings.store_address }}</p>
          <p v-if="settings.operational_hours" class="admin-panel-hint">{{ settings.operational_hours }}</p>
        </article>
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading"><h2>Status Tombol</h2></div>
          <p>{{ settings.is_whatsapp_active && normalizedNumber ? 'Tombol WhatsApp siap ditampilkan kepada pelanggan.' : 'Tombol WhatsApp disembunyikan sampai diaktifkan dengan nomor valid.' }}</p>
        </article>
      </aside>
    </form>
  </section>
</template>
