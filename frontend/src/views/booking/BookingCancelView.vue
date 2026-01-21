<template>
  <div class="text-center py-8">
    <!-- Loading -->
    <template v-if="loading">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
      <p class="mt-4 text-gray-500">Chargement...</p>
    </template>

    <!-- Error -->
    <template v-else-if="error">
      <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </div>
      <h2 class="text-2xl font-semibold text-gray-900 mb-2">Erreur</h2>
      <p class="text-gray-500 mb-6 max-w-md mx-auto">{{ error }}</p>
      <router-link
        to="/booking"
        class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
      >
        Faire une nouvelle réservation
      </router-link>
    </template>

    <!-- Cancelled confirmation -->
    <template v-else-if="cancelled">
      <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </div>
      <h2 class="text-2xl font-semibold text-gray-900 mb-2">
        Rendez-vous annulé
      </h2>
      <p class="text-gray-500 mb-6 max-w-md mx-auto">
        Votre rendez-vous a bien été annulé. Vous pouvez reprendre rendez-vous à tout moment.
      </p>
      <router-link
        to="/booking"
        class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
      >
        Reprendre rendez-vous
      </router-link>
    </template>

    <!-- Confirmation before cancel -->
    <template v-else-if="booking">
      <div class="mx-auto w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>

      <h2 class="text-2xl font-semibold text-gray-900 mb-2">
        Annuler votre rendez-vous ?
      </h2>

      <p class="text-gray-500 mb-6 max-w-md mx-auto">
        Vous êtes sur le point d'annuler votre rendez-vous. Cette action est irréversible.
      </p>

      <!-- Booking details -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-left max-w-md mx-auto mb-6">
        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
          Rendez-vous concerné
        </h3>

        <dl class="space-y-3">
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500">Bénéficiaire</dt>
            <dd class="text-sm font-medium text-gray-900">
              {{ booking.person_first_name }} {{ booking.person_last_name }}
            </dd>
          </div>

          <div class="flex justify-between">
            <dt class="text-sm text-gray-500">Date et heure</dt>
            <dd class="text-sm font-medium text-gray-900">
              {{ formattedDateTime }}
            </dd>
          </div>

          <div class="flex justify-between">
            <dt class="text-sm text-gray-500">Statut</dt>
            <dd class="text-sm font-medium text-gray-900">
              {{ booking.status_label }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Actions -->
      <div class="space-y-3">
        <button
          @click="cancelBooking"
          :disabled="cancelling"
          class="inline-flex items-center px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
        >
          <template v-if="cancelling">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Annulation...
          </template>
          <template v-else>
            Oui, annuler ce rendez-vous
          </template>
        </button>

        <p>
          <router-link
            to="/booking"
            class="text-gray-500 hover:text-gray-700 text-sm"
          >
            Non, garder mon rendez-vous
          </router-link>
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const bookingStore = useBookingStore()
const toastStore = useToastStore()

const token = computed(() => route.params.token)
const loading = ref(true)
const cancelling = ref(false)
const error = ref(null)
const booking = ref(null)
const cancelled = ref(false)

const formattedDateTime = computed(() => {
  if (!booking.value?.session_date) return '-'

  const date = new Date(booking.value.session_date)
  return date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
})

onMounted(async () => {
  if (!token.value) {
    error.value = 'Lien invalide'
    loading.value = false
    return
  }

  try {
    booking.value = await bookingStore.getBookingByToken(token.value)

    // Check if already cancelled
    if (booking.value.status === 'cancelled') {
      cancelled.value = true
    }
  } catch (err) {
    error.value = toastStore.parseApiError(err) || 'Réservation non trouvée'
  } finally {
    loading.value = false
  }
})

async function cancelBooking() {
  cancelling.value = true

  try {
    const result = await bookingStore.cancelBooking(token.value)
    cancelled.value = true

    if (result.already_cancelled) {
      // Already was cancelled
    }
  } catch (err) {
    error.value = toastStore.parseApiError(err) || 'Impossible d\'annuler la réservation'
  } finally {
    cancelling.value = false
  }
}
</script>
