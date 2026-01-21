<template>
  <div class="bg-gray-700/30 rounded-xl border border-gray-600/50 overflow-hidden">
    <!-- Calendar header -->
    <div class="px-4 py-3 bg-gray-700/50 border-b border-gray-600/50 flex items-center justify-between">
      <button
        @click="previousMonth"
        :disabled="!canGoPrevious"
        :class="[
          'p-2 rounded-lg transition-colors',
          canGoPrevious
            ? 'hover:bg-gray-600 text-gray-300'
            : 'text-gray-600 cursor-not-allowed'
        ]"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <h3 class="text-lg font-semibold text-white capitalize">
        {{ monthName }} {{ year }}
      </h3>

      <button
        @click="nextMonth"
        :disabled="!canGoNext"
        :class="[
          'p-2 rounded-lg transition-colors',
          canGoNext
            ? 'hover:bg-gray-600 text-gray-300'
            : 'text-gray-600 cursor-not-allowed'
        ]"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <!-- Loading overlay -->
    <div v-if="loading" class="p-8 flex items-center justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-400"></div>
    </div>

    <!-- Calendar grid -->
    <div v-else class="p-4">
      <!-- Day names -->
      <div class="grid grid-cols-7 mb-2">
        <div
          v-for="day in dayNames"
          :key="day"
          class="text-center text-xs font-medium text-gray-400 py-2"
        >
          {{ day }}
        </div>
      </div>

      <!-- Calendar days -->
      <div class="grid grid-cols-7 gap-1">
        <!-- Empty cells for days before month starts -->
        <div
          v-for="n in firstDayOfMonth"
          :key="'empty-' + n"
          class="aspect-square"
        />

        <!-- Days of the month -->
        <button
          v-for="day in daysInMonth"
          :key="day"
          @click="selectDate(day)"
          :disabled="!isDateAvailable(day)"
          :class="[
            'aspect-square rounded-lg text-sm font-medium transition-all duration-200',
            getDateClasses(day)
          ]"
        >
          {{ day }}
        </button>
      </div>
    </div>

    <!-- Legend -->
    <div class="px-4 py-3 bg-gray-700/50 border-t border-gray-600/50">
      <div class="flex items-center justify-center space-x-4 text-xs">
        <div class="flex items-center">
          <div class="w-3 h-3 rounded bg-indigo-600 mr-1.5"></div>
          <span class="text-gray-400">Sélectionné</span>
        </div>
        <div class="flex items-center">
          <div class="w-3 h-3 rounded bg-green-500/30 border border-green-500/50 mr-1.5"></div>
          <span class="text-gray-400">Disponible</span>
        </div>
        <div class="flex items-center">
          <div class="w-3 h-3 rounded bg-gray-700 mr-1.5"></div>
          <span class="text-gray-400">Indisponible</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  year: {
    type: Number,
    required: true
  },
  month: {
    type: Number,
    required: true
  },
  selectedDate: {
    type: String,
    default: null
  },
  availableDates: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:selectedDate', 'monthChange'])

const dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

const monthNames = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
]

const monthName = computed(() => monthNames[props.month - 1])

const daysInMonth = computed(() => {
  return new Date(props.year, props.month, 0).getDate()
})

const firstDayOfMonth = computed(() => {
  // Get day of week (0 = Sunday, 6 = Saturday)
  const day = new Date(props.year, props.month - 1, 1).getDay()
  // Convert to Monday-first (0 = Monday, 6 = Sunday)
  return day === 0 ? 6 : day - 1
})

const today = computed(() => {
  const now = new Date()
  // Si après 17h, considérer qu'on est le lendemain
  if (now.getHours() >= 17) {
    now.setDate(now.getDate() + 1)
  }
  return {
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate()
  }
})

const canGoPrevious = computed(() => {
  return !(props.year === today.value.year && props.month <= today.value.month)
})

const canGoNext = computed(() => {
  const maxDate = new Date()
  maxDate.setMonth(maxDate.getMonth() + 3)
  const maxYear = maxDate.getFullYear()
  const maxMonth = maxDate.getMonth() + 1
  return !(props.year === maxYear && props.month >= maxMonth)
})

function formatDate(day) {
  const m = String(props.month).padStart(2, '0')
  const d = String(day).padStart(2, '0')
  return `${props.year}-${m}-${d}`
}

function isDateAvailable(day) {
  const dateStr = formatDate(day)

  // Check if in available dates
  if (!props.availableDates.includes(dateStr)) {
    return false
  }

  // Check if not in the past
  if (props.year < today.value.year) return false
  if (props.year === today.value.year && props.month < today.value.month) return false
  if (props.year === today.value.year && props.month === today.value.month && day < today.value.day) return false

  return true
}

function isDateSelected(day) {
  return props.selectedDate === formatDate(day)
}

function isToday(day) {
  return (
    props.year === today.value.year &&
    props.month === today.value.month &&
    day === today.value.day
  )
}

function getDateClasses(day) {
  const available = isDateAvailable(day)
  const selected = isDateSelected(day)
  const todayDate = isToday(day)

  if (selected) {
    return 'bg-indigo-600 text-white hover:bg-indigo-500'
  }

  if (available) {
    return 'bg-green-500/20 text-green-300 border border-green-500/40 hover:bg-green-500/30 hover:border-green-400'
  }

  if (todayDate) {
    return 'bg-gray-700/50 text-gray-500 cursor-not-allowed ring-1 ring-gray-500'
  }

  return 'bg-gray-700/30 text-gray-500 cursor-not-allowed'
}

function selectDate(day) {
  if (isDateAvailable(day)) {
    emit('update:selectedDate', formatDate(day))
  }
}

function previousMonth() {
  if (!canGoPrevious.value) return

  let newMonth = props.month - 1
  let newYear = props.year

  if (newMonth < 1) {
    newMonth = 12
    newYear--
  }

  emit('monthChange', { year: newYear, month: newMonth })
}

function nextMonth() {
  if (!canGoNext.value) return

  let newMonth = props.month + 1
  let newYear = props.year

  if (newMonth > 12) {
    newMonth = 1
    newYear++
  }

  emit('monthChange', { year: newYear, month: newMonth })
}
</script>
