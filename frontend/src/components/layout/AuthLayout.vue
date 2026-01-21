<script setup>
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-950 p-4 overflow-hidden relative">
    <!-- Animated background gradient orbs -->
    <div class="absolute inset-0 overflow-hidden">
      <div class="absolute -top-40 -left-40 w-80 h-80 bg-purple-600/30 rounded-full blur-[100px] animate-pulse"></div>
      <div class="absolute top-1/2 -right-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px] animate-pulse" style="animation-delay: 1s;"></div>
      <div class="absolute -bottom-40 left-1/3 w-80 h-80 bg-violet-600/25 rounded-full blur-[100px] animate-pulse" style="animation-delay: 2s;"></div>
    </div>

    <!-- Subtle grid pattern overlay -->
    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px]"></div>

    <!-- Content -->
    <div class="w-full max-w-5xl relative z-10">
      <!-- Logo -->
      <div class="lg:text-left mb-8 lg:mb-12">
        <div class="inline-flex items-center gap-4">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 shadow-lg shadow-purple-500/25">
            <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke-linecap="round"/>
              <path d="M12 6v6l4 2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">
              sensëa
            </h1>
            <p class="text-gray-500 text-sm">Suivi des séances Snoezelen</p>
          </div>
        </div>
      </div>

      <!-- Main content: Form + Features -->
      <div class="flex flex-col lg:flex-row gap-8 lg:gap-16 items-center">
        <!-- Left: Login form -->
        <div class="w-full max-w-md">
          <RouterView />
        </div>

        <!-- Right: Features -->
        <div class="flex flex-col gap-4 lg:gap-6 flex-1 max-w-md">
          <!-- Mobile: compact horizontal layout -->
          <div class="flex lg:hidden justify-center gap-6 pt-2">
            <!-- Feature 1 -->
            <div class="flex flex-col items-center gap-2">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
              <span class="text-xs text-gray-400 text-center">Historique des séances</span>
            </div>

            <!-- Feature 2 -->
            <div class="flex flex-col items-center gap-2">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500/20 to-green-500/20 border border-emerald-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <span class="text-xs text-gray-400 text-center">Réservation simplifiée</span>
            </div>

            <!-- Feature 3 -->
            <div class="flex flex-col items-center gap-2">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <span class="text-xs text-gray-400 text-center">Documents sécurisés</span>
            </div>
          </div>

          <!-- Desktop: full cards -->
          <div class="hidden lg:flex flex-col gap-6">
            <!-- Feature 1: Historique -->
            <div class="group flex items-start gap-4 p-4 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/[0.07] hover:border-purple-500/30 transition-all duration-300">
              <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-500/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
              <div>
                <h3 class="text-white font-medium mb-1">Historique complet</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                  Accédez à vos rendez-vous passés et aux factures de prestations
                </p>
              </div>
            </div>

            <!-- Feature 2: Réservation rapide -->
            <div class="group flex items-start gap-4 p-4 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/[0.07] hover:border-emerald-500/30 transition-all duration-300">
              <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500/20 to-green-500/20 border border-emerald-500/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <h3 class="text-white font-medium mb-1">Réservation simplifiée</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                  Prenez rendez-vous plus rapidement avec vos informations pré-remplies
                </p>
              </div>
            </div>

            <!-- Feature 3: Documents sécurisés -->
            <div class="group flex items-start gap-4 p-4 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/[0.07] hover:border-amber-500/30 transition-all duration-300">
              <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div>
                <h3 class="text-white font-medium mb-1">Documents sécurisés</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                  Partagez vos documents de manière sécurisée avec sensëa
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% {
    opacity: 0.4;
    transform: scale(1);
  }
  50% {
    opacity: 0.6;
    transform: scale(1.05);
  }
}

.animate-pulse {
  animation: pulse 8s ease-in-out infinite;
}
</style>
