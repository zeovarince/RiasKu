<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'

const route = useRoute()
const router = useRouter()

const isEdit = computed(() => Boolean(route.params.id))
const productId = computed(() => route.params.id)

const categories = ref([])
const loading = ref(false)
const saving = ref(false)
const globalError = ref('')
const serverWarnings = ref([])
const errors = ref({})

// Form state
const form = ref({
  name: '',
  kategori_id: '',
  brand: '',
  sku: '',
  weight_grams: 50,
  base_price: '',
  retail_price: '',
  is_pack: false,
  pack_quantity: '',
  pack_price: '',
  is_preorder: false,
  preorder_estimate_days: '',
  short_ingredients: '',
  bpom_number: '',
  description: '',
  is_active: true,
  initial_stock: 0,
})

// Custom variants toggle & list
const useCustomVariants = ref(false)
const originalInitialStock = ref(null)
const customVariants = ref([
  { name: '', stock: 0 },
])

// Photos state (for edit mode & preview)
const existingPhotos = ref([])
const pendingFiles = ref([])
const previewUrls = ref([])
const uploadingPhoto = ref(false)

// Real-time price warning calculation
const priceWarning = computed(() => {
  const base = Number(form.value.base_price)
  const retail = Number(form.value.retail_price)
  if (!base || !retail) return null

  const minRec = base + 1000
  const maxRec = base + 2000

  if (retail < minRec || retail > maxRec) {
    return `Peringatan: Harga eceran di luar rentang ideal (modal + Rp1.000 s/d Rp2.000). Rentang yang disarankan: Rp${minRec.toLocaleString('id-ID')} - Rp${maxRec.toLocaleString('id-ID')}. (Hanya peringatan, tetap dapat disimpan)`
  }
  return null
})

// Estimated profit calculation
const estimatedProfit = computed(() => {
  const base = Number(form.value.base_price) || 0
  const retail = Number(form.value.retail_price) || 0
  return Math.max(0, retail - base)
})

// Real-time pack price check
const packPriceError = computed(() => {
  if (!form.value.is_pack) return null
  const base = Number(form.value.base_price)
  const retail = Number(form.value.retail_price)
  const pack = Number(form.value.pack_price)

  if (pack && base && retail) {
    if (pack <= base || pack >= retail) {
      return `Harga pack per pcs harus berada di antara harga modal (Rp${base.toLocaleString('id-ID')}) dan harga eceran (Rp${retail.toLocaleString('id-ID')}).`
    }
  }
  return null
})

async function fetchCategories() {
  try {
    const res = await adminService.getCategories()
    categories.value = res.data.data || []
  } catch (err) {
    console.error('Gagal mengambil kategori:', err)
  }
}

async function loadProduct() {
  if (!isEdit.value) return
  loading.value = true
  globalError.value = ''
  try {
    const res = await adminService.getProduct(productId.value)
    const p = res.data.data
    form.value = {
      name: p.name || '',
      kategori_id: p.kategori_id || '',
      brand: p.brand || '',
      sku: p.sku || '',
      weight_grams: p.weight_grams || 50,
      base_price: p.base_price || '',
      retail_price: p.retail_price || '',
      is_pack: Boolean(p.pack_price || p.pack_quantity),
      pack_quantity: p.pack_quantity || '',
      pack_price: p.pack_price || '',
      is_preorder: Boolean(p.is_preorder),
      preorder_estimate_days: p.preorder_estimate_days || '',
      short_ingredients: p.short_ingredients || '',
      bpom_number: p.bpom_number || '',
      description: p.description || '',
      is_active: Boolean(p.is_active),
      initial_stock: p.total_stock || 0,
    }

    if (p.varians && p.varians.length > 0) {
      if (p.varians.length === 1 && p.varians[0].name === 'Standar') {
        useCustomVariants.value = false
        form.value.initial_stock = p.varians[0].stock
        originalInitialStock.value = p.varians[0].stock
      } else {
        useCustomVariants.value = true
        originalInitialStock.value = null
        customVariants.value = p.varians.map(v => ({
          id: v.id,
          name: v.name,
          stock: v.stock,
          original_stock: v.stock,
          is_active: v.is_active,
        }))
      }
    } else {
      originalInitialStock.value = p.total_stock || 0
    }

    existingPhotos.value = p.fotos || []
  } catch (err) {
    globalError.value = err.response?.data?.message || 'Gagal memuat detail produk.'
  } finally {
    loading.value = false
  }
}

function addVariantRow() {
  customVariants.value.push({ name: '', stock: 0 })
}

function removeVariantRow(index) {
  customVariants.value.splice(index, 1)
  if (customVariants.value.length === 0) {
    customVariants.value.push({ name: '', stock: 0 })
  }
}

function handleFileSelect(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if (file.size > 2 * 1024 * 1024) {
      alert(`Berkas ${file.name} melebihi batas ukuran 2 MB.`)
      continue
    }
    pendingFiles.value.push(file)
    previewUrls.value.push({
      file,
      url: URL.createObjectURL(file),
      name: file.name,
    })
  }
  // reset input
  e.target.value = ''
}

function removePendingFile(index) {
  pendingFiles.value.splice(index, 1)
  previewUrls.value.splice(index, 1)
}

async function uploadSinglePhotoInEdit(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    alert('Ukuran foto melebihi batas 2 MB.')
    return
  }

  uploadingPhoto.value = true
  const formData = new FormData()
  formData.append('foto', file)
  try {
    await adminService.uploadPhoto(productId.value, formData)
    await loadProduct()
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal mengunggah foto.')
  } finally {
    uploadingPhoto.value = false
    e.target.value = ''
  }
}

async function setPrimaryPhoto(photoId) {
  try {
    await adminService.updatePhoto(photoId, { is_primary: true })
    await loadProduct()
  } catch (err) {
    alert('Gagal mengatur foto utama.')
  }
}

async function deleteExistingPhoto(photoId) {
  if (!confirm('Hapus foto produk ini?')) return
  try {
    await adminService.deletePhoto(photoId)
    await loadProduct()
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus foto.')
  }
}

async function saveProduct() {
  errors.value = {}
  globalError.value = ''
  serverWarnings.value = []
  saving.value = true

  const payload = {
    name: form.value.name,
    kategori_id: form.value.kategori_id,
    brand: form.value.brand,
    sku: form.value.sku || null,
    weight_grams: Number(form.value.weight_grams),
    base_price: Number(form.value.base_price),
    retail_price: Number(form.value.retail_price),
    is_preorder: form.value.is_preorder,
    preorder_estimate_days: form.value.is_preorder ? Number(form.value.preorder_estimate_days) : null,
    short_ingredients: form.value.short_ingredients || null,
    bpom_number: form.value.bpom_number || null,
    description: form.value.description || null,
    is_active: form.value.is_active,
  }

  if (form.value.is_pack) {
    payload.pack_quantity = Number(form.value.pack_quantity) || null
    payload.pack_price = Number(form.value.pack_price) || null
  } else {
    payload.pack_quantity = null
    payload.pack_price = null
  }

  if (useCustomVariants.value) {
    payload.varians = customVariants.value.filter(v => v.name.trim()).map(v => ({
      ...(v.id ? { id: v.id } : {}),
      ...(v.id && v.original_stock !== undefined ? { original_stock: Number(v.original_stock) } : {}),
      name: v.name.trim(),
      stock: Number(v.stock) || 0,
    }))
  } else {
    payload.initial_stock = Number(form.value.initial_stock) || 0
    if (isEdit.value && originalInitialStock.value !== null) {
      payload.original_initial_stock = Number(originalInitialStock.value)
    }
  }

  try {
    let savedProductId = productId.value
    if (isEdit.value) {
      const res = await adminService.updateProduct(productId.value, payload)
      if (res.data.warnings?.length) {
        serverWarnings.value = res.data.warnings
      }
    } else {
      const res = await adminService.createProduct(payload)
      savedProductId = res.data.data.id
      if (res.data.warnings?.length) {
        serverWarnings.value = res.data.warnings
      }
    }

    // Unggah pending photos jika ada
    if (pendingFiles.value.length > 0 && savedProductId) {
      for (let i = 0; i < pendingFiles.value.length; i++) {
        const formData = new FormData()
        formData.append('foto', pendingFiles.value[i])
        formData.append('sort_order', i + 1)
        if (i === 0 && !isEdit.value) {
          formData.append('is_primary', '1')
        }
        try {
          await adminService.uploadPhoto(savedProductId, formData)
        } catch (e) {
          console.error('Gagal mengunggah foto tambahan:', e)
        }
      }
    }

    alert(isEdit.value ? 'Produk berhasil diperbarui!' : 'Produk berhasil ditambahkan!')
    router.push('/admin/products')
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      errors.value = err.response.data.errors
      globalError.value = 'Periksa kembali formulir di bawah. Terdapat isian yang belum sesuai.'
    } else {
      globalError.value = err.response?.data?.message || 'Gagal menyimpan produk.'
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchCategories()
  if (isEdit.value) {
    loadProduct()
  }
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      :eyebrow="isEdit ? 'ADMIN / PRODUK / UBAH' : 'ADMIN / PRODUK / TAMBAH'"
      :title="isEdit ? `Ubah Produk: ${form.name || ''}` : 'Tambah Produk Baru'"
      description="Lengkapi informasi produk, harga, varian, dan foto agar tampil di katalog RiasKu."
    >
      <template #actions>
        <RouterLink to="/admin/products" class="admin-btn admin-btn-outline">
          Kembali
        </RouterLink>
      </template>
    </AdminPageHeader>

    <div v-if="globalError" class="admin-alert-banner danger">
      <AdminIcon name="alert-triangle" :size="18" />
      <span>{{ globalError }}</span>
      <button class="admin-alert-close" @click="globalError = ''">×</button>
    </div>

    <form class="admin-form-layout" @submit.prevent="saveProduct">
      <div class="admin-form-main">
        <!-- 1. Informasi Produk -->
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Informasi Produk</h2>
              <p>Informasi dasar produk kosmetik yang akan dilihat oleh pelanggan.</p>
            </div>
          </div>

          <label class="admin-field">
            Nama Produk <em>*</em>
            <input
              v-model="form.name"
              placeholder="Contoh: Dewy Cushion SPF 35"
              :class="{ 'is-invalid': errors.name }"
              required
            />
            <small v-if="errors.name" class="admin-error-text">{{ errors.name[0] }}</small>
          </label>

          <div class="admin-field-grid">
            <label class="admin-field">
              Kategori <em>*</em>
              <select
                v-model="form.kategori_id"
                :class="{ 'is-invalid': errors.kategori_id }"
                required
              >
                <option value="" disabled>Pilih kategori</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
              <small v-if="errors.kategori_id" class="admin-error-text">{{ errors.kategori_id[0] }}</small>
            </label>

            <label class="admin-field">
              Merek / Brand <em>*</em>
              <input
                v-model="form.brand"
                placeholder="Contoh: RiasKu, Somethinc, dsb."
                :class="{ 'is-invalid': errors.brand }"
                required
              />
              <small v-if="errors.brand" class="admin-error-text">{{ errors.brand[0] }}</small>
            </label>
          </div>

          <div class="admin-field-grid">
            <label class="admin-field">
              SKU / Kode Produk
              <input
                v-model="form.sku"
                placeholder="Contoh: RSK-CUSH-01"
                :class="{ 'is-invalid': errors.sku }"
              />
              <small v-if="errors.sku" class="admin-error-text">{{ errors.sku[0] }}</small>
            </label>

            <label class="admin-field">
              Berat Bersih (gram) <em>*</em>
              <input
                v-model.number="form.weight_grams"
                type="number"
                min="1"
                placeholder="50"
                :class="{ 'is-invalid': errors.weight_grams }"
                required
              />
              <small v-if="errors.weight_grams" class="admin-error-text">{{ errors.weight_grams[0] }}</small>
            </label>
          </div>

          <label class="admin-field">
            Deskripsi Produk
            <textarea
              v-model="form.description"
              rows="3"
              placeholder="Manfaat, tekstur, cara pemakaian, dan keunggulan produk..."
              :class="{ 'is-invalid': errors.description }"
            ></textarea>
            <small v-if="errors.description" class="admin-error-text">{{ errors.description[0] }}</small>
          </label>

          <div class="admin-field-grid">
            <label class="admin-field">
              Nomor BPOM (opsional)
              <input
                v-model="form.bpom_number"
                placeholder="Contoh: NA18210300123"
                :class="{ 'is-invalid': errors.bpom_number }"
              />
              <small v-if="errors.bpom_number" class="admin-error-text">{{ errors.bpom_number[0] }}</small>
            </label>

            <label class="admin-field">
              Ingredients Singkat (opsional)
              <input
                v-model="form.short_ingredients"
                placeholder="Contoh: Niacinamide, Hyaluronic Acid, Centella"
                :class="{ 'is-invalid': errors.short_ingredients }"
              />
              <small v-if="errors.short_ingredients" class="admin-error-text">{{ errors.short_ingredients[0] }}</small>
            </label>
          </div>
        </article>

        <!-- 2. Harga & Pack -->
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Harga & Keuntungan</h2>
              <p>Atur harga modal (kulakan) dan harga jual eceran serta opsi pack grosir.</p>
            </div>
          </div>

          <div class="admin-field-grid">
            <label class="admin-field">
              Harga Modal / Kulakan (Rp) <em>*</em>
              <input
                v-model.number="form.base_price"
                type="number"
                min="1"
                placeholder="Contoh: 30000"
                :class="{ 'is-invalid': errors.base_price }"
                required
              />
              <small v-if="errors.base_price" class="admin-error-text">{{ errors.base_price[0] }}</small>
            </label>

            <label class="admin-field">
              Harga Jual Eceran (Rp) <em>*</em>
              <input
                v-model.number="form.retail_price"
                type="number"
                min="1"
                placeholder="Contoh: 31500"
                :class="{ 'is-invalid': errors.retail_price }"
                required
              />
              <small v-if="errors.retail_price" class="admin-error-text">{{ errors.retail_price[0] }}</small>
            </label>
          </div>

          <!-- Profit Hint -->
          <div v-if="form.base_price && form.retail_price" class="admin-profit-hint">
            Estimasi Keuntungan Eceran: <strong>Rp{{ estimatedProfit.toLocaleString('id-ID') }}</strong> per pcs
          </div>

          <!-- Price Warning Notification -->
          <div v-if="priceWarning" class="admin-warning-box">
            <AdminIcon name="alert-circle" :size="16" />
            <span>{{ priceWarning }}</span>
          </div>

          <!-- Toggle Pack -->
          <div class="admin-section-divider"></div>
          <label class="admin-switch-row">
            <span>
              <strong>Jual per pack / grosir</strong>
              <small>Aktifkan jika produk memiliki opsi pembelian bundle/pack dengan harga khusus.</small>
            </span>
            <input v-model="form.is_pack" type="checkbox" class="admin-toggle" />
          </label>

          <div v-if="form.is_pack" class="admin-pack-fields">
            <div class="admin-field-grid">
              <label class="admin-field">
                Jumlah Isi dalam 1 Pack (pcs) <em>*</em>
                <input
                  v-model.number="form.pack_quantity"
                  type="number"
                  min="2"
                  placeholder="Contoh: 6"
                  :class="{ 'is-invalid': errors.pack_quantity }"
                  required
                />
                <small v-if="errors.pack_quantity" class="admin-error-text">{{ errors.pack_quantity[0] }}</small>
              </label>

              <label class="admin-field">
                Harga per pcs saat beli pack (Rp) <em>*</em>
                <input
                  v-model.number="form.pack_price"
                  type="number"
                  min="1"
                  placeholder="Contoh: 30800"
                  :class="{ 'is-invalid': errors.pack_price || packPriceError }"
                  required
                />
                <small v-if="errors.pack_price" class="admin-error-text">{{ errors.pack_price[0] }}</small>
                <small v-else-if="packPriceError" class="admin-error-text">{{ packPriceError }}</small>
                <small v-else class="admin-hint-text">
                  Wajib berada di antara harga modal dan harga eceran.
                </small>
              </label>
            </div>
          </div>
        </article>

        <!-- 3. Varian dan Stok -->
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Varian dan Stok</h2>
              <p>Kelola pilihan varian warna/shade/ukuran dan stok awal produk.</p>
            </div>
          </div>

          <label class="admin-switch-row">
            <span>
              <strong>Gunakan varian kustom (warna / shade / ukuran)</strong>
              <small>Bila tidak diaktifkan, produk otomatis memiliki satu varian "Standar".</small>
            </span>
            <input v-model="useCustomVariants" type="checkbox" class="admin-toggle" />
          </label>

          <!-- Standard variant single stock -->
          <div v-if="!useCustomVariants" class="admin-standard-variant">
            <label class="admin-field">
              Jumlah Stok Produk (Varian Standar) <em>*</em>
              <input
                v-model.number="form.initial_stock"
                type="number"
                min="0"
                placeholder="10"
                :class="{ 'is-invalid': errors.initial_stock }"
                required
              />
              <small v-if="errors.initial_stock" class="admin-error-text">{{ errors.initial_stock[0] }}</small>
              <small class="admin-hint-text">Produk akan dibuat dengan satu varian bernama "Standar".</small>
            </label>
          </div>

          <!-- Custom variants list -->
          <div v-else class="admin-custom-variants-table">
            <div
              v-for="(variant, idx) in customVariants"
              :key="idx"
              class="admin-variant-row"
            >
              <label class="admin-field variant-name-field">
                Nama Varian {{ idx + 1 }}
                <input
                  v-model="variant.name"
                  placeholder="Contoh: 01 Natural / 50ml"
                  required
                />
              </label>
              <label class="admin-field variant-stock-field">
                Stok
                <input
                  v-model.number="variant.stock"
                  type="number"
                  min="0"
                  placeholder="0"
                  required
                />
              </label>
              <button
                type="button"
                class="admin-icon-button danger remove-variant-btn"
                title="Hapus baris"
                @click="removeVariantRow(idx)"
              >
                <AdminIcon name="trash" :size="16" />
              </button>
            </div>

            <small v-if="errors.varians" class="admin-error-text">{{ errors.varians[0] }}</small>

            <button
              type="button"
              class="admin-btn admin-btn-outline admin-btn-small add-variant-btn"
              @click="addVariantRow"
            >
              <AdminIcon name="plus" :size="14" /> Tambah Varian Baru
            </button>
          </div>
        </article>

        <!-- 4. Pengelola Foto Produk -->
        <article class="admin-panel admin-form-panel">
          <div class="admin-panel-heading">
            <div>
              <h2>Foto Produk</h2>
              <p>Format yang diterima: JPG, PNG, WEBP. Maksimal 2 MB per foto.</p>
            </div>
          </div>

          <!-- Existing photos in edit mode -->
          <div v-if="existingPhotos.length > 0" class="admin-photos-grid">
            <div
              v-for="photo in existingPhotos"
              :key="photo.id"
              class="admin-photo-card"
              :class="{ 'is-primary-card': photo.is_primary }"
            >
              <img :src="photo.image_url" alt="Foto produk" class="card-thumb" />
              <div class="card-badges">
                <span v-if="photo.is_primary" class="badge-primary">Foto Utama</span>
              </div>
              <div class="card-actions">
                <button
                  v-if="!photo.is_primary"
                  type="button"
                  class="btn-card-action"
                  @click="setPrimaryPhoto(photo.id)"
                >
                  Jadikan Utama
                </button>
                <button
                  type="button"
                  class="btn-card-action danger"
                  @click="deleteExistingPhoto(photo.id)"
                >
                  Hapus
                </button>
              </div>
            </div>
          </div>

          <!-- Pending uploaded files preview -->
          <div v-if="previewUrls.length > 0" class="admin-photos-grid">
            <div
              v-for="(prev, idx) in previewUrls"
              :key="idx"
              class="admin-photo-card"
            >
              <img :src="prev.url" alt="Preview" class="card-thumb" />
              <div class="card-badges">
                <span v-if="idx === 0 && !existingPhotos.length" class="badge-primary">Utama (calon)</span>
              </div>
              <div class="card-actions">
                <button
                  type="button"
                  class="btn-card-action danger"
                  @click="removePendingFile(idx)"
                >
                  Batal
                </button>
              </div>
            </div>
          </div>

          <!-- Upload Dropzone / Button -->
          <div class="admin-upload-zone">
            <AdminIcon name="image" :size="32" />
            <p>Pilih foto produk dari komputer</p>
            <label class="admin-btn admin-btn-outline admin-btn-small upload-trigger">
              Pilih Berkas
              <input
                type="file"
                accept="image/png, image/jpeg, image/webp"
                multiple
                hidden
                @change="isEdit ? uploadSinglePhotoInEdit($event) : handleFileSelect($event)"
              />
            </label>
            <small v-if="uploadingPhoto">Sedang mengunggah foto...</small>
            <small v-else>PNG, JPG, atau WEBP. Maksimal 2 MB.</small>
          </div>
        </article>
      </div>

      <!-- Aside Settings (Status & Pre-Order) -->
      <aside class="admin-form-aside">
        <article class="admin-panel admin-form-panel">
          <h2>Status & Pemesanan</h2>

          <!-- Status Aktif -->
          <label class="admin-switch-row">
            <span>
              <strong>Produk aktif</strong>
              <small>Produk tampil dan dapat dibeli di katalog pelanggan.</small>
            </span>
            <input v-model="form.is_active" type="checkbox" class="admin-toggle" />
          </label>

          <div class="admin-section-divider"></div>

          <!-- Pre-Order Toggle -->
          <label class="admin-switch-row">
            <span>
              <strong>Bisa Pre-Order</strong>
              <small>Pelanggan dapat memesan saat stok kosong.</small>
            </span>
            <input v-model="form.is_preorder" type="checkbox" class="admin-toggle" />
          </label>

          <!-- Pre-Order Estimate Days -->
          <div v-if="form.is_preorder" class="admin-preorder-estimate">
            <label class="admin-field">
              Estimasi Pre-Order (Hari) <em>*</em>
              <input
                v-model.number="form.preorder_estimate_days"
                type="number"
                min="1"
                max="6"
                placeholder="1 sampai 6 hari"
                :class="{ 'is-invalid': errors.preorder_estimate_days }"
                required
              />
              <small v-if="errors.preorder_estimate_days" class="admin-error-text">
                {{ errors.preorder_estimate_days[0] }}
              </small>
              <small v-else class="admin-hint-text">Wajib diisi antara 1 sampai 6 hari.</small>
            </label>
          </div>
        </article>

        <!-- Submit Button -->
        <button
          class="admin-btn admin-btn-primary admin-submit"
          type="submit"
          :disabled="saving"
        >
          <AdminIcon name="check" :size="16" />
          {{ saving ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Simpan Produk') }}
        </button>
      </aside>
    </form>
  </section>
</template>

<style scoped>
.admin-alert-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: var(--admin-radius-input, 12px);
  margin-bottom: 20px;
  font-size: 0.92rem;
  font-weight: 500;
}
.admin-alert-banner.danger {
  background-color: #FDF1F2;
  color: #C53030;
  border: 1px solid #F8D7DA;
}
.admin-alert-close {
  margin-left: auto;
  background: transparent;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: inherit;
  line-height: 1;
}
.admin-section-divider {
  height: 1px;
  background-color: var(--admin-border, #F0DEDB);
  margin: 16px 0;
}
.admin-error-text {
  color: var(--admin-danger, #C53030);
  font-size: 0.85rem;
  margin-top: 4px;
  display: block;
}
.admin-hint-text {
  color: var(--admin-text-muted, #8A6F76);
  font-size: 0.82rem;
  margin-top: 4px;
  display: block;
}
.admin-warning-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background-color: #FEF7ED;
  color: #8C5311;
  border: 1px solid #F6D6A8;
  padding: 12px 16px;
  border-radius: var(--admin-radius-input, 12px);
  font-size: 0.88rem;
  margin-top: 12px;
  line-height: 1.4;
}
.admin-profit-hint {
  font-size: 0.9rem;
  color: #2E8B6A;
  margin-top: 6px;
}
.admin-variant-row {
  display: flex;
  gap: 12px;
  align-items: flex-end;
  margin-bottom: 12px;
}
.variant-name-field {
  flex: 3;
}
.variant-stock-field {
  flex: 1;
}
.remove-variant-btn {
  margin-bottom: 8px;
}
.add-variant-btn {
  margin-top: 10px;
}
.admin-photos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}
.admin-photo-card {
  position: relative;
  border-radius: var(--admin-radius-input, 12px);
  border: 1px solid var(--admin-border, #F0DEDB);
  padding: 8px;
  background-color: #FFFFFF;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.admin-photo-card.is-primary-card {
  border-color: var(--admin-primary, #EBA9B8);
  box-shadow: 0 0 0 2px var(--admin-primary, #EBA9B8);
}
.card-thumb {
  width: 100%;
  height: 110px;
  object-fit: cover;
  border-radius: 8px;
}
.card-badges {
  min-height: 20px;
}
.badge-primary {
  font-size: 0.72rem;
  background-color: var(--admin-accent, #9C4A63);
  color: #FFFFFF;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
}
.card-actions {
  display: flex;
  justify-content: space-between;
  gap: 6px;
}
.btn-card-action {
  font-size: 0.75rem;
  background: transparent;
  border: 1px solid var(--admin-border, #F0DEDB);
  border-radius: 6px;
  padding: 3px 6px;
  cursor: pointer;
  color: var(--admin-text, #4A2C35);
}
.btn-card-action.danger {
  color: var(--admin-danger, #C53030);
  border-color: #F8D7DA;
}
.admin-upload-zone {
  border: 2px dashed var(--admin-border, #F0DEDB);
  border-radius: var(--admin-radius-card, 20px);
  padding: 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: var(--admin-text-muted, #8A6F76);
}
.upload-trigger {
  cursor: pointer;
}
.is-invalid {
  border-color: var(--admin-danger, #C53030) !important;
}
</style>
