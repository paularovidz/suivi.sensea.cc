<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usersApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DocumentsSection from '@/components/documents/DocumentsSection.vue'
import LoyaltyCard from '@/components/loyalty/LoyaltyCard.vue'
import { formatPhoneForDisplay } from '@/utils/phone'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const user = ref(null)
const confirmDialog = ref(null)
const impersonateDialog = ref(null)
const impersonating = ref(false)

onMounted(async () => {
  try {
    const response = await usersApi.getById(route.params.id)
    user.value = response.data.data
  } catch (e) {
    router.push('/app/users')
  } finally {
    loading.value = false
  }
})

function confirmDelete() {
  confirmDialog.value?.open()
}

async function handleDelete() {
  try {
    await usersApi.delete(route.params.id)
    router.push('/app/users')
  } catch (e) {
    console.error('Error deleting user:', e)
  }
}

function confirmImpersonate() {
  impersonateDialog.value?.open()
}

async function handleImpersonate() {
  impersonating.value = true
  try {
    await authStore.impersonate(route.params.id)
  } catch (e) {
    console.error('Error impersonating user:', e)
  } finally {
    impersonating.value = false
  }
}

const canImpersonate = computed(() => {
  // Can impersonate if: user is loaded, not impersonating self, and current user is admin
  return user.value && authStore.user?.id !== user.value.id && authStore.isAdmin && !authStore.isImpersonating
})

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

const clientTypeLabels = {
  personal: 'Particulier',
  association: 'Association',
  friends_family: 'Friends & Family'
}

const roleLabels = {
  member: 'Membre',
  admin: 'Administrateur'
}

const isPersonalClient = computed(() => {
  // Friends & Family are treated like personal clients (eligible for loyalty)
  return ['personal', 'friends_family'].includes(user.value?.client_type)
})
</script>

<template>
  <div class="space-y-6">
    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else-if="user">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div class="flex items-center">
          <RouterLink to="/app/users" class="mr-4 p-2 rounded-lg hover:bg-gray-700">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </RouterLink>
          <div>
            <h1 class="text-2xl font-bold text-white">
              {{ user.first_name }} {{ user.last_name }}
            </h1>
            <p class="text-gray-400">{{ user.email }}</p>
          </div>
        </div>
        <div class="flex space-x-3">
          <button
            v-if="canImpersonate"
            @click="confirmImpersonate"
            :disabled="impersonating"
            class="btn-secondary"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Se connecter en tant que
          </button>
          <RouterLink :to="`/app/users/${user.id}/edit`" class="btn-secondary">
            Modifier
          </RouterLink>
          <button v-if="authStore.user?.id !== user.id" @click="confirmDelete" class="btn-danger">
            Supprimer
          </button>
        </div>
      </div>

      <!-- Info cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <div class="text-sm text-gray-400 mb-1">Role</div>
          <div class="text-lg font-semibold">
            <span :class="user.role === 'admin' ? 'text-primary-400' : 'text-white'">
              {{ roleLabels[user.role] || user.role }}
            </span>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <div class="text-sm text-gray-400 mb-1">Type de client</div>
          <div class="text-lg font-semibold text-white">{{ clientTypeLabels[user.client_type] || user.client_type }}</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <div class="text-sm text-gray-400 mb-1">Statut</div>
          <div class="text-lg font-semibold">
            <span :class="user.is_active ? 'text-green-400' : 'text-red-400'">
              {{ user.is_active ? 'Actif' : 'Inactif' }}
            </span>
          </div>
        </div>
      </div>

      <!-- User details -->
      <div class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="px-6 py-4 border-b border-gray-700">
          <h2 class="font-semibold text-white">Informations</h2>
        </div>
        <div class="p-6">
          <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <dt class="text-sm text-gray-400">Telephone</dt>
              <dd class="text-white">{{ formatPhoneForDisplay(user.phone) || '-' }}</dd>
            </div>
            <div>
              <dt class="text-sm text-gray-400">Date de creation</dt>
              <dd class="text-white">{{ formatDate(user.created_at) }}</dd>
            </div>
            <div>
              <dt class="text-sm text-gray-400">Derniere connexion</dt>
              <dd class="text-white">{{ formatDate(user.last_login_at) }}</dd>
            </div>
          </dl>

          <!-- Association details -->
          <template v-if="user.client_type === 'association'">
            <hr class="my-4 border-gray-700" />
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <dt class="text-sm text-gray-400">Nom de l'association</dt>
                <dd class="text-white">{{ user.company_name || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-400">N SIRET</dt>
                <dd class="text-white">{{ user.siret || '-' }}</dd>
              </div>
            </dl>
          </template>
        </div>
      </div>

      <!-- Assigned persons -->
      <div v-if="user.persons && user.persons.length > 0" class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="px-6 py-4 border-b border-gray-700">
          <h2 class="font-semibold text-white">Personnes assignées ({{ user.persons.length }})</h2>
        </div>
        <div class="divide-y divide-gray-700">
          <RouterLink
            v-for="person in user.persons"
            :key="person.id"
            :to="`/app/persons/${person.id}`"
            class="flex items-center px-6 py-4 hover:bg-gray-700 transition-colors"
          >
            <div class="w-10 h-10 rounded-full bg-primary-900/50 flex items-center justify-center text-primary-400 font-semibold">
              {{ person.first_name.charAt(0) }}{{ person.last_name.charAt(0) }}
            </div>
            <div class="ml-4 flex-1">
              <div class="font-medium text-white">
                {{ person.first_name }} {{ person.last_name }}
              </div>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </RouterLink>
        </div>
      </div>

      <!-- Loyalty Card (personal clients only) -->
      <LoyaltyCard
        v-if="isPersonalClient"
        :user-id="user.id"
      />

      <!-- Documents -->
      <DocumentsSection
        type="user"
        :entity-id="user.id"
      />
    </template>

    <ConfirmDialog
      ref="confirmDialog"
      title="Supprimer cet utilisateur ?"
      :message="`Etes-vous sur de vouloir supprimer ${user?.first_name} ${user?.last_name} ? Cette action est irreversible.`"
      confirm-text="Supprimer"
      danger
      @confirm="handleDelete"
    />

    <ConfirmDialog
      ref="impersonateDialog"
      title="Se connecter en tant que cet utilisateur ?"
      :message="`Vous allez vous connecter en tant que ${user?.first_name} ${user?.last_name}. Vous pourrez revenir a votre compte admin a tout moment via la banniere en haut de page.`"
      confirm-text="Se connecter"
      @confirm="handleImpersonate"
    />
  </div>
</template>
