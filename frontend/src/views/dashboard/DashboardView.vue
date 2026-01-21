<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSessionsStore } from '@/stores/sessions'
import { statsApi, sessionsApi, bookingsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import SessionCalendar from '@/components/ui/SessionCalendar.vue'

const authStore = useAuthStore()
const sessionsStore = useSessionsStore()

const loading = ref(true)
const stats = ref(null)
const calendarData = ref({})
const bookingsCalendarData = ref({})
const calendarYear = ref(new Date().getFullYear())
const calendarMonth = ref(new Date().getMonth() + 1)

onMounted(async () => {
  try {
    // Fetch recent sessions
    await sessionsStore.fetchSessions({ limit: 5 })

    // Fetch admin stats if admin
    if (authStore.isAdmin) {
      const response = await statsApi.getDashboard()
      stats.value = response.data.data
    }

    // Fetch calendar data
    await loadCalendarData()
  } catch (e) {
    console.error('Error loading dashboard:', e)
  } finally {
    loading.value = false
  }
})

async function loadCalendarData() {
  try {
    // Fetch bookings calendar data (admin only) - no longer showing sessions
    if (authStore.isAdmin) {
      try {
        const bookingsResponse = await bookingsApi.getCalendar(calendarYear.value, calendarMonth.value)
        bookingsCalendarData.value = bookingsResponse.data.data.calendar || {}
      } catch (e) {
        console.error('Error loading bookings calendar:', e)
        bookingsCalendarData.value = {}
      }
    }
  } catch (e) {
    console.error('Error loading calendar:', e)
  }
}

async function handleMonthChange({ year, month }) {
  calendarYear.value = year
  calendarMonth.value = month
  await loadCalendarData()
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">
        Bonjour {{ authStore.user?.first_name }} !
      </h1>
      <p class="text-gray-600 mt-1">Bienvenue sur votre tableau de bord Snoezelen</p>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <!-- Admin Stats -->
      <div v-if="authStore.isAdmin && stats" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="card p-6">
          <div class="text-sm text-gray-500 mb-1">Séances ce mois</div>
          <div class="text-3xl font-bold text-gray-900">{{ stats.sessions.this_month }}</div>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <RouterLink to="/app/sessions/new" class="card p-6 hover:shadow-md transition-shadow group">
          <div class="flex items-center space-x-4">
            <div class="w-12 h-12 rounded-lg gradient-sensea flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-900">Nouvelle séance</h3>
              <p class="text-sm text-gray-500">Créer une séance Snoezelen</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink to="/app/proposals" class="card p-6 hover:shadow-md transition-shadow group">
          <div class="flex items-center space-x-4">
            <div class="w-12 h-12 rounded-lg bg-yellow-100 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-900">Propositions sensorielles</h3>
              <p class="text-sm text-gray-500">Gérer les activités</p>
            </div>
          </div>
        </RouterLink>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Sessions Calendar -->
        <div class="card">
          <div class="card-header">
            <h2 class="font-semibold text-gray-900">Calendrier des séances</h2>
          </div>
          <div class="card-body">
            <SessionCalendar
              :data="calendarData"
              :bookings-data="bookingsCalendarData"
              :year="calendarYear"
              :month="calendarMonth"
              @change-month="handleMonthChange"
            />
          </div>
        </div>

        <!-- Recent sessions -->
        <div class="card">
          <div class="card-header flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Dernières séances</h2>
            <RouterLink to="/app/sessions" class="text-sm text-primary-600 hover:text-primary-700">
              Voir tout
            </RouterLink>
          </div>
          <div class="divide-y divide-gray-100">
            <RouterLink
              v-for="session in sessionsStore.sessions.slice(0, 5)"
              :key="session.id"
              :to="`/app/sessions/${session.id}`"
              class="flex items-center px-6 py-4 hover:bg-gray-50 transition-colors"
            >
              <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="ml-4 flex-1">
                <div class="font-medium text-gray-900">
                  {{ session.person_first_name }} {{ session.person_last_name }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ formatDate(session.session_date) }} - {{ session.duration_minutes }} min
                </div>
              </div>
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </RouterLink>
            <div v-if="sessionsStore.sessions.length === 0" class="px-6 py-8 text-center text-gray-500">
              Aucune séance enregistrée
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
