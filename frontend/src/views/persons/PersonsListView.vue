<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { usePersonsStore } from '@/stores/persons'
import { useAuthStore } from '@/stores/auth'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const personsStore = usePersonsStore()
const authStore = useAuthStore()

const loading = ref(true)

onMounted(async () => {
  try {
    await personsStore.fetchPersons()
  } finally {
    loading.value = false
  }
})

async function loadPage(page) {
  loading.value = true
  try {
    await personsStore.fetchPersons({ page })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <RouterLink v-if="authStore.isAdmin" to="/app/persons/new" class="btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nouvelle personne
      </RouterLink>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <EmptyState
        v-if="personsStore.persons.length === 0"
        title="Aucune personne"
        description="Aucune personne n'est assignée à votre compte."
        icon="users"
      >
        <RouterLink v-if="authStore.isAdmin" to="/app/persons/new" class="btn-primary mt-4">
          Ajouter une personne
        </RouterLink>
      </EmptyState>

      <div v-else class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full text-sm text-left">
          <thead>
            <tr class="bg-gray-800/50">
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Nom</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Âge</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Séances</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="person in personsStore.persons" :key="person.id" class="border-t border-gray-700 hover:bg-gray-700/50">
              <td class="px-4 py-3">
                <RouterLink :to="`/app/persons/${person.id}`" class="flex items-center hover:text-primary-400">
                  <div class="w-8 h-8 rounded-full bg-primary-900/50 flex items-center justify-center text-primary-400 font-medium text-sm mr-3">
                    {{ person.first_name[0] }}{{ person.last_name[0] }}
                  </div>
                  <span class="font-medium text-gray-100">{{ person.first_name }} {{ person.last_name }}</span>
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-gray-300">{{ person.age ? person.age + ' ans' : '-' }}</td>
              <td class="px-4 py-3">
                <RouterLink :to="`/app/persons/${person.id}`" class="text-primary-400 hover:text-primary-300">
                  Voir les séances
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-right">
                <RouterLink :to="`/app/sessions/new/${person.id}`" class="btn-primary btn-sm">
                  Nouvelle séance
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="personsStore.pagination.pages > 1" class="px-4 py-3 border-t border-gray-700 flex items-center justify-between">
          <div class="text-sm text-gray-400">
            {{ personsStore.pagination.total }} personne(s)
          </div>
          <div class="flex space-x-2">
            <button
              v-for="page in personsStore.pagination.pages"
              :key="page"
              @click="loadPage(page)"
              :class="[
                'px-3 py-1 text-sm rounded',
                page === personsStore.pagination.page
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
              ]"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
