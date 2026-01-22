<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePersonsStore } from '@/stores/persons'
import { useAuthStore } from '@/stores/auth'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoyaltyCard from '@/components/loyalty/LoyaltyCard.vue'
import DocumentsSection from '@/components/documents/DocumentsSection.vue'
import SessionDocumentsList from '@/components/documents/SessionDocumentsList.vue'
import ImpersonationBanner from '@/components/ui/ImpersonationBanner.vue'
import ProfileEditModal from '@/components/member/ProfileEditModal.vue'
import PersonCreateModal from '@/components/member/PersonCreateModal.vue'

const router = useRouter()
const route = useRoute()
const personsStore = usePersonsStore()
const authStore = useAuthStore()

const loading = ref(true)
const showProfileModal = ref(false)
const showPersonModal = ref(false)

// Check for editProfile query parameter
if (route.query.editProfile === 'true') {
  showProfileModal.value = true
  // Clean up the URL
  router.replace({ query: {} })
}

const persons = computed(() => personsStore.persons)

// Vérifie si l'utilisateur est un particulier (éligible fidélité)
// Inclut personal et friends_family
const isPersonalClient = computed(() => {
  return ['personal', 'friends_family'].includes(authStore.user?.client_type)
})

onMounted(async () => {
  try {
    await personsStore.fetchPersons({ limit: 100 })
  } catch (e) {
    console.error('Error loading persons:', e)
  } finally {
    loading.value = false
  }
})

function selectPerson(personId) {
  router.push(`/app/member/persons/${personId}`)
}

function handleProfileUpdated(updatedUser) {
  // Update user in auth store
  authStore.updateUser(updatedUser)
}

async function handlePersonCreated(newPerson) {
  // Refresh the persons list
  await personsStore.fetchPersons({ limit: 100 })
}

async function handleLogout() {
  await authStore.logout()
}
</script>

<template>
  <div class="min-h-screen bg-dark">
    <!-- Impersonation Banner -->
    <ImpersonationBanner />

    <!-- Header -->
    <header class="header-dark px-4 py-4">
      <div class="max-w-4xl mx-auto flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
            <span class="text-white text-lg font-bold">S</span>
          </div>
          <h1 class="text-xl font-semibold text-white">sensëa</h1>
        </div>
        <div class="flex items-center space-x-4">
          <button
            @click="showProfileModal = true"
            class="text-sm text-gray-400 hover:text-white transition-colors flex items-center"
          >
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            {{ authStore.fullName }}
          </button>
          <button @click="handleLogout" class="text-sm text-red-400 hover:text-red-300 transition-colors">
            Deconnexion
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-4xl mx-auto p-4">
      <LoadingSpinner v-if="loading" size="lg" class="py-12" />

      <template v-else>
        <!-- 1. Bouton Prendre RDV -->
        <div class="mb-6">
          <a
            href="/booking"
            target="_blank"
            class="block bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-xl p-4 transition-all duration-200 shadow-lg hover:shadow-xl"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div>
                  <div class="font-semibold">Prendre rendez-vous</div>
                  <div class="text-sm text-white/70">Réserver une nouvelle séance</div>
                </div>
              </div>
              <svg class="w-5 h-5 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </div>
          </a>
        </div>

        <!-- 2. Mes documents (compte) -->
        <div v-if="authStore.user?.id" class="mb-6">
          <DocumentsSection
            type="user"
            :entity-id="authStore.user.id"
            title="Mes documents"
            :can-upload="true"
            :current-user-id="authStore.user.id"
            dark
          />
        </div>

        <!-- 3. Documents des séances (factures) -->
        <div class="mb-6">
          <SessionDocumentsList
            my-documents
            title="Factures et documents des séances"
            show-person-name
          />
        </div>

        <!-- 4. Liste des personnes accompagnées -->
        <div class="mb-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Personnes accompagnees</h2>
            <button
              @click="showPersonModal = true"
              class="btn-secondary text-sm"
            >
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Ajouter
            </button>
          </div>

          <EmptyState
            v-if="persons.length === 0"
            title="Aucune personne"
            description="Vous n'avez pas encore ajoute de personne. Cliquez sur 'Ajouter' pour commencer."
            icon="people"
            class="py-8"
            dark
          />

          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
              v-for="person in persons"
              :key="person.id"
              @click="selectPerson(person.id)"
              class="card-dark-interactive p-6 text-left"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-semibold text-white">
                    {{ person.first_name }} {{ person.last_name }}
                  </div>
                  <div class="text-sm text-gray-400 mt-1">
                    {{ person.age ? person.age + ' ans' : 'Âge non renseigné' }}
                  </div>
                  <div class="text-sm text-gray-400 mt-1">
                    {{ person.stats?.total_sessions || 0 }} séance(s)
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </div>
            </button>
          </div>
        </div>

        <!-- 5. Carte de fidélité (particuliers uniquement) -->
        <div v-if="isPersonalClient && authStore.user?.id">
          <LoyaltyCard :user-id="authStore.user.id" />
        </div>
      </template>
    </main>

    <!-- Profile Edit Modal -->
    <ProfileEditModal
      v-if="authStore.user"
      v-model="showProfileModal"
      :user="authStore.user"
      @updated="handleProfileUpdated"
    />

    <!-- Person Create Modal -->
    <PersonCreateModal
      v-model="showPersonModal"
      @created="handlePersonCreated"
    />
  </div>
</template>
