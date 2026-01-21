<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const authStore = useAuthStore()

const email = ref('')
const submitted = ref(false)
const error = ref('')

async function handleSubmit() {
  if (!email.value) {
    error.value = 'Veuillez saisir votre adresse email'
    return
  }

  error.value = ''

  try {
    await authStore.requestMagicLink(email.value)
    submitted.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Une erreur est survenue'
  }
}
</script>

<template>
  <!-- Glass card -->
  <div class="backdrop-blur-xl bg-white/5 border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
    <!-- Success state -->
    <div v-if="submitted" class="p-8 text-center">
      <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-emerald-400 to-green-500 flex items-center justify-center shadow-lg shadow-emerald-500/25">
        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </div>
      <h2 class="text-2xl font-semibold text-white mb-3">Vérifiez votre boîte mail</h2>
      <p class="text-gray-400 mb-6">
        Si un compte existe pour <span class="text-purple-400 font-medium">{{ email }}</span>, vous recevrez un lien de connexion dans quelques instants.
      </p>
      <button
        @click="submitted = false; email = ''"
        class="text-purple-400 hover:text-purple-300 font-medium transition-colors inline-flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Utiliser une autre adresse
      </button>
    </div>

    <!-- Login form -->
    <form v-else @submit.prevent="handleSubmit" class="p-5 p-xl-6">
      <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-white">Connexion</h2>
        <p class="text-gray-400 mt-2">Recevez un lien de connexion sécurisé</p>
      </div>

      <!-- Error message -->
      <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-red-400 text-sm font-medium">{{ error }}</p>
          </div>
          <button type="button" @click="error = ''" class="ml-auto text-red-400/60 hover:text-red-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Email input -->
      <div class="mb-6">
        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Adresse email</label>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
            </svg>
          </div>
          <input
            id="email"
            v-model="email"
            type="email"
            class="w-full pl-12 pr-4 py-3.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all"
            placeholder="votre@email.com"
            autocomplete="email"
            required
          />
        </div>
      </div>

      <!-- Submit button -->
      <button
        type="submit"
        class="w-full py-3.5 px-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-medium rounded-xl shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        :disabled="authStore.loading"
      >
        <LoadingSpinner v-if="authStore.loading" size="sm" />
        <span v-if="!authStore.loading">Recevoir le lien de connexion</span>
        <span v-else>Envoi en cours...</span>
        <svg v-if="!authStore.loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>
      </button>

      <!-- Info text -->
      <div class="mt-6 flex items-center justify-center gap-2 text-gray-500 text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span>Connexion sécurisée sans mot de passe</span>
      </div>
    </form>
  </div>
</template>
