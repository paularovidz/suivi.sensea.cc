<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { settingsApi } from '@/services/api'
import ImpersonationBanner from '@/components/ui/ImpersonationBanner.vue'

const authStore = useAuthStore()
const route = useRoute()
const mobileMenuOpen = ref(false)

// SMS Credits alert
const smsCredits = ref(null)
const smsAlertDismissed = ref(false)
const SMS_LOW_THRESHOLD = 20

const showSmsAlert = computed(() => {
  return authStore.isAdmin &&
    smsCredits.value !== null &&
    smsCredits.value < SMS_LOW_THRESHOLD &&
    !smsAlertDismissed.value
})

onMounted(async () => {
  if (authStore.isAdmin) {
    await loadSmsCredits()
  }
})

async function loadSmsCredits() {
  try {
    const response = await settingsApi.getSmsCredits()
    const data = response.data.data || response.data
    if (data.configured) {
      smsCredits.value = data.credits_left || 0
    }
  } catch (e) {
    // Silently fail - not critical
    console.error('Failed to load SMS credits:', e)
  }
}

function dismissSmsAlert() {
  smsAlertDismissed.value = true
}

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
  <div class="min-h-screen bg-gray-900">
    <!-- Impersonation Banner -->
    <ImpersonationBanner />

    <!-- SMS Low Credits Alert -->
    <div
      v-if="showSmsAlert"
      class="bg-amber-900/80 border-b border-amber-700"
    >
      <div class="max-w-7xl mx-auto px-4 py-2 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between flex-wrap">
          <div class="flex items-center flex-1">
            <span class="flex p-1.5 rounded-lg bg-amber-800">
              <svg class="h-5 w-5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </span>
            <p class="ml-3 text-sm font-medium text-amber-100">
              <span class="font-bold">Attention :</span> Il ne reste que <span class="font-bold">{{ smsCredits }}</span> crédits SMS.
              <RouterLink to="/app/settings" class="underline hover:text-white ml-1">Voir les paramètres</RouterLink>
            </p>
          </div>
          <button
            @click="dismissSmsAlert"
            class="flex-shrink-0 ml-4 p-1 rounded-md hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500"
          >
            <svg class="h-5 w-5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile menu button -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-gray-800 border-b border-gray-700 px-4 py-3">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gradient">sensëa</h1>
        <button @click="toggleMobileMenu" class="p-2 text-gray-400 hover:text-gray-200">
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
      <div v-if="mobileMenuOpen" class="lg:hidden fixed inset-0 z-40 bg-gray-800 pt-16">
        <nav class="px-4 py-4 space-y-2">
          <RouterLink
            v-for="item in navigation"
            :key="item.href"
            :to="item.href"
            @click="mobileMenuOpen = false"
            :class="[
              'block px-4 py-3 rounded-lg font-medium transition-colors',
              isActive(item.href) ? 'bg-primary-900/50 text-primary-300' : 'text-gray-300 hover:bg-gray-700'
            ]"
          >
            {{ item.name }}
          </RouterLink>

          <!-- Lien externe vers la réservation publique -->
          <div class="pt-4 mt-4 border-t border-gray-700">
            <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Accès rapide</p>
            <a
              href="/booking"
              target="_blank"
              rel="noopener noreferrer"
              class="flex items-center px-4 py-3 rounded-lg font-medium text-gray-300 hover:bg-gray-700 transition-colors"
            >
              Réservation
              <svg class="w-4 h-4 ml-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>

          <button
            @click="handleLogout"
            class="w-full text-left px-4 py-3 rounded-lg font-medium text-red-400 hover:bg-red-900/30 transition-colors"
          >
            Déconnexion
          </button>
        </nav>
      </div>
    </Transition>

    <!-- Sidebar (desktop) -->
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-gray-800 border-r border-gray-700">
      <div class="flex-1 flex flex-col min-h-0">
        <!-- Logo -->
        <div class="flex items-center h-16 px-6 border-b border-gray-700">
          <h1 class="text-2xl font-bold text-gradient">sensëa</h1>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
          <RouterLink
            v-for="item in navigation"
            :key="item.href"
            :to="item.href"
            :class="[
              'flex items-center px-4 py-2.5 rounded-lg font-medium transition-colors',
              isActive(item.href) ? 'bg-primary-900/50 text-primary-300' : 'text-gray-300 hover:bg-gray-700'
            ]"
          >
            {{ item.name }}
          </RouterLink>

          <!-- Lien externe vers la réservation publique -->
          <div class="pt-4 mt-4 border-t border-gray-700">
            <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Accès rapide</p>
            <a
              href="/booking"
              target="_blank"
              rel="noopener noreferrer"
              class="flex items-center px-4 py-2.5 rounded-lg font-medium text-gray-300 hover:bg-gray-700 transition-colors"
            >
              <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              Réservation
              <svg class="w-4 h-4 ml-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
        </nav>

        <!-- User menu -->
        <div class="p-4 border-t border-gray-700">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full gradient-sensea flex items-center justify-center text-white font-medium">
              {{ authStore.user?.first_name?.[0] }}{{ authStore.user?.last_name?.[0] }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-100 truncate">{{ authStore.fullName }}</p>
              <p class="text-xs text-gray-400 truncate">{{ authStore.user?.email }}</p>
            </div>
          </div>
          <button
            @click="handleLogout"
            class="mt-4 w-full bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 px-4 py-2 text-sm font-medium rounded-lg transition-colors"
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
