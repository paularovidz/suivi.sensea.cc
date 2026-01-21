<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useSessionsStore } from '@/stores/sessions'
import { useProposalsStore } from '@/stores/proposals'
import { useAuthStore } from '@/stores/auth'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DocumentsSection from '@/components/documents/DocumentsSection.vue'

const route = useRoute()
const router = useRouter()
const sessionsStore = useSessionsStore()
const proposalsStore = useProposalsStore()
const authStore = useAuthStore()

const loading = ref(true)
const confirmDialog = ref(null)

const session = computed(() => sessionsStore.currentSession)

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
  }
}

onMounted(async () => {
  try {
    await sessionsStore.fetchSession(route.params.id)
    await proposalsStore.fetchTypes()
  } catch (e) {
    router.push('/app/sessions')
  } finally {
    loading.value = false
  }
})

function confirmDelete() {
  confirmDialog.value?.open()
}

async function handleDelete() {
  try {
    await sessionsStore.deleteSession(route.params.id)
    router.push('/app/sessions')
  } catch (e) {
    console.error('Error deleting session:', e)
  }
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
</script>

<template>
  <div class="space-y-6">
    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else-if="session">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div class="flex items-center">
          <RouterLink to="/app/sessions" class="mr-4 p-2 rounded-lg hover:bg-gray-700">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </RouterLink>
          <div>
            <h1 class="text-2xl font-bold text-white">
              Séance du {{ formatDateTime(session.session_date) }}
            </h1>
            <RouterLink :to="`/app/persons/${session.person_id}`" class="text-primary-400 hover:text-primary-300">
              {{ session.person_first_name }} {{ session.person_last_name }}
              <span v-if="session.person_birth_date"> - {{ new Date().getFullYear() - new Date(session.person_birth_date).getFullYear() }} ans</span>
            </RouterLink>
          </div>
        </div>
        <div class="flex space-x-3">
          <RouterLink :to="`/app/sessions/${session.id}/edit`" class="btn-secondary">
            Modifier
          </RouterLink>
          <button @click="confirmDelete" class="btn-danger">
            Supprimer
          </button>
        </div>
      </div>

      <!-- Info cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <div class="text-sm text-gray-400 mb-1">Durée</div>
          <div class="text-lg font-semibold text-white">{{ session.duration_minutes }} minutes</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <div class="text-sm text-gray-400 mb-1">Séances / mois</div>
          <div class="text-lg font-semibold text-white">{{ session.sessions_per_month || '-' }}</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <div class="text-sm text-gray-400 mb-1">Souhaite revenir</div>
          <div class="text-lg font-semibold">
            <span v-if="session.wants_to_return === true" class="text-green-400">Oui</span>
            <span v-else-if="session.wants_to_return === false" class="text-red-400">Non</span>
            <span v-else class="text-gray-500">Non renseigné</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Début de séance -->
        <div class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="font-semibold text-white">Début de séance</h2>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div>
              <div class="text-sm text-gray-400">Comportement</div>
              <div class="font-medium text-gray-200">{{ labels.behavior[session.behavior_start] || '-' }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-400">Proposition vient de</div>
              <div class="font-medium text-gray-200">{{ labels.proposal_origin[session.proposal_origin] || '-' }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-400">Attitude</div>
              <div class="font-medium text-gray-200">{{ labels.attitude_start[session.attitude_start] || '-' }}</div>
            </div>
          </div>
        </div>

        <!-- Pendant la séance -->
        <div class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="font-semibold text-white">Pendant la séance</h2>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div>
              <div class="text-sm text-gray-400">Position</div>
              <div class="font-medium text-gray-200">{{ labels.position[session.position] || '-' }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-400">Communication</div>
              <div class="flex flex-wrap gap-2 mt-1">
                <span
                  v-for="comm in (session.communication || [])"
                  :key="comm"
                  class="badge-primary"
                >
                  {{ labels.communication[comm] }}
                </span>
                <span v-if="!session.communication?.length" class="text-gray-500">-</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Fin de séance -->
        <div class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="font-semibold text-white">Fin de séance</h2>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div>
              <div class="text-sm text-gray-400">Fin de séance</div>
              <div class="font-medium text-gray-200">{{ labels.session_end[session.session_end] || '-' }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-400">Comportement</div>
              <div class="font-medium text-gray-200">{{ labels.behavior[session.behavior_end] || '-' }}</div>
            </div>
          </div>
        </div>

        <!-- Infos complémentaires -->
        <div class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="font-semibold text-white">Informations</h2>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div>
              <div class="text-sm text-gray-400">Créé par</div>
              <div class="font-medium text-gray-200">{{ session.creator_first_name }} {{ session.creator_last_name }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Propositions sensorielles -->
      <div v-if="session.proposals?.length" class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="px-6 py-4 border-b border-gray-700">
          <h2 class="font-semibold text-white">Propositions sensorielles</h2>
        </div>
        <div class="divide-y divide-gray-700">
          <div v-for="proposal in session.proposals" :key="proposal.link_id" class="px-6 py-4 flex items-center justify-between">
            <div>
              <div class="font-medium text-gray-200">{{ proposal.title }}</div>
              <div class="text-sm text-gray-400">
                <span :class="proposalsStore.getTypeBadgeClass(proposal.type)">
                  {{ proposalsStore.getTypeLabel(proposal.type) }}
                </span>
              </div>
            </div>
            <span v-if="proposal.appreciation" :class="getAppreciationBadgeClass(proposal.appreciation)">
              {{ labels.appreciation[proposal.appreciation] }}
            </span>
          </div>
        </div>
      </div>

      <!-- Notes privées -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div v-if="session.professional_notes" class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold text-white">Impressions du professionnel</h2>
            <span class="text-xs text-gray-500">Note privée</span>
          </div>
          <div class="px-6 py-4">
            <p class="text-gray-300 whitespace-pre-wrap">{{ session.professional_notes }}</p>
          </div>
        </div>

        <div v-if="session.person_expression" class="bg-gray-800 rounded-xl border border-gray-700">
          <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold text-white">Expression de la personne</h2>
            <span class="text-xs text-gray-500">Note privée</span>
          </div>
          <div class="px-6 py-4">
            <p class="text-gray-300 whitespace-pre-wrap">{{ session.person_expression }}</p>
          </div>
        </div>
      </div>

      <!-- Documents (admin only) - attached to the person -->
      <div v-if="authStore.isAdmin" class="space-y-2">
        <p class="text-sm text-gray-400">
          Documents joints à
          <RouterLink :to="`/app/persons/${session.person_id}`" class="text-primary-400 hover:underline">
            {{ session.person_first_name }} {{ session.person_last_name }}
          </RouterLink>
          (factures, etc.)
        </p>
        <DocumentsSection
          type="person"
          :entity-id="session.person_id"
        />
      </div>
    </template>

    <ConfirmDialog
      ref="confirmDialog"
      title="Supprimer cette séance ?"
      message="Êtes-vous sûr de vouloir supprimer cette séance ? Cette action est irréversible."
      confirm-text="Supprimer"
      danger
      @confirm="handleDelete"
    />
  </div>
</template>
