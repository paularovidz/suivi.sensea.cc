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
    // Fetch sessions calendar data
    try {
      const sessionsResponse = await sessionsApi.getStats({ year: calendarYear.value, month: calendarMonth.value })
      calendarData.value = sessionsResponse.data.data.calendar || {}
    } catch (e) {
      console.error('Error loading sessions calendar:', e)
      calendarData.value = {}
    }

    // Fetch bookings calendar data (admin only)
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
    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <!-- Admin Stats -->
      <div v-if="authStore.isAdmin && stats" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <div class="text-sm text-gray-400 mb-1">Séances ce mois</div>
          <div class="text-3xl font-bold text-white">{{ stats.sessions.this_month }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Sessions Calendar -->
        <div class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="font-semibold text-white">Calendrier des séances</h2>
          </div>
          <div class="p-6">
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
        <div class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold text-white">Dernières séances</h2>
            <RouterLink to="/app/sessions" class="text-sm text-primary-400 hover:text-primary-300">
              Voir tout
            </RouterLink>
          </div>
          <div class="divide-y divide-gray-700">
            <RouterLink
              v-for="session in sessionsStore.sessions.slice(0, 5)"
              :key="session.id"
              :to="`/app/sessions/${session.id}`"
              class="flex items-center px-6 py-4 hover:bg-gray-700/50 transition-colors"
            >
              <div class="w-10 h-10 rounded-full bg-green-900/50 flex items-center justify-center text-green-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="ml-4 flex-1">
                <div class="font-medium text-white">
                  {{ session.person_first_name }} {{ session.person_last_name }}
                </div>
                <div class="text-sm text-gray-400">
                  {{ formatDate(session.session_date) }} - {{ session.duration_minutes }} min
                </div>
              </div>
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </RouterLink>
            <div v-if="sessionsStore.sessions.length === 0" class="px-6 py-8 text-center text-gray-400">
              Aucune séance enregistrée
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
