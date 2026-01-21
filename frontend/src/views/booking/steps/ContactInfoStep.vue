<template>
  <div class="p-6">
    <!-- Existing client: show masked info -->
    <template v-if="isExistingClient">
      <h2 class="text-xl font-semibold text-white mb-2">Vérifiez vos informations</h2>
      <p class="text-gray-400 mb-6">
        Confirmez que vos coordonnées sont correctes pour recevoir la confirmation.
      </p>

      <!-- Masked client info display -->
      <div class="space-y-4 mb-6">
        <!-- Client type badge -->
        <div class="p-4 bg-gray-700/30 border border-gray-600/50 rounded-lg">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <svg v-if="bookingStore.existingClientInfo.client_type === 'professional'" class="w-5 h-5 text-amber-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <svg v-else class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <div>
                <p class="text-sm text-gray-400">Type de client</p>
                <p class="text-white font-medium">
                  {{ bookingStore.existingClientInfo.client_type_label || 'Particulier' }}
                  <span v-if="bookingStore.existingClientInfo.has_company" class="text-gray-400">
                    - {{ bookingStore.existingClientInfo.company_name }}
                  </span>
                </p>
              </div>
            </div>
            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
        </div>

        <!-- Email (masked) -->
        <div class="p-4 bg-gray-700/30 border border-gray-600/50 rounded-lg">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <div>
                <p class="text-sm text-gray-400">Adresse email</p>
                <p class="text-white font-medium">{{ bookingStore.existingClientInfo.email_masked }}</p>
              </div>
            </div>
            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
        </div>

        <!-- Phone (masked or input) -->
        <div class="p-4 bg-gray-700/30 border border-gray-600/50 rounded-lg">
          <div v-if="bookingStore.existingClientInfo.has_phone" class="flex items-center justify-between">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              <div>
                <p class="text-sm text-gray-400">Téléphone</p>
                <p class="text-white font-medium">Se terminant par {{ bookingStore.existingClientInfo.phone_masked }}</p>
              </div>
            </div>
            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>

          <!-- No phone: allow input -->
          <div v-else>
            <div class="flex items-start">
              <svg class="w-5 h-5 text-amber-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              <div class="flex-1">
                <p class="text-sm text-gray-400 mb-2">Téléphone mobile (optionnel)</p>
                <input
                  v-model="bookingStore.clientInfo.phone"
                  type="tel"
                  placeholder="06 12 34 56 78"
                  class="w-full px-4 py-2.5 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
                <p class="mt-1.5 text-xs text-gray-500">
                  Ajoutez votre numéro pour recevoir un SMS de rappel la veille
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Reminder info -->
      <div class="p-4 bg-indigo-500/10 border border-indigo-500/30 rounded-lg mb-6">
        <div class="flex items-start">
          <svg class="w-5 h-5 text-indigo-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <div>
            <p class="text-sm text-indigo-300 font-medium">Rappel automatique</p>
            <p class="text-sm text-indigo-200/70 mt-1">
              Vous recevrez un rappel de votre rendez-vous la veille par email{{ hasPhoneForReminder ? ' et par SMS' : '' }}.
            </p>
          </div>
        </div>
      </div>

    </template>

    <!-- New client: show full form -->
    <template v-else>
      <h2 class="text-xl font-semibold text-white mb-2">Vos coordonnées</h2>
      <p class="text-gray-400 mb-6">
        Ces informations nous permettront de vous envoyer la confirmation et les rappels.
      </p>

      <div class="space-y-4">
        <!-- Client Type selection -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            Vous êtes <span class="text-red-400">*</span>
          </label>
          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              @click="bookingStore.clientInfo.clientType = 'personal'"
              :class="[
                'flex items-center justify-center px-4 py-3 border rounded-lg transition-all',
                bookingStore.clientInfo.clientType === 'personal'
                  ? 'border-indigo-500 bg-indigo-500/20 text-white'
                  : 'border-gray-600 bg-gray-700/30 text-gray-400 hover:border-gray-500'
              ]"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Particulier
            </button>
            <button
              type="button"
              @click="bookingStore.clientInfo.clientType = 'professional'"
              :class="[
                'flex items-center justify-center px-4 py-3 border rounded-lg transition-all',
                bookingStore.clientInfo.clientType === 'professional'
                  ? 'border-indigo-500 bg-indigo-500/20 text-white'
                  : 'border-gray-600 bg-gray-700/30 text-gray-400 hover:border-gray-500'
              ]"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Professionnel
            </button>
          </div>
        </div>

        <!-- Professional fields (only shown if professional) -->
        <template v-if="bookingStore.clientInfo.clientType === 'professional'">
          <!-- Company name -->
          <div>
            <label for="client-company" class="block text-sm font-medium text-gray-300 mb-1">
              Nom de l'entreprise / établissement
            </label>
            <input
              id="client-company"
              v-model="bookingStore.clientInfo.companyName"
              type="text"
              placeholder="Nom de votre structure"
              class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>

          <!-- SIRET -->
          <div>
            <label for="client-siret" class="block text-sm font-medium text-gray-300 mb-1">
              SIRET
              <span class="text-gray-500 font-normal">(optionnel)</span>
            </label>
            <input
              id="client-siret"
              v-model="bookingStore.clientInfo.siret"
              type="text"
              placeholder="123 456 789 00012"
              maxlength="17"
              class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
            <p class="mt-1 text-xs text-gray-500">
              14 chiffres pour la facturation professionnelle
            </p>
          </div>
        </template>

        <!-- First name -->
        <div>
          <label for="client-firstname" class="block text-sm font-medium text-gray-300 mb-1">
            Prénom <span class="text-red-400">*</span>
          </label>
          <input
            id="client-firstname"
            v-model="bookingStore.clientInfo.firstName"
            type="text"
            placeholder="Votre prénom"
            class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
        </div>

        <!-- Last name -->
        <div>
          <label for="client-lastname" class="block text-sm font-medium text-gray-300 mb-1">
            Nom <span class="text-red-400">*</span>
          </label>
          <input
            id="client-lastname"
            v-model="bookingStore.clientInfo.lastName"
            type="text"
            placeholder="Votre nom"
            class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
        </div>

        <!-- Email -->
        <div>
          <label for="client-email" class="block text-sm font-medium text-gray-300 mb-1">
            Adresse email <span class="text-red-400">*</span>
          </label>
          <input
            id="client-email"
            v-model="bookingStore.clientInfo.email"
            type="email"
            placeholder="votre@email.com"
            class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
          <p v-if="bookingStore.emailConfirmationRequired" class="mt-1 text-xs text-gray-500">
            Vous recevrez un email pour confirmer votre rendez-vous
          </p>
          <p v-else class="mt-1 text-xs text-gray-500">
            Vous recevrez un email de confirmation avec les détails de votre rendez-vous
          </p>
        </div>

        <!-- Phone (optional) -->
        <div>
          <label for="client-phone" class="block text-sm font-medium text-gray-300 mb-1">
            Téléphone mobile
            <span class="text-gray-500 font-normal">(optionnel)</span>
          </label>
          <input
            id="client-phone"
            v-model="bookingStore.clientInfo.phone"
            type="tel"
            placeholder="06 12 34 56 78"
            class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
          <p class="mt-1 text-xs text-gray-500">
            Pour recevoir un SMS de rappel la veille du rendez-vous
          </p>
        </div>

        <!-- GDPR Consent -->
        <div class="pt-4 border-t border-gray-700">
          <label class="flex items-start">
            <input
              v-model="bookingStore.gdprConsent"
              type="checkbox"
              class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded"
            />
            <span class="ml-3 text-sm text-gray-400">
              J'accepte que mes données personnelles soient enregistrées pour la gestion de mon rendez-vous et le suivi des séances.
              <span class="text-red-400">*</span>
            </span>
          </label>
          <p class="mt-2 ml-7 text-xs text-gray-500">
            Conformément au RGPD, vous pouvez demander l'accès, la rectification ou la suppression de vos données à tout moment.
          </p>
        </div>
      </div>
    </template>

    <!-- Summary (shown for both cases) -->
    <div class="mt-6 p-4 bg-gray-700/30 border border-gray-600/50 rounded-lg">
      <h3 class="text-sm font-medium text-gray-300 mb-3">Récapitulatif de votre réservation</h3>
      <dl class="space-y-2 text-sm">
        <div class="flex justify-between">
          <dt class="text-gray-500">Bénéficiaire :</dt>
          <dd class="font-medium text-white">
            {{ bookingStore.personInfo.firstName }} {{ bookingStore.personInfo.lastName }}
          </dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-500">Type de séance :</dt>
          <dd class="font-medium text-white">
            {{ bookingStore.durationInfo.label }}
          </dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-500">Date et heure :</dt>
          <dd class="font-medium text-white">
            {{ formattedDateTime }}
          </dd>
        </div>
      </dl>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useBookingStore } from '@/stores/booking'

const bookingStore = useBookingStore()

// Check if this is an existing client (has existing client info)
const isExistingClient = computed(() => {
  return bookingStore.existingClientInfo !== null
})

// Check if user has a phone (either existing or newly entered)
const hasPhoneForReminder = computed(() => {
  if (bookingStore.existingClientInfo?.has_phone) {
    return true
  }
  return bookingStore.clientInfo.phone?.trim().length > 0
})

const formattedDateTime = computed(() => {
  if (!bookingStore.selectedDate || !bookingStore.selectedTime) return '-'

  const [year, month, day] = bookingStore.selectedDate.split('-')
  const date = new Date(year, month - 1, day)
  const dateStr = date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  })

  return `${dateStr} à ${bookingStore.selectedTime}`
})
</script>
