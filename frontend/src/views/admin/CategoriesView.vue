<script setup>
import { ref, computed, onMounted } from 'vue'
import AdminPageHeader from './AdminPageHeader.vue'
import AdminBadge from './AdminBadge.vue'
import AdminIcon from '@/components/admin/AdminIcon.vue'
import { adminService } from '@/services/adminService'

const categories = ref([])
const loading = ref(false)
const search = ref('')
const errorMessage = ref('')
const successMessage = ref('')

const showForm = ref(false)
const editingCategory = ref(null)
const formName = ref('')
const formIcon = ref('')
const formErrors = ref({})
const saving = ref(false)

const filteredCategories = computed(() => {
  if (!search.value.trim()) return categories.value
  const q = search.value.toLowerCase()
  return categories.value.filter(c =>
    (c.name && c.name.toLowerCase().includes(q)) ||
    (c.slug && c.slug.toLowerCase().includes(q))
  )
})

async function fetchCategories() {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await adminService.getCategories()
    categories.value = res.data.data || []
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat daftar kategori.'
  } finally {
    loading.value = false
  }
}

function openAddForm() {
  editingCategory.value = null
  formName.value = ''
  formIcon.value = ''
  formErrors.value = {}
  showForm.value = true
}

function openEditForm(category) {
  editingCategory.value = category
  formName.value = category.name
  formIcon.value = category.icon || ''
  formErrors.value = {}
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  editingCategory.value = null
  formName.value = ''
  formIcon.value = ''
  formErrors.value = {}
}

async function saveCategory() {
  formErrors.value = {}
  errorMessage.value = ''
  successMessage.value = ''
  saving.value = true

  try {
    if (editingCategory.value) {
      await adminService.updateCategory(editingCategory.value.id, {
        name: formName.value,
        icon: formIcon.value || null,
      })
      successMessage.value = 'Kategori berhasil diperbarui.'
    } else {
      await adminService.createCategory({
        name: formName.value,
        icon: formIcon.value || null,
      })
      successMessage.value = 'Kategori berhasil ditambahkan.'
    }
    cancelForm()
    await fetchCategories()
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      formErrors.value = err.response.data.errors
    } else {
      errorMessage.value = err.response?.data?.message || 'Gagal menyimpan kategori.'
    }
  } finally {
    saving.value = false
  }
}

async function deleteCategory(category) {
  errorMessage.value = ''
  successMessage.value = ''

  if (category.produks_count > 0) {
    errorMessage.value = `Kategori "${category.name}" tidak dapat dihapus karena masih memiliki ${category.produks_count} produk terkait.`
    return
  }

  if (!confirm(`Hapus kategori "${category.name}"?`)) {
    return
  }

  try {
    await adminService.deleteCategory(category.id)
    successMessage.value = `Kategori "${category.name}" berhasil dihapus.`
    await fetchCategories()
  } catch (err) {
    if (err.response?.status === 422) {
      const errDetail = err.response.data.errors?.kategori?.[0] || err.response.data.message
      errorMessage.value = errDetail || 'Kategori tidak dapat dihapus.'
    } else {
      errorMessage.value = err.response?.data?.message || 'Gagal menghapus kategori.'
    }
  }
}

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <section class="admin-page">
    <AdminPageHeader
      eyebrow="ADMIN / KATALOG / KATEGORI"
      title="Kategori"
      description="Pengelompokan produk untuk memudahkan pelanggan menemukan kebutuhan."
    >
      <template #actions>
        <button class="admin-btn admin-btn-primary" @click="openAddForm">
          <AdminIcon name="plus" :size="16" /> Tambah Kategori
        </button>
      </template>
    </AdminPageHeader>

    <!-- Global Alert Messages -->
    <div v-if="successMessage" class="admin-alert-banner success">
      <AdminIcon name="check" :size="18" />
      <span>{{ successMessage }}</span>
      <button class="admin-alert-close" @click="successMessage = ''">×</button>
    </div>

    <div v-if="errorMessage" class="admin-alert-banner danger">
      <AdminIcon name="alert-triangle" :size="18" />
      <span>{{ errorMessage }}</span>
      <button class="admin-alert-close" @click="errorMessage = ''">×</button>
    </div>

    <!-- Inline Add/Edit Form -->
    <article v-if="showForm" class="admin-panel admin-add-inline">
      <div class="admin-panel-heading">
        <h3>{{ editingCategory ? 'Ubah Nama Kategori' : 'Tambah Kategori Baru' }}</h3>
      </div>
      <form class="admin-category-form" @submit.prevent="saveCategory">
        <label class="admin-field">
          Nama Kategori <em>*</em>
          <input
            v-model="formName"
            placeholder="Contoh: Perawatan Bibir"
            :class="{ 'is-invalid': formErrors.name }"
            required
          />
          <small v-if="formErrors.name" class="admin-error-text">{{ formErrors.name[0] }}</small>
        </label>

        <div class="admin-form-actions">
          <button type="button" class="admin-btn admin-btn-outline" @click="cancelForm">
            Batal
          </button>
          <button type="submit" class="admin-btn admin-btn-primary" :disabled="saving">
            {{ saving ? 'Menyimpan...' : (editingCategory ? 'Simpan Perubahan' : 'Simpan Kategori') }}
          </button>
        </div>
      </form>
    </article>

    <!-- Categories Table -->
    <article class="admin-panel">
      <div class="admin-toolbar">
        <div class="admin-panel-title">
          <h2>Daftar Kategori</h2>
          <AdminBadge tone="neutral">{{ categories.length }} kategori</AdminBadge>
        </div>
        <label class="admin-search">
          <AdminIcon name="search" :size="16" />
          <input v-model="search" placeholder="Cari nama kategori..." />
        </label>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nama Kategori</th>
              <th>Slug</th>
              <th>Jumlah Produk</th>
              <th>Tanggal Dibuat</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="admin-empty">Memuat kategori...</td>
            </tr>
            <tr v-else-if="!filteredCategories.length">
              <td colspan="5" class="admin-empty">Tidak ada kategori ditemukan.</td>
            </tr>
            <tr v-for="category in filteredCategories" :key="category.id">
              <td>
                <div class="admin-category-name">
                  <span class="admin-category-symbol">{{ (category.name || '').slice(0, 1) }}</span>
                  <strong>{{ category.name }}</strong>
                </div>
              </td>
              <td><code>{{ category.slug }}</code></td>
              <td>
                <AdminBadge :tone="category.produks_count > 0 ? 'green' : 'neutral'">
                  {{ category.produks_count || 0 }} produk
                </AdminBadge>
              </td>
              <td>{{ new Date(category.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}</td>
              <td>
                <div class="admin-row-actions">
                  <button
                    class="admin-icon-button"
                    title="Ubah Nama"
                    @click="openEditForm(category)"
                  >
                    <AdminIcon name="edit" :size="15" />
                  </button>
                  <button
                    class="admin-icon-button danger"
                    title="Hapus Kategori"
                    @click="deleteCategory(category)"
                  >
                    <AdminIcon name="trash" :size="15" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
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
.admin-alert-banner.success {
  background-color: #E6F4EA;
  color: #137333;
  border: 1px solid #CEEAD6;
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
.admin-category-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.admin-form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 8px;
}
.admin-error-text {
  color: var(--admin-danger, #C53030);
  font-size: 0.85rem;
  margin-top: 4px;
  display: block;
}
.is-invalid {
  border-color: var(--admin-danger, #C53030) !important;
}
</style>
