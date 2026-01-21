<script setup>
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { onMounted } from 'vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'

const authStore = useAuthStore()

onMounted(async () => {
  authStore.initializeFromStorage()
  // Apply dark mode permanently for the dashboard
  document.documentElement.classList.add('dark')
  // Refresh user data from server to get latest fields (client_type, etc.)
  if (authStore.isAuthenticated) {
    try {
      await authStore.fetchCurrentUser()
    } catch (e) {
      // Ignore errors - will be handled by auth guard
    }
  }
})
</script>

<template>
  <RouterView />
  <ToastContainer />
</template>
