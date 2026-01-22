<script setup>
import { ref, onMounted, watch } from 'vue'
import { documentsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const props = defineProps({
  personId: {
    type: String,
    default: null
  },
  // Si true, charge tous les documents de l'utilisateur connecté
  myDocuments: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Documents des séances'
  },
  showPersonName: {
    type: Boolean,
    default: false
  }
})

const documents = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  await loadDocuments()
})

watch(() => props.personId, async () => {
  if (props.personId) {
    await loadDocuments()
  }
})

async function loadDocuments() {
  loading.value = true
  error.value = ''
  try {
    let response
    if (props.myDocuments) {
      response = await documentsApi.listMySessionDocuments()
    } else if (props.personId) {
      response = await documentsApi.listByPersonSessions(props.personId)
    } else {
      loading.value = false
      return
    }
    documents.value = response.data?.data?.documents || []
  } catch (e) {
    console.error('Error loading session documents:', e)
    if (e.response?.status !== 401 && e.response?.status !== 403) {
      error.value = 'Erreur lors du chargement des documents'
    }
  } finally {
    loading.value = false
  }
}

function viewDocument(doc) {
  const token = localStorage.getItem('access_token')
  const url = documentsApi.getViewUrl(doc.id)
  window.open(url + `?token=${token}`, '_blank')
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
    console.error('Error downloading document:', e)
  }
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko'
  return (bytes / (1024 * 1024)).toFixed(1) + ' Mo'
}

function formatDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function formatSessionDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
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
  <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
      <h2 class="font-semibold text-white">{{ title }}</h2>
    </div>

    <div class="p-4">
      <div v-if="loading" class="flex justify-center py-6">
        <LoadingSpinner size="md" />
      </div>

      <div v-else-if="error" class="text-red-400 text-sm py-4 text-center">
        {{ error }}
      </div>

      <div v-else-if="documents.length === 0" class="text-center py-6 text-gray-400">
        <svg class="w-10 h-10 mx-auto mb-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="text-sm">Aucun document</p>
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="flex items-center p-3 bg-gray-700/30 hover:bg-gray-700/50 rounded-lg transition-colors"
        >
          <!-- Icon -->
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center mr-3 flex-shrink-0"
            :class="isPdf(doc.mime_type) ? 'bg-red-900/50' : 'bg-blue-900/50'"
          >
            <svg v-if="isPdf(doc.mime_type)" class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
            </svg>
            <svg v-else class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
            </svg>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="font-medium text-white text-sm truncate">{{ doc.original_name }}</div>
            <div class="text-xs text-gray-400 mt-0.5">
              <span v-if="showPersonName && doc.person_first_name" class="text-primary-400">
                {{ doc.person_first_name }} {{ doc.person_last_name }} -
              </span>
              <span v-if="doc.session_date">
                Séance du {{ formatSessionDate(doc.session_date) }}
              </span>
              <span class="mx-1">·</span>
              {{ formatFileSize(doc.size) }}
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center space-x-1 ml-2">
            <button
              v-if="isImage(doc.mime_type) || isPdf(doc.mime_type)"
              @click="viewDocument(doc)"
              class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-600 transition-colors"
              title="Visualiser"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
            <button
              @click="downloadDocument(doc)"
              class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-600 transition-colors"
              title="Télécharger"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
