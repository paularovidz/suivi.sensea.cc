<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { promoCodesApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const router = useRouter()

const loading = ref(true)
const promoCodes = ref([])
const pagination = ref({ page: 1, limit: 20, total: 0, pages: 0 })
const labels = ref({ discount_types: {}, application_modes: {} })
const confirmDialog = ref(null)
const promoToDelete = ref(null)
const searchQuery = ref('')
const filterActive = ref(null)
const filterMode = ref('')
let searchTimeout = null

onMounted(async () => {
  await loadPromoCodes()
})

// Debounced search
watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadPromoCodes(1)
  }, 300)
})

watch([filterActive, filterMode], () => {
  loadPromoCodes(1)
})

async function loadPromoCodes(page = 1) {
  loading.value = true
  try {
    const params = { page, limit: 20 }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }
    if (filterActive.value !== null) {
      params.is_active = filterActive.value
    }
    if (filterMode.value) {
      params.application_mode = filterMode.value
    }
    const response = await promoCodesApi.getAll(params)
    promoCodes.value = response.data.data.promo_codes
    pagination.value = response.data.data.pagination
    labels.value = response.data.data.labels
  } catch (e) {
    console.error('Error loading promo codes:', e)
  } finally {
    loading.value = false
  }
}

function confirmDelete(promo) {
  promoToDelete.value = promo
  confirmDialog.value?.open()
}

async function handleDelete() {
  if (!promoToDelete.value) return
  try {
    await promoCodesApi.delete(promoToDelete.value.id)
    await loadPromoCodes(pagination.value.page)
  } catch (e) {
    console.error('Error deleting promo code:', e)
    alert(e.response?.data?.message || 'Erreur lors de la suppression')
  } finally {
    promoToDelete.value = null
  }
}

function goToPromo(promoId) {
  router.push(`/app/promo-codes/${promoId}`)
}

function getDiscountLabel(promo) {
  const value = Number(promo.discount_value)
  switch (promo.discount_type) {
    case 'percentage':
      return `-${value}%`
    case 'fixed_amount':
      return `-${value.toFixed(2).replace('.', ',')} €`
    case 'free_session':
      return 'Gratuit'
    default:
      return ''
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

function isExpired(promo) {
  if (!promo.valid_until) return false
  return new Date(promo.valid_until) < new Date()
}

function getStatusBadge(promo) {
  if (!promo.is_active) {
    return { class: 'badge-danger', text: 'Inactif' }
  }
  if (isExpired(promo)) {
    return { class: 'badge-warning', text: 'Expiré' }
  }
  if (promo.max_uses_total && promo.usage_count >= promo.max_uses_total) {
    return { class: 'badge-warning', text: 'Épuisé' }
  }
  return { class: 'badge-success', text: 'Actif' }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex flex-1 gap-3 flex-wrap">
        <div class="relative flex-1 min-w-[200px] max-w-md">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par code, nom..."
            class="w-full pl-10 pr-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
          <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <select
          v-model="filterActive"
          class="px-3 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-gray-300 focus:ring-2 focus:ring-primary-500"
        >
          <option :value="null">Tous les statuts</option>
          <option :value="true">Actifs</option>
          <option :value="false">Inactifs</option>
        </select>
        <select
          v-model="filterMode"
          class="px-3 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-gray-300 focus:ring-2 focus:ring-primary-500"
        >
          <option value="">Tous les modes</option>
          <option value="manual">Code manuel</option>
          <option value="automatic">Automatique</option>
        </select>
      </div>
      <RouterLink to="/app/promo-codes/new" class="btn-primary whitespace-nowrap">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nouveau code promo
      </RouterLink>
    </div>

    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <template v-else>
      <!-- Empty state -->
      <div v-if="promoCodes.length === 0" class="bg-gray-800 rounded-xl border border-gray-700 p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        </svg>
        <p class="mt-4 text-gray-400">
          {{ searchQuery ? 'Aucun code promo ne correspond à votre recherche' : 'Aucun code promo' }}
        </p>
        <RouterLink to="/app/promo-codes/new" class="btn-primary mt-4">
          Créer votre premier code promo
        </RouterLink>
      </div>

      <div v-else class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full text-sm text-left">
          <thead>
            <tr class="bg-gray-800/50">
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Code / Nom</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Type</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Remise</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Utilisation</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Validité</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Statut</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="promo in promoCodes"
              :key="promo.id"
              @click="goToPromo(promo.id)"
              class="cursor-pointer border-t border-gray-700 hover:bg-gray-700/50"
            >
              <td class="px-4 py-3">
                <div>
                  <div v-if="promo.code" class="font-mono font-bold text-indigo-400">{{ promo.code }}</div>
                  <div v-else class="text-xs text-gray-500 italic">Auto</div>
                  <div class="text-sm text-gray-300">{{ promo.name }}</div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span :class="promo.application_mode === 'automatic' ? 'badge-info' : 'badge-gray'">
                  {{ promo.application_mode === 'automatic' ? 'Auto' : 'Manuel' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="font-medium text-green-400">{{ getDiscountLabel(promo) }}</span>
                <div class="text-xs text-gray-500">
                  {{ labels.discount_types[promo.discount_type] || promo.discount_type }}
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="text-white">{{ promo.usage_count || 0 }}</span>
                <span v-if="promo.max_uses_total" class="text-gray-500"> / {{ promo.max_uses_total }}</span>
                <span v-else class="text-gray-500"> / illimité</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-400">
                <div v-if="promo.valid_from || promo.valid_until">
                  <div v-if="promo.valid_from">Du {{ formatDate(promo.valid_from) }}</div>
                  <div v-if="promo.valid_until">Au {{ formatDate(promo.valid_until) }}</div>
                </div>
                <span v-else class="text-gray-500">Illimitée</span>
              </td>
              <td class="px-4 py-3">
                <span :class="getStatusBadge(promo).class">
                  {{ getStatusBadge(promo).text }}
                </span>
              </td>
              <td class="px-4 py-3 text-right" @click.stop>
                <div class="flex justify-end space-x-2">
                  <RouterLink :to="`/app/promo-codes/${promo.id}/edit`" class="btn-secondary btn-sm">
                    Modifier
                  </RouterLink>
                  <button @click="confirmDelete(promo)" class="btn-danger btn-sm">
                    Supprimer
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination.pages > 1" class="px-4 py-3 border-t border-gray-700 flex items-center justify-between">
          <div class="text-sm text-gray-400">
            {{ pagination.total }} code(s) promo
          </div>
          <div class="flex space-x-2">
            <button
              v-for="page in pagination.pages"
              :key="page"
              @click="loadPromoCodes(page)"
              :class="[
                'px-3 py-1 text-sm rounded',
                page === pagination.page
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

    <ConfirmDialog
      ref="confirmDialog"
      title="Supprimer ce code promo ?"
      :message="`Êtes-vous sûr de vouloir supprimer le code promo '${promoToDelete?.name}' ?${(promoToDelete?.usage_count || 0) > 0 ? ' Ce code a déjà été utilisé ' + promoToDelete.usage_count + ' fois et sera désactivé au lieu d\'être supprimé.' : ''}`"
      confirm-text="Supprimer"
      danger
      @confirm="handleDelete"
    />
  </div>
</template>
