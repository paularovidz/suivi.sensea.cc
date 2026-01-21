<script setup>
import { ref, onMounted } from 'vue'
import { documentsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (v) => ['user', 'person'].includes(v)
  },
  entityId: {
    type: String,
    required: true
  }
})

const documents = ref([])
const loading = ref(true)
const uploading = ref(false)
const error = ref('')
const fileInput = ref(null)
const confirmDialog = ref(null)
const documentToDelete = ref(null)

const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']
const allowedExtensions = '.jpg,.jpeg,.png,.gif,.webp,.pdf'
const maxSize = 10 * 1024 * 1024 // 10 MB

onMounted(async () => {
  await loadDocuments()
})

async function loadDocuments() {
  loading.value = true
  error.value = ''
  try {
    const response = await documentsApi.list(props.type, props.entityId)
    documents.value = response.data.data.documents
  } catch (e) {
    error.value = 'Erreur lors du chargement des documents'
    console.error(e)
  } finally {
    loading.value = false
  }
}

function triggerUpload() {
  fileInput.value?.click()
}

async function handleFileSelect(event) {
  const file = event.target.files[0]
  if (!file) return

  // Validation
  if (!allowedTypes.includes(file.type)) {
    error.value = 'Type de fichier non autorise (JPG, PNG, GIF, WebP, PDF uniquement)'
    event.target.value = ''
    return
  }

  if (file.size > maxSize) {
    error.value = 'Fichier trop volumineux (max 10 Mo)'
    event.target.value = ''
    return
  }

  uploading.value = true
  error.value = ''

  try {
    await documentsApi.upload(props.type, props.entityId, file)
    await loadDocuments()
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur lors du telechargement'
    console.error(e)
  } finally {
    uploading.value = false
    event.target.value = ''
  }
}

async function downloadDocument(doc) {
  try {
    const response = await documentsApi.download(doc.id)
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', doc.original_name)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    error.value = 'Erreur lors du telechargement'
    console.error(e)
  }
}

function viewDocument(doc) {
  // Ouvrir dans un nouvel onglet
  const token = localStorage.getItem('access_token')
  const url = documentsApi.getViewUrl(doc.id)
  window.open(url + `?token=${token}`, '_blank')
}

function confirmDelete(doc) {
  documentToDelete.value = doc
  confirmDialog.value?.open()
}

async function handleDelete() {
  if (!documentToDelete.value) return

  try {
    await documentsApi.delete(documentToDelete.value.id)
    await loadDocuments()
  } catch (e) {
    error.value = 'Erreur lors de la suppression'
    console.error(e)
  }

  documentToDelete.value = null
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko'
  return (bytes / (1024 * 1024)).toFixed(1) + ' Mo'
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function isImage(mimeType) {
  return mimeType && mimeType.startsWith('image/')
}

function isPdf(mimeType) {
  return mimeType === 'application/pdf'
}
</script>

<template>
  <div class="card">
    <div class="card-header flex items-center justify-between">
      <h2 class="font-semibold text-gray-900">Documents</h2>
      <button @click="triggerUpload" class="btn-secondary text-sm" :disabled="uploading">
        <LoadingSpinner v-if="uploading" size="sm" class="mr-2" />
        <svg v-else class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Ajouter
      </button>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        :accept="allowedExtensions"
        @change="handleFileSelect"
      />
    </div>

    <div class="card-body">
      <AlertMessage v-if="error" type="error" dismissible @dismiss="error = ''" class="mb-4">
        {{ error }}
      </AlertMessage>

      <div v-if="loading" class="flex justify-center py-8">
        <LoadingSpinner size="lg" />
      </div>

      <div v-else-if="documents.length === 0" class="text-center py-8 text-gray-500">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p>Aucun document</p>
      </div>

      <div v-else class="divide-y divide-gray-100">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="flex items-center py-3 hover:bg-gray-50 -mx-4 px-4 transition-colors"
        >
          <!-- Icon -->
          <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
               :class="isPdf(doc.mime_type) ? 'bg-red-100' : 'bg-blue-100'">
            <!-- PDF Icon -->
            <svg v-if="isPdf(doc.mime_type)" class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
            </svg>
            <!-- Image Icon -->
            <svg v-else class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
            </svg>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="font-medium text-gray-900 truncate">{{ doc.original_name }}</div>
            <div class="text-sm text-gray-500">
              {{ formatFileSize(doc.size) }} - {{ formatDate(doc.created_at) }}
              <span v-if="doc.uploader_first_name" class="hidden sm:inline">
                par {{ doc.uploader_first_name }} {{ doc.uploader_last_name }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center space-x-1">
            <!-- View (for images and PDF) -->
            <button
              v-if="isImage(doc.mime_type) || isPdf(doc.mime_type)"
              @click="viewDocument(doc)"
              class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
              title="Visualiser"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>

            <!-- Download -->
            <button
              @click="downloadDocument(doc)"
              class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
              title="Telecharger"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
            </button>

            <!-- Delete -->
            <button
              @click="confirmDelete(doc)"
              class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50"
              title="Supprimer"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <ConfirmDialog
      ref="confirmDialog"
      title="Supprimer ce document ?"
      :message="`Etes-vous sur de vouloir supprimer '${documentToDelete?.original_name}' ? Cette action est irreversible.`"
      confirm-text="Supprimer"
      danger
      @confirm="handleDelete"
    />
  </div>
</template>
