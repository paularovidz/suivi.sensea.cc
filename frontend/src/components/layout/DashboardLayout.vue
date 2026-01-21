<script setup>
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const mobileMenuOpen = ref(false)

const navigation = computed(() => {
  const items = [
    { name: 'Tableau de bord', href: '/app/dashboard', icon: 'dashboard' },
    { name: 'Agenda', href: '/app/agenda', icon: 'agenda' },
    { name: 'Personnes', href: '/app/persons', icon: 'people' },
    { name: 'Séances', href: '/app/sessions', icon: 'calendar' },
    { name: 'Propositions', href: '/app/proposals', icon: 'lightbulb' }
  ]

  if (authStore.isAdmin) {
    items.push({ name: 'Utilisateurs', href: '/app/users', icon: 'users' })
    items.push({ name: 'Paramètres', href: '/app/settings', icon: 'settings' })
  }

  return items
})

function isActive(href) {
  return route.path.startsWith(href)
}

function toggleMobileMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

async function handleLogout() {
  await authStore.logout()
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Mobile menu button -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-200 px-4 py-3">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gradient">Sensea</h1>
        <button @click="toggleMobileMenu" class="p-2 text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div v-if="mobileMenuOpen" class="lg:hidden fixed inset-0 z-40 bg-white pt-16">
        <nav class="px-4 py-4 space-y-2">
          <RouterLink
            v-for="item in navigation"
            :key="item.href"
            :to="item.href"
            @click="mobileMenuOpen = false"
            :class="[
              'block px-4 py-3 rounded-lg font-medium transition-colors',
              isActive(item.href) ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:bg-gray-100'
            ]"
          >
            {{ item.name }}
          </RouterLink>
          <button
            @click="handleLogout"
            class="w-full text-left px-4 py-3 rounded-lg font-medium text-red-600 hover:bg-red-50 transition-colors"
          >
            Déconnexion
          </button>
        </nav>
      </div>
    </Transition>

    <!-- Sidebar (desktop) -->
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-white border-r border-gray-200">
      <div class="flex-1 flex flex-col min-h-0">
        <!-- Logo -->
        <div class="flex items-center h-16 px-6 border-b border-gray-200">
          <h1 class="text-2xl font-bold text-gradient">Sensea</h1>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
          <RouterLink
            v-for="item in navigation"
            :key="item.href"
            :to="item.href"
            :class="[
              'flex items-center px-4 py-2.5 rounded-lg font-medium transition-colors',
              isActive(item.href) ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:bg-gray-100'
            ]"
          >
            {{ item.name }}
          </RouterLink>
        </nav>

        <!-- User menu -->
        <div class="p-4 border-t border-gray-200">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full gradient-sensea flex items-center justify-center text-white font-medium">
              {{ authStore.user?.first_name?.[0] }}{{ authStore.user?.last_name?.[0] }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ authStore.fullName }}</p>
              <p class="text-xs text-gray-500 truncate">{{ authStore.user?.email }}</p>
            </div>
          </div>
          <button
            @click="handleLogout"
            class="mt-4 w-full btn-secondary text-sm"
          >
            Déconnexion
          </button>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <main class="lg:pl-64 pt-16 lg:pt-0">
      <div class="p-4 lg:p-8">
        <RouterView />
      </div>
    </main>
  </div>
</template>
