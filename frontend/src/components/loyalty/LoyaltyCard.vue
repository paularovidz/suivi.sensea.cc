<script setup>
import { ref, onMounted, computed } from 'vue'
import { usersApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const props = defineProps({
  userId: {
    type: String,
    required: true
  }
})

const loading = ref(true)
const loyaltyData = ref(null)
const error = ref('')

onMounted(async () => {
  await loadLoyaltyCard()
})

async function loadLoyaltyCard() {
  loading.value = true
  error.value = ''
  try {
    const response = await usersApi.getLoyaltyCard(props.userId)
    loyaltyData.value = response.data.data
  } catch (e) {
    error.value = 'Erreur lors du chargement de la carte'
    console.error(e)
  } finally {
    loading.value = false
  }
}

const progressWidth = computed(() => {
  if (!loyaltyData.value?.eligible) return '0%'
  return loyaltyData.value.progress_percent + '%'
})

// Stamps for paid sessions (séances à payer)
const stamps = computed(() => {
  if (!loyaltyData.value?.eligible) return []
  const total = loyaltyData.value.sessions_required
  const filled = loyaltyData.value.sessions_count
  return Array.from({ length: total }, (_, i) => i < filled)
})

// Is the free session available (card complete and not yet used)
const freeSessionUnlocked = computed(() => {
  return loyaltyData.value?.free_session_available || false
})

const remainingSessions = computed(() => {
  if (!loyaltyData.value?.eligible) return 0
  return loyaltyData.value.sessions_required - loyaltyData.value.sessions_count
})

function formatDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}
</script>

<template>
  <div class="card-dark overflow-hidden">
    <div class="card-dark-header">
      <h2 class="font-semibold text-white">Carte de fidélité</h2>
    </div>
    <div class="card-dark-body">
      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-8">
        <LoadingSpinner size="lg" />
      </div>

      <!-- Not eligible -->
      <div v-else-if="!loyaltyData?.eligible" class="text-center py-6">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-700 flex items-center justify-center">
          <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
          </svg>
        </div>
        <p class="text-gray-400">{{ loyaltyData?.reason || 'Programme de fidelite non disponible' }}</p>
      </div>

      <!-- Eligible -->
      <template v-else>
        <!-- Stamps grid: paid sessions + free session box -->
        <div class="mb-6">
          <!-- Label -->
          <div class="text-sm text-gray-400 mb-3 text-center">Séances à payer</div>

          <!-- Paid sessions stamps -->
          <div class="grid gap-2 mb-3" :style="{ gridTemplateColumns: `repeat(${Math.min(stamps.length, 5)}, minmax(0, 1fr))` }">
            <div
              v-for="(filled, index) in stamps"
              :key="index"
              :class="[
                'aspect-square rounded-xl flex items-center justify-center text-lg font-bold transition-all duration-300',
                filled
                  ? 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md'
                  : 'bg-gray-700 text-gray-500 border-2 border-dashed border-gray-600'
              ]"
            >
              <svg v-if="filled" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span v-else class="text-sm">{{ index + 1 }}</span>
            </div>
          </div>

          <!-- Free session box (bonus) -->
          <div class="flex justify-center">
            <div
              :class="[
                'px-6 py-3 rounded-xl flex items-center justify-center font-semibold transition-all duration-300',
                freeSessionUnlocked
                  ? 'bg-gradient-to-br from-amber-400 to-amber-500 text-white shadow-lg animate-pulse'
                  : loyaltyData.free_session_used_at
                    ? 'bg-gray-700 text-gray-500 line-through'
                    : 'bg-amber-900/30 text-amber-400/50 border-2 border-dashed border-amber-700/50'
              ]"
            >
              <svg v-if="freeSessionUnlocked" class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              <svg v-else-if="loyaltyData.free_session_used_at" class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
              </svg>
              Séance offerte
            </div>
          </div>
        </div>

        <!-- Progress info -->
        <div class="mb-4 text-center">
          <span class="text-sm text-gray-400">{{ loyaltyData.sessions_count }} / {{ loyaltyData.sessions_required }} séances validées</span>
        </div>

        <!-- Status messages -->
        <!-- Free session available -->
        <div v-if="loyaltyData.free_session_available" class="p-4 bg-green-900/30 border border-green-700/50 rounded-xl">
          <div class="flex items-center text-green-300">
            <div class="w-10 h-10 rounded-full bg-green-800/50 flex items-center justify-center mr-3 flex-shrink-0">
              <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div>
              <div class="font-semibold text-green-200">Félicitations ! Votre séance gratuite vous attend !</div>
              <div class="text-sm text-green-400">
                Carte complétée le {{ formatDate(loyaltyData.completed_at) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Free session used -->
        <div v-else-if="loyaltyData.is_completed && loyaltyData.free_session_used_at" class="p-4 bg-gray-700/50 border border-gray-600/50 rounded-xl">
          <div class="flex items-center text-gray-300">
            <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center mr-3 flex-shrink-0">
              <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div>
              <div class="font-medium text-gray-200">Séance gratuite utilisée</div>
              <div class="text-sm text-gray-400">
                Le {{ formatDate(loyaltyData.free_session_used_at) }} — Nouvelle carte en cours
              </div>
            </div>
          </div>
        </div>

        <!-- Progress message -->
        <div v-else class="text-center text-gray-400">
          <p v-if="remainingSessions === 1">
            Plus qu'<strong class="text-indigo-400">1 séance</strong> pour débloquer votre séance gratuite !
          </p>
          <p v-else>
            Encore <strong class="text-indigo-400">{{ remainingSessions }} séances</strong> pour débloquer votre séance gratuite
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
