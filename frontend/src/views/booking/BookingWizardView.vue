<template>
  <div>
    <!-- Progress bar -->
    <BookingProgressBar :current-step="bookingStore.currentStep" />

    <!-- Error message -->
    <div
      v-if="bookingStore.error"
      class="mb-6 bg-red-900/30 border border-red-500/50 rounded-lg p-4 flex items-start"
    >
      <svg class="w-5 h-5 text-red-400 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
      </svg>
      <div>
        <p class="text-sm text-red-300">{{ bookingStore.error }}</p>
      </div>
    </div>

    <!-- Step content -->
    <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl shadow-lg border border-gray-700/50 overflow-hidden">
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
import BookingProgressBar from '@/components/booking/BookingProgressBar.vue'
import ClientTypeStep from './steps/ClientTypeStep.vue'
import PersonSelectionStep from './steps/PersonSelectionStep.vue'
import DateTimeStep from './steps/DateTimeStep.vue'
import ContactInfoStep from './steps/ContactInfoStep.vue'
import ConfirmationStep from './steps/ConfirmationStep.vue'

const bookingStore = useBookingStore()

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
      // Error is handled in store
    }
  } else {
    bookingStore.nextStep()
  }
}
</script>
