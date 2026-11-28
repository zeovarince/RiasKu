<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue'
import AdminIcon from './AdminIcon.vue'
import { useCameraBarcodeScanner } from '@/composables/useCameraBarcodeScanner'

const props = defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'detected'])

const videoRef = ref(null)
const photoInputRef = ref(null)
const decodingPhoto = ref(false)
const localError = ref('')

const {
  isStarting,
  isScanning,
  errorMessage,
  cameras,
  selectedDeviceId,
  startCamera,
  stopCamera,
  decodeImageFile,
} = useCameraBarcodeScanner()

function handleDetected(code, source, format = '') {
  emit('detected', { barcode: code, source, format })
  emit('close')
}

async function initScanner(deviceId = '') {
  localError.value = ''
  await nextTick()
  if (!videoRef.value) return
  await startCamera(videoRef.value, handleDetected, deviceId)
}

function handleCameraChange() {
  initScanner(selectedDeviceId.value)
}

async function handlePhotoUpload(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  localError.value = ''
  decodingPhoto.value = true
  try {
    const result = await decodeImageFile(file)
    if (!result?.barcode) {
      localError.value =
        'Barcode tidak terbaca pada foto tersebut. Pastikan garis barcode terlihat jelas, cukup cahaya, dan tidak buram.'
      return
    }
    stopCamera()
    handleDetected(result.barcode, 'photo', result.format)
  } catch {
    localError.value = 'Gagal memproses gambar barcode. Coba unggah foto lain yang lebih fokus.'
  } finally {
    decodingPhoto.value = false
  }
}

function handleKeydown(event) {
  if (event.key === 'Escape' && props.open) {
    emit('close')
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      window.addEventListener('keydown', handleKeydown)
      initScanner()
    } else {
      window.removeEventListener('keydown', handleKeydown)
      stopCamera()
      localError.value = ''
    }
  },
)

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  stopCamera()
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="open" class="barcode-modal-backdrop" @click.self="emit('close')">
        <section
          class="barcode-modal-dialog"
          role="dialog"
          aria-modal="true"
          aria-labelledby="barcode-scanner-title"
        >
          <header class="barcode-modal-header">
            <div>
              <span class="barcode-modal-eyebrow">SCANNER PRODUK RIASKU</span>
              <h2 id="barcode-scanner-title">Scan Barcode via Kamera</h2>
            </div>
            <button
              type="button"
              class="barcode-modal-close"
              aria-label="Tutup kamera scanner"
              @click="emit('close')"
            >
              ×
            </button>
          </header>

          <div v-if="cameras.length > 1" class="barcode-camera-picker">
            <label for="barcode-camera-select">Pilih Kamera</label>
            <select
              id="barcode-camera-select"
              v-model="selectedDeviceId"
              @change="handleCameraChange"
            >
              <option v-for="(cam, index) in cameras" :key="cam.deviceId" :value="cam.deviceId">
                {{ cam.label || `Kamera ${index + 1}` }}
              </option>
            </select>
          </div>

          <div class="barcode-viewport">
            <video ref="videoRef" class="barcode-video" muted playsinline></video>
            <div class="barcode-reticle" aria-hidden="true">
              <span class="reticle-corner tl"></span>
              <span class="reticle-corner tr"></span>
              <span class="reticle-corner bl"></span>
              <span class="reticle-corner br"></span>
              <span v-if="isScanning" class="reticle-laser"></span>
            </div>
            <div v-if="isStarting" class="barcode-overlay-status">
              Menyalakan kamera...
            </div>
          </div>

          <p class="barcode-hint">
            Arahkan barcode kemasan (EAN-13, UPC, Code-128, atau QR) ke dalam kotak bingkai dengan pencahayaan cukup.
          </p>

          <p v-if="errorMessage || localError" class="barcode-error" role="alert">
            <AdminIcon name="alert-triangle" :size="16" />
            <span>{{ localError || errorMessage }}</span>
          </p>

          <footer class="barcode-modal-footer">
            <input
              ref="photoInputRef"
              type="file"
              accept="image/*"
              capture="environment"
              class="sr-only-input"
              @change="handlePhotoUpload"
            />
            <button
              type="button"
              class="admin-btn admin-btn-outline"
              :disabled="decodingPhoto"
              @click="photoInputRef?.click()"
            >
              <AdminIcon name="camera" :size="16" />
              {{ decodingPhoto ? 'Membaca Foto...' : 'Upload / Foto Barcode' }}
            </button>
            <button
              type="button"
              class="admin-btn admin-btn-primary"
              @click="emit('close')"
            >
              Tutup
            </button>
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.barcode-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(45, 25, 32, 0.56);
  backdrop-filter: blur(4px);
}

.barcode-modal-dialog {
  width: min(100%, 540px);
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 22px;
  border: 1px solid #e6cbd1;
  border-radius: 16px;
  background: #fffdfc;
  box-shadow: 0 24px 64px rgba(42, 20, 29, 0.28);
}

.barcode-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.barcode-modal-eyebrow {
  display: inline-block;
  font-family: 'Space Mono', monospace;
  font-size: 10px;
  letter-spacing: 0.08em;
  color: #9c4a63;
  margin-bottom: 4px;
}

.barcode-modal-header h2 {
  margin: 0;
  font-family: 'Playfair Display', Georgia, serif;
  font-size: 21px;
  color: #4a2c35;
}

.barcode-modal-close {
  width: 34px;
  height: 34px;
  border: 1px solid #ead6d9;
  border-radius: 50%;
  background: #fff;
  color: #4a2c35;
  font-size: 22px;
  line-height: 1;
  display: grid;
  place-items: center;
  cursor: pointer;
}

.barcode-modal-close:hover {
  background: #fadadd;
}

.barcode-camera-picker {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: #6b4e57;
}

.barcode-camera-picker select {
  flex: 1;
  height: 36px;
  padding: 0 10px;
  border: 1px solid #dec2c8;
  border-radius: 8px;
  background: #fff;
  color: #4a2c35;
  font-size: 12px;
}

.barcode-viewport {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 10;
  border-radius: 12px;
  overflow: hidden;
  background: #1b1115;
  border: 1px solid #4a2c35;
}

.barcode-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.barcode-reticle {
  position: absolute;
  inset: 18% 12%;
  pointer-events: none;
}

.reticle-corner {
  position: absolute;
  width: 24px;
  height: 24px;
  border-color: #f6d6a8;
  border-style: solid;
}

.reticle-corner.tl { top: 0; left: 0; border-width: 3px 0 0 3px; border-top-left-radius: 8px; }
.reticle-corner.tr { top: 0; right: 0; border-width: 3px 3px 0 0; border-top-right-radius: 8px; }
.reticle-corner.bl { bottom: 0; left: 0; border-width: 0 0 3px 3px; border-bottom-left-radius: 8px; }
.reticle-corner.br { bottom: 0; right: 0; border-width: 0 3px 3px 0; border-bottom-right-radius: 8px; }

.reticle-laser {
  position: absolute;
  left: 6%;
  right: 6%;
  top: 50%;
  height: 2px;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent, #eba9b8, #ffffff, #eba9b8, transparent);
  box-shadow: 0 0 12px rgba(235, 169, 184, 0.9);
}

@media (prefers-reduced-motion: no-preference) {
  .reticle-laser {
    animation: barcode-laser-sweep 2.1s cubic-bezier(0.77, 0, 0.175, 1) infinite alternate;
  }
}

@keyframes barcode-laser-sweep {
  from { transform: translateY(-42px); }
  to { transform: translateY(42px); }
}

.barcode-overlay-status {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(27, 17, 21, 0.72);
  color: #fff9f7;
  font-size: 13px;
  font-weight: 600;
}

.barcode-hint {
  margin: 0;
  font-size: 12px;
  line-height: 1.55;
  color: #7d626a;
}

.barcode-error {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #f2bcc5;
  background: #fff3f5;
  color: #9e2f44;
  font-size: 12px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.barcode-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.sr-only-input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
</style>
