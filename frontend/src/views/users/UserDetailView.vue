<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usersApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DocumentsSection from '@/components/documents/DocumentsSection.vue'
import LoyaltyCard from '@/components/loyalty/LoyaltyCard.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const user = ref(null)
const confirmDialog = ref(null)

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
  association: 'Association'
}

const roleLabels = {
  member: 'Membre',
  admin: 'Administrateur'
}

const isPersonalClient = computed(() => {
  return user.value?.client_type === 'personal'
})
</script>

<template>
  <div class="space-y-6">
    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else-if="user">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div class="flex items-center">
          <RouterLink to="/app/users" class="mr-4 p-2 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </RouterLink>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">
              {{ user.first_name }} {{ user.last_name }}
            </h1>
            <p class="text-gray-600">{{ user.email }}</p>
          </div>
        </div>
        <div class="flex space-x-3">
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
        <div class="card p-6">
          <div class="text-sm text-gray-500 mb-1">Role</div>
          <div class="text-lg font-semibold">
            <span :class="user.role === 'admin' ? 'text-primary-600' : 'text-gray-900'">
              {{ roleLabels[user.role] || user.role }}
            </span>
          </div>
        </div>
        <div class="card p-6">
          <div class="text-sm text-gray-500 mb-1">Type de client</div>
          <div class="text-lg font-semibold">{{ clientTypeLabels[user.client_type] || user.client_type }}</div>
        </div>
        <div class="card p-6">
          <div class="text-sm text-gray-500 mb-1">Statut</div>
          <div class="text-lg font-semibold">
            <span :class="user.is_active ? 'text-green-600' : 'text-red-600'">
              {{ user.is_active ? 'Actif' : 'Inactif' }}
            </span>
          </div>
        </div>
      </div>

      <!-- User details -->
      <div class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Informations</h2>
        </div>
        <div class="card-body">
          <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <dt class="text-sm text-gray-500">Login</dt>
              <dd class="text-gray-900">{{ user.login }}</dd>
            </div>
            <div>
              <dt class="text-sm text-gray-500">Telephone</dt>
              <dd class="text-gray-900">{{ user.phone || '-' }}</dd>
            </div>
            <div>
              <dt class="text-sm text-gray-500">Date de creation</dt>
              <dd class="text-gray-900">{{ formatDate(user.created_at) }}</dd>
            </div>
            <div>
              <dt class="text-sm text-gray-500">Derniere connexion</dt>
              <dd class="text-gray-900">{{ formatDate(user.last_login_at) }}</dd>
            </div>
          </dl>

          <!-- Association details -->
          <template v-if="user.client_type === 'association'">
            <hr class="my-4" />
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <dt class="text-sm text-gray-500">Nom de l'association</dt>
                <dd class="text-gray-900">{{ user.company_name || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">N SIRET</dt>
                <dd class="text-gray-900">{{ user.siret || '-' }}</dd>
              </div>
            </dl>
          </template>
        </div>
      </div>

      <!-- Assigned persons -->
      <div v-if="user.persons && user.persons.length > 0" class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Personnes assignees ({{ user.persons.length }})</h2>
        </div>
        <div class="divide-y divide-gray-100">
          <RouterLink
            v-for="person in user.persons"
            :key="person.id"
            :to="`/app/persons/${person.id}`"
            class="flex items-center px-6 py-4 hover:bg-gray-50 transition-colors"
          >
            <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 font-semibold">
              {{ person.first_name.charAt(0) }}{{ person.last_name.charAt(0) }}
            </div>
            <div class="ml-4 flex-1">
              <div class="font-medium text-gray-900">
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
  </div>
</template>
