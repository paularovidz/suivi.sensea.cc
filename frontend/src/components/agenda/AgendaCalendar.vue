<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  bookings: {
    type: Array,
    default: () => []
  },
  selectedDate: {
    type: Date,
    default: () => new Date()
  }
})

const emit = defineEmits(['select-date', 'select-booking', 'week-change'])

// État local
const currentWeekStart = ref(getWeekStart(props.selectedDate))

// Configuration horaires (9h - 18h)
const startHour = 8
const endHour = 19
const hours = computed(() => {
  const result = []
  for (let h = startHour; h <= endHour; h++) {
    result.push(h)
  }
  return result
})

// Jours de la semaine
const weekDays = computed(() => {
  const days = []
  const start = new Date(currentWeekStart.value)
  for (let i = 0; i < 7; i++) {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    days.push({
      date,
      dayName: date.toLocaleDateString('fr-FR', { weekday: 'short' }),
      dayNumber: date.getDate(),
      monthName: date.toLocaleDateString('fr-FR', { month: 'short' }),
      isToday: isSameDay(date, new Date()),
      isWeekend: date.getDay() === 0 || date.getDay() === 6,
      dateStr: formatDateStr(date)
    })
  }
  return days
})

// Titre de la semaine
const weekTitle = computed(() => {
  const start = weekDays.value[0]
  const end = weekDays.value[6]

  if (start.date.getMonth() === end.date.getMonth()) {
    return `${start.dayNumber} - ${end.dayNumber} ${end.date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })}`
  }

  return `${start.dayNumber} ${start.monthName} - ${end.dayNumber} ${end.monthName} ${end.date.getFullYear()}`
})

// Grouper les bookings par jour
const bookingsByDay = computed(() => {
  const grouped = {}
  weekDays.value.forEach(day => {
    grouped[day.dateStr] = []
  })

  props.bookings.forEach(booking => {
    const bookingDate = new Date(booking.session_date)
    const dateStr = formatDateStr(bookingDate)
    if (grouped[dateStr]) {
      grouped[dateStr].push(booking)
    }
  })

  return grouped
})

// Fonctions utilitaires
function getWeekStart(date) {
  const d = new Date(date)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1) // Lundi comme premier jour
  d.setDate(diff)
  d.setHours(0, 0, 0, 0)
  return d
}

function isSameDay(d1, d2) {
  return d1.getFullYear() === d2.getFullYear() &&
    d1.getMonth() === d2.getMonth() &&
    d1.getDate() === d2.getDate()
}

function formatDateStr(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function formatTime(dateString) {
  return new Date(dateString).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Position et hauteur des bookings
function getBookingStyle(booking) {
  const date = new Date(booking.session_date)
  const hour = date.getHours()
  const minutes = date.getMinutes()

  // Position depuis le haut (basée sur l'heure)
  const topOffset = (hour - startHour) * 60 + minutes
  const top = (topOffset / 60) * 48 // 48px par heure

  // Hauteur basée sur la durée totale (séance + pause)
  const displayDuration = booking.duration_display_minutes || 45
  const blockedDuration = booking.duration_blocked_minutes || (displayDuration + 20)
  const totalHeight = (blockedDuration / 60) * 48

  return {
    top: `${top}px`,
    height: `${Math.max(totalHeight, 24)}px`
  }
}

// Calcul des hauteurs séparées pour séance et pause
function getSessionHeight(booking) {
  const displayDuration = booking.duration_display_minutes || 45
  return (displayDuration / 60) * 48
}

function getPauseHeight(booking) {
  const displayDuration = booking.duration_display_minutes || 45
  const blockedDuration = booking.duration_blocked_minutes || (displayDuration + 20)
  const pauseDuration = blockedDuration - displayDuration
  return (pauseDuration / 60) * 48
}

function getBookingClasses(booking) {
  const isAssociation = booking.client_type === 'association'

  // Couleurs pour particuliers (teintes vertes/jaunes)
  const personalColors = {
    pending: 'bg-yellow-100 border-yellow-300 text-yellow-800 hover:bg-yellow-200',
    confirmed: 'bg-green-100 border-green-300 text-green-800 hover:bg-green-200',
    completed: 'bg-blue-100 border-blue-300 text-blue-800 hover:bg-blue-200',
    cancelled: 'bg-red-100 border-red-300 text-red-800 hover:bg-red-200',
    no_show: 'bg-gray-100 border-gray-300 text-gray-800 hover:bg-gray-200'
  }

  // Couleurs pour associations/pro (teintes violettes)
  const associationColors = {
    pending: 'bg-amber-100 border-amber-400 text-amber-800 hover:bg-amber-200',
    confirmed: 'bg-violet-100 border-violet-400 text-violet-800 hover:bg-violet-200',
    completed: 'bg-indigo-100 border-indigo-400 text-indigo-800 hover:bg-indigo-200',
    cancelled: 'bg-red-100 border-red-300 text-red-800 hover:bg-red-200',
    no_show: 'bg-gray-100 border-gray-300 text-gray-800 hover:bg-gray-200'
  }

  const colors = isAssociation ? associationColors : personalColors
  return colors[booking.status] || 'bg-gray-100 border-gray-300 text-gray-800'
}

function isAssociation(booking) {
  return booking.client_type === 'association'
}

// Navigation
function previousWeek() {
  const newStart = new Date(currentWeekStart.value)
  newStart.setDate(newStart.getDate() - 7)
  currentWeekStart.value = newStart
  emit('week-change', { start: newStart, end: getWeekEnd(newStart) })
}

function nextWeek() {
  const newStart = new Date(currentWeekStart.value)
  newStart.setDate(newStart.getDate() + 7)
  currentWeekStart.value = newStart
  emit('week-change', { start: newStart, end: getWeekEnd(newStart) })
}

function goToToday() {
  currentWeekStart.value = getWeekStart(new Date())
  emit('week-change', { start: currentWeekStart.value, end: getWeekEnd(currentWeekStart.value) })
}

function getWeekEnd(start) {
  const end = new Date(start)
  end.setDate(end.getDate() + 6)
  end.setHours(23, 59, 59, 999)
  return end
}

function selectDay(day) {
  emit('select-date', day.date)
}

function selectBooking(booking) {
  emit('select-booking', booking)
}

// Watcher pour synchroniser avec selectedDate externe
watch(() => props.selectedDate, (newDate) => {
  const newWeekStart = getWeekStart(newDate)
  if (newWeekStart.getTime() !== currentWeekStart.value.getTime()) {
    currentWeekStart.value = newWeekStart
  }
})

// Émettre la semaine initiale
emit('week-change', { start: currentWeekStart.value, end: getWeekEnd(currentWeekStart.value) })
</script>

<template>
  <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <!-- Header navigation -->
    <div class="flex items-center justify-between px-4 py-3 bg-gray-800/50 border-b border-gray-700">
      <div class="flex items-center space-x-2">
        <button
          @click="goToToday"
          class="px-3 py-1.5 text-sm font-medium text-gray-300 bg-gray-700 border border-gray-600 rounded-lg hover:bg-gray-600 transition-colors"
        >
          Aujourd'hui
        </button>
        <div class="flex items-center">
          <button
            @click="previousWeek"
            class="p-1.5 hover:bg-gray-700 rounded-lg transition-colors"
          >
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <button
            @click="nextWeek"
            class="p-1.5 hover:bg-gray-700 rounded-lg transition-colors"
          >
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>

      <h2 class="text-lg font-semibold text-white">{{ weekTitle }}</h2>

      <div class="w-32"></div> <!-- Spacer pour centrer le titre -->
    </div>

    <!-- Calendrier grille -->
    <div class="flex overflow-x-auto">
      <!-- Colonne des heures -->
      <div class="flex-shrink-0 w-14">
        <div class="h-12 border-b border-gray-700"></div> <!-- Header vide -->
        <div class="relative">
          <div v-for="hour in hours" :key="hour" class="h-12 flex items-start justify-end pr-2 border-b border-gray-700/50">
            <span class="text-[11px] text-gray-500 leading-none -translate-y-1/2">
              {{ String(hour).padStart(2, '0') }}:00
            </span>
          </div>
        </div>
      </div>

      <!-- Colonnes des jours -->
      <div class="flex-1 flex min-w-0 border-l border-gray-700">
        <div
          v-for="day in weekDays"
          :key="day.dateStr"
          class="flex-1 min-w-[120px] border-r border-gray-700 last:border-r-0"
          :class="{ 'bg-gray-900/30': day.isWeekend }"
        >
          <!-- Header du jour -->
          <div
            @click="selectDay(day)"
            class="h-12 px-2 py-1 border-b border-gray-700 text-center cursor-pointer hover:bg-gray-700/50 transition-colors"
            :class="{ 'bg-primary-900/30': day.isToday }"
          >
            <div class="text-xs font-medium text-gray-500 uppercase">{{ day.dayName }}</div>
            <div
              class="text-lg font-semibold"
              :class="day.isToday ? 'text-primary-600 bg-primary-600 text-white rounded-full w-8 h-8 flex items-center justify-center mx-auto -mt-0.5' : 'text-gray-200'"
            >
              {{ day.dayNumber }}
            </div>
          </div>

          <!-- Grille horaire du jour -->
          <div class="relative">
            <!-- Lignes horaires -->
            <div v-for="hour in hours" :key="hour" class="h-12 border-b border-gray-700/50"></div>

            <!-- Bookings du jour -->
            <div
              v-for="booking in bookingsByDay[day.dateStr]"
              :key="booking.id"
              @click="selectBooking(booking)"
              :style="getBookingStyle(booking)"
              class="absolute left-0.5 right-0.5 flex flex-col cursor-pointer overflow-hidden"
              :title="`${booking.person_first_name} ${booking.person_last_name} - ${formatTime(booking.session_date)}`"
            >
              <!-- Partie séance -->
              <div
                :style="{ height: getSessionHeight(booking) + 'px' }"
                :class="[
                  'px-1 py-0.5 rounded-t border border-b-0 text-xs transition-colors flex-shrink-0',
                  getBookingClasses(booking)
                ]"
              >
                <div class="font-medium truncate flex items-center gap-0.5">
                  <!-- Icône type client -->
                  <svg v-if="isAssociation(booking)" class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" title="Association">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                  </svg>
                  <svg v-else class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" title="Particulier">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                  </svg>
                  <span class="truncate">{{ formatTime(booking.session_date) }}</span>
                </div>
                <div class="truncate text-[10px] opacity-80 pl-3.5">
                  {{ booking.person_first_name }} {{ booking.person_last_name }}
                </div>
              </div>
              <!-- Partie pause inter-séance -->
              <div
                :style="{ height: getPauseHeight(booking) + 'px' }"
                class="px-1 rounded-b border border-t-0 border-dashed bg-gray-700/50 border-gray-500 flex-shrink-0 flex items-center justify-center"
              >
                <span class="text-[9px] text-gray-400 truncate">pause</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Légende -->
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 py-3 bg-gray-800/50 border-t border-gray-700 text-xs">
      <!-- Type de client -->
      <div class="flex items-center">
        <svg class="w-3 h-3 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
        </svg>
        <div class="w-3 h-3 rounded bg-green-100 border border-green-300 mr-1"></div>
        <span class="text-gray-400">Particulier</span>
      </div>
      <div class="flex items-center">
        <svg class="w-3 h-3 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
        </svg>
        <div class="w-3 h-3 rounded bg-violet-100 border border-violet-400 mr-1"></div>
        <span class="text-gray-400">Association</span>
      </div>
      <div class="border-l border-gray-600 pl-4 ml-2 flex items-center gap-4">
        <!-- Statuts -->
        <div class="flex items-center">
          <div class="w-3 h-3 rounded bg-yellow-100 border border-yellow-300 mr-1.5"></div>
          <span class="text-gray-400">En attente</span>
        </div>
        <div class="flex items-center">
          <div class="w-3 h-3 rounded bg-green-100 border border-green-300 mr-1.5"></div>
          <span class="text-gray-400">Confirmé</span>
        </div>
        <div class="flex items-center">
          <div class="w-3 h-3 rounded bg-blue-100 border border-blue-300 mr-1.5"></div>
          <span class="text-gray-400">Effectué</span>
        </div>
        <div class="flex items-center">
          <div class="w-3 h-3 rounded bg-red-100 border border-red-300 mr-1.5"></div>
          <span class="text-gray-400">Annulé</span>
        </div>
      </div>
      <div class="flex items-center border-l border-gray-600 pl-4 ml-2">
        <div class="w-3 h-3 rounded bg-gray-700 border border-dashed border-gray-500 mr-1.5"></div>
        <span class="text-gray-400">Pause</span>
      </div>
    </div>
  </div>
</template>
