<template>
  <div class="text-center py-8">
    <!-- Loading -->
    <template v-if="loading">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
      <p class="mt-4 text-gray-500">Confirmation en cours...</p>
    </template>

    <!-- Error -->
    <template v-else-if="error">
      <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </div>
      <h2 class="text-2xl font-semibold text-gray-900 mb-2">Oups !</h2>
      <p class="text-gray-500 mb-6 max-w-md mx-auto">{{ error }}</p>
      <router-link
        to="/booking"
        class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
      >
        Faire une nouvelle réservation
      </router-link>
    </template>

    <!-- Success -->
    <template v-else-if="booking">
      <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <h2 class="text-2xl font-semibold text-gray-900 mb-2">
        {{ alreadyConfirmed ? 'Déjà confirmé' : 'Rendez-vous confirmé !' }}
      </h2>

      <p class="text-gray-500 mb-6 max-w-md mx-auto">
        <template v-if="alreadyConfirmed">
          Votre rendez-vous était déjà confirmé. Voici les détails de votre réservation.
        </template>
        <template v-else>
          Votre rendez-vous est maintenant confirmé. Un email de confirmation avec un fichier calendrier (.ics) vous a été envoyé.
        </template>
      </p>

      <!-- Booking details -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-left max-w-md mx-auto mb-6">
        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
          Détails du rendez-vous
        </h3>

        <dl class="space-y-3">
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500">Bénéficiaire</dt>
            <dd class="text-sm font-medium text-gray-900">
              {{ booking.person_first_name }} {{ booking.person_last_name }}
            </dd>
          </div>

          <div class="flex justify-between">
            <dt class="text-sm text-gray-500">Type</dt>
            <dd class="text-sm font-medium text-gray-900">
              {{ booking.duration_type_label }}
            </dd>
          </div>

          <div class="flex justify-between">
            <dt class="text-sm text-gray-500">Date et heure</dt>
            <dd class="text-sm font-medium text-gray-900">
              {{ formattedDateTime }}
            </dd>
          </div>

          <div class="flex justify-between">
            <dt class="text-sm text-gray-500">Durée</dt>
            <dd class="text-sm font-medium text-gray-900">
              {{ booking.duration_display_minutes }} minutes
            </dd>
          </div>
        </dl>
      </div>

      <!-- Actions -->
      <div class="space-y-3">
        <a
          :href="icsDownloadUrl"
          class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          Ajouter à mon calendrier
        </a>

        <p class="text-sm text-gray-400">
          Besoin d'annuler ?
          <router-link
            :to="`/booking/cancel/${token}`"
            class="text-red-500 hover:underline"
          >
            Annuler ce rendez-vous
          </router-link>
        </p>
      </div>

      <!-- Reminder -->
      <div class="mt-8 p-4 bg-indigo-50 rounded-lg max-w-md mx-auto text-left">
        <div class="flex">
          <svg class="w-5 h-5 text-indigo-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <div class="ml-3">
            <p class="text-sm text-indigo-700">
              Vous recevrez un rappel la veille de votre rendez-vous.
            </p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'

const route = useRoute()
const bookingStore = useBookingStore()

const token = computed(() => route.params.token)
const loading = ref(true)
const error = ref(null)
const booking = ref(null)
const alreadyConfirmed = ref(false)

const apiUrl = import.meta.env.VITE_API_URL || '/api'
const icsDownloadUrl = computed(() => `${apiUrl}/public/bookings/${token.value}/ics`)

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
    error.value = 'Lien de confirmation invalide'
    loading.value = false
    return
  }

  try {
    const result = await bookingStore.confirmBooking(token.value)
    booking.value = result.booking
    alreadyConfirmed.value = result.already_confirmed || false
  } catch (err) {
    error.value = err.response?.data?.message || 'Impossible de confirmer la réservation'
  } finally {
    loading.value = false
  }
})
</script>
