<template>
  <div class="p-6 text-center">
    <!-- Success icon -->
    <div class="mx-auto w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mb-4">
      <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </div>

    <h2 class="text-2xl font-semibold text-white mb-2">
      {{ bookingStore.emailConfirmationRequired ? 'Demande envoyée !' : 'Rendez-vous confirmé !' }}
    </h2>

    <p class="text-gray-400 mb-6 max-w-md mx-auto">
      <template v-if="bookingStore.emailConfirmationRequired">
        Un email de confirmation a été envoyé à
        <strong class="text-white">{{ bookingStore.clientInfo.email }}</strong>.
        Veuillez cliquer sur le lien dans l'email pour confirmer votre rendez-vous.
      </template>
      <template v-else>
        Un email de confirmation avec les détails de votre rendez-vous a été envoyé à
        <strong class="text-white">{{ bookingStore.clientInfo.email }}</strong>.
      </template>
    </p>

    <!-- Booking summary -->
    <div class="bg-gray-700/30 border border-gray-600/50 rounded-xl p-6 text-left max-w-sm mx-auto mb-6">
      <h3 class="text-sm font-medium text-gray-400 uppercase tracking-wider mb-4">
        Votre réservation
      </h3>

      <dl class="space-y-3">
        <div>
          <dt class="text-xs text-gray-500">Bénéficiaire</dt>
          <dd class="text-sm font-medium text-white">
            {{ bookingStore.personInfo.firstName }} {{ bookingStore.personInfo.lastName }}
          </dd>
        </div>

        <div>
          <dt class="text-xs text-gray-500">Type de séance</dt>
          <dd class="text-sm font-medium text-white">
            {{ bookingStore.durationInfo.label }}
          </dd>
        </div>

        <div>
          <dt class="text-xs text-gray-500">Date et heure</dt>
          <dd class="text-sm font-medium text-white">
            {{ formattedDateTime }}
          </dd>
        </div>

        <div>
          <dt class="text-xs text-gray-500">Contact</dt>
          <dd class="text-sm font-medium text-white">
            {{ bookingStore.clientInfo.firstName }} {{ bookingStore.clientInfo.lastName }}
          </dd>
        </div>
      </dl>
    </div>

    <!-- Clothing tip -->
    <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4 mb-6 max-w-md mx-auto text-left">
      <div class="flex">
        <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <div class="ml-3">
          <h4 class="text-sm font-medium text-amber-300">Conseil</h4>
          <p class="text-sm text-amber-200/80 mt-1">
            Pensez à vous habiller confortablement, idéalement une tenue de sport ou des vêtements souples.
          </p>
        </div>
      </div>
    </div>

    <!-- Important notice (only when email confirmation required) -->
    <div v-if="bookingStore.emailConfirmationRequired" class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4 mb-6 max-w-md mx-auto text-left">
      <div class="flex">
        <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        <div class="ml-3">
          <h4 class="text-sm font-medium text-amber-300">Important</h4>
          <p class="text-sm text-amber-200/80 mt-1">
            Votre créneau sera réservé une fois que vous aurez cliqué sur le lien de confirmation dans l'email.
            Le lien est valable 24 heures.
          </p>
        </div>
      </div>
    </div>

    <!-- Reminder info (when confirmation is NOT required) -->
    <div v-else class="bg-indigo-500/10 border border-indigo-500/30 rounded-lg p-4 mb-6 max-w-md mx-auto text-left">
      <div class="flex">
        <svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <div class="ml-3">
          <h4 class="text-sm font-medium text-indigo-300">Rappel automatique</h4>
          <p class="text-sm text-indigo-200/80 mt-1">
            Vous recevrez un rappel de votre rendez-vous la veille par email.
          </p>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="space-y-3">
      <button
        @click="newBooking"
        class="w-full sm:w-auto px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-500 transition-colors"
      >
        Faire une nouvelle réservation
      </button>

      <p v-if="bookingStore.emailConfirmationRequired" class="text-sm text-gray-500">
        Vous n'avez pas reçu l'email ?
        <button @click="resendEmail" class="text-indigo-400 hover:underline">
          Renvoyer
        </button>
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useBookingStore } from '@/stores/booking'

const bookingStore = useBookingStore()

const formattedDateTime = computed(() => {
  if (!bookingStore.selectedDate || !bookingStore.selectedTime) return '-'

  const [year, month, day] = bookingStore.selectedDate.split('-')
  const date = new Date(year, month - 1, day)
  const dateStr = date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })

  return `${dateStr} à ${bookingStore.selectedTime}`
})

function newBooking() {
  bookingStore.resetWizard()
}

function resendEmail() {
  // The user can simply create a new booking with the same info
  // For now, just show an alert
  alert('Pour renvoyer l\'email, veuillez refaire une demande de réservation avec la même adresse email.')
}
</script>
