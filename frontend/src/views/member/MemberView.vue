<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePersonsStore } from '@/stores/persons'
import { useAuthStore } from '@/stores/auth'
import { personsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoyaltyCard from '@/components/loyalty/LoyaltyCard.vue'

const router = useRouter()
const personsStore = usePersonsStore()
const authStore = useAuthStore()

const loading = ref(true)
const selectedPersonId = ref(null)
const sessions = ref([])
const sessionsLoading = ref(false)
const sessionsPagination = ref({ page: 1, total: 0, pages: 0 })

const persons = computed(() => personsStore.persons)

// Vérifie si l'utilisateur est un particulier (éligible fidélité)
const isPersonalClient = computed(() => {
  return authStore.user?.client_type === 'personal'
})

const selectedPerson = computed(() => {
  if (!selectedPersonId.value) return null
  return persons.value.find(p => p.id === selectedPersonId.value)
})

onMounted(async () => {
  try {
    await personsStore.fetchPersons({ limit: 100 })

    // Si une seule personne, la sélectionner automatiquement
    if (persons.value.length === 1) {
      selectedPersonId.value = persons.value[0].id
      await loadSessions()
    }
  } catch (e) {
    console.error('Error loading persons:', e)
  } finally {
    loading.value = false
  }
})

async function selectPerson(personId) {
  selectedPersonId.value = personId
  await loadSessions()
}

async function loadSessions(page = 1) {
  if (!selectedPersonId.value) return

  sessionsLoading.value = true
  try {
    const response = await personsApi.getSessions(selectedPersonId.value, { page, limit: 20 })
    sessions.value = response.data.data.sessions
    sessionsPagination.value = response.data.data.pagination
  } catch (e) {
    console.error('Error loading sessions:', e)
  } finally {
    sessionsLoading.value = false
  }
}

function viewSession(sessionId) {
  router.push(`/app/member/sessions/${sessionId}`)
}

function formatDateTime(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
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

async function handleLogout() {
  await authStore.logout()
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-4 py-4">
      <div class="max-w-4xl mx-auto flex items-center justify-between">
        <h1 class="text-xl font-bold text-gradient">Sensea</h1>
        <div class="flex items-center space-x-4">
          <span class="text-sm text-gray-600">{{ authStore.fullName }}</span>
          <button @click="handleLogout" class="text-sm text-red-600 hover:text-red-700">
            Déconnexion
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-4xl mx-auto p-4">
      <!-- Carte de fidélité (particuliers uniquement) -->
      <div v-if="isPersonalClient" class="mb-6">
        <LoyaltyCard :user-id="authStore.user.id" />
      </div>

      <LoadingSpinner v-if="loading" size="lg" class="py-12" />

      <template v-else>
        <!-- Aucune personne assignée -->
        <EmptyState
          v-if="persons.length === 0"
          title="Aucune personne assignée"
          description="Vous n'avez pas encore de personne assignée à votre compte. Contactez un administrateur."
          icon="people"
          class="py-12"
        />

        <!-- Sélection de personne (si plusieurs) -->
        <template v-else-if="persons.length > 1 && !selectedPersonId">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Sélectionnez une personne</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
              v-for="person in persons"
              :key="person.id"
              @click="selectPerson(person.id)"
              class="card p-6 text-left hover:border-primary-300 hover:shadow-md transition-all"
            >
              <div class="font-semibold text-gray-900">
                {{ person.first_name }} {{ person.last_name }}
              </div>
              <div class="text-sm text-gray-500 mt-1">
                {{ person.age ? person.age + ' ans' : 'Âge non renseigné' }}
              </div>
              <div class="text-sm text-gray-500 mt-1">
                {{ person.stats?.total_sessions || 0 }} séance(s)
              </div>
            </button>
          </div>
        </template>

        <!-- Vue des séances d'une personne -->
        <template v-else-if="selectedPersonId">
          <!-- Bouton retour si plusieurs personnes -->
          <button
            v-if="persons.length > 1"
            @click="selectedPersonId = null; sessions = []"
            class="flex items-center text-gray-600 hover:text-gray-900 mb-4"
          >
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Retour à la liste
          </button>

          <!-- Infos personne -->
          <div class="card p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900">
              {{ selectedPerson?.first_name }} {{ selectedPerson?.last_name }}
            </h2>
            <div class="mt-2 text-gray-600">
              <span v-if="selectedPerson?.birth_date">
                Né(e) le {{ formatDate(selectedPerson.birth_date) }}
                <span v-if="selectedPerson?.age"> ({{ selectedPerson.age }} ans)</span>
              </span>
            </div>
            <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
              <span>{{ sessionsPagination.total }} séance(s)</span>
              <span v-if="selectedPerson?.stats?.average_duration">
                Durée moyenne : {{ selectedPerson.stats.average_duration }} min
              </span>
            </div>
          </div>

          <!-- Liste des séances -->
          <h3 class="font-semibold text-gray-900 mb-4">Historique des séances</h3>

          <LoadingSpinner v-if="sessionsLoading" size="md" class="py-8" />

          <EmptyState
            v-else-if="sessions.length === 0"
            title="Aucune séance"
            description="Aucune séance n'a encore été enregistrée pour cette personne."
            icon="calendar"
            class="py-8"
          />

          <div v-else class="space-y-3">
            <button
              v-for="session in sessions"
              :key="session.id"
              @click="viewSession(session.id)"
              class="w-full card p-4 text-left hover:border-primary-300 hover:shadow-md transition-all"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-medium text-gray-900">
                    {{ formatDateTime(session.session_date) }}
                  </div>
                  <div class="text-sm text-gray-500 mt-1">
                    {{ session.duration_minutes }} minutes
                    <span v-if="session.creator_first_name">
                      - par {{ session.creator_first_name }} {{ session.creator_last_name }}
                    </span>
                  </div>
                </div>
                <div class="flex items-center space-x-3">
                  <span v-if="session.behavior_end" :class="getBehaviorBadgeClass(session.behavior_end)">
                    {{ behaviorLabels[session.behavior_end] }}
                  </span>
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </button>
          </div>

          <!-- Pagination -->
          <div v-if="sessionsPagination.pages > 1" class="mt-6 flex justify-center">
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
        </template>
      </template>
    </main>
  </div>
</template>
