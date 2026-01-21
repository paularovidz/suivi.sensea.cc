<template>
  <div
    :class="[
      'min-h-screen',
      customBgClass || 'bg-gradient-to-br from-indigo-50 via-white to-purple-50'
    ]"
    :style="customStyles"
  >
    <div class="max-w-2xl mx-auto px-4 py-6">
      <!-- Minimal header for embed -->
      <div v-if="!hideTitle" class="text-center mb-6">
        <h1
          class="text-xl font-semibold"
          :style="{ color: primaryColor || '#4f46e5' }"
        >
          Réservation de séance
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          sensëa Snoezelen
        </p>
      </div>

      <!-- Progress bar -->
      <BookingProgressBar
        v-if="bookingStore.currentStep < 5"
        :current-step="bookingStore.currentStep"
        :style="progressBarStyles"
      />

      <!-- Error message -->
      <div
        v-if="bookingStore.error"
        class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700"
      >
        {{ bookingStore.error }}
      </div>

      <!-- Step content -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <ClientTypeStep v-if="bookingStore.currentStep === 1" />
        <PersonSelectionStep v-else-if="bookingStore.currentStep === 2" />
        <DateTimeStep v-else-if="bookingStore.currentStep === 3" />
        <ContactInfoStep v-else-if="bookingStore.currentStep === 4" />
        <ConfirmationStep v-else-if="bookingStore.currentStep === 5" />
      </div>

      <!-- Navigation buttons -->
      <div class="mt-4 flex justify-between">
        <button
          v-if="bookingStore.currentStep > 1 && bookingStore.currentStep < 5"
          @click="bookingStore.prevStep"
          class="px-3 py-2 text-gray-600 hover:text-gray-900 text-sm font-medium flex items-center"
        >
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            'px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-all duration-200',
            bookingStore.canGoNext && !bookingStore.loading
              ? 'text-white hover:opacity-90'
              : 'bg-gray-200 text-gray-400 cursor-not-allowed'
          ]"
          :style="bookingStore.canGoNext && !bookingStore.loading ? { backgroundColor: primaryColor || '#4f46e5' } : {}"
        >
          <span v-if="bookingStore.loading">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </span>
          {{ bookingStore.currentStep === 4 ? 'Confirmer' : 'Continuer' }}
        </button>
      </div>

      <!-- Powered by -->
      <div v-if="!hidePoweredBy" class="mt-6 text-center">
        <p class="text-xs text-gray-400">
          Propulsé par
          <a
            href="https://sensea.cc"
            target="_blank"
            rel="noopener noreferrer"
            class="text-indigo-500 hover:underline"
          >
            sensëa Snoezelen
          </a>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import BookingProgressBar from '@/components/booking/BookingProgressBar.vue'
import ClientTypeStep from './steps/ClientTypeStep.vue'
import PersonSelectionStep from './steps/PersonSelectionStep.vue'
import DateTimeStep from './steps/DateTimeStep.vue'
import ContactInfoStep from './steps/ContactInfoStep.vue'
import ConfirmationStep from './steps/ConfirmationStep.vue'

const route = useRoute()
const bookingStore = useBookingStore()

// Customization options from query params
const primaryColor = computed(() => route.query.primaryColor || route.query.color)
const hideTitle = computed(() => route.query.hideTitle === 'true')
const hidePoweredBy = computed(() => route.query.hidePoweredBy === 'true')
const bgColor = computed(() => route.query.bgColor)

const customBgClass = computed(() => {
  if (bgColor.value === 'white') return 'bg-white'
  if (bgColor.value === 'transparent') return 'bg-transparent'
  return null
})

const customStyles = computed(() => {
  const styles = {}
  if (bgColor.value && !['white', 'transparent'].includes(bgColor.value)) {
    styles.backgroundColor = bgColor.value
  }
  return styles
})

const progressBarStyles = computed(() => {
  if (primaryColor.value) {
    return { '--progress-color': primaryColor.value }
  }
  return {}
})

onMounted(async () => {
  // Fetch schedule info
  await bookingStore.fetchScheduleInfo()

  // Notify parent window that embed is ready
  notifyParent('booking_ready', { step: bookingStore.currentStep })
})

// Watch for step changes to notify parent
onUnmounted(() => {
  // Clean up if needed
})

async function handleNext() {
  if (bookingStore.currentStep === 4) {
    try {
      await bookingStore.createBooking()
      bookingStore.nextStep()
      notifyParent('booking_completed', {
        email: bookingStore.clientInfo.email,
        date: bookingStore.selectedDate,
        time: bookingStore.selectedTime
      })
    } catch (err) {
      notifyParent('booking_error', { error: bookingStore.error })
    }
  } else {
    bookingStore.nextStep()
    notifyParent('step_changed', { step: bookingStore.currentStep })
  }
}

function notifyParent(event, data) {
  if (window.parent !== window) {
    window.parent.postMessage({
      type: 'sensea_booking',
      event,
      data
    }, '*')
  }
}

// Listen for messages from parent
window.addEventListener('message', (event) => {
  if (event.data?.type === 'sensea_booking_command') {
    switch (event.data.command) {
      case 'reset':
        bookingStore.resetWizard()
        break
      case 'setDurationType':
        bookingStore.setDurationType(event.data.value)
        break
    }
  }
})
</script>

<style scoped>
/* Allow custom primary color for progress bar */
:deep(.bg-indigo-600) {
  background-color: var(--progress-color, #4f46e5);
}

:deep(.ring-indigo-100) {
  --tw-ring-color: var(--progress-color, #4f46e5);
  --tw-ring-opacity: 0.2;
}
</style>
