import { onBeforeUnmount, ref } from 'vue'

export function useVoiceAssistant() {
  const hasWindow = typeof window !== 'undefined'
  const SpeechRec = hasWindow ? (window.SpeechRecognition || window.webkitSpeechRecognition || null) : null

  const isSttSupported = ref(Boolean(SpeechRec))
  const isListening = ref(false)
  const interimTranscript = ref('')
  const voiceError = ref('')

  let recognition = null

  function clearVoiceError() {
    voiceError.value = ''
  }

  function abortListening() {
    const active = recognition
    recognition = null
    interimTranscript.value = ''
    isListening.value = false
    if (active) {
      active.onstart = null
      active.onresult = null
      active.onerror = null
      active.onend = null
      try {
        active.abort()
      } catch {
        // Ignore browser abort errors when recognition already ended
      }
    }
  }

  function stopListening() {
    if (recognition) {
      try {
        recognition.stop()
      } catch {
        // Ignore browser stop errors when recognition already ended
      }
    }
    isListening.value = false
  }

  function startListening({ onInterim = null, onFinal = null, onEnd = null } = {}) {
    voiceError.value = ''
    if (!SpeechRec) {
      voiceError.value = 'Browser ini belum mendukung fitur bicara ke teks (gunakan Chrome atau Edge).'
      return false
    }

    abortListening()

    try {
      const rec = new SpeechRec()
      rec.lang = 'id-ID'
      rec.interimResults = true
      rec.continuous = false
      rec.maxAlternatives = 1

      let finalCaptured = ''

      rec.onstart = () => {
        if (recognition !== rec) {
          return
        }
        isListening.value = true
        interimTranscript.value = ''
      }

      rec.onresult = (event) => {
        if (recognition !== rec) {
          return
        }
        let interim = ''
        for (let i = event.resultIndex; i < event.results.length; i++) {
          const piece = event.results[i][0]?.transcript || ''
          if (event.results[i].isFinal) {
            finalCaptured = `${finalCaptured} ${piece}`.trim()
          } else {
            interim = `${interim} ${piece}`.trim()
          }
        }
        interimTranscript.value = interim || finalCaptured
        if (typeof onInterim === 'function') {
          onInterim(finalCaptured || interim)
        }
      }

      rec.onerror = (event) => {
        if (recognition !== rec) {
          return
        }
        switch (event.error) {
          case 'not-allowed':
          case 'service-not-allowed':
            voiceError.value = 'Izin mikrofon ditolak browser. Aktifkan izin mikrofon pada browser lalu coba lagi.'
            break
          case 'no-speech':
            voiceError.value = 'Tidak ada suara terdeteksi. Coba berbicara lebih dekat ke mikrofon.'
            break
          case 'audio-capture':
            voiceError.value = 'Mikrofon tidak ditemukan pada perangkat ini.'
            break
          case 'network':
            voiceError.value = 'Koneksi layanan pengenalan suara terganggu. Periksa jaringan internet Anda.'
            break
          case 'aborted':
            break
          default:
            voiceError.value = 'Gagal menangkap suara. Silakan coba kembali.'
            break
        }
        isListening.value = false
      }

      rec.onend = () => {
        if (recognition !== rec) {
          return
        }
        isListening.value = false
        recognition = null
        const finalText = (finalCaptured || interimTranscript.value || '').trim()
        interimTranscript.value = ''
        if (finalText && typeof onFinal === 'function') {
          onFinal(finalText)
        }
        if (typeof onEnd === 'function') {
          onEnd(finalText)
        }
      }

      recognition = rec
      rec.start()
      return true
    } catch {
      voiceError.value = 'Mikrofon tidak dapat diaktifkan pada perangkat ini.'
      isListening.value = false
      return false
    }
  }

  onBeforeUnmount(() => {
    abortListening()
  })

  return {
    isSttSupported,
    isListening,
    interimTranscript,
    voiceError,
    clearVoiceError,
    startListening,
    stopListening,
    abortListening,
  }
}
