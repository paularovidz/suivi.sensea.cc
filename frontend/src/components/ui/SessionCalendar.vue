<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  // Legacy prop - kept for backward compatibility but no longer used
  data: {
    type: Object,
    default: () => ({})
  },
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
    result.push({ day: null, bookingCount: 0 })
  }

  // Add days of the month - only count bookings, not sessions
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    const bookingCount = props.bookingsData[dateStr] || 0
    result.push({ day, bookingCount, date: dateStr })
  }

  return result
})

const today = computed(() => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
})

// Check if there are any bookings in the current month
const hasBookings = computed(() => {
  return Object.keys(props.bookingsData).length > 0
})

function getBackgroundClass(cell) {
  const count = cell.bookingCount
  if (count === 0) return 'bg-gray-50'
  if (count === 1) return 'bg-amber-100'
  if (count === 2) return 'bg-amber-200'
  if (count <= 3) return 'bg-amber-300'
  if (count <= 5) return 'bg-amber-400'
  return 'bg-amber-500'
}

function getTextClass(cell) {
  const count = cell.bookingCount
  return count > 4 ? 'text-white' : 'text-gray-700'
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
  <div class="bg-white rounded-xl border border-gray-100 p-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <button @click="prevMonth" class="p-1 hover:bg-gray-100 rounded">
        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h3 class="font-semibold text-gray-900">{{ months[month - 1] }} {{ year }}</h3>
      <button @click="nextMonth" class="p-1 hover:bg-gray-100 rounded">
        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
          cell.date === today ? 'ring-2 ring-amber-500' : ''
        ]"
        :title="cell.day && cell.bookingCount > 0 ? `${cell.bookingCount} RDV` : ''"
      >
        <span v-if="cell.day" :class="getTextClass(cell)">
          {{ cell.day }}
        </span>
        <!-- Indicator for bookings count -->
        <div v-if="cell.day && cell.bookingCount > 0" class="text-[10px] font-medium" :class="getTextClass(cell)">
          {{ cell.bookingCount }}
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 mt-4 text-xs text-gray-500">
      <div class="flex items-center gap-1">
        <span>0</span>
        <div class="w-4 h-4 rounded bg-gray-50 border border-gray-200"></div>
        <div class="w-4 h-4 rounded bg-amber-100"></div>
        <div class="w-4 h-4 rounded bg-amber-200"></div>
        <div class="w-4 h-4 rounded bg-amber-300"></div>
        <div class="w-4 h-4 rounded bg-amber-400"></div>
        <div class="w-4 h-4 rounded bg-amber-500"></div>
        <span>5+</span>
      </div>
      <div class="text-gray-400">Nombre de RDV par jour</div>
    </div>
  </div>
</template>
