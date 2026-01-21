<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  // Sessions data (for past days and today)
  data: {
    type: Object,
    default: () => ({})
  },
  // Bookings data (for future days)
  bookingsData: {
    type: Object,
    default: () => ({})
  },
  year: {
    type: Number,
    default: () => new Date().getFullYear()
  },
  month: {
    type: Number,
    default: () => new Date().getMonth() + 1
  }
})

const emit = defineEmits(['change-month'])

const months = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
]

const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

const today = computed(() => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
})

// Check if a date is in the future (after today)
function isFutureDate(dateStr) {
  return dateStr > today.value
}

const calendarDays = computed(() => {
  const year = props.year
  const month = props.month - 1 // JS months are 0-indexed

  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  // Get the day of week for first day (0 = Sunday, we want Monday = 0)
  let startDayOfWeek = firstDay.getDay() - 1
  if (startDayOfWeek < 0) startDayOfWeek = 6

  const daysInMonth = lastDay.getDate()
  const result = []

  // Add empty cells for days before the first of the month
  for (let i = 0; i < startDayOfWeek; i++) {
    result.push({ day: null, count: 0, type: null })
  }

  // Add days of the month
  // - Past days & today: show sessions count
  // - Future days: show bookings count
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    const isFuture = isFutureDate(dateStr)

    let count, type
    if (isFuture) {
      // Future: show bookings (RDV à venir)
      count = props.bookingsData[dateStr] || 0
      type = 'booking'
    } else {
      // Past or today: show sessions (séances effectuées)
      count = props.data[dateStr] || 0
      type = 'session'
    }

    result.push({ day, count, date: dateStr, type })
  }

  return result
})

function getBackgroundClass(cell) {
  const count = cell.count
  if (count === 0) return 'bg-gray-700/50'

  // Different colors for sessions (green) and bookings (amber)
  if (cell.type === 'session') {
    if (count === 1) return 'bg-green-900/60'
    if (count === 2) return 'bg-green-800/70'
    if (count <= 3) return 'bg-green-700/80'
    if (count <= 5) return 'bg-green-600'
    return 'bg-green-500'
  } else {
    if (count === 1) return 'bg-amber-900/60'
    if (count === 2) return 'bg-amber-800/70'
    if (count <= 3) return 'bg-amber-700/80'
    if (count <= 5) return 'bg-amber-600'
    return 'bg-amber-500'
  }
}

function getTextClass(cell) {
  const count = cell.count
  return count > 2 ? 'text-white' : 'text-gray-300'
}

function prevMonth() {
  let newMonth = props.month - 1
  let newYear = props.year
  if (newMonth < 1) {
    newMonth = 12
    newYear--
  }
  emit('change-month', { year: newYear, month: newMonth })
}

function nextMonth() {
  let newMonth = props.month + 1
  let newYear = props.year
  if (newMonth > 12) {
    newMonth = 1
    newYear++
  }
  emit('change-month', { year: newYear, month: newMonth })
}
</script>

<template>
  <div class="p-2">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <button @click="prevMonth" class="p-1 hover:bg-gray-700 rounded">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h3 class="font-semibold text-white">{{ months[month - 1] }} {{ year }}</h3>
      <button @click="nextMonth" class="p-1 hover:bg-gray-700 rounded">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <!-- Days header -->
    <div class="grid grid-cols-7 gap-1 mb-1">
      <div v-for="day in days" :key="day" class="text-center text-xs font-medium text-gray-500 py-1">
        {{ day }}
      </div>
    </div>

    <!-- Calendar grid -->
    <div class="grid grid-cols-7 gap-1">
      <div
        v-for="(cell, index) in calendarDays"
        :key="index"
        :class="[
          'aspect-square flex flex-col items-center justify-center text-sm rounded-lg transition-colors relative',
          cell.day ? getBackgroundClass(cell) : '',
          cell.date === today ? 'ring-2 ring-primary-500' : ''
        ]"
        :title="cell.day && cell.count > 0 ? `${cell.count} ${cell.type === 'session' ? 'séance(s)' : 'RDV'}` : ''"
      >
        <span v-if="cell.day" :class="getTextClass(cell)">
          {{ cell.day }}
        </span>
        <!-- Indicator for count -->
        <div v-if="cell.day && cell.count > 0" class="text-[10px] font-medium" :class="getTextClass(cell)">
          {{ cell.count }}
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-col items-center gap-2 mt-4 text-xs text-gray-400">
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-1">
          <div class="w-4 h-4 rounded bg-green-800 border border-green-700"></div>
          <span>Séances (passé)</span>
        </div>
        <div class="flex items-center gap-1">
          <div class="w-4 h-4 rounded bg-amber-800 border border-amber-700"></div>
          <span>RDV (futur)</span>
        </div>
      </div>
    </div>
  </div>
</template>
