<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ProductArtwork from '@/components/ProductArtwork.vue'
import { useVoiceAssistant } from '@/composables/useVoiceAssistant'
import { useAiChatStore } from '@/stores/aiChat'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import { formatChatMarkdown } from '@/utils/formatChatMarkdown'
import { formatRupiah } from '@/utils/currency'

const props = defineProps({
  mode: { type: String, default: 'page' }, // 'modal' | 'page'
  contextProductId: { type: [Number, String], default: null },
  initialPrompt: { type: String, default: '' },
})

const emit = defineEmits(['navigate'])

const router = useRouter()
const aiChat = useAiChatStore()
const cart = useCartStore()
const ui = useUiStore()
const voice = useVoiceAssistant()

const question = ref('')
const composerInput = ref(null)
const messagesContainer = ref(null)
const quickCartFeedback = ref('')
const copiedMessageId = ref(null)

const desktopSidebarOpen = ref(true)
const mobileSidebarOpen = ref(false)
const historySearch = ref('')
const renamingToken = ref(null)
const renameDraft = ref('')

const STARTER_META = [
  { tag: 'KULIT & SHADE', hint: 'Cocokkan tekstur & warna shade' },
  { tag: 'JASA RIAS', hint: 'Paket wisuda, lamaran & pengantin' },
  { tag: 'STOK & PRE-ORDER', hint: 'Cek produk PO & estimasi hari' },
  { tag: 'ORDER & COD', hint: 'Info bayar Surabaya & cek resi' },
]

const starterCards = computed(() => {
  return aiChat.suggestions.slice(0, 4).map((prompt, idx) => ({
    prompt,
    tag: STARTER_META[idx]?.tag || 'REKOMENDASI',
    hint: STARTER_META[idx]?.hint || 'Tanyakan langsung ke Asisten AI',
  }))
})

const hasConversationTurns = computed(() => {
  return aiChat.messages.some((m) => m.role === 'user')
})

const latestFollowUps = computed(() => {
  for (let i = aiChat.messages.length - 1; i >= 0; i--) {
    const msg = aiChat.messages[i]
    if (msg.role === 'assistant' && Array.isArray(msg.follow_up_suggestions) && msg.follow_up_suggestions.length > 0) {
      return msg.follow_up_suggestions
    }
  }
  return []
})

const groupedSessions = computed(() => {
  const query = historySearch.value.trim().toLowerCase()
  const filtered = query
    ? aiChat.sessions.filter((s) => (
        String(s.title || '').toLowerCase().includes(query)
        || String(s.last_message_preview || '').toLowerCase().includes(query)
      ))
    : aiChat.sessions

  const now = new Date()
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
  const startOfYesterday = startOfToday - 86400000
  const startOfLast7Days = startOfToday - 7 * 86400000

  const buckets = {
    today: { label: 'Hari Ini', items: [] },
    yesterday: { label: 'Kemarin', items: [] },
    week: { label: '7 Hari Terakhir', items: [] },
    older: { label: 'Lebih Lama', items: [] },
  }

  for (const s of filtered) {
    const ts = s.last_message_at ? new Date(s.last_message_at).getTime() : startOfToday
    if (ts >= startOfToday) buckets.today.items.push(s)
    else if (ts >= startOfYesterday) buckets.yesterday.items.push(s)
    else if (ts >= startOfLast7Days) buckets.week.items.push(s)
    else buckets.older.items.push(s)
  }

  return Object.values(buckets).filter((group) => group.items.length > 0)
})

function providerChipLabel(provider) {
  switch (provider) {
    case 'gemini': return 'Gemini AI'
    case 'groq': return 'Groq Llama'
    case 'openrouter': return 'OpenRouter'
    case 'fallback': return 'RiasKu Smart AI'
    default: return ''
  }
}

function resizeTextarea() {
  const el = composerInput.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${Math.min(el.scrollHeight, 150)}px`
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

watch(() => aiChat.messages.length, scrollToBottom)
watch(() => aiChat.isLoading, scrollToBottom)
watch(question, () => nextTick(resizeTextarea))

async function handleSend(textOverride = null) {
  const raw = typeof textOverride === 'string' ? textOverride : question.value
  const trimmed = String(raw || '').trim()
  if (trimmed.length < 2 || aiChat.isLoading) return

  voice.stopListening()
  voice.clearVoiceError()

  if (!textOverride) {
    question.value = ''
    nextTick(resizeTextarea)
  }

  const replyMsg = await aiChat.sendMessage(trimmed, {
    contextProductId: props.contextProductId || null,
  })

  if (!replyMsg && !textOverride && aiChat.error) {
    question.value = trimmed
    nextTick(resizeTextarea)
  }
}

function handleComposerKeydown(event) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    handleSend()
  }
}

function handleMicToggle() {
  if (voice.isListening.value) {
    voice.stopListening()
    return
  }
  const baseText = question.value.trim()
  voice.startListening({
    onInterim: (spoken) => {
      question.value = baseText ? `${baseText} ${spoken}` : spoken
    },
    onFinal: (spoken) => {
      question.value = baseText ? `${baseText} ${spoken}` : spoken
      nextTick(resizeTextarea)
    },
  })
}

function handleStartNewChat() {
  voice.stopListening()
  voice.clearVoiceError()
  aiChat.startNewChat()
  mobileSidebarOpen.value = false
  nextTick(() => composerInput.value?.focus())
}

async function handleSelectSession(token) {
  voice.stopListening()
  voice.clearVoiceError()
  await aiChat.selectSession(token)
  mobileSidebarOpen.value = false
}

function beginRename(sessionItem) {
  renamingToken.value = sessionItem.session_token
  renameDraft.value = sessionItem.title || ''
}

function cancelRename() {
  renamingToken.value = null
  renameDraft.value = ''
}

async function submitRename(token) {
  const clean = renameDraft.value.trim()
  renamingToken.value = null
  if (!clean) return
  await aiChat.renameSession(token, clean)
}

async function handleDeleteSession(sessionItem) {
  await aiChat.deleteSession(sessionItem.session_token)
}

async function copyMessage(message) {
  const content = String(message?.content || '')
  if (!content) return
  try {
    await navigator.clipboard.writeText(content)
    copiedMessageId.value = message.id
    setTimeout(() => {
      if (copiedMessageId.value === message.id) copiedMessageId.value = null
    }, 1800)
  } catch {
    copiedMessageId.value = null
  }
}

function stockPillClass(product) {
  if (product.stock_label === 'Tersedia') return 'pill-ready'
  if (product.stock_label === 'Pre-Order') return 'pill-preorder'
  return 'pill-empty'
}

function stockBadgeText(product) {
  if (product.stock_label === 'Pre-Order') {
    return product.preorder_estimate_days
      ? `Pre-Order · Est. ${product.preorder_estimate_days}h`
      : 'Pre-Order'
  }
  return product.stock_label || 'Tersedia'
}

function handleQuickCart(product) {
  if (product.type === 'service') {
    if (props.mode === 'modal') {
      ui.closeModal()
    }
    emit('navigate')
    router.push('/consultation')
    return
  }
  if (product.stock_label === 'Stok Habis') return
  const variant = product.default_variant_id
    ? {
        id: product.default_variant_id,
        name: product.default_variant_name || 'Standar',
        stock: product.default_variant_stock ?? 0,
        price: product.retail_price,
      }
    : null
  const added = cart.addItem(product, 1, variant)
  if (!added) return
  quickCartFeedback.value = `${product.name} ditambahkan ke keranjang.`
  setTimeout(() => { quickCartFeedback.value = '' }, 2500)
}

function openProductDetail(productId) {
  if (props.mode === 'modal') {
    ui.closeModal()
  }
  emit('navigate')
  router.push(`/products/${productId}`)
}

function openFullPage() {
  voice.stopListening()
  voice.clearVoiceError()
  ui.closeModal()
  router.push('/ai-assistant')
}

watch(
  () => props.initialPrompt,
  (val) => {
    if (val) {
      question.value = String(val)
      nextTick(resizeTextarea)
    }
  },
  { immediate: true },
)

onMounted(async () => {
  await aiChat.initSession()
  nextTick(resizeTextarea)
  scrollToBottom()
})
</script>

<template>
  <div
    class="ai-chat-panel"
    :class="[
      `mode-${mode}`,
      { 'sidebar-collapsed': mode === 'page' && !desktopSidebarOpen, 'mobile-drawer-open': mode === 'page' && mobileSidebarOpen },
    ]"
  >
    <!-- SIDEBAR RIWAYAT MULTI-SESI (Mode Halaman Penuh) -->
    <template v-if="mode === 'page'">
      <div
        v-if="mobileSidebarOpen"
        class="ai-sidebar-backdrop"
        aria-hidden="true"
        @click="mobileSidebarOpen = false"
      ></div>

      <aside class="ai-history-sidebar" aria-label="Riwayat percakapan AI">
        <div class="ai-sidebar-top">
          <button
            type="button"
            class="ai-new-chat-btn"
            @click="handleStartNewChat"
          >
            <span>＋</span>
            <span>Obrolan Baru</span>
          </button>
          <button
            type="button"
            class="ai-sidebar-close-mobile"
            aria-label="Tutup riwayat"
            @click="mobileSidebarOpen = false"
          >
            ✕
          </button>
        </div>

        <label class="ai-sidebar-search">
          <span aria-hidden="true">⌕</span>
          <input
            v-model="historySearch"
            type="search"
            placeholder="Cari riwayat obrolan..."
            aria-label="Cari riwayat obrolan"
          />
        </label>

        <div class="ai-sessions-scroll">
          <p v-if="aiChat.isLoadingSessions && !aiChat.sessions.length" class="ai-sessions-empty">
            Memuat riwayat sesi...
          </p>
          <div v-else-if="!groupedSessions.length" class="ai-sessions-empty">
            Belum ada riwayat tersimpan. Mulai obrolan baru dan setiap sesi otomatis tersimpan di sini.
          </div>

          <div v-for="group in groupedSessions" :key="group.label" class="ai-session-group">
            <span class="ai-session-group-label">{{ group.label }}</span>

            <div
              v-for="item in group.items"
              :key="item.session_token"
              class="ai-session-row"
              :class="{ active: aiChat.sessionToken === item.session_token }"
            >
              <form
                v-if="renamingToken === item.session_token"
                class="ai-session-rename-form"
                @submit.prevent="submitRename(item.session_token)"
              >
                <input
                  v-model="renameDraft"
                  type="text"
                  maxlength="115"
                  aria-label="Ubah judul sesi"
                  @keyup.escape="cancelRename"
                />
                <button type="submit" aria-label="Simpan judul">✓</button>
              </form>

              <template v-else>
                <button
                  type="button"
                  class="ai-session-select"
                  @click="handleSelectSession(item.session_token)"
                >
                  <strong class="ai-session-title">{{ item.title }}</strong>
                  <small v-if="item.last_message_preview" class="ai-session-preview">
                    {{ item.last_message_preview }}
                  </small>
                </button>
                <div class="ai-session-actions">
                  <button
                    type="button"
                    class="ai-session-icon-btn"
                    title="Ubah judul"
                    aria-label="Ubah judul sesi"
                    @click.stop="beginRename(item)"
                  >
                    ✎
                  </button>
                  <button
                    type="button"
                    class="ai-session-icon-btn danger"
                    title="Hapus percakapan"
                    aria-label="Hapus sesi"
                    @click.stop="handleDeleteSession(item)"
                  >
                    🗑
                  </button>
                </div>
              </template>
            </div>
          </div>
        </div>
      </aside>
    </template>

    <!-- MAIN CHAT STAGE -->
    <div class="ai-stage-main">
      <!-- Top Bar -->
      <div class="ai-panel-toolbar">
        <div class="ai-toolbar-left">
          <button
            v-if="mode === 'page'"
            type="button"
            class="ai-sidebar-toggle-btn"
            aria-label="Tampilkan atau sembunyikan riwayat"
            @click="desktopSidebarOpen = !desktopSidebarOpen; mobileSidebarOpen = !mobileSidebarOpen"
          >
            ☰ Riwayat
          </button>

          <div class="ai-toolbar-title-wrap">
            <span class="online-dot"></span>
            <strong v-if="mode === 'page'" class="ai-stage-title">
              {{ aiChat.activeSessionTitle || 'Asisten Cerdas RiasKu' }}
            </strong>
            <span v-else class="ai-stage-subtitle">Terhubung ke Katalog RiasKu</span>
          </div>
        </div>

        <div v-if="mode === 'modal'" class="ai-toolbar-actions">
          <button
            type="button"
            class="ai-toolbar-btn"
            @click="aiChat.resetSession()"
          >
            ↺ Percakapan Baru
          </button>
          <button
            type="button"
            class="ai-toolbar-btn primary-link"
            @click="openFullPage"
          >
            Buka Layar Penuh →
          </button>
        </div>
      </div>

      <!-- Modal Quick Suggestions Strip (kept crisp on modal mode) -->
      <div v-if="mode === 'modal'" class="chat-suggestions" aria-label="Saran pertanyaan">
        <span>PERTANYAAN POPULER</span>
        <button
          v-for="prompt in aiChat.suggestions"
          :key="prompt"
          type="button"
          :disabled="aiChat.isLoading"
          @click="handleSend(prompt)"
        >
          {{ prompt }}
        </button>
      </div>

      <!-- Scrollable Message Area -->
      <div
        ref="messagesContainer"
        class="chat-messages"
        :class="{ 'modal-chat-messages': mode === 'modal', 'page-chat-messages': mode === 'page' }"
        aria-live="polite"
      >
        <div class="chat-stream-inner">
          <div v-if="aiChat.isHydrating" class="ai-hydrating-note">Memuat riwayat percakapan...</div>

          <!-- Welcome Hero ala ChatGPT untuk Page Mode saat belum ada chat user -->
          <div v-if="mode === 'page' && !hasConversationTurns && !aiChat.isHydrating" class="ai-welcome-hero">
            <div class="ai-welcome-orb" aria-hidden="true">✦</div>
            <span class="eyebrow-badge">RIASKU BEAUTY &amp; SHOPPING AI</span>
            <h1>Ada yang bisa RiasKu bantu hari ini?</h1>
            <p>
              Konsultasikan kecocokan shade, kandungan produk, ketersediaan stok &amp; Pre-Order, booking rias Surabaya, hingga status pesanan Anda.
            </p>

            <div class="ai-starter-grid">
              <button
                v-for="card in starterCards"
                :key="card.prompt"
                type="button"
                class="ai-starter-card"
                :disabled="aiChat.isLoading"
                @click="handleSend(card.prompt)"
              >
                <span class="starter-tag">{{ card.tag }}</span>
                <strong>{{ card.prompt }}</strong>
                <small>{{ card.hint }} →</small>
              </button>
            </div>
          </div>

          <!-- Render Chat Bubbles -->
          <template v-for="message in aiChat.messages" :key="message.id">
            <div
              v-if="!(mode === 'page' && !hasConversationTurns && message.id === 'welcome')"
              :class="['chat-bubble', message.role]"
            >
              <div v-if="message.role === 'assistant'" class="assistant-msg-shell">
                <div class="assistant-msg-avatar" aria-hidden="true">✦</div>
                <div class="assistant-msg-body">
                  <!-- eslint-disable-next-line vue/no-v-html -->
                  <div class="chat-bubble-markdown" v-html="formatChatMarkdown(message.content)"></div>

                  <!-- Kartu Rekomendasi Produk Nyata -->
                  <div
                    v-if="message.recommended_products?.length"
                    class="ai-rec-grid"
                  >
                    <div v-for="prod in message.recommended_products" :key="prod.id" class="ai-rec-card">
                      <div class="ai-rec-thumb">
                        <img v-if="prod.image_url" :src="prod.image_url" :alt="prod.name" loading="lazy" />
                        <ProductArtwork v-else :type="prod.art || 'cushion'" />
                      </div>
                      <div class="ai-rec-info">
                        <strong>{{ prod.name }}</strong>
                        <div class="ai-rec-meta">
                          <span class="ai-rec-price">{{ prod.price_label || formatRupiah(prod.retail_price) }}</span>
                          <span class="stock-pill" :class="stockPillClass(prod)">{{ stockBadgeText(prod) }}</span>
                        </div>
                        <div class="ai-rec-actions">
                          <button type="button" class="ai-rec-btn-detail" @click="openProductDetail(prod.id)">
                            Lihat Produk
                          </button>
                          <button
                            v-if="prod.type === 'service'"
                            type="button"
                            class="ai-rec-btn-cart"
                            @click="() => { if (mode === 'modal') ui.closeModal(); router.push('/consultation') }"
                          >
                            Konsultasi
                          </button>
                          <button
                            v-else
                            type="button"
                            class="ai-rec-btn-cart"
                            :disabled="prod.stock_label === 'Stok Habis'"
                            @click="handleQuickCart(prod)"
                          >
                            {{ prod.stock_label === 'Pre-Order' ? '＋ PO' : (prod.stock_label === 'Stok Habis' ? 'Habis' : '＋ Keranjang') }}
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Action Bar Per Pesan (Salin + Model Badge) -->
                  <div class="msg-action-bar">
                    <button
                      type="button"
                      class="msg-action-btn"
                      @click="copyMessage(message)"
                    >
                      {{ copiedMessageId === message.id ? '✓ Disalin' : '📋 Salin' }}
                    </button>

                    <span v-if="providerChipLabel(message.provider)" class="msg-provider-tag">
                      {{ providerChipLabel(message.provider) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- User Bubble -->
              <p v-else class="chat-bubble-text">{{ message.content }}</p>
            </div>
          </template>

          <div v-if="aiChat.isLoading" class="chat-bubble assistant ai-typing-bubble">
            <div class="assistant-msg-shell">
              <div class="assistant-msg-avatar" aria-hidden="true">✦</div>
              <div class="typing-dots">
                <span>Asisten RiasKu sedang menyiapkan jawaban</span>
                <i></i><i></i><i></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Dock: Follow-ups, Notices & ChatGPT Pill Composer -->
      <div class="ai-composer-dock">
        <div class="ai-composer-inner">
          <div v-if="latestFollowUps.length && !aiChat.isLoading" class="ai-followups">
            <span>Lanjutkan tanya:</span>
            <button
              v-for="follow in latestFollowUps"
              :key="follow"
              type="button"
              @click="handleSend(follow)"
            >
              {{ follow }}
            </button>
          </div>

          <p v-if="quickCartFeedback" class="ai-cart-toast" role="status">✓ {{ quickCartFeedback }}</p>
          <p v-if="aiChat.error" class="form-error" role="alert">⚠️ {{ aiChat.error }}</p>
          <p v-if="voice.voiceError.value" class="form-error" role="alert">🎙 {{ voice.voiceError.value }}</p>

          <div v-if="voice.isListening.value" class="ai-stt-banner" role="status">
            <span class="stt-live-dot" aria-hidden="true"></span>
            <span class="stt-live-text">
              Mendengarkan suara Anda (Bahasa Indonesia){{ voice.interimTranscript.value ? `: “${voice.interimTranscript.value}”` : '...' }}
            </span>
            <button type="button" class="stt-stop-chip" @click="voice.stopListening()">Selesai</button>
          </div>

          <form class="chat-composer ai-pill-composer" @submit.prevent="handleSend()">
            <textarea
              ref="composerInput"
              v-model="question"
              class="ai-composer-textarea"
              rows="1"
              maxlength="1000"
              placeholder="Tulis pertanyaan produk, shade, Pre-Order, atau COD..."
              aria-label="Pesan untuk asisten AI"
              :disabled="aiChat.isLoading"
              @keydown="handleComposerKeydown"
            ></textarea>

            <div class="ai-composer-buttons">
              <button
                type="button"
                class="ai-icon-action"
                :class="{ recording: voice.isListening.value }"
                :title="voice.isListening.value ? 'Hentikan dikte suara' : 'Bicara ke teks (Bahasa Indonesia)'"
                :aria-label="voice.isListening.value ? 'Hentikan rekaman suara' : 'Input suara ke teks'"
                :disabled="aiChat.isLoading"
                @click="handleMicToggle"
              >
                <svg v-if="voice.isListening.value" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                  <rect x="6" y="6" width="12" height="12" rx="2.5" />
                </svg>
                <svg v-else viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
                  <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                  <line x1="12" x2="12" y1="19" y2="22" />
                </svg>
              </button>

              <button
                class="ai-send-circle"
                type="submit"
                aria-label="Kirim pesan"
                :disabled="aiChat.isLoading || question.trim().length < 2"
              >
                {{ aiChat.isLoading ? '…' : '➤' }}
              </button>
            </div>
          </form>

          <p v-if="mode === 'page'" class="ai-composer-footnote">
            Asisten RiasKu merujuk data katalog &amp; pesanan nyata · Tekan <strong>Enter</strong> kirim, <strong>Shift+Enter</strong> baris baru.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ============================================================
   SHELL & MODE LAYOUTS
   ============================================================ */
.ai-chat-panel.mode-modal {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.ai-chat-panel.mode-page {
  position: relative;
  display: grid;
  grid-template-columns: 288px minmax(0, 1fr);
  height: calc(100vh - 74px);
  min-height: 580px;
  max-width: 1440px;
  width: 100%;
  margin: 0 auto;
  border-top: 1px solid var(--border, #ead6d9);
  background: #fffdfb;
  overflow: hidden;
  transition: grid-template-columns 0.22s ease;
}

.ai-chat-panel.mode-page.sidebar-collapsed {
  grid-template-columns: 0px minmax(0, 1fr);
}

/* ============================================================
   LEFT HISTORY SIDEBAR (ChatGPT Style)
   ============================================================ */
.ai-history-sidebar {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  background: #fdf6f7;
  border-right: 1px solid #eedbe0;
  padding: 14px 12px;
  gap: 10px;
}

.ai-chat-panel.mode-page.sidebar-collapsed .ai-history-sidebar {
  padding-inline: 0;
  border-right: 0;
  opacity: 0;
  pointer-events: none;
}

.ai-sidebar-top {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ai-new-chat-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid #d9b2be;
  background: #ffffff;
  color: #4a2c35;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(74, 44, 53, 0.04);
  transition: background 0.15s ease, transform 0.15s ease;
}
.ai-new-chat-btn:hover:not(:disabled) {
  background: #fff0f4;
  transform: translateY(-1px);
}

.ai-sidebar-close-mobile {
  display: none;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid #e5ccd3;
  background: #fff;
  color: #4a2c35;
}

.ai-sidebar-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 11px;
  border-radius: 10px;
  border: 1px solid #ebd7dc;
  background: #ffffff;
  color: #8c777b;
  font-size: 12px;
}
.ai-sidebar-search input {
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: #4a2c35;
  font-size: 12px;
}

.ai-sessions-scroll {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-right: 2px;
}

.ai-sessions-empty {
  padding: 18px 10px;
  text-align: center;
  font-size: 12px;
  line-height: 1.55;
  color: #8c777b;
}

.ai-session-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ai-session-group-label {
  padding: 2px 8px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  color: #9c7a84;
}

.ai-session-row {
  display: flex;
  align-items: center;
  gap: 4px;
  border-radius: 10px;
  padding: 3px;
  transition: background 0.15s ease;
}
.ai-session-row:hover { background: rgba(156, 74, 99, 0.08); }
.ai-session-row.active {
  background: #f9dfe6;
  border: 1px solid #eabcc8;
}

.ai-session-select {
  flex: 1;
  min-width: 0;
  text-align: left;
  border: 0;
  background: transparent;
  padding: 6px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  cursor: pointer;
}

.ai-session-title {
  font-size: 12.5px;
  font-weight: 600;
  color: #3b1e2e;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ai-session-preview {
  font-size: 11px;
  color: #856b72;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ai-session-actions {
  display: flex;
  align-items: center;
  gap: 2px;
  opacity: 0.7;
  padding-right: 4px;
}
.ai-session-row:hover .ai-session-actions,
.ai-session-row.active .ai-session-actions { opacity: 1; }

.ai-session-icon-btn {
  width: 24px;
  height: 24px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #72515c;
  font-size: 12px;
  display: grid;
  place-items: center;
  cursor: pointer;
}
.ai-session-icon-btn:hover { background: #ffffff; color: #3b1e2e; }
.ai-session-icon-btn.danger:hover { color: #b91c1c; }

.ai-session-rename-form {
  flex: 1;
  display: flex;
  gap: 4px;
  padding: 4px;
}
.ai-session-rename-form input {
  flex: 1;
  min-width: 0;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid #9c4a63;
  font-size: 12px;
  outline: 0;
}
.ai-session-rename-form button {
  padding: 4px 8px;
  border-radius: 6px;
  border: 0;
  background: #9c4a63;
  color: #fff;
  font-size: 12px;
}

.ai-sidebar-footer {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 10px;
  border-top: 1px solid #ecd8de;
}

.ai-engine-card {
  padding: 9px 11px;
  border-radius: 10px;
  border: 1px solid #f3d5a5;
  background: #fffaf0;
  font-size: 11px;
  color: #7c521b;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.ai-engine-card.is-live {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #166534;
}
.ai-engine-head {
  display: flex;
  align-items: center;
  gap: 6px;
}
.engine-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}
.ai-engine-card code {
  font-size: 10px;
  background: rgba(0, 0, 0, 0.06);
  padding: 1px 4px;
  border-radius: 4px;
}

/* ============================================================
   RIGHT MAIN STAGE & TOOLBAR
   ============================================================ */
.ai-stage-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  height: 100%;
  background: #fffdfb;
}

.ai-panel-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 12px;
  color: var(--muted, #7d636c);
}

.mode-page .ai-panel-toolbar {
  padding: 11px 22px;
  border-bottom: 1px solid #f0e0e4;
  background: rgba(255, 253, 251, 0.94);
}

.mode-modal .ai-panel-toolbar {
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
}

.ai-toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}

.ai-sidebar-toggle-btn {
  padding: 6px 11px;
  border-radius: 9px;
  border: 1px solid #e6d1d7;
  background: #fff;
  color: #4a2c35;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
.ai-sidebar-toggle-btn:hover { background: #fdf1f4; }

.ai-toolbar-title-wrap {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;
}

.ai-stage-title {
  font-size: 14px;
  color: #3b1e2e;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ai-stage-subtitle {
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}

.ai-engine-pill {
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  white-space: nowrap;
}
.ai-engine-pill.is-real {
  background: #dcfce7;
  color: #166534;
  border-color: #bbf7d0;
}

.ai-toolbar-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 7px;
}

.ai-toolbar-btn {
  padding: 6px 11px;
  border-radius: 999px;
  border: 1px solid var(--border, #edd6dc);
  background: #fff;
  color: var(--ink, #4a2c35);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
.ai-toolbar-btn:hover:not(:disabled) { background: var(--pink, #fdf1f4); }
.ai-toolbar-btn.primary-link {
  color: var(--rose, #a54865);
  border-color: #f3cdd7;
}

/* ============================================================
   MESSAGE STREAM & WELCOME SCREEN
   ============================================================ */
.mode-page .chat-messages {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 24px 20px;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.mode-modal .modal-chat-messages {
  min-height: 250px;
  max-height: 44vh;
  overflow-y: auto;
  padding: 14px;
  border: 1px solid var(--border, #edd6dc);
  border-radius: 12px;
  background: #fffaf9;
}

.chat-stream-inner {
  width: 100%;
  max-width: 780px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ai-welcome-hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 28px 10px 12px;
  gap: 10px;
}

.ai-welcome-orb {
  width: 60px;
  height: 60px;
  border-radius: 18px;
  display: grid;
  place-items: center;
  font-size: 28px;
  color: #fff;
  background: linear-gradient(135deg, #d7728f 0%, #9c4a63 100%);
  box-shadow: 0 10px 28px rgba(156, 74, 99, 0.25);
  margin-bottom: 4px;
}

.ai-welcome-hero h1 {
  margin: 4px 0 2px;
  font-family: var(--serif);
  font-size: clamp(26px, 2.8vw, 34px);
  font-weight: 600;
  color: #3b1e2e;
}

.ai-welcome-hero p {
  max-width: 560px;
  margin: 0 0 12px;
  font-size: 13.5px;
  line-height: 1.65;
  color: #745c63;
}

.ai-starter-grid {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  text-align: left;
}

.ai-starter-card {
  display: flex;
  flex-direction: column;
  gap: 5px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid #ecd8de;
  background: #ffffff;
  color: #3b1e2e;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}
.ai-starter-card:hover:not(:disabled) {
  border-color: #c97b92;
  background: #fff9fb;
  box-shadow: 0 8px 20px rgba(74, 44, 53, 0.07);
  transform: translateY(-2px);
}
.starter-tag {
  font-family: var(--mono);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.7px;
  color: #9c4a63;
}
.ai-starter-card strong {
  font-size: 13px;
  line-height: 1.4;
}
.ai-starter-card small {
  font-size: 11px;
  color: #8c777b;
}

/* ============================================================
   BUBBLES, MARKDOWN, CARDS & MESSAGE ACTIONS
   ============================================================ */
.chat-bubble {
  max-width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
}

.chat-bubble.user {
  align-self: flex-end;
  max-width: 78%;
  padding: 11px 16px;
  border-radius: 18px 18px 4px 18px;
  border: 1px solid #ecc0cb;
  background: linear-gradient(145deg, #fde8ed 0%, #fadadd 100%);
  color: #3b1e2e;
  box-shadow: 0 2px 8px rgba(156, 74, 99, 0.08);
}

.chat-bubble.assistant {
  align-self: stretch;
}

.assistant-msg-shell {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.assistant-msg-avatar {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #f9dce4;
  border: 1px solid #e7b5c3;
  color: #9c4a63;
  font-size: 14px;
  margin-top: 2px;
}

.assistant-msg-body {
  flex: 1;
  min-width: 0;
  padding: 12px 16px;
  border-radius: 16px;
  border: 1px solid #f0e0e4;
  background: #ffffff;
  box-shadow: 0 2px 10px rgba(74, 44, 53, 0.03);
}

.chat-bubble-text {
  margin: 0;
  white-space: pre-line;
  font-size: 13.5px;
  line-height: 1.55;
}

.chat-bubble-markdown {
  font-size: 13.5px;
  line-height: 1.65;
  color: #3b1e2e;
}
.chat-bubble-markdown :deep(.chat-md-p) { margin: 0 0 8px; }
.chat-bubble-markdown :deep(.chat-md-p:last-child) { margin-bottom: 0; }
.chat-bubble-markdown :deep(.chat-md-heading) {
  margin: 8px 0 4px;
  font-family: var(--serif);
  font-size: 15px;
  color: #3b1e2e;
}
.chat-bubble-markdown :deep(.chat-md-list) {
  margin: 4px 0 8px;
  padding-left: 18px;
}
.chat-bubble-markdown :deep(.chat-md-list li) { margin-bottom: 3px; }
.chat-bubble-markdown :deep(.chat-inline-code) {
  font-family: var(--mono);
  font-size: 11.5px;
  background: #fdf1f4;
  color: #8f3551;
  padding: 1px 5px;
  border-radius: 5px;
}

.msg-action-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px dashed #f1dfe4;
}

.msg-action-btn {
  padding: 4px 9px;
  border-radius: 999px;
  border: 1px solid #ebd7dc;
  background: #fff9fb;
  color: #6f4e59;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}
.msg-action-btn:hover { background: #fdebf0; color: #3b1e2e; }
.msg-action-btn.active {
  border-color: #9c4a63;
  background: #9c4a63;
  color: #ffffff;
}

.msg-provider-tag {
  margin-left: auto;
  font-family: var(--mono);
  font-size: 10px;
  color: #9c828a;
}

.typing-dots {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 8px 14px;
  border-radius: 14px;
  background: #fff;
  border: 1px solid #f0e0e4;
  font-size: 12.5px;
  color: #7d636c;
}
.typing-dots i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #c86481;
  animation: dot-bounce 1s infinite ease-in-out;
}
.typing-dots i:nth-child(3) { animation-delay: 0.15s; }
.typing-dots i:nth-child(4) { animation-delay: 0.3s; }

@keyframes dot-bounce {
  0%, 80%, 100% { transform: scale(0.7); opacity: 0.45; }
  40% { transform: scale(1.1); opacity: 1; }
}

/* Recommendation Cards */
.ai-rec-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 8px;
  margin-top: 10px;
}
.mode-modal .ai-rec-grid {
  grid-template-columns: 1fr;
}

.ai-rec-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: 12px;
  border: 1px solid #efd8de;
  background: #fffaf9;
  color: #3b1e2e;
}

.ai-rec-thumb {
  width: 50px;
  height: 50px;
  flex-shrink: 0;
  border-radius: 9px;
  border: 1px solid #f0dfe1;
  background: #ffffff;
  display: grid;
  place-items: center;
  overflow: hidden;
}
.ai-rec-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ai-rec-thumb :deep(.product-artwork) { width: 38px; height: 38px; }

.ai-rec-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ai-rec-info strong { font-size: 12px; line-height: 1.25; color: #3b1e2e; }
.ai-rec-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.ai-rec-price { font-size: 12px; font-weight: 700; color: #a54865; }
.ai-rec-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }

.ai-rec-btn-detail,
.ai-rec-btn-cart {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
}
.ai-rec-btn-detail { border: 1px solid #d9bdc5; background: #ffffff; color: #4a2c35; }
.ai-rec-btn-cart { border: 1px solid #a54865; background: #a54865; color: #ffffff; }
.ai-rec-btn-cart:disabled { opacity: 0.45; cursor: not-allowed; }

/* ============================================================
   COMPOSER DOCK (Floating Pill ala ChatGPT)
   ============================================================ */
.ai-composer-dock {
  background: linear-gradient(180deg, rgba(255, 253, 251, 0) 0%, #fffdfb 22%);
}
.mode-page .ai-composer-dock {
  padding: 8px 20px 14px;
}

.ai-composer-inner {
  width: 100%;
  max-width: 780px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ai-followups {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.ai-followups span { font-size: 10px; font-weight: 700; color: var(--muted, #7d636c); }
.ai-followups button {
  padding: 5px 10px;
  border-radius: 999px;
  border: 1px dashed #d7b2be;
  background: #fff8fa;
  color: #7c3149;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}
.ai-followups button:hover { background: #fdeef2; }

.ai-cart-toast {
  margin: 0;
  padding: 7px 11px;
  border-radius: 8px;
  background: #e6f4ea;
  border: 1px solid #bbf7d0;
  color: #166534;
  font-size: 11.5px;
  font-weight: 600;
}

.ai-pill-composer {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 8px 10px 8px 16px;
  border-radius: 24px;
  border: 1.5px solid #e3c8cf;
  background: #ffffff;
  box-shadow: 0 8px 24px rgba(74, 44, 53, 0.06);
  transition: border-color 0.16s ease, box-shadow 0.16s ease;
}
.ai-pill-composer:focus-within {
  border-color: #9c4a63;
  box-shadow: 0 8px 28px rgba(156, 74, 99, 0.12);
}

.ai-composer-textarea {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  resize: none;
  background: transparent;
  color: #3b1e2e;
  font-size: 13.5px;
  line-height: 1.45;
  padding: 6px 0;
  max-height: 150px;
}
.ai-composer-textarea::placeholder { color: #9c868d; }

.ai-composer-buttons {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.ai-icon-action {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #ebd7dc;
  background: #fff9fb;
  color: #5c3a46;
  font-size: 15px;
  display: grid;
  place-items: center;
  cursor: pointer;
}
.ai-icon-action:hover:not(:disabled) { background: #fdebf0; }
.ai-icon-action.recording {
  border-color: #ef4444;
  background: #fee2e2;
  color: #b91c1c;
  animation: mic-pulse 1s infinite;
}

.ai-stt-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  border-radius: 12px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
  font-size: 12px;
}
.stt-live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ef4444;
  flex-shrink: 0;
}
.stt-live-text {
  flex: 1;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 600;
}
.stt-stop-chip {
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid #fca5a5;
  background: #ffffff;
  color: #991b1b;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.ai-send-circle {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  border: 1px solid #4a2c35;
  background: #eba9b8;
  color: #3b1e2e;
  font-size: 15px;
  font-weight: 800;
  display: grid;
  place-items: center;
  cursor: pointer;
}
.ai-send-circle:hover:not(:disabled) { background: #e49aab; }
.ai-send-circle:disabled { opacity: 0.45; cursor: not-allowed; }

.ai-composer-footnote {
  margin: 0;
  text-align: center;
  font-size: 10.5px;
  color: #9c828a;
}

.ai-hydrating-note {
  font-size: 12px;
  color: var(--muted, #7d636c);
  text-align: center;
  padding: 16px;
}

@keyframes mic-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.35); }
  50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
}

/* ============================================================
   RESPONSIVE MOBILE DRAWER (< 900px)
   ============================================================ */
.ai-sidebar-backdrop { display: none; }

@media (max-width: 900px) {
  .ai-chat-panel.mode-page,
  .ai-chat-panel.mode-page.sidebar-collapsed {
    grid-template-columns: 1fr;
    height: calc(100vh - 72px);
  }

  .ai-history-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: min(300px, 85vw);
    z-index: 160;
    transform: translateX(-105%);
    transition: transform 0.22s ease;
    box-shadow: 8px 0 32px rgba(48, 29, 36, 0.2);
  }

  .ai-chat-panel.mode-page.mobile-drawer-open .ai-history-sidebar {
    transform: translateX(0);
    opacity: 1;
    pointer-events: auto;
    padding: 14px 12px;
  }

  .ai-sidebar-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 150;
    background: rgba(48, 29, 36, 0.45);
  }

  .ai-sidebar-close-mobile {
    display: grid;
    place-items: center;
  }

  .ai-starter-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 560px) {
  .mode-page .ai-panel-toolbar { padding: 10px 12px; }
  .mode-page .chat-messages { padding: 14px 12px; }
  .mode-page .ai-composer-dock { padding: 6px 10px 10px; }
  .ai-engine-pill { display: none; }
}
</style>
