<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useSessionsStore } from '@/stores/sessions'
import { useAuthStore } from '@/stores/auth'
import { sessionsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SessionCalendar from '@/components/ui/SessionCalendar.vue'

const sessionsStore = useSessionsStore()
const authStore = useAuthStore()

const loading = ref(true)
const stats = ref(null)
const calendarData = ref({})
const calendarYear = ref(new Date().getFullYear())
const calendarMonth = ref(new Date().getMonth() + 1)

const isAdmin = computed(() => authStore.user?.role === 'admin')

onMounted(async () => {
  try {
    await sessionsStore.fetchSessions()
    if (isAdmin.value) {
      await loadStats()
    }
  } finally {
    loading.value = false
  }
})

async function loadStats() {
  try {
    const response = await sessionsApi.getStats({
      year: calendarYear.value,
      month: calendarMonth.value
    })
    stats.value = response.data.data.stats
    calendarData.value = response.data.data.calendar
  } catch (e) {
    console.error('Error loading stats:', e)
  }
}

async function handleMonthChange({ year, month }) {
  calendarYear.value = year
  calendarMonth.value = month
  await loadStats()
}

async function loadPage(page) {
  loading.value = true
  try {
    await sessionsStore.fetchSessions({ page })
  } finally {
    loading.value = false
  }
}

function formatDate(dateString) {
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
    <!-- Header -->
    <div class="flex items-center justify-between">
      <RouterLink to="/app/sessions/new" class="btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nouvelle séance
      </RouterLink>
    </div>

    <!-- Admin KPIs -->
    <div v-if="isAdmin && stats" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-primary-900/50 mr-4">
            <svg class="w-6 h-6 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-400">Séances ce mois</p>
            <p class="text-2xl font-bold text-white">{{ stats.sessions_this_month }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-green-900/50 mr-4">
            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-400">Séances aujourd'hui</p>
            <p class="text-2xl font-bold text-white">{{ stats.sessions_today }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-purple-900/50 mr-4">
            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-400">Total séances</p>
            <p class="text-2xl font-bold text-white">{{ stats.total_sessions }}</p>
          </div>
        </div>
      </div>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sessions table -->
        <div class="lg:col-span-2">
          <EmptyState
            v-if="sessionsStore.sessions.length === 0"
            title="Aucune séance"
            description="Aucune séance n'a encore été enregistrée."
            icon="calendar"
          >
            <RouterLink to="/app/sessions/new" class="btn-primary mt-4">
              Créer une séance
            </RouterLink>
          </EmptyState>

          <div v-else class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <table class="w-full text-sm text-left">
              <thead>
                <tr class="bg-gray-800/50">
                  <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Personne</th>
                  <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Date</th>
                  <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Durée</th>
                  <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Comportement (fin)</th>
                  <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="session in sessionsStore.sessions" :key="session.id" class="border-t border-gray-700 hover:bg-gray-700/50">
                  <td class="px-4 py-3 text-gray-100">
                    <RouterLink :to="`/app/persons/${session.person_id}`" class="font-medium hover:text-primary-400">
                      {{ session.person_first_name }} {{ session.person_last_name }}
                    </RouterLink>
                  </td>
                  <td class="px-4 py-3 text-gray-300">{{ formatDate(session.session_date) }}</td>
                  <td class="px-4 py-3 text-gray-300">{{ session.duration_minutes }} min</td>
                  <td class="px-4 py-3">
                    <span v-if="session.behavior_end" :class="getBehaviorBadgeClass(session.behavior_end)">
                      {{ behaviorLabels[session.behavior_end] }}
                    </span>
                    <span v-else class="text-gray-500">-</span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <RouterLink :to="`/app/sessions/${session.id}`" class="bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 px-3 py-1.5 text-xs rounded-lg transition-colors">
                      Voir
                    </RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="sessionsStore.pagination.pages > 1" class="px-4 py-3 border-t border-gray-700 flex items-center justify-between">
              <div class="text-sm text-gray-400">
                {{ sessionsStore.pagination.total }} séance(s)
              </div>
              <div class="flex space-x-2">
                <button
                  v-for="page in sessionsStore.pagination.pages"
                  :key="page"
                  @click="loadPage(page)"
                  :class="[
                    'px-3 py-1 text-sm rounded',
                    page === sessionsStore.pagination.page
                      ? 'bg-primary-600 text-white'
                      : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                  ]"
                >
                  {{ page }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Calendar (admin only) -->
        <div v-if="isAdmin" class="lg:col-span-1">
          <SessionCalendar
            :data="calendarData"
            :year="calendarYear"
            :month="calendarMonth"
            @change-month="handleMonthChange"
          />
        </div>
      </div>
    </template>
  </div>
</template>
