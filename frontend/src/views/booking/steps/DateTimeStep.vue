<template>
  <div class="p-6">
    <h2 class="text-xl font-semibold text-white mb-2">Choisissez votre créneau</h2>
    <p class="text-gray-400 mb-4">
      Sélectionnez une date puis un horaire disponible pour votre {{ durationLabel }}.
    </p>

    <!-- Session type and price info -->
    <div class="mb-6 p-4 bg-gray-700/30 border border-gray-600/50 rounded-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <svg class="w-5 h-5 text-indigo-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="text-white font-medium">{{ sessionTypeLabel }}</p>
            <p class="text-sm text-gray-400">{{ sessionTypeDescription }}</p>
          </div>
        </div>
        <div class="text-right">
          <!-- Prix avec promo -->
          <template v-if="bookingStore.hasPromoApplied">
            <p class="text-lg text-gray-400 line-through">{{ formatPrice(bookingStore.originalPrice) }} &euro;</p>
            <p class="text-2xl font-bold text-green-400">{{ formatPrice(bookingStore.currentPrice) }} &euro;</p>
          </template>
          <template v-else>
            <p class="text-2xl font-bold text-white">{{ formatPrice(bookingStore.currentPrice) }} &euro;</p>
          </template>
          <p class="text-xs text-gray-400">par séance</p>
        </div>
      </div>

      <!-- Promo code applied indicator -->
      <div v-if="bookingStore.hasPromoApplied" class="mt-3 pt-3 border-t border-gray-600/50">
        <div class="flex items-center justify-between">
          <div class="flex items-center text-green-400">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v14l3.5-2 3.5 2 3.5-2 3.5 2V4a2 2 0 00-2-2H5zm2.5 3a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm6.207.293a1 1 0 00-1.414 0l-6 6a1 1 0 101.414 1.414l6-6a1 1 0 000-1.414zM12.5 10a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" clip-rule="evenodd" />
            </svg>
            <span class="text-sm font-medium">
              {{ bookingStore.appliedPromo.code || bookingStore.appliedPromo.name }}
              <span class="text-green-300 ml-1">({{ bookingStore.appliedPromo.discount_label }})</span>
            </span>
          </div>
          <button
            @click="bookingStore.clearPromoCode()"
            class="text-gray-400 hover:text-red-400 transition-colors text-sm"
          >
            Retirer
          </button>
        </div>
        <p class="text-xs text-gray-400 mt-1">
          Remise : {{ bookingStore.appliedPromo.discount_label }}
          <span v-if="bookingStore.appliedPromo.discount_type !== 'percentage'">
            ({{ formatPrice(bookingStore.promoPricing.discount_amount) }} &euro;)
          </span>
        </p>
      </div>

      <!-- Promo code input (only if manual codes exist and no promo applied) -->
      <div v-else-if="bookingStore.hasManualPromoCodes" class="mt-3 pt-3 border-t border-gray-600/50">
        <div v-if="!showPromoInput" class="text-center">
          <button
            @click="showPromoInput = true"
            class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors"
          >
            Vous avez un code promotionnel ?
          </button>
        </div>
        <div v-else>
          <label class="block text-sm text-gray-400 mb-2">Code promotionnel</label>
          <div class="flex gap-2">
            <input
              v-model="promoCodeValue"
              type="text"
              placeholder="Entrez votre code"
              class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm uppercase placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              :disabled="bookingStore.promoLoading"
              @keyup.enter="applyPromoCode"
            />
            <button
              @click="applyPromoCode"
              :disabled="bookingStore.promoLoading || !promoCodeValue.trim()"
              class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="bookingStore.promoLoading">...</span>
              <span v-else>Appliquer</span>
            </button>
          </div>
          <p v-if="bookingStore.promoError" class="mt-2 text-sm text-red-400">
            {{ bookingStore.promoError }}
          </p>
        </div>
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
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useBookingStore } from '@/stores/booking'
import BookingCalendar from '@/components/booking/BookingCalendar.vue'
import TimeSlotPicker from '@/components/booking/TimeSlotPicker.vue'

const bookingStore = useBookingStore()

function formatPrice(value) {
  return Number(value).toFixed(2).replace('.', ',')
}

const loadingDates = ref(false)
const loadingSlots = ref(false)
const showPromoInput = ref(false)
const promoCodeValue = ref('')

// Réinitialiser l'input quand le code promo est retiré
watch(() => bookingStore.hasPromoApplied, (hasPromo, wasApplied) => {
  if (!hasPromo && wasApplied) {
    // Le code promo a été retiré, réinitialiser et montrer l'input
    promoCodeValue.value = ''
    showPromoInput.value = true
  }
})

const durationLabel = computed(() => {
  return bookingStore.durationType === 'discovery'
    ? 'séance découverte (1h15)'
    : 'séance classique (45 min)'
})

const sessionTypeLabel = computed(() => {
  return bookingStore.durationType === 'discovery'
    ? 'Séance découverte'
    : 'Séance classique'
})

const sessionTypeDescription = computed(() => {
  return bookingStore.durationType === 'discovery'
    ? 'Durée : 1h15 - Première séance pour découvrir l\'approche Snoezelen'
    : 'Durée : 45 min - Séance de suivi régulier'
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
  // Déterminer automatiquement le type de séance :
  // - Nouvelle personne (pas d'ID) = découverte
  // - Personne existante (avec ID) = classique
  const isNewPerson = !bookingStore.selectedPersonId
  const newType = isNewPerson ? 'discovery' : 'regular'

  if (bookingStore.durationType !== newType) {
    // Utiliser setDurationType pour reset correctement toutes les données
    bookingStore.setDurationType(newType)
  }

  await fetchAvailableDates()

  // Si une date est déjà sélectionnée (retour depuis l'étape 4), recharger les créneaux
  if (bookingStore.selectedDate) {
    loadingSlots.value = true
    try {
      await bookingStore.fetchAvailableSlots(bookingStore.selectedDate)
    } finally {
      loadingSlots.value = false
    }
  }

  // Check if manual promo codes exist
  await bookingStore.checkHasManualPromoCodes()

  // Check for automatic promo (only if no promo already applied)
  if (!bookingStore.hasPromoApplied) {
    await bookingStore.checkAutomaticPromo()
  }
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
  // Passer automatiquement à l'étape suivante après sélection
  nextTick(() => {
    bookingStore.nextStep()
  })
}

async function applyPromoCode() {
  if (!promoCodeValue.value.trim()) return

  const success = await bookingStore.validatePromoCode(promoCodeValue.value)
  if (success) {
    showPromoInput.value = false
  }
}
</script>
