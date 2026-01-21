<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { bookingsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AgendaCalendar from '@/components/agenda/AgendaCalendar.vue'
import BookingDetailModal from '@/components/agenda/BookingDetailModal.vue'

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const bookings = ref([])
const stats = ref(null)
const pagination = ref({ page: 1, limit: 100, total: 0, pages: 1 })

// Vue mode: 'calendar' ou 'list'
const viewMode = ref('calendar')

// Date range pour le calendrier
const calendarDateRange = ref({ start: null, end: null })

// Booking sélectionné pour le modal
const selectedBooking = ref(null)

// Filters
const filterStatus = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')

// Status options
const statusOptions = [
  { value: '', label: 'Tous les statuts' },
  { value: 'pending', label: 'En attente' },
  { value: 'confirmed', label: 'Confirmé' },
  { value: 'completed', label: 'Effectué' },
  { value: 'cancelled', label: 'Annulé' },
  { value: 'no_show', label: 'Absent' }
]

const statusLabels = {
  pending: 'En attente',
  confirmed: 'Confirmé',
  cancelled: 'Annulé',
  completed: 'Effectué',
  no_show: 'Absent'
}

const statusClasses = {
  pending: 'bg-yellow-100 text-yellow-800',
  confirmed: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
  completed: 'bg-blue-100 text-blue-800',
  no_show: 'bg-gray-100 text-gray-800'
}

const durationLabels = {
  discovery: 'Découverte (1h15)',
  regular: 'Classique (45min)'
}

// Bookings filtrés pour le calendrier
const calendarBookings = computed(() => {
  if (!calendarDateRange.value.start || !calendarDateRange.value.end) {
    return bookings.value
  }
  return bookings.value.filter(b => {
    if (filterStatus.value && b.status !== filterStatus.value) {
      return false
    }
    return true
  })
})

onMounted(async () => {
  await loadStats()
  loading.value = false
})

async function loadStats() {
  try {
    const response = await bookingsApi.getStats()
    // Stats are nested under 'bookings' key in the API response
    stats.value = response.data.data.bookings || response.data.data
  } catch (e) {
    console.error('Error loading stats:', e)
  }
}

async function loadBookings(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      limit: pagination.value.limit
    }

    if (filterStatus.value) {
      params.status = filterStatus.value
    }

    // En mode calendrier, utiliser les dates de la semaine affichée
    if (viewMode.value === 'calendar' && calendarDateRange.value.start) {
      params.date_from = formatDateStr(calendarDateRange.value.start)
      params.date_to = formatDateStr(calendarDateRange.value.end)
    } else {
      if (filterDateFrom.value) {
        params.date_from = filterDateFrom.value
      }
      if (filterDateTo.value) {
        params.date_to = filterDateTo.value
      }
    }

    const response = await bookingsApi.getAll(params)
    bookings.value = response.data.data.bookings || []
    pagination.value = {
      page: response.data.data.pagination?.page || 1,
      limit: response.data.data.pagination?.limit || 100,
      total: response.data.data.pagination?.total || 0,
      pages: response.data.data.pagination?.pages || 1
    }
  } catch (e) {
    console.error('Error loading bookings:', e)
  } finally {
    loading.value = false
  }
}

function formatDateStr(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function applyFilters() {
  loadBookings(1)
}

function clearFilters() {
  filterStatus.value = ''
  filterDateFrom.value = ''
  filterDateTo.value = ''
  loadBookings(1)
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function formatTime(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatDateTime(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function navigateToSession(booking) {
  if (booking.linked_session_id) {
    router.push(`/app/sessions/${booking.linked_session_id}`)
  }
}

function getClientTypeIcon(booking) {
  return booking.client_type === 'professional' ? 'briefcase' : 'user'
}

function isUpcoming(dateString) {
  return new Date(dateString) >= new Date()
}

// Événements du calendrier
function onWeekChange({ start, end }) {
  calendarDateRange.value = { start, end }
  loadBookings(1)
}

function onSelectBooking(booking) {
  if (booking.linked_session_id) {
    router.push(`/app/sessions/${booking.linked_session_id}`)
  } else {
    selectedBooking.value = booking
  }
}

function closeBookingModal() {
  selectedBooking.value = null
}

function onBookingDeleted(bookingId) {
  // Supprimer le booking de la liste locale
  bookings.value = bookings.value.filter(b => b.id !== bookingId)
  // Recharger les stats
  loadStats()
}

function onBookingStatusChanged({ id, status }) {
  // Mettre à jour le statut localement
  const booking = bookings.value.find(b => b.id === id)
  if (booking) {
    booking.status = status
  }
  // Mettre à jour le booking sélectionné
  if (selectedBooking.value?.id === id) {
    selectedBooking.value.status = status
  }
  // Recharger les stats
  loadStats()
}

function onSelectDate(date) {
  // Filtrer sur cette date dans la vue liste
  const dateStr = formatDateStr(date)
  filterDateFrom.value = dateStr
  filterDateTo.value = dateStr
  viewMode.value = 'list'
  loadBookings(1)
}

// Charger les bookings quand on change de mode
watch(viewMode, (newMode) => {
  if (newMode === 'list') {
    // En mode liste, par défaut afficher les RDV à venir
    if (!filterDateFrom.value) {
      const today = new Date()
      filterDateFrom.value = formatDateStr(today)
    }
    loadBookings(1)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">

      <!-- Toggle vue -->
      <div class="flex items-center bg-gray-700 rounded-lg p-1">
        <button
          @click="viewMode = 'calendar'"
          :class="[
            'flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
            viewMode === 'calendar'
              ? 'bg-gray-600 text-white shadow-sm'
              : 'text-gray-400 hover:text-white'
          ]"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          Calendrier
        </button>
        <button
          @click="viewMode = 'list'"
          :class="[
            'flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
            viewMode === 'list'
              ? 'bg-gray-600 text-white shadow-sm'
              : 'text-gray-400 hover:text-white'
          ]"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
          </svg>
          Liste
        </button>
      </div>
    </div>

    <!-- Stats cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-amber-900/50 mr-4">
            <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-400">Aujourd'hui</p>
            <p class="text-2xl font-bold text-white">{{ stats.today }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-green-900/50 mr-4">
            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-400">A venir</p>
            <p class="text-2xl font-bold text-white">{{ stats.upcoming }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-yellow-900/50 mr-4">
            <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-400">En attente</p>
            <p class="text-2xl font-bold text-white">{{ stats.pending }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-blue-900/50 mr-4">
            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-400">Ce mois (confirmés)</p>
            <p class="text-2xl font-bold text-white">{{ stats.by_status_this_month?.confirmed || 0 }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtre par statut (visible dans les deux modes) -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
      <div class="flex items-center gap-4">
        <div class="flex-1 max-w-xs">
          <label class="block text-sm font-medium text-gray-300 mb-1">Filtrer par statut</label>
          <select
            v-model="filterStatus"
            @change="viewMode === 'calendar' ? loadBookings(1) : applyFilters()"
            class="w-full rounded-lg bg-gray-700 border-gray-600 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Filtres supplémentaires en mode liste -->
        <template v-if="viewMode === 'list'">
          <div class="flex-1 max-w-xs">
            <label class="block text-sm font-medium text-gray-300 mb-1">Date de début</label>
            <input
              v-model="filterDateFrom"
              type="date"
              @change="applyFilters"
              class="w-full rounded-lg bg-gray-700 border-gray-600 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
            />
          </div>

          <div class="flex-1 max-w-xs">
            <label class="block text-sm font-medium text-gray-300 mb-1">Date de fin</label>
            <input
              v-model="filterDateTo"
              type="date"
              @change="applyFilters"
              class="w-full rounded-lg bg-gray-700 border-gray-600 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
            />
          </div>

          <div class="flex items-end">
            <button
              @click="clearFilters"
              class="bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 px-4 py-2 text-sm font-medium rounded-lg transition-colors"
            >
              Effacer
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- Vue Calendrier -->
    <template v-if="viewMode === 'calendar'">
      <AgendaCalendar
        :bookings="calendarBookings"
        @week-change="onWeekChange"
        @select-booking="onSelectBooking"
        @select-date="onSelectDate"
      />
    </template>

    <!-- Vue Liste -->
    <template v-else>
      <LoadingSpinner v-if="loading" size="lg" class="py-12" />

      <template v-else>
        <EmptyState
          v-if="bookings.length === 0"
          title="Aucun rendez-vous"
          description="Aucun rendez-vous ne correspond aux filtres."
          icon="calendar"
        />

        <div v-else class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
          <table class="w-full text-sm text-left">
            <thead>
              <tr class="bg-gray-800/50">
                <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Type</th>
                <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Date / Heure</th>
                <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Client</th>
                <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Bénéficiaire</th>
                <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Durée</th>
                <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Statut</th>
                <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="booking in bookings"
                :key="booking.id"
                :class="[
                  'border-t border-gray-700',
                  !isUpcoming(booking.session_date) ? 'bg-gray-800/30' : 'hover:bg-gray-700/50'
                ]"
              >
                <!-- Client type icon -->
                <td class="px-4 py-3">
                  <span
                    :title="booking.client_type === 'professional' ? 'Professionnel' : 'Particulier'"
                    :class="booking.client_type === 'professional' ? 'text-amber-400' : 'text-gray-400'"
                  >
                    <svg v-if="booking.client_type === 'professional'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </span>
                </td>

                <!-- Date/Time -->
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-100">{{ formatDate(booking.session_date) }}</div>
                  <div class="text-sm text-gray-400">{{ formatTime(booking.session_date) }}</div>
                </td>

                <!-- Client -->
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-100">{{ booking.client_first_name }} {{ booking.client_last_name }}</div>
                  <div class="text-sm text-gray-400">{{ booking.client_email }}</div>
                  <div v-if="booking.company_name" class="text-xs text-amber-400">{{ booking.company_name }}</div>
                </td>

                <!-- Beneficiary -->
                <td class="px-4 py-3">
                  <RouterLink
                    v-if="booking.person_id"
                    :to="`/app/persons/${booking.person_id}`"
                    class="font-medium text-gray-100 hover:text-primary-400"
                  >
                    {{ booking.person_first_name }} {{ booking.person_last_name }}
                  </RouterLink>
                  <span v-else class="text-gray-300">
                    {{ booking.person_first_name }} {{ booking.person_last_name }}
                  </span>
                </td>

                <!-- Duration -->
                <td class="px-4 py-3">
                  <span class="text-sm text-gray-300">{{ durationLabels[booking.duration_type] || booking.duration_type }}</span>
                </td>

                <!-- Status -->
                <td class="px-4 py-3">
                  <span :class="['px-2 py-1 text-xs font-medium rounded-full', statusClasses[booking.status]]">
                    {{ statusLabels[booking.status] || booking.status }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end space-x-2">
                    <RouterLink
                      v-if="booking.linked_session_id"
                      :to="`/app/sessions/${booking.linked_session_id}`"
                      class="btn-primary btn-sm"
                      title="Voir la séance"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      Séance
                    </RouterLink>
                    <button
                      v-else
                      @click="selectedBooking = booking"
                      class="bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 px-3 py-1.5 text-xs rounded-lg transition-colors"
                      title="Voir les détails"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      Détails
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="pagination.pages > 1" class="px-4 py-3 border-t border-gray-700 flex items-center justify-between">
            <div class="text-sm text-gray-400">
              {{ pagination.total }} rendez-vous
            </div>
            <div class="flex space-x-2">
              <button
                v-for="page in pagination.pages"
                :key="page"
                @click="loadBookings(page)"
                :class="[
                  'px-3 py-1 text-sm rounded',
                  page === pagination.page
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                ]"
              >
                {{ page }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </template>

    <!-- Modal détail booking -->
    <BookingDetailModal
      :booking="selectedBooking"
      @close="closeBookingModal"
      @deleted="onBookingDeleted"
      @status-changed="onBookingStatusChanged"
    />
  </div>
</template>
