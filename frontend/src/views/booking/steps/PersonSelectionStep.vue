<template>
  <div class="p-6">
    <h2 class="text-xl font-semibold text-white mb-2">Pour qui est cette séance ?</h2>
    <p class="text-gray-400 mb-6">
      {{ bookingStore.isNewClient
        ? 'Indiquez les informations de la personne qui profitera de la séance Snoezelen.'
        : 'Sélectionnez la personne qui profitera de la séance Snoezelen.'
      }}
    </p>

    <!-- New client: just enter person info -->
    <template v-if="bookingStore.isNewClient">
      <div class="space-y-4">
        <div>
          <label for="person-firstname" class="block text-sm font-medium text-gray-300 mb-1">
            Prénom <span class="text-red-400">*</span>
          </label>
          <input
            id="person-firstname"
            v-model="bookingStore.newPerson.firstName"
            type="text"
            placeholder="Prénom de la personne"
            class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
        </div>

        <div>
          <label for="person-lastname" class="block text-sm font-medium text-gray-300 mb-1">
            Nom <span class="text-red-400">*</span>
          </label>
          <input
            id="person-lastname"
            v-model="bookingStore.newPerson.lastName"
            type="text"
            placeholder="Nom de la personne"
            class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
        </div>
      </div>

      <p class="mt-4 text-sm text-gray-400">
        <svg class="inline w-4 h-4 mr-1 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        Il peut s'agir de vous-même ou d'un proche que vous accompagnez.
      </p>
    </template>

    <!-- Existing client: show persons list -->
    <template v-else>
      <!-- Existing persons list -->
      <div v-if="bookingStore.existingPersons.length > 0" class="mb-6">
        <div class="space-y-2">
          <button
            v-for="person in bookingStore.existingPersons"
            :key="person.id"
            @click="selectPerson(person)"
            :class="[
              'w-full p-4 rounded-lg border-2 text-left transition-all duration-200 flex items-center',
              isPersonSelected(person)
                ? 'border-indigo-500 bg-indigo-500/20'
                : 'border-gray-600 hover:border-indigo-400'
            ]"
          >
            <div
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 mr-3',
                isPersonSelected(person) ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400'
              ]"
            >
              {{ person.first_name.charAt(0) }}{{ person.last_name.charAt(0) }}
            </div>
            <div>
              <p class="font-medium text-white">
                {{ person.first_name }} {{ person.last_name }}
              </p>
            </div>
            <svg
              v-if="isPersonSelected(person)"
              class="w-5 h-5 ml-auto text-indigo-400"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
      </div>

      <!-- No persons found -->
      <div v-if="bookingStore.existingPersons.length === 0 && !showNewPersonForm" class="mb-6 p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
        <p class="text-sm text-amber-300">
          Aucune personne enregistrée pour cette adresse email. Créez une fiche ci-dessous.
        </p>
      </div>

      <!-- Add new person option -->
      <div :class="bookingStore.existingPersons.length > 0 ? 'border-t border-gray-700 pt-6' : ''">
        <button
          @click="toggleNewPerson"
          :class="[
            'w-full p-4 rounded-lg border-2 text-left transition-all duration-200 flex items-center',
            showNewPersonForm || (bookingStore.selectedPersonId === null && bookingStore.newPerson.firstName)
              ? 'border-indigo-500 bg-indigo-500/20'
              : 'border-dashed border-gray-600 hover:border-indigo-400'
          ]"
        >
          <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center flex-shrink-0 mr-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
          </div>
          <div>
            <p class="font-medium text-white">Ajouter une nouvelle personne</p>
            <p class="text-sm text-gray-400">Créer une fiche pour quelqu'un d'autre</p>
          </div>
        </button>

        <!-- New person form -->
        <div v-if="showNewPersonForm" class="mt-4 pl-4 border-l-2 border-indigo-500/50">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Prénom</label>
              <input
                v-model="bookingStore.newPerson.firstName"
                type="text"
                placeholder="Prénom"
                class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                @input="clearPersonSelection"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Nom</label>
              <input
                v-model="bookingStore.newPerson.lastName"
                type="text"
                placeholder="Nom"
                class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                @input="clearPersonSelection"
              />
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useBookingStore } from '@/stores/booking'

const bookingStore = useBookingStore()

const showNewPersonForm = ref(false)

onMounted(() => {
  // If no persons found for existing client, show new person form
  if (!bookingStore.isNewClient && bookingStore.existingPersons.length === 0) {
    showNewPersonForm.value = true
  }
})

function selectPerson(person) {
  // Reset date/time if selecting a different person
  if (bookingStore.selectedPersonId !== person.id) {
    bookingStore.resetDateTimeSelection()
  }
  bookingStore.selectedPersonId = person.id
  bookingStore.newPerson = { firstName: '', lastName: '' }
  showNewPersonForm.value = false

  // Auto-advance to next step
  nextTick(() => {
    bookingStore.nextStep()
  })
}

function isPersonSelected(person) {
  return bookingStore.selectedPersonId === person.id
}

function toggleNewPerson() {
  showNewPersonForm.value = !showNewPersonForm.value
  if (showNewPersonForm.value) {
    bookingStore.selectedPersonId = null
  }
}

function clearPersonSelection() {
  bookingStore.selectedPersonId = null
}
</script>
