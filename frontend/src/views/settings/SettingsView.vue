<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-900/50 border border-red-700 rounded-lg p-4 text-red-300">
      {{ error }}
    </div>

    <!-- Settings Content -->
    <template v-else>
      <!-- SMS Credits Card -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center">
          <svg class="w-5 h-5 text-primary-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
          </svg>
          <h2 class="text-lg font-medium text-white">Crédits SMS</h2>
        </div>
        <div class="px-6 py-4">
          <div v-if="smsCredits.loading" class="text-gray-400">
            Chargement des crédits...
          </div>
          <div v-else-if="!smsCredits.configured" class="text-amber-400 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            Service SMS non configuré
          </div>
          <div v-else-if="smsCredits.error" class="text-red-400">
            {{ smsCredits.error }}
          </div>
          <div v-else class="flex items-center space-x-6">
            <div>
              <span class="text-3xl font-bold text-primary-400">{{ smsCredits.credits_left }}</span>
              <span class="text-gray-400 ml-1">crédits restants</span>
            </div>
            <div class="text-sm text-gray-400">
              Service: {{ smsCredits.service_name || 'OVH SMS' }}
            </div>
            <button
              @click="loadSmsCredits"
              class="text-primary-400 hover:text-primary-300 text-sm"
            >
              Actualiser
            </button>
          </div>
        </div>
      </div>

      <!-- Settings Categories -->
      <div
        v-for="category in settingsGroups"
        :key="category.category"
        class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden"
      >
        <div class="px-6 py-4 border-b border-gray-700">
          <h2 class="text-lg font-medium text-white">{{ category.label }}</h2>
        </div>

        <div class="divide-y divide-gray-700">
          <div
            v-for="setting in category.settings"
            :key="setting.key"
            class="px-6 py-4 flex items-center justify-between"
          >
            <div class="flex-1">
              <label :for="setting.key" class="text-sm font-medium text-white">
                {{ setting.label }}
              </label>
              <p v-if="setting.description" class="text-sm text-gray-400 mt-0.5">
                {{ setting.description }}
              </p>
            </div>

            <div class="ml-4">
              <!-- Boolean toggle -->
              <template v-if="setting.type === 'boolean'">
                <button
                  type="button"
                  @click="toggleBoolean(setting.key)"
                  :class="[
                    formData[setting.key] ? 'bg-primary-600' : 'bg-gray-600',
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-gray-800'
                  ]"
                >
                  <span
                    :class="[
                      formData[setting.key] ? 'translate-x-5' : 'translate-x-0',
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                    ]"
                  />
                </button>
              </template>

              <!-- Integer input -->
              <template v-else-if="setting.type === 'integer'">
                <input
                  :id="setting.key"
                  type="number"
                  v-model.number="formData[setting.key]"
                  class="w-24 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </template>

              <!-- Time input (for HH:MM fields) -->
              <template v-else-if="isTimeField(setting.key)">
                <input
                  :id="setting.key"
                  type="time"
                  v-model="formData[setting.key]"
                  class="w-32 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </template>

              <!-- JSON business hours (special case) -->
              <template v-else-if="setting.key === 'business_hours'">
                <button
                  type="button"
                  @click="showBusinessHoursModal = true"
                  class="px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg transition-colors"
                >
                  Modifier les horaires
                </button>
              </template>

              <!-- String input -->
              <template v-else>
                <input
                  :id="setting.key"
                  type="text"
                  v-model="formData[setting.key]"
                  :class="[
                    'px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500',
                    setting.key.includes('secret') || setting.key.includes('key') ? 'w-64 font-mono text-xs' : 'w-48'
                  ]"
                  :placeholder="setting.key.includes('secret') ? '••••••••' : ''"
                />
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="flex justify-end">
        <button
          @click="saveSettings"
          :disabled="saving || !hasChanges"
          :class="[
            'px-6 py-2 rounded-lg font-medium transition-all duration-200',
            hasChanges && !saving
              ? 'bg-primary-600 text-white hover:bg-primary-700'
              : 'bg-gray-700 text-gray-500 cursor-not-allowed'
          ]"
        >
          <span v-if="saving" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Enregistrement...
          </span>
          <span v-else>Enregistrer les modifications</span>
        </button>
      </div>

      <!-- Success toast -->
      <div
        v-if="showSuccess"
        class="fixed bottom-4 right-4 bg-green-900/50 border border-green-700 rounded-lg px-4 py-3 shadow-lg flex items-center"
      >
        <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-green-300">Paramètres enregistrés</span>
      </div>
    </template>

    <!-- Business Hours Modal -->
    <div
      v-if="showBusinessHoursModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-modal="true"
    >
      <div class="flex min-h-screen items-center justify-center p-4">
        <!-- Backdrop -->
        <div
          class="fixed inset-0 bg-black/70 transition-opacity"
          @click="showBusinessHoursModal = false"
        ></div>

        <!-- Modal -->
        <div class="relative bg-gray-800 rounded-xl shadow-xl max-w-lg w-full p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Horaires d'ouverture</h3>

          <div class="space-y-3">
            <div
              v-for="(dayName, dayIndex) in dayNames"
              :key="dayIndex"
              class="flex items-center justify-between py-2 border-b border-gray-700 last:border-0"
            >
              <div class="flex items-center space-x-3">
                <input
                  type="checkbox"
                  :id="'day-' + dayIndex"
                  :checked="businessHours[dayIndex] !== null"
                  @change="toggleDay(dayIndex)"
                  class="h-4 w-4 text-primary-600 border-gray-600 bg-gray-700 rounded focus:ring-primary-500"
                />
                <label :for="'day-' + dayIndex" class="text-sm font-medium text-white w-24">
                  {{ dayName }}
                </label>
              </div>

              <div v-if="businessHours[dayIndex]" class="flex items-center space-x-2">
                <input
                  type="time"
                  v-model="businessHours[dayIndex].open"
                  class="w-28 px-2 py-1 text-sm bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-primary-500"
                />
                <span class="text-gray-500">-</span>
                <input
                  type="time"
                  v-model="businessHours[dayIndex].close"
                  class="w-28 px-2 py-1 text-sm bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div v-else class="text-sm text-gray-500 italic">
                Fermé
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end space-x-3">
            <button
              @click="showBusinessHoursModal = false"
              class="px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg transition-colors"
            >
              Annuler
            </button>
            <button
              @click="saveBusinessHours"
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
            >
              Appliquer
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { settingsApi } from '@/services/api'

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const showSuccess = ref(false)
const showBusinessHoursModal = ref(false)

const settingsGroups = ref([])
const formData = reactive({})
const originalData = ref({})

// Business hours editing
const dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']
const businessHours = reactive({
  0: null,
  1: { open: '09:00', close: '18:00' },
  2: { open: '09:00', close: '18:00' },
  3: { open: '09:00', close: '18:00' },
  4: null,
  5: { open: '09:00', close: '18:00' },
  6: { open: '10:00', close: '17:00' }
})

const smsCredits = reactive({
  loading: true,
  configured: false,
  credits_left: 0,
  service_name: null,
  error: null
})

const hasChanges = computed(() => {
  return JSON.stringify(formData) !== JSON.stringify(originalData.value)
})

// Check if a field is a time field (HH:MM format)
function isTimeField(key) {
  return key.includes('_start') || key.includes('_end') || key === 'first_slot_time'
}

onMounted(async () => {
  await Promise.all([loadSettings(), loadSmsCredits()])
})

async function loadSettings() {
  loading.value = true
  error.value = null

  try {
    const response = await settingsApi.getAll()
    settingsGroups.value = response.data.data || response.data

    // Populate form data
    for (const category of settingsGroups.value) {
      for (const setting of category.settings) {
        formData[setting.key] = setting.value

        // Initialize business hours if present
        if (setting.key === 'business_hours' && setting.value) {
          const hours = typeof setting.value === 'string' ? JSON.parse(setting.value) : setting.value
          for (const day in hours) {
            businessHours[day] = hours[day]
          }
        }
      }
    }

    // Save original data for comparison
    originalData.value = JSON.parse(JSON.stringify(formData))
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors du chargement des paramètres'
  } finally {
    loading.value = false
  }
}

async function loadSmsCredits() {
  smsCredits.loading = true
  smsCredits.error = null

  try {
    const response = await settingsApi.getSmsCredits()
    const data = response.data.data || response.data

    smsCredits.configured = data.configured
    smsCredits.credits_left = data.credits_left || 0
    smsCredits.service_name = data.service_name
  } catch (err) {
    smsCredits.error = err.response?.data?.message || 'Erreur lors du chargement des crédits SMS'
  } finally {
    smsCredits.loading = false
  }
}

function toggleBoolean(key) {
  formData[key] = !formData[key]
}

function toggleDay(dayIndex) {
  if (businessHours[dayIndex] === null) {
    // Enable day with default hours
    businessHours[dayIndex] = { open: '09:00', close: '18:00' }
  } else {
    businessHours[dayIndex] = null
  }
}

function saveBusinessHours() {
  // Convert reactive to plain object
  const hours = {}
  for (let i = 0; i <= 6; i++) {
    hours[i] = businessHours[i]
  }
  formData.business_hours = hours
  showBusinessHoursModal.value = false
}

async function saveSettings() {
  if (!hasChanges.value) return

  saving.value = true

  try {
    // Only send changed settings
    const changedSettings = {}
    for (const key in formData) {
      if (JSON.stringify(formData[key]) !== JSON.stringify(originalData.value[key])) {
        changedSettings[key] = formData[key]
      }
    }

    await settingsApi.update(changedSettings)

    // Update original data
    originalData.value = JSON.parse(JSON.stringify(formData))

    // Show success message
    showSuccess.value = true
    setTimeout(() => {
      showSuccess.value = false
    }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de l\'enregistrement'
  } finally {
    saving.value = false
  }
}
</script>
