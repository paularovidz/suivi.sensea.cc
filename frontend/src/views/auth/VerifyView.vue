<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const verifying = ref(true)
const error = ref('')

onMounted(async () => {
  const token = route.params.token

  if (!token) {
    error.value = 'Token manquant'
    verifying.value = false
    return
  }

  try {
    await authStore.verifyMagicLink(token)

    // Redirect to dashboard or requested page
    const redirect = route.query.redirect || '/app/dashboard'
    router.push(redirect)
  } catch (e) {
    error.value = e.response?.data?.message || 'Lien invalide ou expiré'
    verifying.value = false
  }
})
</script>

<template>
  <!-- Glass card -->
  <div class="backdrop-blur-xl bg-white/5 border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
    <!-- Verifying state -->
    <div v-if="verifying" class="p-8 text-center">
      <div class="relative w-20 h-20 mx-auto mb-6">
        <!-- Spinning ring -->
        <div class="absolute inset-0 rounded-full border-4 border-purple-500/20"></div>
        <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-purple-500 animate-spin"></div>
        <!-- Inner icon -->
        <div class="absolute inset-3 rounded-full bg-gradient-to-br from-purple-500/20 to-indigo-500/20 flex items-center justify-center">
          <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
        </div>
      </div>
      <h2 class="text-2xl font-semibold text-white mb-2">Vérification en cours</h2>
      <p class="text-gray-400">Validation de votre lien de connexion...</p>
    </div>

    <!-- Error state -->
    <div v-else class="p-8 text-center">
      <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-red-500/20 to-orange-500/20 border border-red-500/30 flex items-center justify-center">
        <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>

      <h2 class="text-2xl font-semibold text-white mb-3">Échec de la vérification</h2>

      <!-- Error message -->
      <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-left">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center">
            <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <p class="text-red-400 text-sm">{{ error }}</p>
        </div>
      </div>

      <p class="text-gray-400 mb-6">
        Le lien de connexion est peut-être expiré ou a déjà été utilisé.
      </p>

      <RouterLink
        to="/login"
        class="inline-flex items-center justify-center gap-2 w-full py-3.5 px-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-medium rounded-xl shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all duration-200"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        Demander un nouveau lien
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>
