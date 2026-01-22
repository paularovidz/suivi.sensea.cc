<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usersApi, personsApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import PhoneInput from '@/components/ui/PhoneInput.vue'

const route = useRoute()
const router = useRouter()

const isEdit = computed(() => !!route.params.id)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const allPersons = ref([])

const form = ref({
  email: '',
  first_name: '',
  last_name: '',
  phone: '',
  role: 'member',
  is_active: true,
  client_type: 'personal',
  company_name: '',
  siret: '',
  assign_to_persons: []
})

const isAssociation = computed(() => form.value.client_type === 'association')

const clientTypes = [
  { value: 'personal', label: 'Particulier' },
  { value: 'association', label: 'Association' },
  { value: 'friends_family', label: 'Friends & Family' }
]

const assignedPersons = ref([])
const personSearch = ref('')

onMounted(async () => {
  loading.value = true
  try {
    // Load all persons for assignment
    const personsResponse = await personsApi.getAll({ limit: 200 })
    allPersons.value = personsResponse.data.data.persons

    if (isEdit.value) {
      const response = await usersApi.getById(route.params.id)
      const user = response.data.data
      form.value = {
        email: user.email || '',
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        phone: user.phone || '',
        role: user.role || 'member',
        is_active: user.is_active ?? true,
        client_type: user.client_type || 'personal',
        company_name: user.company_name || '',
        siret: user.siret || ''
      }
      assignedPersons.value = (user.persons || []).map(p => p.id)
    }
  } catch (e) {
    if (isEdit.value) {
      router.push('/app/users')
    }
  } finally {
    loading.value = false
  }
})

function togglePerson(personId) {
  const index = assignedPersons.value.indexOf(personId)
  if (index === -1) {
    assignedPersons.value.push(personId)
  } else {
    assignedPersons.value.splice(index, 1)
  }
}

const filteredPersons = computed(() => {
  if (!personSearch.value) return allPersons.value
  const search = personSearch.value.toLowerCase()
  return allPersons.value.filter(p =>
    p.first_name.toLowerCase().includes(search) ||
    p.last_name.toLowerCase().includes(search)
  )
})

const assignedPersonsList = computed(() => {
  return allPersons.value.filter(p => assignedPersons.value.includes(p.id))
})

function removePerson(personId) {
  const index = assignedPersons.value.indexOf(personId)
  if (index !== -1) {
    assignedPersons.value.splice(index, 1)
  }
}

async function handleSubmit() {
  error.value = ''
  saving.value = true

  try {
    if (isEdit.value) {
      await usersApi.update(route.params.id, {
        ...form.value,
        assign_to_users: assignedPersons.value
      })

      // Update person assignments
      const currentUser = (await usersApi.getById(route.params.id)).data.data
      const currentAssigned = (currentUser.persons || []).map(p => p.id)

      // Unassign removed persons
      for (const personId of currentAssigned) {
        if (!assignedPersons.value.includes(personId)) {
          await usersApi.unassignPerson(route.params.id, personId)
        }
      }

      // Assign new persons
      for (const personId of assignedPersons.value) {
        if (!currentAssigned.includes(personId)) {
          await usersApi.assignPerson(route.params.id, personId)
        }
      }

      router.push('/app/users')
    } else {
      const response = await usersApi.create(form.value)
      const userId = response.data.data.id

      // Assign persons to new user
      for (const personId of assignedPersons.value) {
        await usersApi.assignPerson(userId, personId)
      }

      router.push('/app/users')
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Une erreur est survenue'
    if (e.response?.data?.errors) {
      error.value = Object.values(e.response.data.errors).join(', ')
    }
  } finally {
    saving.value = false
  }
}

function cancel() {
  router.push('/app/users')
}
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-white">
        {{ isEdit ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' }}
      </h1>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <AlertMessage v-if="error" type="error" dismissible @dismiss="error = ''">
        {{ error }}
      </AlertMessage>

      <div class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="px-6 py-4 border-b border-gray-700">
          <h2 class="font-semibold text-white">Informations du compte</h2>
        </div>
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="first_name" class="block text-sm font-medium text-gray-300 mb-1">Prénom *</label>
              <input id="first_name" v-model="form.first_name" type="text" class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" required />
            </div>

            <div>
              <label for="last_name" class="block text-sm font-medium text-gray-300 mb-1">Nom *</label>
              <input id="last_name" v-model="form.last_name" type="text" class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" required />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email *</label>
              <input id="email" v-model="form.email" type="email" class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" required />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Téléphone</label>
              <PhoneInput v-model="form.phone" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="role" class="block text-sm font-medium text-gray-300 mb-1">Rôle *</label>
              <select id="role" v-model="form.role" class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <option value="member">Membre</option>
                <option value="admin">Administrateur</option>
              </select>
            </div>

            <div>
              <label for="client_type" class="block text-sm font-medium text-gray-300 mb-1">Type de client *</label>
              <select id="client_type" v-model="form.client_type" class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <option v-for="type in clientTypes" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </select>
            </div>
          </div>

          <template v-if="isAssociation">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="company_name" class="block text-sm font-medium text-gray-300 mb-1">Nom de l'association</label>
                <input id="company_name" v-model="form.company_name" type="text" class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Nom de l'association" />
              </div>

              <div>
                <label for="siret" class="block text-sm font-medium text-gray-300 mb-1">N SIRET</label>
                <input id="siret" v-model="form.siret" type="text" class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" maxlength="14" placeholder="12345678901234" />
              </div>
            </div>
          </template>

          <div v-if="isEdit" class="flex items-center">
            <input
              id="is_active"
              v-model="form.is_active"
              type="checkbox"
              class="w-4 h-4 text-primary-600 border-gray-600 bg-gray-700 rounded focus:ring-primary-500"
            />
            <label for="is_active" class="ml-2 text-sm text-gray-300">
              Compte actif
            </label>
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="px-6 py-4 border-b border-gray-700">
          <h2 class="font-semibold text-white">Personnes assignées</h2>
          <p class="text-sm text-gray-400">Sélectionnez les personnes que cet utilisateur pourra suivre</p>
        </div>
        <div class="p-6 space-y-4">
          <!-- Personnes actuellement assignées -->
          <div v-if="assignedPersonsList.length" class="space-y-2">
            <div class="text-sm font-medium text-gray-300">Personnes assignées ({{ assignedPersonsList.length }})</div>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="person in assignedPersonsList"
                :key="person.id"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-primary-900/50 text-primary-300"
              >
                {{ person.first_name }} {{ person.last_name }}
                <button
                  type="button"
                  @click="removePerson(person.id)"
                  class="ml-2 text-primary-400 hover:text-primary-200"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </span>
            </div>
          </div>

          <!-- Recherche et ajout de personnes -->
          <div>
            <div class="text-sm font-medium text-gray-300 mb-2">Ajouter des personnes</div>
            <input
              v-model="personSearch"
              type="text"
              class="w-full px-4 py-2 text-sm bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent mb-2"
              placeholder="Rechercher une personne..."
            />
            <div v-if="allPersons.length" class="border border-gray-600 rounded-lg max-h-48 overflow-y-auto">
              <label
                v-for="person in filteredPersons"
                :key="person.id"
                class="flex items-center px-3 py-2 hover:bg-gray-700 cursor-pointer border-b border-gray-700 last:border-b-0"
              >
                <input
                  type="checkbox"
                  :checked="assignedPersons.includes(person.id)"
                  @change="togglePerson(person.id)"
                  class="w-4 h-4 text-primary-600 border-gray-600 bg-gray-700 rounded focus:ring-primary-500"
                />
                <span class="ml-3 text-sm text-gray-300">
                  {{ person.first_name }} {{ person.last_name }}
                </span>
              </label>
              <div v-if="filteredPersons.length === 0" class="px-3 py-2 text-sm text-gray-400">
                Aucune personne trouvée
              </div>
            </div>
            <p v-else class="text-gray-400 text-sm">Aucune personne disponible</p>
          </div>
        </div>
      </div>

      <div class="flex justify-end space-x-3">
        <button type="button" @click="cancel" class="btn-secondary">Annuler</button>
        <button type="submit" class="btn-primary" :disabled="saving">
          <LoadingSpinner v-if="saving" size="sm" class="mr-2" />
          {{ isEdit ? 'Enregistrer' : 'Créer le compte' }}
        </button>
      </div>
    </form>
  </div>
</template>
