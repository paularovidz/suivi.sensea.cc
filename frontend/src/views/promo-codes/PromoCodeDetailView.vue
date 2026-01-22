<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { promoCodesApi } from '@/services/api'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const promoCode = ref(null)
const stats = ref(null)
const labels = ref({ discount_types: {}, application_modes: {} })
const usages = ref([])
const usagesPagination = ref({ page: 1, limit: 10, total: 0, pages: 0 })
const loadingUsages = ref(false)
const confirmDialog = ref(null)
const error = ref(null)

onMounted(async () => {
  await loadPromoCode()
  await loadUsages()
})

async function loadPromoCode() {
  loading.value = true
  error.value = null
  try {
    const response = await promoCodesApi.getById(route.params.id)
    promoCode.value = response.data.data.promo_code
    stats.value = response.data.data.stats
    labels.value = response.data.data.labels
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur lors du chargement'
    console.error('Error loading promo code:', e)
  } finally {
    loading.value = false
  }
}

async function loadUsages(page = 1) {
  loadingUsages.value = true
  try {
    const response = await promoCodesApi.getUsages(route.params.id, { page, limit: 10 })
    usages.value = response.data.data.usages
    usagesPagination.value = response.data.data.pagination
  } catch (e) {
    console.error('Error loading usages:', e)
  } finally {
    loadingUsages.value = false
  }
}

function confirmDelete() {
  confirmDialog.value?.open()
}

async function handleDelete() {
  try {
    await promoCodesApi.delete(route.params.id)
    router.push('/app/promo-codes')
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur lors de la suppression')
    console.error('Error deleting promo code:', e)
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatPrice(value) {
  if (value === null || value === undefined) return '-'
  return Number(value).toFixed(2).replace('.', ',') + ' €'
}

const discountLabel = computed(() => {
  if (!promoCode.value) return ''
  switch (promoCode.value.discount_type) {
    case 'percentage':
      return `-${promoCode.value.discount_value}%`
    case 'fixed_amount':
      return `-${Number(promoCode.value.discount_value).toFixed(2).replace('.', ',')} €`
    case 'free_session':
      return 'Gratuit'
    default:
      return ''
  }
})

const statusBadge = computed(() => {
  if (!promoCode.value) return { class: '', text: '' }

  if (!promoCode.value.is_active) {
    return { class: 'badge-danger', text: 'Inactif' }
  }
  if (promoCode.value.valid_until && new Date(promoCode.value.valid_until) < new Date()) {
    return { class: 'badge-warning', text: 'Expiré' }
  }
  if (promoCode.value.max_uses_total && stats.value?.usage_count >= promoCode.value.max_uses_total) {
    return { class: 'badge-warning', text: 'Épuisé' }
  }
  return { class: 'badge-success', text: 'Actif' }
})

const usageProgress = computed(() => {
  if (!promoCode.value || !stats.value) return null
  if (!promoCode.value.max_uses_total) return null

  const used = stats.value.usage_count || 0
  const max = promoCode.value.max_uses_total
  const percent = Math.min(100, (used / max) * 100)

  return {
    used,
    max,
    percent,
    remaining: max - used
  }
})
</script>

<template>
  <div>
    <LoadingSpinner v-if="loading" size="lg" class="py-12" />

    <div v-else-if="error" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-red-400">
      {{ error }}
    </div>

    <template v-else-if="promoCode">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <h1 class="text-2xl font-bold text-white">{{ promoCode.name }}</h1>
            <span :class="statusBadge.class">{{ statusBadge.text }}</span>
          </div>
          <div v-if="promoCode.code" class="font-mono text-xl text-indigo-400 bg-indigo-500/10 px-3 py-1 rounded inline-block">
            {{ promoCode.code }}
          </div>
          <div v-else class="text-sm text-gray-500 italic">Promotion automatique</div>
          <p v-if="promoCode.description" class="text-gray-400 mt-2">{{ promoCode.description }}</p>
        </div>
        <div class="flex gap-2">
          <RouterLink :to="`/app/promo-codes/${promoCode.id}/edit`" class="btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Modifier
          </RouterLink>
          <button @click="confirmDelete" class="btn-danger">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Supprimer
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
          <div class="text-gray-400 text-sm">Remise</div>
          <div class="text-2xl font-bold text-green-400">{{ discountLabel }}</div>
          <div class="text-xs text-gray-500">{{ labels.discount_types[promoCode.discount_type] }}</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
          <div class="text-gray-400 text-sm">Utilisations</div>
          <div class="text-2xl font-bold text-white">{{ stats.usage_count || 0 }}</div>
          <div v-if="promoCode.max_uses_total" class="text-xs text-gray-500">
            sur {{ promoCode.max_uses_total }} max
          </div>
          <div v-else class="text-xs text-gray-500">illimité</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
          <div class="text-gray-400 text-sm">Total remisé</div>
          <div class="text-2xl font-bold text-amber-400">{{ formatPrice(stats.total_discount) }}</div>
          <div class="text-xs text-gray-500">économisés par les clients</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
          <div class="text-gray-400 text-sm">Mode</div>
          <div class="text-xl font-bold text-white">
            {{ promoCode.application_mode === 'automatic' ? 'Automatique' : 'Manuel' }}
          </div>
          <div class="text-xs text-gray-500">
            {{ promoCode.application_mode === 'automatic' ? 'S\'applique automatiquement' : 'Code à saisir' }}
          </div>
        </div>
      </div>

      <!-- Usage Progress (if limited) -->
      <div v-if="usageProgress" class="bg-gray-800 rounded-xl border border-gray-700 p-4 mb-6">
        <div class="flex justify-between items-center mb-2">
          <span class="text-gray-400">Progression des utilisations</span>
          <span class="text-white font-medium">{{ usageProgress.used }} / {{ usageProgress.max }}</span>
        </div>
        <div class="w-full bg-gray-700 rounded-full h-3">
          <div
            class="h-3 rounded-full transition-all"
            :class="usageProgress.percent >= 100 ? 'bg-red-500' : usageProgress.percent >= 75 ? 'bg-amber-500' : 'bg-green-500'"
            :style="{ width: usageProgress.percent + '%' }"
          ></div>
        </div>
        <div class="text-xs text-gray-500 mt-1">
          {{ usageProgress.remaining }} utilisation(s) restante(s)
        </div>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Configuration -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Configuration</h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-gray-400">Types de séances</dt>
              <dd class="text-white">
                <span v-if="promoCode.applies_to_discovery && promoCode.applies_to_regular">Toutes</span>
                <span v-else-if="promoCode.applies_to_discovery">Découverte uniquement</span>
                <span v-else-if="promoCode.applies_to_regular">Classique uniquement</span>
                <span v-else class="text-red-400">Aucune</span>
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-400">Limite par client</dt>
              <dd class="text-white">
                {{ promoCode.max_uses_per_user || 'Illimité' }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-400">Ciblage client</dt>
              <dd class="text-white">
                <span v-if="promoCode.target_client_type === 'personal'">Particuliers</span>
                <span v-else-if="promoCode.target_client_type === 'association'">Associations</span>
                <span v-else>Tous</span>
              </dd>
            </div>
            <div v-if="promoCode.target_user_id" class="flex justify-between">
              <dt class="text-gray-400">Utilisateur ciblé</dt>
              <dd class="text-white">
                {{ promoCode.target_first_name }} {{ promoCode.target_last_name }}
                <span class="text-gray-500 text-sm">({{ promoCode.target_email }})</span>
              </dd>
            </div>
          </dl>
        </div>

        <!-- Validity -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Validité</h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-gray-400">Début de validité</dt>
              <dd class="text-white">{{ promoCode.valid_from ? formatDate(promoCode.valid_from) : 'Immédiat' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-400">Fin de validité</dt>
              <dd class="text-white">{{ promoCode.valid_until ? formatDate(promoCode.valid_until) : 'Illimité' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-400">Créé le</dt>
              <dd class="text-white">{{ formatDate(promoCode.created_at) }}</dd>
            </div>
            <div v-if="promoCode.creator_first_name" class="flex justify-between">
              <dt class="text-gray-400">Créé par</dt>
              <dd class="text-white">{{ promoCode.creator_first_name }} {{ promoCode.creator_last_name }}</dd>
            </div>
            <div v-if="stats.first_use" class="flex justify-between">
              <dt class="text-gray-400">Première utilisation</dt>
              <dd class="text-white">{{ formatDate(stats.first_use) }}</dd>
            </div>
            <div v-if="stats.last_use" class="flex justify-between">
              <dt class="text-gray-400">Dernière utilisation</dt>
              <dd class="text-white">{{ formatDate(stats.last_use) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Usage History -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-700">
          <h2 class="text-lg font-semibold text-white">Historique d'utilisation</h2>
        </div>

        <LoadingSpinner v-if="loadingUsages" size="sm" class="py-8" />

        <div v-else-if="usages.length === 0" class="p-8 text-center text-gray-400">
          Ce code promo n'a pas encore été utilisé
        </div>

        <table v-else class="w-full text-sm text-left">
          <thead>
            <tr class="bg-gray-800/50">
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Date</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Client</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs">Bénéficiaire</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Prix original</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Remise</th>
              <th class="px-4 py-3 font-medium text-gray-400 uppercase tracking-wider text-xs text-right">Prix final</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="usage in usages"
              :key="usage.id"
              class="border-t border-gray-700 hover:bg-gray-700/50"
            >
              <td class="px-4 py-3 text-gray-300">{{ formatDate(usage.used_at) }}</td>
              <td class="px-4 py-3">
                <div class="text-white">{{ usage.user_first_name }} {{ usage.user_last_name }}</div>
                <div class="text-xs text-gray-500">{{ usage.user_email }}</div>
              </td>
              <td class="px-4 py-3 text-gray-300">
                {{ usage.person_first_name }} {{ usage.person_last_name }}
              </td>
              <td class="px-4 py-3 text-right text-gray-400">{{ formatPrice(usage.original_price) }}</td>
              <td class="px-4 py-3 text-right text-green-400">-{{ formatPrice(usage.discount_amount) }}</td>
              <td class="px-4 py-3 text-right text-white font-medium">{{ formatPrice(usage.final_price) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="usagesPagination.pages > 1" class="px-4 py-3 border-t border-gray-700 flex items-center justify-between">
          <div class="text-sm text-gray-400">
            {{ usagesPagination.total }} utilisation(s)
          </div>
          <div class="flex space-x-2">
            <button
              v-for="page in usagesPagination.pages"
              :key="page"
              @click="loadUsages(page)"
              :class="[
                'px-3 py-1 text-sm rounded',
                page === usagesPagination.page
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
      :message="`Êtes-vous sûr de vouloir supprimer le code promo '${promoCode?.name}' ?${(stats?.usage_count || 0) > 0 ? ' Ce code a été utilisé ' + stats.usage_count + ' fois.' : ''}`"
      confirm-text="Supprimer"
      danger
      @confirm="handleDelete"
    />
  </div>
</template>
