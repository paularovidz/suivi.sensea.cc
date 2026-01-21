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

const stamps = computed(() => {
  if (!loyaltyData.value?.eligible) return []
  const total = loyaltyData.value.sessions_required
  const filled = loyaltyData.value.sessions_count
  return Array.from({ length: total }, (_, i) => i < filled)
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
  <div class="card">
    <div class="card-header">
      <h2 class="font-semibold text-gray-900">Carte de fidelite</h2>
    </div>
    <div class="card-body">
      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-8">
        <LoadingSpinner size="lg" />
      </div>

      <!-- Not eligible -->
      <div v-else-if="!loyaltyData?.eligible" class="text-center py-6">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
          </svg>
        </div>
        <p class="text-gray-500">{{ loyaltyData?.reason || 'Programme de fidelite non disponible' }}</p>
      </div>

      <!-- Eligible -->
      <template v-else>
        <!-- Stamps grid -->
        <div class="grid gap-2 mb-6" :style="{ gridTemplateColumns: `repeat(${Math.min(stamps.length, 5)}, minmax(0, 1fr))` }">
          <div
            v-for="(filled, index) in stamps"
            :key="index"
            :class="[
              'aspect-square rounded-xl flex items-center justify-center text-lg font-bold transition-all duration-300',
              filled
                ? 'bg-gradient-to-br from-sensea-light to-sensea text-white shadow-md'
                : 'bg-gray-100 text-gray-300 border-2 border-dashed border-gray-200'
            ]"
          >
            <svg v-if="filled" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <span v-else class="text-sm">{{ index + 1 }}</span>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="mb-6">
          <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-600">{{ loyaltyData.sessions_count }} / {{ loyaltyData.sessions_required }} seances</span>
            <span class="font-semibold text-sensea">{{ loyaltyData.progress_percent }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div
              class="h-full rounded-full bg-gradient-to-r from-sensea-light to-sensea transition-all duration-500"
              :style="{ width: progressWidth }"
            ></div>
          </div>
        </div>

        <!-- Status messages -->
        <!-- Free session available -->
        <div v-if="loyaltyData.free_session_available" class="p-4 bg-green-50 border border-green-200 rounded-xl">
          <div class="flex items-center text-green-800">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
              <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div>
              <div class="font-semibold">Seance gratuite disponible!</div>
              <div class="text-sm text-green-700">
                Carte completee le {{ formatDate(loyaltyData.completed_at) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Free session used -->
        <div v-else-if="loyaltyData.is_completed && loyaltyData.free_session_used_at" class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
          <div class="flex items-center text-gray-600">
            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mr-3">
              <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div>
              <div class="font-medium">Seance gratuite utilisee</div>
              <div class="text-sm">
                Le {{ formatDate(loyaltyData.free_session_used_at) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Progress message -->
        <div v-else class="text-center text-gray-500">
          <p v-if="remainingSessions === 1">
            Plus qu'<strong class="text-sensea">1 seance</strong> pour obtenir une seance gratuite!
          </p>
          <p v-else>
            Encore <strong class="text-sensea">{{ remainingSessions }} seances</strong> pour obtenir une seance gratuite
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
