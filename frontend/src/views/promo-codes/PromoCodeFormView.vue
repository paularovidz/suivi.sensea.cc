<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { promoCodesApi, usersApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const route = useRoute()
const router = useRouter()

const isEditMode = computed(() => route.params.id && route.params.id !== 'new')
const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const generatingCode = ref(false)

const form = ref({
  code: '',
  name: '',
  description: '',
  discount_type: 'percentage',
  discount_value: 10,
  application_mode: 'manual',
  target_user_id: '',
  target_client_type: '',
  max_uses_total: '',
  max_uses_per_user: '',
  valid_from: '',
  valid_until: '',
  applies_to_discovery: true,
  applies_to_regular: true,
  is_active: true
})

const users = ref([])
const loadingUsers = ref(false)
const userSearch = ref('')
const showUserDropdown = ref(false)

const discountTypes = [
  { value: 'percentage', label: 'Pourcentage', icon: '%' },
  { value: 'fixed_amount', label: 'Montant fixe', icon: '€' },
  { value: 'free_session', label: 'Séance gratuite', icon: '0€' }
]

const applicationModes = [
  { value: 'manual', label: 'Code à saisir', description: 'Le client doit entrer le code lors de la réservation' },
  { value: 'automatic', label: 'Automatique', description: 'La remise s\'applique automatiquement si les conditions sont remplies' }
]

const clientTypes = [
  { value: '', label: 'Tous les clients' },
  { value: 'personal', label: 'Particuliers uniquement' },
  { value: 'association', label: 'Associations uniquement' },
  { value: 'friends_family', label: 'Friends & Family uniquement' }
]

onMounted(async () => {
  // Load users for target selection
  await loadUsers()

  if (isEditMode.value) {
    await loadPromoCode()
  }
})

async function loadUsers() {
  loadingUsers.value = true
  try {
    const response = await usersApi.getAll({ limit: 100 })
    users.value = response.data.data.users
  } catch (e) {
    console.error('Error loading users:', e)
  } finally {
    loadingUsers.value = false
  }
}

async function loadPromoCode() {
  loading.value = true
  error.value = null
  try {
    const response = await promoCodesApi.getById(route.params.id)
    const promo = response.data.data.promo_code

    form.value = {
      code: promo.code || '',
      name: promo.name,
      description: promo.description || '',
      discount_type: promo.discount_type,
      discount_value: promo.discount_value,
      application_mode: promo.application_mode,
      target_user_id: promo.target_user_id || '',
      target_client_type: promo.target_client_type || '',
      max_uses_total: promo.max_uses_total || '',
      max_uses_per_user: promo.max_uses_per_user || '',
      valid_from: promo.valid_from ? promo.valid_from.slice(0, 16) : '',
      valid_until: promo.valid_until ? promo.valid_until.slice(0, 16) : '',
      applies_to_discovery: promo.applies_to_discovery,
      applies_to_regular: promo.applies_to_regular,
      is_active: promo.is_active
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur lors du chargement'
    console.error('Error loading promo code:', e)
  } finally {
    loading.value = false
  }
}

async function generateCode() {
  generatingCode.value = true
  try {
    const response = await promoCodesApi.generateCode(8)
    form.value.code = response.data.data.code
  } catch (e) {
    console.error('Error generating code:', e)
  } finally {
    generatingCode.value = false
  }
}

async function handleSubmit() {
  saving.value = true
  error.value = null

  try {
    const data = {
      ...form.value,
      max_uses_total: form.value.max_uses_total ? parseInt(form.value.max_uses_total) : null,
      max_uses_per_user: form.value.max_uses_per_user ? parseInt(form.value.max_uses_per_user) : null,
      valid_from: form.value.valid_from || null,
      valid_until: form.value.valid_until || null,
      target_user_id: form.value.target_user_id || null,
      target_client_type: form.value.target_client_type || null
    }

    // For automatic mode, code is not required
    if (data.application_mode === 'automatic') {
      data.code = null
    }

    if (isEditMode.value) {
      await promoCodesApi.update(route.params.id, data)
    } else {
      await promoCodesApi.create(data)
    }

    router.push('/app/promo-codes')
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur lors de l\'enregistrement'
    console.error('Error saving promo code:', e)
  } finally {
    saving.value = false
  }
}

// When application mode changes, handle code requirement
watch(() => form.value.application_mode, (newMode) => {
  if (newMode === 'automatic') {
    form.value.code = ''
  }
})

const discountValueLabel = computed(() => {
  switch (form.value.discount_type) {
    case 'percentage':
      return 'Pourcentage de remise'
    case 'fixed_amount':
      return 'Montant de la remise (€)'
    case 'free_session':
      return 'Valeur (100 = gratuit)'
    default:
      return 'Valeur'
  }
})

const discountValueMax = computed(() => {
  return form.value.discount_type === 'percentage' ? 100 : 9999
})

// User search and selection
const filteredUsers = computed(() => {
  if (!userSearch.value) return users.value
  const search = userSearch.value.toLowerCase()
  return users.value.filter(u =>
    u.first_name.toLowerCase().includes(search) ||
    u.last_name.toLowerCase().includes(search) ||
    u.email.toLowerCase().includes(search) ||
    `${u.first_name} ${u.last_name}`.toLowerCase().includes(search)
  )
})

const selectedUser = computed(() => {
  if (!form.value.target_user_id) return null
  return users.value.find(u => u.id === form.value.target_user_id)
})

function selectUser(user) {
  form.value.target_user_id = user.id
  userSearch.value = ''
  showUserDropdown.value = false
}

function clearSelectedUser() {
  form.value.target_user_id = ''
  userSearch.value = ''
}

function handleUserSearchBlur() {
  // Delay to allow click on dropdown item
  setTimeout(() => {
    showUserDropdown.value = false
  }, 200)
}
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-white">
        {{ isEditMode ? 'Modifier le code promo' : 'Nouveau code promo' }}
      </h1>
      <p class="text-gray-400 mt-1">
        {{ isEditMode ? 'Modifiez les paramètres du code promo' : 'Créez un nouveau code promo ou une remise automatique' }}
      </p>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <div v-else-if="error && isEditMode" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-red-400">
      {{ error }}
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Application Mode -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Mode d'application</h2>
        <div class="grid grid-cols-2 gap-4">
          <button
            v-for="mode in applicationModes"
            :key="mode.value"
            type="button"
            @click="form.application_mode = mode.value"
            :class="[
              'p-4 border rounded-lg text-left transition-all',
              form.application_mode === mode.value
                ? 'border-indigo-500 bg-indigo-500/20'
                : 'border-gray-600 hover:border-gray-500'
            ]"
          >
            <div class="font-medium text-white">{{ mode.label }}</div>
            <div class="text-sm text-gray-400 mt-1">{{ mode.description }}</div>
          </button>
        </div>
      </div>

      <!-- Code & Name -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Identification</h2>
        <div class="space-y-4">
          <!-- Code (only for manual mode) -->
          <div v-if="form.application_mode === 'manual'">
            <label class="block text-sm font-medium text-gray-300 mb-1">
              Code promotionnel <span class="text-red-400">*</span>
            </label>
            <div class="flex gap-2">
              <input
                v-model="form.code"
                type="text"
                required
                placeholder="Ex: SUMMER2024"
                class="flex-1 px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white uppercase placeholder-gray-500 focus:ring-2 focus:ring-primary-500"
              />
              <button
                type="button"
                @click="generateCode"
                :disabled="generatingCode"
                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-500 disabled:opacity-50"
              >
                {{ generatingCode ? '...' : 'Générer' }}
              </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Le code que le client devra saisir (majuscules automatiques)</p>
          </div>

          <!-- Name -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">
              Nom <span class="text-red-400">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="Ex: Promo été 2024"
              class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500"
            />
            <p class="mt-1 text-xs text-gray-500">Nom interne pour identifier la promotion</p>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
            <textarea
              v-model="form.description"
              rows="2"
              placeholder="Description optionnelle..."
              class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500"
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Discount -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Remise</h2>
        <div class="space-y-4">
          <!-- Discount type -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Type de remise</label>
            <div class="grid grid-cols-3 gap-3">
              <button
                v-for="type in discountTypes"
                :key="type.value"
                type="button"
                @click="form.discount_type = type.value"
                :class="[
                  'p-3 border rounded-lg text-center transition-all',
                  form.discount_type === type.value
                    ? 'border-green-500 bg-green-500/20'
                    : 'border-gray-600 hover:border-gray-500'
                ]"
              >
                <div class="text-2xl font-bold text-green-400">{{ type.icon }}</div>
                <div class="text-sm text-gray-300 mt-1">{{ type.label }}</div>
              </button>
            </div>
          </div>

          <!-- Discount value -->
          <div v-if="form.discount_type !== 'free_session'">
            <label class="block text-sm font-medium text-gray-300 mb-1">
              {{ discountValueLabel }} <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <input
                v-model.number="form.discount_value"
                type="number"
                required
                min="0"
                :max="discountValueMax"
                step="0.01"
                class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500"
              />
              <span class="absolute right-4 top-2 text-gray-400">
                {{ form.discount_type === 'percentage' ? '%' : '€' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Session Types -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Types de séances</h2>
        <div class="space-y-3">
          <label class="flex items-center">
            <input
              v-model="form.applies_to_discovery"
              type="checkbox"
              class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded"
            />
            <span class="ml-3 text-gray-300">Séances découverte (1h15)</span>
          </label>
          <label class="flex items-center">
            <input
              v-model="form.applies_to_regular"
              type="checkbox"
              class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded"
            />
            <span class="ml-3 text-gray-300">Séances classiques (45 min)</span>
          </label>
        </div>
      </div>

      <!-- Usage Limits -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Limites d'utilisation</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Utilisations totales max</label>
            <input
              v-model="form.max_uses_total"
              type="number"
              min="1"
              placeholder="Illimité"
              class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Utilisations par client max</label>
            <input
              v-model="form.max_uses_per_user"
              type="number"
              min="1"
              placeholder="Illimité"
              class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>
      </div>

      <!-- Validity Period -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Période de validité</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Date de début</label>
            <input
              v-model="form.valid_from"
              type="datetime-local"
              class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Date de fin</label>
            <input
              v-model="form.valid_until"
              type="datetime-local"
              class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">Laissez vide pour une validité illimitée</p>
      </div>

      <!-- Targeting -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Ciblage (optionnel)</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Type de client</label>
            <select
              v-model="form.target_client_type"
              class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-primary-500"
            >
              <option v-for="type in clientTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Utilisateur spécifique</label>

            <!-- Selected user display -->
            <div v-if="selectedUser" class="flex items-center justify-between px-4 py-2 bg-indigo-500/20 border border-indigo-500/50 rounded-lg mb-2">
              <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium mr-3">
                  {{ selectedUser.first_name.charAt(0) }}{{ selectedUser.last_name.charAt(0) }}
                </div>
                <div>
                  <div class="text-white font-medium">{{ selectedUser.first_name }} {{ selectedUser.last_name }}</div>
                  <div class="text-xs text-gray-400">{{ selectedUser.email }}</div>
                </div>
              </div>
              <button
                type="button"
                @click="clearSelectedUser"
                class="p-1 text-gray-400 hover:text-white rounded transition-colors"
                title="Retirer"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Search input -->
            <div v-else class="relative">
              <input
                v-model="userSearch"
                type="text"
                @focus="showUserDropdown = true"
                @blur="handleUserSearchBlur"
                :disabled="loadingUsers"
                placeholder="Rechercher un utilisateur..."
                class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500"
              />
              <svg class="w-5 h-5 text-gray-400 absolute right-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>

              <!-- Dropdown list -->
              <div
                v-if="showUserDropdown && !loadingUsers"
                class="absolute z-10 w-full mt-1 bg-gray-800 border border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto"
              >
                <div
                  v-if="filteredUsers.length === 0"
                  class="px-4 py-3 text-sm text-gray-400"
                >
                  {{ userSearch ? 'Aucun utilisateur trouvé' : 'Commencez à taper pour rechercher...' }}
                </div>
                <button
                  v-for="user in filteredUsers"
                  :key="user.id"
                  type="button"
                  @mousedown.prevent="selectUser(user)"
                  class="w-full flex items-center px-4 py-2 hover:bg-gray-700 text-left transition-colors"
                >
                  <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-white text-sm font-medium mr-3">
                    {{ user.first_name.charAt(0) }}{{ user.last_name.charAt(0) }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-white font-medium truncate">{{ user.first_name }} {{ user.last_name }}</div>
                    <div class="text-xs text-gray-400 truncate">{{ user.email }}</div>
                  </div>
                  <span
                    v-if="user.client_type"
                    :class="[
                      'ml-2 px-2 py-0.5 text-xs rounded',
                      user.client_type === 'association' ? 'bg-violet-500/20 text-violet-300' :
                      user.client_type === 'friends_family' ? 'bg-amber-500/20 text-amber-300' :
                      'bg-gray-600 text-gray-300'
                    ]"
                  >
                    {{ user.client_type === 'association' ? 'Asso' : user.client_type === 'friends_family' ? 'F&F' : 'Part.' }}
                  </span>
                </button>
              </div>
            </div>

            <p class="mt-1 text-xs text-gray-500">Réservez cette promotion à un client spécifique</p>
          </div>
        </div>
      </div>

      <!-- Status -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <label class="flex items-center justify-between">
          <div>
            <span class="text-white font-medium">Code actif</span>
            <p class="text-sm text-gray-400">Le code peut être utilisé par les clients</p>
          </div>
          <input
            v-model="form.is_active"
            type="checkbox"
            class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded"
          />
        </label>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-red-400">
        {{ error }}
      </div>

      <!-- Actions -->
      <div class="flex justify-end space-x-4">
        <button
          type="button"
          @click="router.back()"
          class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-500"
        >
          Annuler
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50"
        >
          {{ saving ? 'Enregistrement...' : (isEditMode ? 'Mettre à jour' : 'Créer le code promo') }}
        </button>
      </div>
    </form>
  </div>
</template>
