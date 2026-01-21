<template>
  <div>
    <!-- Progress bar -->
    <BookingProgressBar :current-step="bookingStore.currentStep" />

    <!-- Step content -->
    <div class="card-dark shadow-lg overflow-hidden">
      <ClientTypeStep v-if="bookingStore.currentStep === 1" />
      <PersonSelectionStep v-else-if="bookingStore.currentStep === 2" />
      <DateTimeStep v-else-if="bookingStore.currentStep === 3" />
      <ContactInfoStep v-else-if="bookingStore.currentStep === 4" />
      <ConfirmationStep v-else-if="bookingStore.currentStep === 5" />
    </div>

    <!-- Navigation buttons -->
    <div class="mt-6 flex justify-between">
      <button
        v-if="bookingStore.currentStep > 1 && bookingStore.currentStep < 5"
        @click="bookingStore.prevStep"
        class="px-4 py-2 text-gray-400 hover:text-white font-medium flex items-center transition-colors"
      >
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Retour
      </button>
      <div v-else></div>

      <button
        v-if="bookingStore.currentStep < 5"
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
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useBookingStore } from '@/stores/booking'
import { useToastStore } from '@/stores/toast'
import BookingProgressBar from '@/components/booking/BookingProgressBar.vue'
import ClientTypeStep from './steps/ClientTypeStep.vue'
import PersonSelectionStep from './steps/PersonSelectionStep.vue'
import DateTimeStep from './steps/DateTimeStep.vue'
import ContactInfoStep from './steps/ContactInfoStep.vue'
import ConfirmationStep from './steps/ConfirmationStep.vue'

const bookingStore = useBookingStore()
const toastStore = useToastStore()

onMounted(async () => {
  // Try to restore from storage
  bookingStore.restoreFromStorage()

  // Fetch schedule info
  await bookingStore.fetchScheduleInfo()
})

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
