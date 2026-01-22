<template>
  <div class="p-6">
    <h2 class="text-xl font-semibold text-white mb-2">Bienvenue !</h2>
    <p class="text-gray-400 mb-6">
      Entrez votre adresse email pour commencer votre réservation.
    </p>

    <!-- Email input -->
    <div class="mb-6">
      <label for="email-booking" class="block text-sm font-medium text-gray-300 mb-1">
        Adresse email <span class="text-red-400">*</span>
      </label>
      <input
        id="email-booking"
        v-model="email"
        type="email"
        placeholder="votre@email.com"
        class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        :disabled="loading"
        @keyup.enter="handleSubmit"
      />
      <p v-if="error" class="mt-2 text-sm text-red-400">{{ error }}</p>
    </div>

    <!-- Submit button -->
    <button
      @click="handleSubmit"
      :disabled="!isValidEmail || loading"
      :class="[
        'w-full px-6 py-3 rounded-lg font-medium flex items-center justify-center transition-all duration-200',
        isValidEmail && !loading
          ? 'bg-indigo-600 text-white hover:bg-indigo-500'
          : 'bg-gray-700 text-gray-500 cursor-not-allowed'
      ]"
    >
      <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span>{{ loading ? 'Recherche...' : 'Continuer' }}</span>
      <svg v-if="!loading" class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </button>

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
import { ref, computed, onMounted } from 'vue'
import { useBookingStore } from '@/stores/booking'

const emit = defineEmits(['selected'])

const bookingStore = useBookingStore()

const email = ref('')
const loading = ref(false)
const error = ref('')

// Restore email if already set
onMounted(() => {
  if (bookingStore.clientInfo.email) {
    email.value = bookingStore.clientInfo.email
  }
})

const isValidEmail = computed(() => {
  return email.value && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)
})

async function handleSubmit() {
  if (!isValidEmail.value || loading.value) return

  error.value = ''
  loading.value = true

  try {
    // Store email for later
    bookingStore.clientInfo.email = email.value.trim().toLowerCase()

    // Check if email exists and fetch persons
    await bookingStore.fetchPersonsByEmail(email.value)

    // Determine if new or existing client based on API response
    const isExistingClient = bookingStore.existingClientInfo !== null

    if (isExistingClient) {
      // Existing client: regular session
      bookingStore.isNewClient = false
      bookingStore.setDurationType('regular')
    } else {
      // New client: discovery session
      bookingStore.isNewClient = true
      bookingStore.setDurationType('discovery')
    }

    // Auto-advance to next step
    emit('selected')
  } catch (err) {
    // Even on error, treat as new client
    bookingStore.isNewClient = true
    bookingStore.setDurationType('discovery')
    emit('selected')
  } finally {
    loading.value = false
  }
}
</script>
