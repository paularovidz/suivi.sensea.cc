<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <!-- Header (can be hidden for embed mode) -->
    <header v-if="!isEmbed" class="bg-gray-800/50 backdrop-blur-sm border-b border-gray-700/50">
      <div class="max-w-5xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
              <span class="text-white text-lg font-bold">S</span>
            </div>
            <div>
              <h1 class="text-xl font-semibold text-white">Sensea Snoezelen</h1>
              <p class="text-sm text-gray-400">Réservation de séance</p>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main :class="[isEmbed ? 'py-4' : 'py-8']">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <router-view />
      </div>
    </main>

    <!-- Footer (can be hidden for embed mode) -->
    <footer v-if="!isEmbed" class="mt-auto py-6">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-center text-sm text-gray-500">
          &copy; {{ new Date().getFullYear() }} Sensea Snoezelen. Tous droits réservés.
        </p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

// Check if we're in embed mode (via query param or route meta)
const isEmbed = computed(() => {
  return route.query.embed === 'true' || route.meta.embed === true
})
</script>
