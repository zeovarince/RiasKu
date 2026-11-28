import { onMounted, onUnmounted, toValue } from 'vue'

/**
 * Listens for USB/Bluetooth HID keyboard-wedge barcode scanners.
 * Hardware scanners type characters rapidly (<60ms apart) ending with Enter or Tab.
 */
export function useHardwareBarcodeScanner({
  enabled = true,
  onScan,
  minLength = 5,
  maxIntervalMs = 65,
}) {
  let buffer = ''
  let lastKeyTime = 0
  let burstTarget = null
  let preBurstValue = null

  function resetBuffer() {
    buffer = ''
    lastKeyTime = 0
    burstTarget = null
    preBurstValue = null
  }

  function handleKeyDown(event) {
    if (!toValue(enabled)) {
      resetBuffer()
      return
    }

    if (event.ctrlKey || event.altKey || event.metaKey) {
      return
    }

    const now = Date.now()
    const target = event.target
    const isBarcodeField = target instanceof HTMLElement && target.dataset?.barcodeInput === 'true'
    const isEditableField =
      target instanceof HTMLInputElement ||
      target instanceof HTMLTextAreaElement ||
      target instanceof HTMLSelectElement ||
      target?.isContentEditable

    if (event.key === 'Enter' || event.key === 'Tab') {
      const code = buffer.trim()
      const isRapidBurst = code.length >= minLength && lastKeyTime > 0 && now - lastKeyTime <= maxIntervalMs * 2

      if (isRapidBurst) {
        event.preventDefault()
        event.stopPropagation()

        // If a hardware scan fired while another input (not barcode input) had focus,
        // restore its pre-burst value so scanned digits do not pollute that field.
        if (
          isEditableField &&
          !isBarcodeField &&
          burstTarget === target &&
          preBurstValue !== null &&
          'value' in target
        ) {
          target.value = preBurstValue
          target.dispatchEvent(new Event('input', { bubbles: true }))
        }

        resetBuffer()
        onScan?.(code, 'hardware')
        return
      }

      resetBuffer()
      return
    }

    if (event.key.length !== 1) {
      return
    }

    if (now - lastKeyTime > maxIntervalMs) {
      buffer = ''
      burstTarget = isEditableField && !isBarcodeField ? target : null
      preBurstValue =
        burstTarget && 'value' in burstTarget ? String(burstTarget.value) : null
    }

    buffer += event.key
    lastKeyTime = now
  }

  onMounted(() => {
    window.addEventListener('keydown', handleKeyDown, true)
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown, true)
    resetBuffer()
  })

  return { resetBuffer }
}
