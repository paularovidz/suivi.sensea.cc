<template>
  <div class="p-6">
    <h2 class="text-xl font-semibold text-white mb-2">Choisissez votre créneau</h2>
    <p class="text-gray-400 mb-6">
      Sélectionnez une date puis un horaire disponible pour votre {{ durationLabel }}.
    </p>

    <!-- Duration type selector (for returning clients) -->
    <div v-if="!bookingStore.isNewClient" class="mb-6">
      <label class="block text-sm font-medium text-gray-300 mb-2">Type de séance</label>
      <div class="flex space-x-3">
        <button
          @click="setDurationType('regular')"
          :class="[
            'flex-1 py-3 px-4 rounded-lg border-2 text-sm font-medium transition-all',
            bookingStore.durationType === 'regular'
              ? 'border-indigo-500 bg-indigo-500/20 text-indigo-300'
              : 'border-gray-600 text-gray-400 hover:border-gray-500'
          ]"
        >
          Classique (45 min)
        </button>
        <button
          @click="setDurationType('discovery')"
          :class="[
            'flex-1 py-3 px-4 rounded-lg border-2 text-sm font-medium transition-all',
            bookingStore.durationType === 'discovery'
              ? 'border-indigo-500 bg-indigo-500/20 text-indigo-300'
              : 'border-gray-600 text-gray-400 hover:border-gray-500'
          ]"
        >
          Découverte (1h15)
        </button>
      </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
      <!-- Calendar -->
      <div>
        <BookingCalendar
          :year="bookingStore.currentYear"
          :month="bookingStore.currentMonth"
          :selected-date="bookingStore.selectedDate"
          :available-dates="bookingStore.availableDates"
          :loading="loadingDates"
          @update:selected-date="selectDate"
          @month-change="handleMonthChange"
        />
      </div>

      <!-- Time slots -->
      <div>
        <template v-if="bookingStore.selectedDate">
          <TimeSlotPicker
            :date="bookingStore.selectedDate"
            :selected-time="bookingStore.selectedTime"
            :slots="bookingStore.availableSlots"
            :duration-minutes="bookingStore.durationInfo.display"
            :loading="loadingSlots"
            @update:selected-time="selectTime"
          />
        </template>
        <template v-else>
          <div class="bg-gray-700/30 rounded-xl border border-gray-600/50 p-8 text-center h-full flex items-center justify-center">
            <div>
              <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <p class="mt-2 text-sm text-gray-400">
                Sélectionnez d'abord une date sur le calendrier
              </p>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Selected slot summary -->
    <div v-if="bookingStore.selectedDate && bookingStore.selectedTime" class="mt-6 p-4 bg-green-900/30 border border-green-500/50 rounded-lg">
      <div class="flex items-center">
        <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span class="text-green-300 font-medium">
          {{ formattedSelection }}
        </span>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useBookingStore } from '@/stores/booking'
import BookingCalendar from '@/components/booking/BookingCalendar.vue'
import TimeSlotPicker from '@/components/booking/TimeSlotPicker.vue'

const bookingStore = useBookingStore()

const loadingDates = ref(false)
const loadingSlots = ref(false)

const durationLabel = computed(() => {
  return bookingStore.durationType === 'discovery'
    ? 'séance découverte (1h15)'
    : 'séance classique (45 min)'
})

const formattedSelection = computed(() => {
  if (!bookingStore.selectedDate || !bookingStore.selectedTime) return ''

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

onMounted(async () => {
  await fetchAvailableDates()
})

// Refetch when duration type changes
watch(() => bookingStore.durationType, async () => {
  await fetchAvailableDates()
})

async function fetchAvailableDates() {
  loadingDates.value = true
  try {
    await bookingStore.fetchAvailableDates()
  } finally {
    loadingDates.value = false
  }
}

async function handleMonthChange({ year, month }) {
  bookingStore.currentYear = year
  bookingStore.currentMonth = month
  await fetchAvailableDates()
}

async function selectDate(date) {
  bookingStore.selectedDate = date
  bookingStore.selectedTime = null

  loadingSlots.value = true
  try {
    await bookingStore.fetchAvailableSlots(date)
  } finally {
    loadingSlots.value = false
  }
}

function selectTime(time) {
  bookingStore.selectedTime = time
}

function setDurationType(type) {
  if (type !== bookingStore.durationType) {
    bookingStore.setDurationType(type)
  }
}
</script>
