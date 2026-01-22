<script setup>
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { bookingsApi } from '@/services/api'
import { useToastStore } from '@/stores/toast'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { formatPhoneForDisplay } from '@/utils/phone'

const props = defineProps({
  booking: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'deleted', 'status-changed', 'updated'])

const toast = useToastStore()
const confirmDialog = ref(null)
const changingStatus = ref(false)
const deleting = ref(false)

// Price editing
const editingPrice = ref(false)
const editedPrice = ref(null)
const savingPrice = ref(false)

const statusLabels = {
  pending: 'En attente',
  confirmed: 'Confirmé',
  cancelled: 'Annulé',
  completed: 'Effectué',
  no_show: 'Absent'
}

const statusClasses = {
  pending: 'bg-yellow-100 text-yellow-800 border-yellow-300',
  confirmed: 'bg-green-100 text-green-800 border-green-300',
  cancelled: 'bg-red-100 text-red-800 border-red-300',
  completed: 'bg-blue-100 text-blue-800 border-blue-300',
  no_show: 'bg-gray-100 text-gray-800 border-gray-300'
}

const clientTypeLabels = {
  personal: 'Particulier',
  association: 'Association',
  friends_family: 'Friends & Family'
}

const clientTypeLabel = computed(() => {
  return clientTypeLabels[props.booking?.client_type] || 'Particulier'
})

const durationLabel = computed(() => {
  return props.booking?.duration_type === 'discovery' ? 'Séance découverte (1h15)' : 'Séance classique (45min)'
})

const sessionEndTime = computed(() => {
  if (!props.booking?.session_date || !props.booking?.duration_display_minutes) return null
  const start = new Date(props.booking.session_date)
  return new Date(start.getTime() + props.booking.duration_display_minutes * 60000)
})

const blockEndTime = computed(() => {
  if (!props.booking?.session_date || !props.booking?.duration_blocked_minutes) return null
  const start = new Date(props.booking.session_date)
  return new Date(start.getTime() + props.booking.duration_blocked_minutes * 60000)
})

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

function formatTime(dateInput) {
  if (!dateInput) return '-'
  const date = dateInput instanceof Date ? dateInput : new Date(dateInput)
  return date.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

async function changeStatus(newStatus) {
  if (!props.booking || changingStatus.value) return

  changingStatus.value = true
  try {
    await bookingsApi.updateStatus(props.booking.id, newStatus)
    toast.success(`Statut changé en "${statusLabels[newStatus]}"`)
    emit('status-changed', { id: props.booking.id, status: newStatus })
  } catch (e) {
    toast.apiError(e, 'Erreur lors du changement de statut')
  } finally {
    changingStatus.value = false
  }
}

function confirmDelete() {
  confirmDialog.value?.open()
}

async function handleDelete() {
  if (!props.booking || deleting.value) return

  deleting.value = true
  try {
    const response = await bookingsApi.delete(props.booking.id)
    const emailSent = response.data?.data?.email_sent

    if (emailSent) {
      toast.success('Réservation supprimée et email d\'annulation envoyé au client')
    } else {
      toast.success('Réservation supprimée')
    }

    emit('deleted', props.booking.id)
    emit('close')
  } catch (e) {
    toast.apiError(e, 'Erreur lors de la suppression')
  } finally {
    deleting.value = false
  }
}

function close() {
  emit('close')
}

// Price editing functions
function startEditPrice() {
  editedPrice.value = props.booking?.price ?? 0
  editingPrice.value = true
}

function cancelEditPrice() {
  editingPrice.value = false
  editedPrice.value = null
}

async function savePrice() {
  if (savingPrice.value || !props.booking) return

  savingPrice.value = true
  try {
    await bookingsApi.update(props.booking.id, { price: editedPrice.value })
    toast.success('Tarif mis à jour')
    editingPrice.value = false
    // Emit updated event with new price
    emit('updated', { id: props.booking.id, price: editedPrice.value })
  } catch (e) {
    toast.apiError(e, 'Erreur lors de la mise à jour du tarif')
  } finally {
    savingPrice.value = false
  }
}
</script>

<template>
  <div v-if="booking" class="fixed inset-0 z-50 overflow-y-auto">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50 transition-opacity" @click="close"></div>

    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
      <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full transform transition-all">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Détails du rendez-vous</h3>
            <span :class="['inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full border', statusClasses[booking.status]]">
              {{ statusLabels[booking.status] }}
            </span>
          </div>
          <button @click="close" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="px-6 py-4 space-y-4">
          <!-- Date/Time -->
          <div class="flex items-start">
            <div class="p-2 bg-primary-100 rounded-lg mr-3">
              <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="flex-1">
              <div class="font-medium text-gray-900">{{ formatDate(booking.session_date) }}</div>
              <div class="text-gray-600">{{ durationLabel }}</div>
              <div class="text-sm text-gray-500 mt-1">
                <span class="font-medium">{{ formatTime(booking.session_date) }}</span>
                → <span class="font-medium">{{ formatTime(sessionEndTime) }}</span>
                <span class="text-gray-400">(séance)</span>
              </div>
              <div class="text-sm text-gray-400">
                Disponible à {{ formatTime(blockEndTime) }}
                <span class="text-xs">(+{{ booking.duration_blocked_minutes - booking.duration_display_minutes }}min inter-prestation)</span>
              </div>
            </div>
          </div>

          <!-- Price (editable) -->
          <div class="flex items-start">
            <div class="p-2 bg-amber-100 rounded-lg mr-3">
              <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 15.536c-1.171 1.952-3.07 1.952-4.242 0-1.172-1.953-1.172-5.119 0-7.072 1.171-1.952 3.07-1.952 4.242 0M8 10.5h4m-4 3h4m9-1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="flex-1">
              <div class="text-sm text-gray-500">Tarif</div>
              <div class="flex items-center gap-2">
                <div v-if="!editingPrice" class="flex items-center gap-2">
                  <span class="font-medium text-gray-900">{{ booking.price ?? '-' }} €</span>
                  <button
                    @click="startEditPrice"
                    class="p-1 text-gray-400 hover:text-gray-600 rounded"
                    title="Modifier le tarif"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </button>
                </div>
                <div v-else class="flex items-center gap-2">
                  <input
                    v-model.number="editedPrice"
                    type="number"
                    min="0"
                    step="1"
                    class="w-20 px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded focus:ring-primary-500 focus:border-primary-500"
                    @keyup.enter="savePrice"
                    @keyup.escape="cancelEditPrice"
                    @wheel.prevent
                  />
                  <span class="text-gray-500">€</span>
                  <button
                    @click="savePrice"
                    :disabled="savingPrice"
                    class="p-1 text-green-600 hover:text-green-700 rounded disabled:opacity-50"
                    title="Enregistrer"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </button>
                  <button
                    @click="cancelEditPrice"
                    class="p-1 text-gray-400 hover:text-gray-600 rounded"
                    title="Annuler"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Beneficiary -->
          <div class="flex items-start">
            <div class="p-2 bg-green-100 rounded-lg mr-3">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div>
              <div class="text-sm text-gray-500">Bénéficiaire</div>
              <div class="font-medium text-gray-900">
                <RouterLink
                  v-if="booking.person_id"
                  :to="`/app/persons/${booking.person_id}`"
                  class="hover:text-primary-600"
                  @click="close"
                >
                  {{ booking.person_first_name }} {{ booking.person_last_name }}
                </RouterLink>
                <span v-else>{{ booking.person_first_name }} {{ booking.person_last_name }}</span>
              </div>
            </div>
          </div>

          <!-- Client -->
          <div class="flex items-start">
            <div class="p-2 rounded-lg mr-3" :class="booking.client_type === 'association' ? 'bg-violet-100' : 'bg-blue-100'">
              <svg v-if="booking.client_type === 'association'" class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
              </svg>
              <svg v-else class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div>
              <div class="text-sm text-gray-500">Contact ({{ clientTypeLabel }})</div>
              <div class="font-medium text-gray-900">{{ booking.client_first_name }} {{ booking.client_last_name }}</div>
              <div class="text-sm text-gray-600">{{ booking.client_email }}</div>
              <div v-if="booking.client_phone" class="text-sm text-gray-600">{{ formatPhoneForDisplay(booking.client_phone) }}</div>
              <div v-if="booking.company_name" class="text-sm text-violet-600 font-medium">{{ booking.company_name }}</div>
            </div>
          </div>

          <!-- Session link -->
          <div v-if="booking.linked_session_id" class="bg-green-50 border border-green-200 rounded-lg p-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-green-700">Séance créée</span>
              <RouterLink
                :to="`/app/sessions/${booking.linked_session_id}`"
                class="text-sm font-medium text-green-700 hover:text-green-800"
                @click="close"
              >
                Voir la séance →
              </RouterLink>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-200">
          <!-- Status change buttons -->
          <div v-if="!booking.linked_session_id && booking.status !== 'cancelled'" class="mb-4">
            <div class="text-sm font-medium text-gray-700 mb-2">Changer le statut :</div>
            <div class="flex flex-wrap gap-2">
              <button
                v-if="booking.status !== 'confirmed'"
                @click="changeStatus('confirmed')"
                :disabled="changingStatus"
                class="px-3 py-1.5 text-sm font-medium bg-green-100 text-green-700 rounded-lg hover:bg-green-200 disabled:opacity-50"
              >
                Confirmer
              </button>
              <button
                v-if="booking.status !== 'cancelled'"
                @click="changeStatus('cancelled')"
                :disabled="changingStatus"
                class="px-3 py-1.5 text-sm font-medium bg-red-100 text-red-700 rounded-lg hover:bg-red-200 disabled:opacity-50"
              >
                Annuler
              </button>
              <button
                v-if="booking.status === 'confirmed'"
                @click="changeStatus('no_show')"
                :disabled="changingStatus"
                class="px-3 py-1.5 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-50"
              >
                Absent
              </button>
            </div>
          </div>

          <!-- Delete button -->
          <div class="flex items-center justify-between">
            <button
              v-if="!booking.linked_session_id"
              @click="confirmDelete"
              :disabled="deleting"
              class="flex items-center px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Supprimer
            </button>
            <span v-else class="text-sm text-gray-500">
              Supprimez d'abord la séance liée
            </span>

            <button @click="close" class="btn-secondary">
              Fermer
            </button>
          </div>
        </div>
      </div>
    </div>

    <ConfirmDialog
      ref="confirmDialog"
      title="Supprimer ce rendez-vous ?"
      :message="`Êtes-vous sûr de vouloir supprimer ce rendez-vous ? Un email d'annulation sera envoyé à ${booking.client_email}.`"
      confirm-text="Supprimer"
      danger
      @confirm="handleDelete"
    />
  </div>
</template>
