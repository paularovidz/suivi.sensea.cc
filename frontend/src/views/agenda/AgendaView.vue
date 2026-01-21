<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { bookingsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const bookings = ref([])
const stats = ref(null)
const pagination = ref({ page: 1, limit: 50, total: 0, pages: 1 })

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

onMounted(async () => {
  // Set default date filter to show upcoming bookings
  const today = new Date()
  filterDateFrom.value = today.toISOString().split('T')[0]

  await Promise.all([
    loadBookings(),
    loadStats()
  ])
  loading.value = false
})

async function loadStats() {
  try {
    const response = await bookingsApi.getStats()
    stats.value = response.data.data
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
    if (filterDateFrom.value) {
      params.date_from = filterDateFrom.value
    }
    if (filterDateTo.value) {
      params.date_to = filterDateTo.value
    }

    const response = await bookingsApi.getAll(params)
    bookings.value = response.data.data.bookings || []
    pagination.value = {
      page: response.data.data.pagination?.page || 1,
      limit: response.data.data.pagination?.limit || 50,
      total: response.data.data.pagination?.total || 0,
      pages: response.data.data.pagination?.pages || 1
    }
  } catch (e) {
    console.error('Error loading bookings:', e)
  } finally {
    loading.value = false
  }
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
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Agenda des rendez-vous</h1>
        <p class="text-gray-600 mt-1">Tous les rendez-vous et réservations</p>
      </div>
    </div>

    <!-- Stats cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="card p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-amber-100 mr-4">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Aujourd'hui</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.today }}</p>
          </div>
        </div>
      </div>

      <div class="card p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-green-100 mr-4">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">A venir</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.upcoming }}</p>
          </div>
        </div>
      </div>

      <div class="card p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-yellow-100 mr-4">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">En attente</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.pending }}</p>
          </div>
        </div>
      </div>

      <div class="card p-4">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-blue-100 mr-4">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Ce mois (confirmés)</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.by_status_this_month?.confirmed || 0 }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
          <select
            v-model="filterStatus"
            @change="applyFilters"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
          <input
            v-model="filterDateFrom"
            type="date"
            @change="applyFilters"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
          <input
            v-model="filterDateTo"
            type="date"
            @change="applyFilters"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
          />
        </div>

        <div class="flex items-end">
          <button
            @click="clearFilters"
            class="btn-secondary w-full"
          >
            Effacer les filtres
          </button>
        </div>
      </div>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <EmptyState
        v-if="bookings.length === 0"
        title="Aucun rendez-vous"
        description="Aucun rendez-vous ne correspond aux filtres."
        icon="calendar"
      />

      <div v-else class="card overflow-hidden">
        <table class="table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Date / Heure</th>
              <th>Client</th>
              <th>Bénéficiaire</th>
              <th>Durée</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="booking in bookings"
              :key="booking.id"
              :class="{ 'bg-gray-50': !isUpcoming(booking.session_date) }"
            >
              <!-- Client type icon -->
              <td>
                <span
                  :title="booking.client_type === 'professional' ? 'Professionnel' : 'Particulier'"
                  :class="booking.client_type === 'professional' ? 'text-amber-600' : 'text-gray-500'"
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
              <td>
                <div class="font-medium">{{ formatDate(booking.session_date) }}</div>
                <div class="text-sm text-gray-500">{{ formatTime(booking.session_date) }}</div>
              </td>

              <!-- Client -->
              <td>
                <div class="font-medium">{{ booking.client_first_name }} {{ booking.client_last_name }}</div>
                <div class="text-sm text-gray-500">{{ booking.client_email }}</div>
                <div v-if="booking.company_name" class="text-xs text-amber-600">{{ booking.company_name }}</div>
              </td>

              <!-- Beneficiary -->
              <td>
                <RouterLink
                  v-if="booking.person_id"
                  :to="`/app/persons/${booking.person_id}`"
                  class="font-medium hover:text-primary-600"
                >
                  {{ booking.person_first_name }} {{ booking.person_last_name }}
                </RouterLink>
                <span v-else>
                  {{ booking.person_first_name }} {{ booking.person_last_name }}
                </span>
              </td>

              <!-- Duration -->
              <td>
                <span class="text-sm">{{ durationLabels[booking.duration_type] || booking.duration_type }}</span>
              </td>

              <!-- Status -->
              <td>
                <span :class="['px-2 py-1 text-xs font-medium rounded-full', statusClasses[booking.status]]">
                  {{ statusLabels[booking.status] || booking.status }}
                </span>
              </td>

              <!-- Actions -->
              <td class="text-right">
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
                  <span
                    v-else-if="booking.status === 'confirmed'"
                    class="text-xs text-gray-400"
                  >
                    Séance non créée
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination.pages > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
          <div class="text-sm text-gray-500">
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
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              ]"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
