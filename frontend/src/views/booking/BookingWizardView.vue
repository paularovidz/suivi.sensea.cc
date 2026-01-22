<template>
  <div>
    <!-- Loading state -->
    <template v-if="initializing">
      <div class="card-dark shadow-lg overflow-hidden p-12">
        <div class="flex flex-col items-center justify-center">
          <svg class="animate-spin h-8 w-8 text-indigo-500 mb-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="text-gray-400">Chargement...</p>
        </div>
      </div>
    </template>

    <template v-else>
      <!-- Progress bar -->
      <BookingProgressBar :current-step="bookingStore.currentStep" />

      <!-- Step content -->
      <div class="card-dark shadow-lg overflow-hidden">
        <ClientTypeStep v-if="bookingStore.currentStep === 1" />
        <PersonSelectionStep v-else-if="bookingStore.currentStep === 2" />
        <DateTimeStep v-else-if="bookingStore.currentStep === 3" />
        <ContactInfoStep v-else-if="bookingStore.currentStep === 4" />
        <ConfirmationStep v-else-if="bookingStore.currentStep === 5" @new-booking="handleNewBooking" />
      </div>

      <!-- Navigation buttons -->
      <div class="mt-6 flex justify-between">
        <button
          v-if="showBackButton"
          @click="handlePrev"
          class="px-4 py-2 text-gray-400 hover:text-white font-medium flex items-center transition-colors"
        >
          <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Retour
        </button>
        <div v-else></div>

        <button
          v-if="bookingStore.currentStep < 5 && bookingStore.currentStep !== 3"
          @click="handleNext"
          :disabled="!bookingStore.canGoNext || bookingStore.loading"
          :class="[
            'px-6 py-2 rounded-lg font-medium flex items-center transition-all duration-200',
            bookingStore.canGoNext && !bookingStore.loading
              ? 'bg-indigo-600 text-white hover:bg-indigo-500'
              : 'bg-gray-700 text-gray-500 cursor-not-allowed'
          ]"
        >
          <span v-if="bookingStore.loading">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </span>
          {{ bookingStore.currentStep === 4 ? 'Confirmer' : 'Continuer' }}
          <svg v-if="bookingStore.currentStep < 4" class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { personsApi } from '@/services/api'
import BookingProgressBar from '@/components/booking/BookingProgressBar.vue'
import ClientTypeStep from './steps/ClientTypeStep.vue'
import PersonSelectionStep from './steps/PersonSelectionStep.vue'
import DateTimeStep from './steps/DateTimeStep.vue'
import ContactInfoStep from './steps/ContactInfoStep.vue'
import ConfirmationStep from './steps/ConfirmationStep.vue'

const bookingStore = useBookingStore()
const authStore = useAuthStore()
const toastStore = useToastStore()

const initializing = ref(true)

// Afficher le bouton retour sauf à l'étape 1, à l'étape 5, et à l'étape 2 pour les utilisateurs connectés
const showBackButton = computed(() => {
  if (bookingStore.currentStep <= 1 || bookingStore.currentStep >= 5) {
    return false
  }
  // Pour les utilisateurs connectés, pas de retour à l'étape 2 (ils commencent là)
  if (authStore.isAuthenticated && bookingStore.currentStep === 2) {
    return false
  }
  return true
})

onMounted(async () => {
  try {
    // Fetch schedule info
    await bookingStore.fetchScheduleInfo()

    // Si l'utilisateur est connecté, pré-remplir et passer à l'étape 2
    if (authStore.isAuthenticated && authStore.user) {
      await initForAuthenticatedUser()
    } else {
      // Try to restore from storage only for non-authenticated users
      bookingStore.restoreFromStorage()
    }
  } finally {
    initializing.value = false
  }
})

async function initForAuthenticatedUser() {
  const user = authStore.user

  // Marquer comme client existant
  bookingStore.isNewClient = false

  // Pré-remplir les infos client
  bookingStore.clientInfo.email = user.email
  bookingStore.clientInfo.firstName = user.first_name
  bookingStore.clientInfo.lastName = user.last_name
  bookingStore.clientInfo.phone = user.phone || ''
  bookingStore.clientInfo.clientType = user.client_type || 'personal'
  bookingStore.clientInfo.companyName = user.company_name || ''
  bookingStore.clientInfo.siret = user.siret || ''

  // RGPD déjà accepté
  bookingStore.gdprConsent = true

  // Marquer les infos existantes pour l'affichage
  bookingStore.existingClientInfo = {
    email_masked: maskEmail(user.email),
    phone_masked: user.phone ? user.phone.slice(-4) : null,
    has_phone: !!user.phone,
    gdpr_already_accepted: true,
    first_name: user.first_name,
    last_name: user.last_name,
    client_type: user.client_type || 'personal',
    client_type_label: { personal: 'Particulier', association: 'Association', friends_family: 'Friends & Family' }[user.client_type] || 'Particulier',
    company_name: user.company_name || null,
    has_company: !!user.company_name
  }

  // Charger les personnes assignées à l'utilisateur
  try {
    const response = await personsApi.getAll()
    bookingStore.existingPersons = response.data.data?.persons || []
  } catch (e) {
    console.error('Failed to fetch persons:', e)
    bookingStore.existingPersons = []
  }

  // Passer directement à l'étape 2 (sélection de la personne)
  bookingStore.goToStep(2)
}

function maskEmail(email) {
  const [local, domain] = email.split('@')
  if (local.length <= 2) {
    return `${local[0]}***@${domain}`
  }
  return `${local.slice(0, 2)}***@${domain}`
}

async function handleNewBooking() {
  // Reset le wizard
  bookingStore.resetWizard()

  // Si l'utilisateur est connecté, réinitialiser pour lui
  if (authStore.isAuthenticated && authStore.user) {
    await initForAuthenticatedUser()
  }
}

function handlePrev() {
  // Pour les utilisateurs connectés à l'étape 2, ne pas revenir à l'étape 1
  if (authStore.isAuthenticated && bookingStore.currentStep === 2) {
    return
  }
  bookingStore.prevStep()
}

async function handleNext() {
  if (bookingStore.currentStep === 4) {
    // Submit booking
    try {
      await bookingStore.createBooking()
      bookingStore.nextStep()
    } catch (err) {
      // Afficher l'erreur via toast
      toastStore.apiError(err, 'Impossible de créer la réservation')
    }
  } else {
    bookingStore.nextStep()
  }
}
</script>
