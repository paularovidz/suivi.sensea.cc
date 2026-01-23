<template>
  <div class="min-h-screen bg-dark">
    <!-- Impersonation Banner (above header) -->
    <ImpersonationBanner />

    <!-- Header (can be hidden for embed mode) -->
    <header v-if="!isEmbed" class="header-dark">
      <div class="max-w-5xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
          <div>
            <span class="text-2xl font-logo text-white">sensëa</span>
            <p class="text-sm text-gray-400">Réservation de séance</p>
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
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ImpersonationBanner from '@/components/ui/ImpersonationBanner.vue'

const route = useRoute()

// Check if we're in embed mode (via query param or route meta)
const isEmbed = computed(() => {
  return route.query.embed === 'true' || route.meta.embed === true
})
</script>
