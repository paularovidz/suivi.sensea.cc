<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { sessionsApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const session = ref(null)

const labels = {
  behavior: {
    calm: 'Calme',
    agitated: 'Agité',
    tired: 'Fatigué',
    defensive: 'Défensif',
    anxious: 'Inquiet',
    passive: 'Passif (apathique)'
  },
  proposal_origin: {
    person: 'La personne elle-même',
    relative: 'Un proche'
  },
  attitude_start: {
    accepts: 'Accepte la séance',
    indifferent: 'Indifférente',
    refuses: 'Refuse'
  },
  position: {
    standing: 'Debout',
    lying: 'Allongée',
    sitting: 'Assise',
    moving: 'Se déplace'
  },
  communication: {
    body: 'Corporelle',
    verbal: 'Verbale',
    vocal: 'Vocale'
  },
  session_end: {
    accepts: 'Accepte',
    refuses: 'Refuse',
    interrupts: 'Interrompt la séance'
  },
  appreciation: {
    negative: 'Apprécié négativement',
    neutral: 'Neutralité',
    positive: 'Apprécié positivement'
  },
  sensoryType: {
    tactile: 'Tactile',
    visual: 'Visuelle',
    olfactory: 'Olfactive',
    gustatory: 'Gustative',
    auditory: 'Auditive',
    proprioceptive: 'Proprioceptive',
    vestibular: 'Vestibulaire'
  }
}

onMounted(async () => {
  try {
    const response = await sessionsApi.getById(route.params.id)
    session.value = response.data.data
  } catch (e) {
    console.error('Error loading session:', e)
    router.push('/app/member')
  } finally {
    loading.value = false
  }
})

function goBack() {
  router.push('/app/member')
}

function formatDateTime(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getAppreciationBadgeClass(appreciation) {
  const classes = {
    negative: 'badge-danger',
    neutral: 'badge-gray',
    positive: 'badge-success'
  }
  return classes[appreciation] || 'badge-gray'
}

function getTypeBadgeClass(type) {
  const classes = {
    tactile: 'badge-tactile',
    visual: 'badge-visual',
    olfactory: 'badge-olfactory',
    gustatory: 'badge-gustatory',
    auditory: 'badge-auditory',
    proprioceptive: 'badge-proprioceptive',
    vestibular: 'badge-vestibular'
  }
  return classes[type] || 'badge-gray'
}

async function handleLogout() {
  await authStore.logout()
}
</script>

<template>
  <div class="min-h-screen bg-gray-900">
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700 px-4 py-4">
      <div class="max-w-4xl mx-auto flex items-center justify-between">
        <div class="flex items-center">
          <button @click="goBack" class="mr-4 p-2 rounded-lg hover:bg-gray-700">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <h1 class="text-xl font-bold text-gradient">sensëa</h1>
        </div>
        <div class="flex items-center space-x-4">
          <span class="text-sm text-gray-400">{{ authStore.fullName }}</span>
          <button @click="handleLogout" class="text-sm text-red-400 hover:text-red-300">
            Déconnexion
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-4xl mx-auto p-4">
      <LoadingSpinner v-if="loading" size="lg" class="py-12" />

      <template v-else-if="session">
        <!-- Titre -->
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-white">
            Séance du {{ formatDateTime(session.session_date) }}
          </h1>
          <p class="text-gray-400 mt-1">
            {{ session.person_first_name }} {{ session.person_last_name }}
          </p>
        </div>

        <!-- Info cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <div class="text-sm text-gray-400 mb-1">Durée</div>
            <div class="text-lg font-semibold text-white">{{ session.duration_minutes }} min</div>
          </div>
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <div class="text-sm text-gray-400 mb-1">Séances / mois</div>
            <div class="text-lg font-semibold text-white">{{ session.sessions_per_month || '-' }}</div>
          </div>
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <div class="text-sm text-gray-400 mb-1">Souhaite revenir</div>
            <div class="text-lg font-semibold">
              <span v-if="session.wants_to_return === true" class="text-green-400">Oui</span>
              <span v-else-if="session.wants_to_return === false" class="text-red-400">Non</span>
              <span v-else class="text-gray-500">-</span>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <!-- Début de séance -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <h3 class="font-semibold text-white mb-3">Début de séance</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-400">Comportement</span>
                <span class="font-medium text-white">{{ labels.behavior[session.behavior_start] || '-' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Proposition vient de</span>
                <span class="font-medium text-white">{{ labels.proposal_origin[session.proposal_origin] || '-' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Attitude</span>
                <span class="font-medium text-white">{{ labels.attitude_start[session.attitude_start] || '-' }}</span>
              </div>
            </div>
          </div>

          <!-- Fin de séance -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <h3 class="font-semibold text-white mb-3">Fin de séance</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-400">Fin</span>
                <span class="font-medium text-white">{{ labels.session_end[session.session_end] || '-' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Comportement</span>
                <span class="font-medium text-white">{{ labels.behavior[session.behavior_end] || '-' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Pendant la séance -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 mb-6">
          <h3 class="font-semibold text-white mb-3">Pendant la séance</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-400">Position : </span>
              <span class="font-medium text-white">{{ labels.position[session.position] || '-' }}</span>
            </div>
            <div>
              <span class="text-gray-400">Communication : </span>
              <span v-if="session.communication?.length" class="font-medium text-white">
                {{ session.communication.map(c => labels.communication[c]).join(', ') }}
              </span>
              <span v-else class="text-gray-500">-</span>
            </div>
          </div>
        </div>

        <!-- Propositions sensorielles -->
        <div v-if="session.proposals?.length" class="bg-gray-800 rounded-xl border border-gray-700 mb-6">
          <div class="p-4 border-b border-gray-700">
            <h3 class="font-semibold text-white">Propositions sensorielles</h3>
          </div>
          <div class="divide-y divide-gray-700">
            <div v-for="proposal in session.proposals" :key="proposal.link_id" class="px-4 py-3 flex items-center justify-between">
              <div>
                <div class="font-medium text-white">{{ proposal.title }}</div>
                <span :class="getTypeBadgeClass(proposal.type)" class="text-xs">
                  {{ labels.sensoryType[proposal.type] }}
                </span>
              </div>
              <span v-if="proposal.appreciation" :class="getAppreciationBadgeClass(proposal.appreciation)">
                {{ labels.appreciation[proposal.appreciation] }}
              </span>
            </div>
          </div>
        </div>

        <!-- Notes (si visibles) -->
        <div v-if="session.professional_notes || session.person_expression" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-if="session.professional_notes" class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <h3 class="font-semibold text-white mb-2">Impressions du professionnel</h3>
            <p class="text-gray-300 text-sm whitespace-pre-wrap">{{ session.professional_notes }}</p>
          </div>
          <div v-if="session.person_expression" class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <h3 class="font-semibold text-white mb-2">Expression de la personne</h3>
            <p class="text-gray-300 text-sm whitespace-pre-wrap">{{ session.person_expression }}</p>
          </div>
        </div>

        <!-- Créé par -->
        <div class="mt-6 text-sm text-gray-400 text-center">
          Séance enregistrée par {{ session.creator_first_name }} {{ session.creator_last_name }}
        </div>
      </template>
    </main>
  </div>
</template>
