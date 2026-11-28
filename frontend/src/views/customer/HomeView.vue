<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import CategoryQuickLinks from '@/components/CategoryQuickLinks.vue'
import ProductCard from '@/components/ProductCard.vue'
import ProductArtwork from '@/components/ProductArtwork.vue'
import { getHomeData } from '@/services/homeService'
import { useStoreSettingsStore } from '@/stores/storeSettings'
import { buildWhatsAppUrl } from '@/utils/whatsapp'

const storeSettings = useStoreSettingsStore()
const loading = ref(true)
const loadError = ref('')
const categories = ref([])
const products = ref([])
const isFallbackCatalog = ref(false)
const activeFilter = ref('Semua')
const activeSlide = ref(0)
let sliderTimer

const heroWhatsAppUrl = computed(() => buildWhatsAppUrl(storeSettings.settings))

const filters = computed(() => {
  const productCategories = [...new Set(
    products.value
      .map((product) => String(product.categoryLabel || '').trim())
      .filter(Boolean),
  )]
  const dynamicNames = (productCategories.length > 0
    ? productCategories
    : categories.value.map((cat) => cat.name).filter(Boolean)
  ).slice(0, 4)
  return ['Semua', ...dynamicNames]
})

const slides = [
  {
    eyebrow: 'RIASKU SIGNATURE STUDIO',
    title: 'Beauty made personal.',
    description: 'Temukan sentuhan rias natural dan produk pilihan untuk setiap momen istimewa.',
    art: 'cushion',
    image: '/images/products/velvet-matte-cushion.jpg',
    imageAlt: 'Koleksi Velvet Matte Cushion SPF 35 RiasKu',
    imagePosition: 'center',
    note: 'Official Cosmetic Store · Surabaya',
    action: 'Jelajahi produk pilihan',
  },
  {
    eyebrow: 'BEST SELLER COLLECTION',
    title: 'Glow with confidence.',
    description: 'Pilihan complexion dan lip care untuk tampilan segar, lembut, dan percaya diri.',
    art: 'lip',
    image: '/images/products/glaze-tinted-lip-oil.jpg',
    imageAlt: 'Glaze Tinted Lip Oil Butter koleksi best seller RiasKu',
    imagePosition: 'center',
    note: 'Produk favorit pelanggan RiasKu',
    action: 'Lihat koleksi best seller',
  },
  {
    eyebrow: 'MAKEUP SERVICE',
    title: 'Your moment, beautifully yours.',
    description: 'Reservasi layanan makeup profesional untuk wisuda, lamaran, dan hari pernikahan.',
    art: 'bridal',
    image: '/images/products/paket-rias-pengantin.jpg',
    imageAlt: 'Layanan Paket Rias Pengantin Modern RiasKu Surabaya',
    imagePosition: 'center 20%',
    note: 'Booking makeup artist Surabaya',
    action: 'Konsultasi jadwal rias',
  },
]

const currentSlide = computed(() => slides[activeSlide.value])
const filteredProducts = computed(() => {
  if (activeFilter.value === 'Semua') return products.value
  return products.value.filter(
    (product) => String(product.categoryLabel || '').toLowerCase() === activeFilter.value.toLowerCase(),
  )
})

function goToSlide(index) {
  activeSlide.value = (index + slides.length) % slides.length
}

function nextSlide() {
  goToSlide(activeSlide.value + 1)
}

onMounted(async () => {
  sliderTimer = window.setInterval(nextSlide, 5500)
  try {
    const data = await getHomeData()
    categories.value = data.categories
    products.value = data.bestSellers
    isFallbackCatalog.value = Boolean(data.isFallbackCatalog)
  } catch (error) {
    loadError.value = 'Belum dapat memuat produk. Silakan coba lagi.'
    console.error(error)
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => window.clearInterval(sliderTimer))
</script>

<template>
  <div class="home-page">
    <section class="hero-section page-container">
      <div class="hero-copy">
        <span class="eyebrow-badge">LAYANAN &amp; PRODUK RESMI RIASKU</span>
        <h1>Sentuhan Rias Natural untuk Momen Bersejarah Anda.</h1>
        <p>Koleksi kosmetik teruji klinis dan layanan tata rias profesional di Surabaya. Diformulasikan lembut untuk ketahanan prima pada kulit perempuan Indonesia.</p>
        <div class="hero-actions">
          <RouterLink to="/products" class="button button-primary">Jelajahi Katalog <span aria-hidden="true">↗</span></RouterLink>
          <a
            v-if="storeSettings.hasActiveWhatsApp && heroWhatsAppUrl"
            class="button button-outline"
            :href="heroWhatsAppUrl"
            target="_blank"
            rel="noopener noreferrer"
          >
            <span class="button-symbol" aria-hidden="true">◔</span> Konsultasi WhatsApp
          </a>
          <RouterLink v-else to="/consultation" class="button button-outline">
            <span class="button-symbol" aria-hidden="true">◔</span> Konsultasi Rias
          </RouterLink>
        </div>
      </div>

      <div class="hero-slider" aria-roledescription="carousel" aria-label="Sorotan RiasKu">
        <Transition name="slide-fade" mode="out-in">
          <article :key="activeSlide" class="hero-slide-card">
            <div class="hero-slide-topline"><span class="eyebrow-badge">{{ currentSlide.eyebrow }}</span><span class="slide-index">0{{ activeSlide + 1 }} / 0{{ slides.length }}</span></div>
            <div class="hero-slide-art">
              <img
                v-if="currentSlide.image"
                :src="currentSlide.image"
                :alt="currentSlide.imageAlt || currentSlide.title"
                class="hero-slide-photo"
                width="248"
                height="248"
                :fetchpriority="activeSlide === 0 ? 'high' : undefined"
                :style="{ objectPosition: currentSlide.imagePosition || 'center' }"
              />
              <ProductArtwork v-else :type="currentSlide.art" />
            </div>
            <div class="hero-slide-copy">
              <h2>{{ currentSlide.title }}</h2>
              <p>{{ currentSlide.description }}</p>
              <span class="hero-slide-note"><span class="online-dot"></span>{{ currentSlide.note }}</span>
            </div>
            <RouterLink :to="activeSlide === 2 ? '/consultation' : '/products'" class="hero-slide-link">{{ currentSlide.action }} <span>→</span></RouterLink>
          </article>
        </Transition>
        <div class="slider-controls">
          <div class="slider-dots" aria-label="Pilih slide">
            <button v-for="(_, index) in slides" :key="index" type="button" :aria-label="`Slide ${index + 1}`" :aria-current="activeSlide === index ? 'true' : undefined" :class="{ active: activeSlide === index }" @click="goToSlide(index)"></button>
          </div>
          <div class="slider-arrows">
            <button type="button" aria-label="Slide sebelumnya" @click="goToSlide(activeSlide - 1)">←</button>
            <button type="button" aria-label="Slide berikutnya" @click="nextSlide">→</button>
          </div>
        </div>
      </div>
    </section>

    <CategoryQuickLinks :categories="categories" />

    <section class="bestseller-section page-container">
      <div class="section-heading">
        <div>
          <span class="section-kicker">PILIHAN PELANGGAN</span>
          <h2>
            {{ isFallbackCatalog ? 'Produk Pilihan' : 'Paling Diminati' }}
            <span>{{ isFallbackCatalog ? '(Koleksi Terbaru)' : '(Best Seller)' }}</span>
          </h2>
          <p>
            {{ isFallbackCatalog
              ? 'Koleksi produk terbaru dan unggulan yang siap dipesan dari RiasKu Official Store.'
              : 'Produk yang paling sering dibeli dalam 90 hari terakhir, dengan jumlah unit dan rating sebagai penilaian tambahan.'
            }}
          </p>
        </div>
        <div class="filter-pills" aria-label="Filter kategori produk">
          <button v-for="filter in filters" :key="filter" type="button" :class="['filter-pill', { active: activeFilter === filter }]" @click="activeFilter = filter">{{ filter }}</button>
        </div>
      </div>

      <p v-if="loading" class="state-message">Memuat produk pilihan...</p>
      <p v-else-if="loadError" class="state-message state-error">{{ loadError }}</p>
      <div v-else class="product-grid">
        <ProductCard v-for="product in filteredProducts" :key="product.id" :product="product" />
        <p v-if="filteredProducts.length === 0" class="state-message">Belum ada produk pada kategori ini.</p>
      </div>
      <div class="section-bottom-link"><RouterLink to="/products">Lihat semua produk <span>→</span></RouterLink></div>
    </section>

    <section class="consultation-wrap page-container">
      <div class="consultation-banner">
        <div>
          <span class="eyebrow-badge">LAYANAN RIAS &amp; KONSULTASI RESMI</span>
          <h2>Konsultasi Shade &amp; Reservasi Jadwal Rias Studio Surabaya</h2>
          <p>Dapatkan panduan pemilihan warna kosmetik yang presisi sesuai undertone kulit Anda atau jadwalkan sesi rias wisuda, lamaran, dan pernikahan bersama Makeup Artist profesional RiasKu.</p>
        </div>
        <RouterLink to="/consultation" class="button button-primary">Konsultasi Sekarang <span>›</span></RouterLink>
      </div>
    </section>
  </div>
</template>
