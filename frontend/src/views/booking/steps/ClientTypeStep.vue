<template>
  <div class="p-6">
    <h2 class="text-xl font-semibold text-white mb-2">Bienvenue !</h2>
    <p class="text-gray-400 mb-6">
      Comment souhaitez-vous procéder pour votre réservation ?
    </p>

    <div class="space-y-4">
      <!-- New client -->
      <button
        @click="selectType(true)"
        :class="[
          'w-full p-4 rounded-xl border-2 text-left transition-all duration-200 flex items-start',
          bookingStore.isNewClient === true
            ? 'border-indigo-500 bg-indigo-500/20'
            : 'border-gray-600 hover:border-indigo-400 hover:bg-gray-700/50'
        ]"
      >
        <div
          :class="[
            'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 mr-4',
            bookingStore.isNewClient === true ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400'
          ]"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
        </div>
        <div>
          <h3 class="font-medium text-white">C'est ma première fois</h3>
          <p class="text-sm text-gray-400 mt-1">
            Je souhaite découvrir les séances Snoezelen (séance découverte de 1h15)
          </p>
        </div>
      </button>

      <!-- Returning client -->
      <button
        @click="selectType(false)"
        :class="[
          'w-full p-4 rounded-xl border-2 text-left transition-all duration-200 flex items-start',
          bookingStore.isNewClient === false
            ? 'border-indigo-500 bg-indigo-500/20'
            : 'border-gray-600 hover:border-indigo-400 hover:bg-gray-700/50'
        ]"
      >
        <div
          :class="[
            'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 mr-4',
            bookingStore.isNewClient === false ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400'
          ]"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </div>
        <div>
          <h3 class="font-medium text-white">Je suis déjà venu(e)</h3>
          <p class="text-sm text-gray-400 mt-1">
            Je souhaite prendre un nouveau rendez-vous (séance de 45 minutes)
          </p>
        </div>
      </button>
    </div>

    <!-- Session type info -->
    <div class="mt-6 p-4 bg-indigo-500/10 border border-indigo-500/30 rounded-lg">
      <h4 class="text-sm font-medium text-indigo-300 mb-2">Information sur les séances</h4>
      <ul class="text-sm text-indigo-200/80 space-y-1">
        <li class="flex items-center">
          <svg class="w-4 h-4 mr-2 text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <strong>Séance découverte :</strong>&nbsp;1h15 pour une première expérience complète
        </li>
        <li class="flex items-center">
          <svg class="w-4 h-4 mr-2 text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <strong>Séance classique :</strong>&nbsp;45 minutes de stimulation sensorielle
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { useBookingStore } from '@/stores/booking'

const emit = defineEmits(['selected'])

const bookingStore = useBookingStore()

function selectType(isNew) {
  // Reset all following steps if changing client type
  if (bookingStore.isNewClient !== isNew && bookingStore.isNewClient !== null) {
    bookingStore.resetFollowingSteps()
    // Also reset person selection
    bookingStore.selectedPersonId = null
    bookingStore.newPerson = { firstName: '', lastName: '' }
    bookingStore.existingPersons = []
    bookingStore.existingClientInfo = null
  }

  bookingStore.isNewClient = isNew
  // Set duration type based on selection
  if (isNew) {
    bookingStore.setDurationType('discovery')
  } else {
    bookingStore.setDurationType('regular')
  }

  // Emit event to auto-advance
  emit('selected')
}
</script>
