<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { usePersonsStore } from '@/stores/persons'
import { useAuthStore } from '@/stores/auth'
import { personsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PersonSessionStats from '@/components/sessions/PersonSessionStats.vue'
import DocumentsSection from '@/components/documents/DocumentsSection.vue'

const route = useRoute()
const router = useRouter()
const personsStore = usePersonsStore()
const authStore = useAuthStore()

const loading = ref(true)
const sessions = ref([])
const sessionsPagination = ref({ page: 1, total: 0 })
const confirmDialog = ref(null)

const person = computed(() => personsStore.currentPerson)

onMounted(async () => {
  try {
    await personsStore.fetchPerson(route.params.id)
    await loadSessions()
  } catch (e) {
    router.push('/app/persons')
  } finally {
    loading.value = false
  }
})

async function loadSessions(page = 1) {
  try {
    const response = await personsApi.getSessions(route.params.id, { page, limit: 10 })
    sessions.value = response.data.data.sessions
    sessionsPagination.value = response.data.data.pagination
  } catch (e) {
    console.error('Error loading sessions:', e)
  }
}

function confirmDelete() {
  confirmDialog.value?.open()
}

async function handleDelete() {
  try {
    await personsStore.deletePerson(route.params.id)
    router.push('/app/persons')
  } catch (e) {
    console.error('Error deleting person:', e)
  }
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

function formatDateTime(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const behaviorLabels = {
  calm: 'Calme',
  agitated: 'Agité',
  tired: 'Fatigué',
  defensive: 'Défensif',
  anxious: 'Inquiet',
  passive: 'Passif'
}

function getBehaviorBadgeClass(behavior) {
  const classes = {
    calm: 'badge-success',
    agitated: 'badge-warning',
    tired: 'badge-gray',
    defensive: 'badge-danger',
    anxious: 'badge-warning',
    passive: 'badge-gray'
  }
  return classes[behavior] || 'badge-gray'
}
</script>

<template>
  <div class="space-y-6">
    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else-if="person">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div class="flex items-center">
          <RouterLink to="/app/persons" class="mr-4 p-2 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </RouterLink>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">
              {{ person.first_name }} {{ person.last_name }}
            </h1>
            <p class="text-gray-600">{{ person.age ? person.age + ' ans' : 'Âge non renseigné' }}</p>
          </div>
        </div>
        <div class="flex space-x-3">
          <RouterLink :to="`/app/sessions/new/${person.id}`" class="btn-primary">
            Nouvelle séance
          </RouterLink>
          <RouterLink :to="`/app/persons/${person.id}/edit`" class="btn-secondary">
            Modifier
          </RouterLink>
          <button v-if="authStore.isAdmin" @click="confirmDelete" class="btn-danger">
            Supprimer
          </button>
        </div>
      </div>

      <!-- Info cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-6">
          <div class="text-sm text-gray-500 mb-1">Date de naissance</div>
          <div class="text-lg font-semibold">{{ formatDate(person.birth_date) }}</div>
        </div>
        <div class="card p-6">
          <div class="text-sm text-gray-500 mb-1">Nombre de séances</div>
          <div class="text-lg font-semibold">{{ person.stats?.total_sessions || 0 }}</div>
        </div>
        <div class="card p-6">
          <div class="text-sm text-gray-500 mb-1">Durée moyenne</div>
          <div class="text-lg font-semibold">{{ person.stats?.average_duration || 0 }} min</div>
        </div>
      </div>

      <!-- Sessions list -->
      <div class="card">
        <div class="card-header flex items-center justify-between">
          <h2 class="font-semibold text-gray-900">Historique des séances</h2>
          <span class="text-sm text-gray-500">{{ sessionsPagination.total }} séance(s)</span>
        </div>

        <EmptyState
          v-if="sessions.length === 0"
          title="Aucune séance"
          description="Aucune séance n'a encore été enregistrée pour cette personne."
          icon="calendar"
          class="py-8"
        >
          <RouterLink :to="`/app/sessions/new/${person.id}`" class="btn-primary mt-4">
            Créer la première séance
          </RouterLink>
        </EmptyState>

        <div v-else class="divide-y divide-gray-100">
          <RouterLink
            v-for="session in sessions"
            :key="session.id"
            :to="`/app/sessions/${session.id}`"
            class="flex items-center px-6 py-4 hover:bg-gray-50 transition-colors"
          >
            <div class="flex-1">
              <div class="font-medium text-gray-900">
                {{ formatDateTime(session.session_date) }}
              </div>
              <div class="text-sm text-gray-500">
                {{ session.duration_minutes }} minutes
                <span v-if="session.creator_first_name"> - par {{ session.creator_first_name }} {{ session.creator_last_name }}</span>
              </div>
            </div>
            <div v-if="session.behavior_end" class="mr-4">
              <span :class="getBehaviorBadgeClass(session.behavior_end)">
                {{ behaviorLabels[session.behavior_end] }}
              </span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </RouterLink>
        </div>

        <!-- Pagination -->
        <div v-if="sessionsPagination.pages > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-center">
          <div class="flex space-x-2">
            <button
              v-for="page in sessionsPagination.pages"
              :key="page"
              @click="loadSessions(page)"
              :class="[
                'px-3 py-1 text-sm rounded',
                page === sessionsPagination.page
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              ]"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>

      <!-- Statistics and charts -->
      <div class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Statistiques et analyses</h2>
        </div>
        <div class="card-body">
          <PersonSessionStats :person-id="person.id" />
        </div>
      </div>

      <!-- Notes (at the end) -->
      <div v-if="person.notes" class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Notes</h2>
        </div>
        <div class="card-body">
          <p class="text-gray-700 whitespace-pre-wrap">{{ person.notes }}</p>
        </div>
      </div>

      <!-- Documents (admin only) -->
      <DocumentsSection
        v-if="authStore.isAdmin"
        type="person"
        :entity-id="person.id"
      />
    </template>

    <ConfirmDialog
      ref="confirmDialog"
      title="Supprimer cette personne ?"
      :message="`Êtes-vous sûr de vouloir supprimer ${person?.first_name} ${person?.last_name} ? Cette action est irréversible.`"
      confirm-text="Supprimer"
      danger
      @confirm="handleDelete"
    />
  </div>
</template>
