<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePersonsStore } from '@/stores/persons'
import { useAuthStore } from '@/stores/auth'
import { personsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoyaltyCard from '@/components/loyalty/LoyaltyCard.vue'
import DocumentsSection from '@/components/documents/DocumentsSection.vue'
import ImpersonationBanner from '@/components/ui/ImpersonationBanner.vue'

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

function goBackToHome() {
  selectedPersonId.value = null
  sessions.value = []
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
  <div class="min-h-screen bg-dark">
    <!-- Impersonation Banner -->
    <ImpersonationBanner />

    <!-- Header -->
    <header class="header-dark px-4 py-4">
      <div class="max-w-4xl mx-auto flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
            <span class="text-white text-lg font-bold">S</span>
          </div>
          <h1 class="text-xl font-semibold text-white">sensëa</h1>
        </div>
        <div class="flex items-center space-x-4">
          <span class="text-sm text-gray-400">{{ authStore.fullName }}</span>
          <button @click="handleLogout" class="text-sm text-red-400 hover:text-red-300 transition-colors">
            Déconnexion
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-4xl mx-auto p-4">
      <LoadingSpinner v-if="loading" size="lg" class="py-12" />

      <!-- ACCUEIL (aucune personne sélectionnée) -->
      <template v-else-if="!selectedPersonId">
        <!-- 1. Bouton Prendre RDV -->
        <div class="mb-6">
          <a
            href="/booking"
            target="_blank"
            class="block bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-xl p-4 transition-all duration-200 shadow-lg hover:shadow-xl"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div>
                  <div class="font-semibold">Prendre rendez-vous</div>
                  <div class="text-sm text-white/70">Réserver une nouvelle séance</div>
                </div>
              </div>
              <svg class="w-5 h-5 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </div>
          </a>
        </div>

        <!-- 2. Mes documents -->
        <div v-if="authStore.user?.id" class="mb-6">
          <DocumentsSection
            type="user"
            :entity-id="authStore.user.id"
            title="Mes documents"
            readonly
            dark
          />
        </div>

        <!-- 3. Liste des personnes accompagnées -->
        <div class="mb-6">
          <h2 class="text-lg font-semibold text-white mb-4">Personnes accompagnées</h2>

          <EmptyState
            v-if="persons.length === 0"
            title="Aucune personne assignée"
            description="Vous n'avez pas encore de personne assignée à votre compte. Contactez un administrateur."
            icon="people"
            class="py-8"
            dark
          />

          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
              v-for="person in persons"
              :key="person.id"
              @click="selectPerson(person.id)"
              class="card-dark-interactive p-6 text-left"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-semibold text-white">
                    {{ person.first_name }} {{ person.last_name }}
                  </div>
                  <div class="text-sm text-gray-400 mt-1">
                    {{ person.age ? person.age + ' ans' : 'Âge non renseigné' }}
                  </div>
                  <div class="text-sm text-gray-400 mt-1">
                    {{ person.stats?.total_sessions || 0 }} séance(s)
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </div>
            </button>
          </div>
        </div>

        <!-- 4. Carte de fidélité (particuliers uniquement) -->
        <div v-if="isPersonalClient && authStore.user?.id">
          <LoyaltyCard :user-id="authStore.user.id" />
        </div>
      </template>

      <!-- VUE DÉTAIL D'UNE PERSONNE -->
      <template v-else>
        <!-- Bouton retour -->
        <button
          @click="goBackToHome"
          class="flex items-center text-gray-400 hover:text-white mb-4 transition-colors"
        >
          <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Retour à l'accueil
        </button>

        <!-- Infos personne -->
        <div class="card-dark p-6 mb-6">
          <h2 class="text-xl font-bold text-white">
            {{ selectedPerson?.first_name }} {{ selectedPerson?.last_name }}
          </h2>
          <div class="mt-2 text-gray-300">
            <span v-if="selectedPerson?.birth_date">
              Né(e) le {{ formatDate(selectedPerson.birth_date) }}
              <span v-if="selectedPerson?.age"> ({{ selectedPerson.age }} ans)</span>
            </span>
          </div>
          <div class="mt-2 flex items-center space-x-4 text-sm text-gray-400">
            <span>{{ sessionsPagination.total }} séance(s)</span>
            <span v-if="selectedPerson?.stats?.average_duration">
              Durée moyenne : {{ selectedPerson.stats.average_duration }} min
            </span>
          </div>
        </div>

        <!-- Documents de la personne -->
        <div class="mb-6">
          <DocumentsSection
            type="person"
            :entity-id="selectedPersonId"
            :title="`Documents de ${selectedPerson?.first_name}`"
            readonly
            dark
          />
        </div>

        <!-- Liste des séances -->
        <h3 class="font-semibold text-white mb-4">Historique des séances</h3>

        <LoadingSpinner v-if="sessionsLoading" size="md" class="py-8" />

        <EmptyState
          v-else-if="sessions.length === 0"
          title="Aucune séance"
          description="Aucune séance n'a encore été enregistrée pour cette personne."
          icon="calendar"
          class="py-8"
          dark
        />

        <div v-else class="space-y-3">
          <button
            v-for="session in sessions"
            :key="session.id"
            @click="viewSession(session.id)"
            class="w-full card-dark-interactive p-4 text-left"
          >
            <div class="flex items-center justify-between">
              <div>
                <div class="font-medium text-white">
                  {{ formatDateTime(session.session_date) }}
                </div>
                <div class="text-sm text-gray-400 mt-1">
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
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                'px-3 py-1 text-sm rounded-lg transition-colors',
                page === sessionsPagination.page
                  ? 'bg-indigo-600 text-white'
                  : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
              ]"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
