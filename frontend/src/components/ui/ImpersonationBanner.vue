<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const loading = ref(false)

async function stopImpersonating() {
  loading.value = true
  try {
    await authStore.stopImpersonate()
  } catch (e) {
    console.error('Error stopping impersonation:', e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div v-if="authStore.isImpersonating" class="bg-amber-500 text-amber-950">
    <div class="max-w-7xl mx-auto px-4 py-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <span class="font-medium">
            Connecte en tant que <strong>{{ authStore.fullName }}</strong>
            <span class="hidden sm:inline">
              ({{ authStore.user?.email }})
            </span>
          </span>
        </div>
        <button
          @click="stopImpersonating"
          :disabled="loading"
          class="flex items-center space-x-1 px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
          </svg>
          <span>Revenir admin</span>
          <span class="hidden sm:inline">
            ({{ authStore.impersonator?.first_name }} {{ authStore.impersonator?.last_name }})
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
