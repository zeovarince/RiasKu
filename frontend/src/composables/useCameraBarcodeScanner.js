import { onUnmounted, ref } from 'vue'

const SUPPORTED_FORMATS = [
  'ean_13',
  'ean_8',
  'upc_a',
  'upc_e',
  'code_128',
  'code_39',
  'qr_code',
]

let detectorPromise = null

async function getBarcodeDetector() {
  if (!detectorPromise) {
    detectorPromise = import('barcode-detector/ponyfill').then(({ BarcodeDetector }) => {
      return new BarcodeDetector({ formats: SUPPORTED_FORMATS })
    })
  }
  return detectorPromise
}

function playScanBeep() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext
    if (!AudioCtx) return
    const ctx = new AudioCtx()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = 'sine'
    osc.frequency.setValueAtTime(1046.5, ctx.currentTime)
    gain.gain.setValueAtTime(0.08, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.14)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start()
    osc.stop(ctx.currentTime + 0.15)
  } catch {
    // Ignore audio context blocks
  }
}

export function useCameraBarcodeScanner() {
  const isStarting = ref(false)
  const isScanning = ref(false)
  const errorMessage = ref('')
  const cameras = ref([])
  const selectedDeviceId = ref('')
  const lastDetectedFormat = ref('')

  let stream = null
  let scanTimer = null
  let activeVideoEl = null
  let isStopping = false

  function stopCamera() {
    isStopping = true
    if (scanTimer) {
      clearTimeout(scanTimer)
      scanTimer = null
    }
    if (stream) {
      stream.getTracks().forEach((track) => track.stop())
      stream = null
    }
    if (activeVideoEl) {
      activeVideoEl.srcObject = null
      activeVideoEl = null
    }
    isScanning.value = false
    isStarting.value = false
  }

  async function refreshCameraList() {
    if (!navigator.mediaDevices?.enumerateDevices) return
    try {
      const devices = await navigator.mediaDevices.enumerateDevices()
      cameras.value = devices.filter((device) => device.kind === 'videoinput')
      if (!selectedDeviceId.value && cameras.value.length > 0) {
        const rear = cameras.value.find((d) => /back|rear|environment/i.test(d.label))
        selectedDeviceId.value = (rear || cameras.value[0]).deviceId
      }
    } catch {
      cameras.value = []
    }
  }

  async function startCamera(videoEl, onDetected, preferredDeviceId = '') {
    stopCamera()
    isStopping = false
    errorMessage.value = ''

    if (!navigator.mediaDevices?.getUserMedia) {
      errorMessage.value =
        'Kamera tidak tersedia di browser ini. Pastikan halaman diakses melalui HTTPS atau localhost, atau gunakan opsi Foto/Upload Barcode.'
      return
    }

    isStarting.value = true
    try {
      const detector = await getBarcodeDetector()
      if (isStopping) return

      const deviceId = preferredDeviceId || selectedDeviceId.value
      const videoConstraints = deviceId
        ? { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } }
        : { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }

      stream = await navigator.mediaDevices.getUserMedia({
        video: videoConstraints,
        audio: false,
      })

      if (isStopping) {
        stopCamera()
        return
      }

      activeVideoEl = videoEl
      videoEl.srcObject = stream
      videoEl.setAttribute('playsinline', 'true')
      await videoEl.play()

      await refreshCameraList()
      isStarting.value = false
      isScanning.value = true

      const detectLoop = async () => {
        if (isStopping || !activeVideoEl || !stream) return
        try {
          if (activeVideoEl.readyState >= 2 && activeVideoEl.videoWidth > 0) {
            const results = await detector.detect(activeVideoEl)
            if (results && results.length > 0 && results[0].rawValue) {
              const rawValue = String(results[0].rawValue).trim()
              lastDetectedFormat.value = results[0].format || ''
              playScanBeep()
              stopCamera()
              onDetected?.(rawValue, 'camera', lastDetectedFormat.value)
              return
            }
          }
        } catch {
          // Skip transient frame read errors
        }

        if (!isStopping) {
          scanTimer = window.setTimeout(detectLoop, 125)
        }
      }

      scanTimer = window.setTimeout(detectLoop, 200)
    } catch (err) {
      stopCamera()
      if (err?.name === 'NotAllowedError' || err?.name === 'PermissionDeniedError') {
        errorMessage.value =
          'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser atau gunakan tombol Foto / Upload Barcode.'
      } else if (err?.name === 'NotFoundError' || err?.name === 'DevicesNotFoundError') {
        errorMessage.value =
          'Kamera tidak ditemukan pada perangkat ini. Anda tetap bisa memakai Scanner USB/Bluetooth atau Upload Foto Barcode.'
      } else {
        errorMessage.value =
          err?.message || 'Gagal menyalakan kamera. Coba muat ulang atau gunakan Upload Foto Barcode.'
      }
    }
  }

  async function decodeImageFile(file) {
    errorMessage.value = ''
    const detector = await getBarcodeDetector()

    let source = null
    let objectUrl = null

    try {
      if (typeof createImageBitmap === 'function') {
        source = await createImageBitmap(file)
      } else {
        objectUrl = URL.createObjectURL(file)
        source = await new Promise((resolve, reject) => {
          const img = new Image()
          img.onload = () => resolve(img)
          img.onerror = reject
          img.src = objectUrl
        })
      }

      const results = await detector.detect(source)
      if (!results || results.length === 0 || !results[0].rawValue) {
        return null
      }

      const rawValue = String(results[0].rawValue).trim()
      lastDetectedFormat.value = results[0].format || ''
      playScanBeep()
      return {
        barcode: rawValue,
        format: lastDetectedFormat.value,
      }
    } finally {
      if (source && typeof source.close === 'function') {
        source.close()
      }
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl)
      }
    }
  }

  onUnmounted(() => {
    stopCamera()
  })

  return {
    isStarting,
    isScanning,
    errorMessage,
    cameras,
    selectedDeviceId,
    lastDetectedFormat,
    startCamera,
    stopCamera,
    decodeImageFile,
  }
}
