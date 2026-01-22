<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { sessionsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const router = useRouter()

const loading = ref(true)
const todaySessions = ref([])
const nextDaySessions = ref([])
const nextWorkingDay = ref(null)
const searchResults = ref([])
const searchPagination = ref({ page: 1, pages: 1, total: 0 })
const isSearching = ref(false)
const hasSearched = ref(false)

// Search form
const searchQuery = ref('')
const searchDateFrom = ref('')
const searchDateTo = ref('')

// Jours fermés : jeudi (4) et dimanche (0)
const closedDays = [0, 4]

function getNextWorkingDay(fromDate) {
  const date = new Date(fromDate)
  date.setDate(date.getDate() + 1)

  // Chercher le prochain jour travaillé
  while (closedDays.includes(date.getDay())) {
    date.setDate(date.getDate() + 1)
  }

  return date
}

function formatDateISO(date) {
  // Utiliser la date locale, pas UTC
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function formatDateLabel(date) {
  const today = new Date()
  const tomorrow = new Date(today)
  tomorrow.setDate(tomorrow.getDate() + 1)

  if (formatDateISO(date) === formatDateISO(today)) {
    return "Aujourd'hui"
  }
  if (formatDateISO(date) === formatDateISO(tomorrow)) {
    return 'Demain'
  }

  return date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  })
}

onMounted(async () => {
  try {
    const today = new Date()
    const todayISO = formatDateISO(today)

    // Charger les sessions d'aujourd'hui
    const todayResponse = await sessionsApi.list({ date: todayISO, limit: 50 })
    // Trier par heure croissante
    todaySessions.value = (todayResponse.data.data.sessions || []).sort((a, b) =>
      new Date(a.session_date) - new Date(b.session_date)
    )

    // Déterminer le prochain jour travaillé (depuis aujourd'hui, pas depuis le jour courant)
    const nextDay = getNextWorkingDay(today)
    nextWorkingDay.value = nextDay
    const nextDayISO = formatDateISO(nextDay)

    // Charger les sessions du prochain jour travaillé
    const nextDayResponse = await sessionsApi.list({ date: nextDayISO, limit: 50 })
    // Trier par heure croissante
    nextDaySessions.value = (nextDayResponse.data.data.sessions || []).sort((a, b) =>
      new Date(a.session_date) - new Date(b.session_date)
    )
  } catch (e) {
    console.error('Error loading sessions:', e)
  } finally {
    loading.value = false
  }
})

async function search(page = 1) {
  isSearching.value = true
  hasSearched.value = true

  try {
    const params = { page, limit: 20 }

    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }

    // Gestion des dates
    if (searchDateFrom.value && searchDateTo.value) {
      // Plage de dates
      params.date_from = searchDateFrom.value + ' 00:00:00'
      params.date_to = searchDateTo.value + ' 23:59:59'
    } else if (searchDateFrom.value) {
      // Date unique (début)
      params.date = searchDateFrom.value
    } else if (searchDateTo.value) {
      // Date unique (fin utilisée comme date exacte)
      params.date = searchDateTo.value
    }

    const response = await sessionsApi.list(params)
    searchResults.value = response.data.data.sessions || []
    searchPagination.value = response.data.data.pagination
  } catch (e) {
    console.error('Error searching sessions:', e)
  } finally {
    isSearching.value = false
  }
}

function clearSearch() {
  searchQuery.value = ''
  searchDateFrom.value = ''
  searchDateTo.value = ''
  searchResults.value = []
  hasSearched.value = false
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

function formatTime(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleTimeString('fr-FR', {
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

const statusLabels = {
  pending: 'En attente',
  confirmed: 'Confirmée',
  completed: 'Effectuée',
  cancelled: 'Annulée',
  no_show: 'Absent'
}

function getStatusBadgeClass(status) {
  const classes = {
    pending: 'badge-warning',
    confirmed: 'badge-info',
    completed: 'badge-success',
    cancelled: 'badge-gray',
    no_show: 'badge-danger'
  }
  return classes[status] || 'badge-gray'
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

function goToSession(sessionId) {
  router.push(`/app/sessions/${sessionId}`)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <h1 class="text-2xl font-bold text-white">Séances</h1>
      <RouterLink to="/app/sessions/new" class="btn-primary whitespace-nowrap">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nouvelle séance
      </RouterLink>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <!-- Sessions du jour -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
          <h2 class="font-semibold text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Aujourd'hui
          </h2>
          <span class="text-sm text-gray-400">{{ todaySessions.length }} séance(s)</span>
        </div>

        <div v-if="todaySessions.length === 0" class="p-6 text-center text-gray-400">
          Aucune séance prévue aujourd'hui
        </div>

        <table v-else class="w-full text-sm text-left">
          <thead>
            <tr class="bg-gray-800/50">
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Heure</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Personne</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Durée</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Statut</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="session in todaySessions"
              :key="session.id"
              class="border-t border-gray-700 hover:bg-gray-700/50 cursor-pointer"
              @click="goToSession(session.id)"
            >
              <td class="px-4 py-3 text-gray-100 font-medium">{{ formatTime(session.session_date) }}</td>
              <td class="px-4 py-3 text-gray-100">
                <RouterLink :to="`/app/persons/${session.person_id}`" class="hover:text-primary-400" @click.stop>
                  {{ session.person_first_name }} {{ session.person_last_name }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-gray-300">{{ session.duration_minutes }} min</td>
              <td class="px-4 py-3">
                <span :class="getStatusBadgeClass(session.status)">
                  {{ statusLabels[session.status] || session.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <RouterLink :to="`/app/sessions/${session.id}/edit`" class="bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 px-3 py-1.5 text-xs rounded-lg transition-colors" @click.stop>
                  Modifier
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Sessions du prochain jour travaillé -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
          <h2 class="font-semibold text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
            {{ nextWorkingDay ? formatDateLabel(nextWorkingDay) : 'Prochain jour' }}
          </h2>
          <span class="text-sm text-gray-400">{{ nextDaySessions.length }} séance(s)</span>
        </div>

        <div v-if="nextDaySessions.length === 0" class="p-6 text-center text-gray-400">
          Aucune séance prévue
        </div>

        <table v-else class="w-full text-sm text-left">
          <thead>
            <tr class="bg-gray-800/50">
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Heure</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Personne</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Durée</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Statut</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="session in nextDaySessions"
              :key="session.id"
              class="border-t border-gray-700 hover:bg-gray-700/50 cursor-pointer"
              @click="goToSession(session.id)"
            >
              <td class="px-4 py-3 text-gray-100 font-medium">{{ formatTime(session.session_date) }}</td>
              <td class="px-4 py-3 text-gray-100">
                <RouterLink :to="`/app/persons/${session.person_id}`" class="hover:text-primary-400" @click.stop>
                  {{ session.person_first_name }} {{ session.person_last_name }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-gray-300">{{ session.duration_minutes }} min</td>
              <td class="px-4 py-3">
                <span :class="getStatusBadgeClass(session.status)">
                  {{ statusLabels[session.status] || session.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <RouterLink :to="`/app/sessions/${session.id}/edit`" class="bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 px-3 py-1.5 text-xs rounded-lg transition-colors" @click.stop>
                  Modifier
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Recherche -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
          <h2 class="font-semibold text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            Rechercher des séances
          </h2>
        </div>

        <div class="p-6">
          <form @submit.prevent="search(1)" class="space-y-4">
            <div class="flex flex-col sm:flex-row gap-4">
              <div class="flex-1">
                <label class="block text-sm text-gray-400 mb-1">Recherche</label>
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Nom, prénom..."
                  class="input w-full"
                />
              </div>
              <div class="w-full sm:w-40">
                <label class="block text-sm text-gray-400 mb-1">Date début</label>
                <input
                  v-model="searchDateFrom"
                  type="date"
                  class="input w-full"
                />
              </div>
              <div class="w-full sm:w-40">
                <label class="block text-sm text-gray-400 mb-1">Date fin</label>
                <input
                  v-model="searchDateTo"
                  type="date"
                  class="input w-full"
                />
              </div>
              <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary" :disabled="isSearching">
                  <LoadingSpinner v-if="isSearching" size="sm" class="mr-2" />
                  Rechercher
                </button>
                <button v-if="hasSearched" type="button" @click="clearSearch" class="btn-secondary">
                  Effacer
                </button>
              </div>
            </div>
            <p class="text-xs text-gray-500">
              Une seule date = recherche sur ce jour. Deux dates = recherche sur la période.
            </p>
          </form>
        </div>

        <!-- Résultats de recherche -->
        <template v-if="hasSearched">
          <div class="border-t border-gray-700">
            <div v-if="searchResults.length === 0" class="p-6 text-center text-gray-400">
              Aucune séance trouvée
            </div>

            <template v-else>
              <table class="w-full text-sm text-left">
                <thead>
                  <tr class="bg-gray-800/50">
                    <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Date</th>
                    <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Personne</th>
                    <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Durée</th>
                    <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Comportement (fin)</th>
                    <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="session in searchResults"
                    :key="session.id"
                    class="border-t border-gray-700 hover:bg-gray-700/50 cursor-pointer"
                    @click="goToSession(session.id)"
                  >
                    <td class="px-4 py-3 text-gray-100">{{ formatDateTime(session.session_date) }}</td>
                    <td class="px-4 py-3 text-gray-100">
                      <RouterLink :to="`/app/persons/${session.person_id}`" class="font-medium hover:text-primary-400" @click.stop>
                        {{ session.person_first_name }} {{ session.person_last_name }}
                      </RouterLink>
                    </td>
                    <td class="px-4 py-3 text-gray-300">{{ session.duration_minutes }} min</td>
                    <td class="px-4 py-3">
                      <span v-if="session.behavior_end" :class="getBehaviorBadgeClass(session.behavior_end)">
                        {{ behaviorLabels[session.behavior_end] }}
                      </span>
                      <span v-else class="text-gray-500">-</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <RouterLink :to="`/app/sessions/${session.id}/edit`" class="bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 px-3 py-1.5 text-xs rounded-lg transition-colors" @click.stop>
                        Modifier
                      </RouterLink>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Pagination -->
              <div v-if="searchPagination.pages > 1" class="px-4 py-3 border-t border-gray-700 flex items-center justify-between">
                <div class="text-sm text-gray-400">
                  {{ searchPagination.total }} séance(s)
                </div>
                <div class="flex space-x-2">
                  <button
                    v-for="page in searchPagination.pages"
                    :key="page"
                    @click="search(page)"
                    :class="[
                      'px-3 py-1 text-sm rounded',
                      page === searchPagination.page
                        ? 'bg-primary-600 text-white'
                        : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                    ]"
                  >
                    {{ page }}
                  </button>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
