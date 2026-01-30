<template>
  <div class="space-y-6">
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-900/50 border border-red-700 rounded-lg p-4 text-red-300">
      {{ error }}
    </div>

    <!-- Settings Content -->
    <template v-else>
      <!-- Tabs Navigation -->
      <div class="border-b border-gray-700">
        <nav class="flex space-x-1 overflow-x-auto pb-px" aria-label="Tabs">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'px-4 py-2.5 text-sm font-medium rounded-t-lg whitespace-nowrap transition-colors',
              activeTab === tab.id
                ? 'bg-gray-800 text-white border-b-2 border-primary-500'
                : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800/50'
            ]"
          >
            <span class="flex items-center">
              <component :is="tab.icon" class="w-4 h-4 mr-2" />
              {{ tab.label }}
            </span>
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <!-- Tarifs Tab -->
        <div v-show="activeTab === 'pricing'" class="p-6 space-y-6">
          <div class="grid md:grid-cols-2 gap-6">
            <!-- Particuliers -->
            <div class="space-y-4">
              <h3 class="text-sm font-semibold text-primary-400 uppercase tracking-wide flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Particuliers
              </h3>
              <div class="bg-gray-900/50 rounded-lg p-4 space-y-4">
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Séance classique (45min)</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_regular_price"
                      class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 w-6">€</span>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Séance découverte (1h15)</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_discovery_price"
                      class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 w-6">€</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Associations -->
            <div class="space-y-4">
              <h3 class="text-sm font-semibold text-amber-400 uppercase tracking-wide flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Associations
              </h3>
              <div class="bg-gray-900/50 rounded-lg p-4 space-y-4">
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Séance classique (45min)</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_regular_price_association"
                      class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 w-6">€</span>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Séance découverte (1h15)</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_discovery_price_association"
                      class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 w-6">€</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Fidélité -->
          <div class="pt-4 border-t border-gray-700">
            <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Programme de fidélité</h3>
            <div class="flex items-center justify-between max-w-md">
              <label class="text-sm text-gray-300">Séances pour carte complète</label>
              <input
                type="number"
                v-model.number="formData.loyalty_sessions_required"
                class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                @wheel.prevent
              />
            </div>
            <p class="text-xs text-gray-500 mt-2">Après ce nombre de séances, le client reçoit une séance gratuite.</p>
          </div>
        </div>

        <!-- Horaires Tab -->
        <div v-show="activeTab === 'scheduling'" class="p-6 space-y-6">
          <!-- Horaires d'ouverture -->
          <div>
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide">Horaires d'ouverture</h3>
              <button
                @click="showBusinessHoursModal = true"
                class="px-3 py-1.5 text-sm bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg transition-colors"
              >
                Modifier
              </button>
            </div>
            <div class="bg-gray-900/50 rounded-lg p-4">
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div v-for="(dayName, dayIndex) in dayNames" :key="dayIndex" class="flex flex-col">
                  <span class="text-gray-500 text-xs">{{ dayName }}</span>
                  <span v-if="businessHours[dayIndex]" class="text-gray-300">
                    {{ businessHours[dayIndex].open }} - {{ businessHours[dayIndex].close }}
                  </span>
                  <span v-else class="text-gray-600 italic">Fermé</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Créneaux -->
          <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide">Créneaux</h3>
              <div class="bg-gray-900/50 rounded-lg p-4 space-y-3">
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Premier créneau</label>
                  <input
                    type="time"
                    v-model="formData.first_slot_time"
                    class="w-28 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white"
                  />
                </div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Début pause déjeuner</label>
                  <input
                    type="time"
                    v-model="formData.lunch_break_start"
                    class="w-28 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white"
                  />
                </div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Fin pause déjeuner</label>
                  <input
                    type="time"
                    v-model="formData.lunch_break_end"
                    class="w-28 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white"
                  />
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide">Durées des séances</h3>
              <div class="bg-gray-900/50 rounded-lg p-4 space-y-3">
                <div class="text-xs text-gray-500 mb-2">Séance classique</div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Durée affichée</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_regular_display_minutes"
                      class="w-16 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 text-sm">min</span>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Pause après</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_regular_pause_minutes"
                      class="w-16 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 text-sm">min</span>
                  </div>
                </div>
                <div class="border-t border-gray-700 my-3"></div>
                <div class="text-xs text-gray-500 mb-2">Séance découverte</div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Durée affichée</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_discovery_display_minutes"
                      class="w-16 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 text-sm">min</span>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <label class="text-sm text-gray-300">Pause après</label>
                  <div class="flex items-center">
                    <input
                      type="number"
                      v-model.number="formData.session_discovery_pause_minutes"
                      class="w-16 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                      @wheel.prevent
                    />
                    <span class="ml-2 text-gray-400 text-sm">min</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Réservations Tab -->
        <div v-show="activeTab === 'booking'" class="p-6 space-y-6">
          <!-- Délais -->
          <div>
            <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Délais de réservation</h3>
            <div class="grid md:grid-cols-3 gap-4">
              <div class="bg-gray-900/50 rounded-lg p-4">
                <label class="text-sm text-gray-300 block mb-2">Délai minimum</label>
                <div class="flex items-center">
                  <input
                    type="number"
                    v-model.number="formData.booking_min_advance_hours"
                    class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                    @wheel.prevent
                  />
                  <span class="ml-2 text-gray-400 text-sm">heures</span>
                </div>
              </div>
              <div class="bg-gray-900/50 rounded-lg p-4">
                <label class="text-sm text-gray-300 block mb-2">Délai max (particuliers)</label>
                <div class="flex items-center">
                  <input
                    type="number"
                    v-model.number="formData.booking_max_advance_days"
                    class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                    @wheel.prevent
                  />
                  <span class="ml-2 text-gray-400 text-sm">jours</span>
                </div>
              </div>
              <div class="bg-gray-900/50 rounded-lg p-4">
                <label class="text-sm text-gray-300 block mb-2">Délai max (associations)</label>
                <div class="flex items-center">
                  <input
                    type="number"
                    v-model.number="formData.booking_max_advance_days_association"
                    class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                    @wheel.prevent
                  />
                  <span class="ml-2 text-gray-400 text-sm">jours</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Limites -->
          <div>
            <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Limites anti-spam (par IP)</h3>
            <div class="grid md:grid-cols-2 gap-6">
              <!-- Particuliers -->
              <div class="bg-gray-900/50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <label class="text-sm text-gray-300">Particuliers</label>
                    <p class="text-xs text-gray-500">Max réservations à venir par IP</p>
                  </div>
                  <input
                    type="number"
                    v-model.number="formData.booking_max_per_ip"
                    class="w-16 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                    @wheel.prevent
                  />
                </div>
              </div>
              <!-- Associations -->
              <div class="bg-gray-900/50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <label class="text-sm text-gray-300">Associations</label>
                    <p class="text-xs text-gray-500">Max réservations à venir par IP</p>
                  </div>
                  <input
                    type="number"
                    v-model.number="formData.booking_max_per_ip_association"
                    class="w-16 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                    @wheel.prevent
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Autres options -->
          <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-gray-900/50 rounded-lg p-4">
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-sm text-gray-300 font-medium">Confirmation email requise</label>
                  <p class="text-xs text-gray-500 mt-1">Les réservations doivent être confirmées par email</p>
                </div>
                <button
                  type="button"
                  @click="formData.booking_email_confirmation_required = !formData.booking_email_confirmation_required"
                  :class="[
                    formData.booking_email_confirmation_required ? 'bg-primary-600' : 'bg-gray-600',
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full transition-colors'
                  ]"
                >
                  <span
                    :class="[
                      formData.booking_email_confirmation_required ? 'translate-x-5' : 'translate-x-0',
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow mt-0.5 ml-0.5 transition'
                    ]"
                  />
                </button>
              </div>
            </div>
            <div class="bg-gray-900/50 rounded-lg p-4">
              <div class="flex items-center justify-between">
                <label class="text-sm text-gray-300">Max séances par personne</label>
                <input
                  type="number"
                  v-model.number="formData.booking_max_per_person"
                  class="w-16 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                  @wheel.prevent
                />
              </div>
              <p class="text-xs text-gray-500 mt-2">Nombre max de séances à venir par bénéficiaire</p>
            </div>
          </div>
        </div>

        <!-- Notifications Tab -->
        <div v-show="activeTab === 'notifications'" class="p-6 space-y-6">
          <!-- SMS Credits -->
          <div class="bg-gray-900/50 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <div class="p-3 rounded-full bg-primary-900/50">
                  <svg class="w-6 h-6 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                  </svg>
                </div>
                <div>
                  <div v-if="smsCredits.loading" class="text-gray-400">Chargement...</div>
                  <div v-else-if="!smsCredits.configured" class="text-amber-400">Service SMS non configuré</div>
                  <div v-else-if="smsCredits.error" class="text-red-400">{{ smsCredits.error }}</div>
                  <div v-else>
                    <span class="text-2xl font-bold text-primary-400">{{ smsCredits.credits_left }}</span>
                    <span class="text-gray-400 ml-2">crédits SMS restants</span>
                  </div>
                </div>
              </div>
              <button
                v-if="smsCredits.configured && !smsCredits.error"
                @click="refreshSmsCredits"
                :disabled="smsCredits.refreshing"
                class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-50"
                title="Actualiser"
              >
                <svg :class="['w-5 h-5', smsCredits.refreshing ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </button>
            </div>
          </div>

          <!-- SMS Settings -->
          <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-gray-900/50 rounded-lg p-4">
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-sm text-gray-300 font-medium">Rappels SMS activés</label>
                  <p class="text-xs text-gray-500 mt-1">Envoyer un rappel la veille des RDV</p>
                </div>
                <button
                  type="button"
                  @click="formData.sms_reminders_enabled = !formData.sms_reminders_enabled"
                  :class="[
                    formData.sms_reminders_enabled ? 'bg-primary-600' : 'bg-gray-600',
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full transition-colors'
                  ]"
                >
                  <span
                    :class="[
                      formData.sms_reminders_enabled ? 'translate-x-5' : 'translate-x-0',
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow mt-0.5 ml-0.5 transition'
                    ]"
                  />
                </button>
              </div>
            </div>
            <div class="bg-gray-900/50 rounded-lg p-4">
              <label class="text-sm text-gray-300 font-medium block mb-2">Nom d'expéditeur SMS</label>
              <input
                type="text"
                v-model="formData.sms_sender_name"
                class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white"
                maxlength="11"
              />
              <p class="text-xs text-gray-500 mt-1">Max 11 caractères, sans espaces</p>
            </div>
          </div>
        </div>

        <!-- Technique Tab -->
        <div v-show="activeTab === 'technical'" class="p-6 space-y-6">
          <!-- Captcha -->
          <div>
            <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Protection Captcha</h3>
            <div class="space-y-4">
              <div class="bg-gray-900/50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <label class="text-sm text-gray-300 font-medium">Captcha activé</label>
                    <p class="text-xs text-gray-500 mt-1">Protection anti-bot sur le formulaire de réservation</p>
                  </div>
                  <button
                    type="button"
                    @click="formData.captcha_enabled = !formData.captcha_enabled"
                    :class="[
                      formData.captcha_enabled ? 'bg-primary-600' : 'bg-gray-600',
                      'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full transition-colors'
                    ]"
                  >
                    <span
                      :class="[
                        formData.captcha_enabled ? 'translate-x-5' : 'translate-x-0',
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow mt-0.5 ml-0.5 transition'
                      ]"
                    />
                  </button>
                </div>
              </div>

              <div v-if="formData.captcha_enabled" class="grid md:grid-cols-2 gap-4">
                <div class="bg-gray-900/50 rounded-lg p-4">
                  <label class="text-sm text-gray-300 block mb-2">Fournisseur</label>
                  <select
                    v-model="formData.captcha_provider"
                    class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white"
                  >
                    <option value="hcaptcha">hCaptcha</option>
                    <option value="recaptcha">reCAPTCHA</option>
                  </select>
                </div>
                <div></div>
                <div class="bg-gray-900/50 rounded-lg p-4">
                  <label class="text-sm text-gray-300 block mb-2">Clé publique (Site Key)</label>
                  <input
                    type="text"
                    v-model="formData.captcha_site_key"
                    class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white font-mono"
                    placeholder="Clé publique..."
                  />
                </div>
                <div class="bg-gray-900/50 rounded-lg p-4">
                  <label class="text-sm text-gray-300 block mb-2">Clé secrète (Secret Key)</label>
                  <input
                    type="password"
                    v-model="formData.captcha_secret_key"
                    class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white font-mono"
                    placeholder="••••••••"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Cache -->
          <div>
            <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Cache</h3>
            <div class="bg-gray-900/50 rounded-lg p-4 max-w-md">
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-sm text-gray-300">Cache calendrier Google</label>
                  <p class="text-xs text-gray-500 mt-1">Durée de mise en cache des événements</p>
                </div>
                <div class="flex items-center">
                  <input
                    type="number"
                    v-model.number="formData.calendar_cache_ttl"
                    class="w-20 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white text-right"
                    @wheel.prevent
                  />
                  <span class="ml-2 text-gray-400 text-sm">sec</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Jours Off Tab -->
        <div v-show="activeTab === 'offdays'" class="p-6 space-y-6">
          <!-- Loading -->
          <div v-if="offDaysLoading" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-500"></div>
          </div>

          <!-- Error -->
          <div v-else-if="offDaysError" class="bg-red-900/50 border border-red-700 rounded-lg p-4 text-red-300 text-sm">
            {{ offDaysError }}
          </div>

          <template v-else>
            <!-- Add new off day -->
            <div>
              <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Ajouter un jour de fermeture</h3>
              <div class="bg-gray-900/50 rounded-lg p-4">
                <div class="flex flex-col sm:flex-row gap-3">
                  <div class="flex-1">
                    <label class="text-xs text-gray-500 block mb-1">Date</label>
                    <input
                      type="date"
                      v-model="newOffDay.date"
                      class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white"
                    />
                  </div>
                  <div class="flex-1">
                    <label class="text-xs text-gray-500 block mb-1">Raison (optionnel)</label>
                    <input
                      type="text"
                      v-model="newOffDay.reason"
                      placeholder="Ex: Vacances, Jour férié..."
                      class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white placeholder-gray-500"
                    />
                  </div>
                  <div class="flex items-end">
                    <button
                      @click="addOffDay"
                      :disabled="!newOffDay.date"
                      class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      Ajouter
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Off days list -->
            <div>
              <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">
                Jours de fermeture programmés
                <span class="text-gray-500 font-normal">({{ offDays.length }})</span>
              </h3>
              <div v-if="offDays.length === 0" class="bg-gray-900/50 rounded-lg p-6 text-center text-gray-500">
                Aucun jour de fermeture programmé
              </div>
              <div v-else class="bg-gray-900/50 rounded-lg divide-y divide-gray-700">
                <div
                  v-for="offDay in offDays"
                  :key="offDay.id"
                  class="flex items-center justify-between p-4"
                >
                  <div>
                    <div class="text-white font-medium">{{ formatOffDayDate(offDay.date) }}</div>
                    <div v-if="offDay.reason" class="text-sm text-gray-400 mt-0.5">{{ offDay.reason }}</div>
                  </div>
                  <button
                    @click="deleteOffDay(offDay.id)"
                    class="p-2 text-gray-400 hover:text-red-400 hover:bg-red-900/20 rounded-lg transition-colors"
                    title="Supprimer"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Calendrier ICS -->
            <div>
              <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Calendrier ICS</h3>
              <div class="bg-gray-900/50 rounded-lg p-4 space-y-4">
                <p class="text-sm text-gray-400">
                  Abonnez-vous à ce calendrier dans Google Calendar ou Outlook pour voir toutes les séances et jours de fermeture.
                </p>

                <!-- Token de sécurité -->
                <div>
                  <label class="text-sm text-gray-300 block mb-2">Token de sécurité (optionnel)</label>
                  <input
                    type="text"
                    v-model="formData.calendar_feed_token"
                    placeholder="Laisser vide pour accès public"
                    class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-white placeholder-gray-500 font-mono"
                  />
                  <p class="text-xs text-gray-500 mt-1">Si défini, le token sera requis dans l'URL pour accéder au calendrier.</p>
                </div>

                <!-- URL du calendrier -->
                <div>
                  <label class="text-sm text-gray-300 block mb-2">Lien d'abonnement</label>
                  <div class="flex items-center gap-2">
                    <input
                      type="text"
                      :value="calendarFeedUrl"
                      readonly
                      @click="copyIcsLink"
                      class="flex-1 px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-sm text-gray-300 font-mono cursor-pointer hover:bg-gray-700 transition-colors truncate"
                      title="Cliquer pour copier"
                    />
                    <button
                      @click="copyIcsLink"
                      :class="[
                        'px-3 py-2 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium whitespace-nowrap',
                        icsLinkCopied
                          ? 'bg-green-600 text-white'
                          : 'bg-gray-600 hover:bg-gray-500 text-gray-200'
                      ]"
                    >
                      <svg v-if="!icsLinkCopied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      {{ icsLinkCopied ? 'Copié !' : 'Copier' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Save Button (sticky) -->
      <div class="sticky bottom-4 flex justify-end">
        <button
          @click="saveSettings"
          :disabled="saving || !hasChanges"
          :class="[
            'px-6 py-2.5 rounded-lg font-medium transition-all duration-200 shadow-lg',
            hasChanges && !saving
              ? 'bg-primary-600 text-white hover:bg-primary-700'
              : 'bg-gray-700 text-gray-500 cursor-not-allowed'
          ]"
        >
          <span v-if="saving" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Enregistrement...
          </span>
          <span v-else-if="hasChanges">Enregistrer les modifications</span>
          <span v-else>Aucune modification</span>
        </button>
      </div>

      <!-- Success toast -->
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div
          v-if="showSuccess"
          class="fixed bottom-4 left-1/2 -translate-x-1/2 bg-green-900/90 border border-green-700 rounded-lg px-4 py-3 shadow-lg flex items-center"
        >
          <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span class="text-green-300">Paramètres enregistrés</span>
        </div>
      </Transition>
    </template>

    <!-- Business Hours Modal -->
    <Teleport to="body">
      <div
        v-if="showBusinessHoursModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-modal="true"
      >
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="fixed inset-0 bg-black/70" @click="showBusinessHoursModal = false"></div>
          <div class="relative bg-gray-800 rounded-xl shadow-xl max-w-lg w-full p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Horaires d'ouverture</h3>
            <div class="space-y-3">
              <div
                v-for="(dayName, dayIndex) in dayNames"
                :key="dayIndex"
                class="flex items-center justify-between py-2 border-b border-gray-700 last:border-0"
              >
                <div class="flex items-center space-x-3">
                  <input
                    type="checkbox"
                    :id="'day-' + dayIndex"
                    :checked="businessHours[dayIndex] !== null"
                    @change="toggleDay(dayIndex)"
                    class="h-4 w-4 text-primary-600 border-gray-600 bg-gray-700 rounded"
                  />
                  <label :for="'day-' + dayIndex" class="text-sm font-medium text-white w-24">{{ dayName }}</label>
                </div>
                <div v-if="businessHours[dayIndex]" class="flex items-center space-x-2">
                  <input type="time" v-model="businessHours[dayIndex].open" class="w-28 px-2 py-1 text-sm bg-gray-700 border border-gray-600 text-white rounded-lg" />
                  <span class="text-gray-500">-</span>
                  <input type="time" v-model="businessHours[dayIndex].close" class="w-28 px-2 py-1 text-sm bg-gray-700 border border-gray-600 text-white rounded-lg" />
                </div>
                <div v-else class="text-sm text-gray-500 italic">Fermé</div>
              </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
              <button @click="showBusinessHoursModal = false" class="px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">Annuler</button>
              <button @click="saveBusinessHours" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Appliquer</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h, watch } from 'vue'
import { settingsApi, offDaysApi } from '@/services/api'

// Icons as render functions
const IconPricing = () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
])
const IconScheduling = () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' })
])
const IconBooking = () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' })
])
const IconNotifications = () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' })
])
const IconTechnical = () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }),
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z' })
])
const IconOffDays = () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M6 18L18 6M6 6l12 12' })
])

const tabs = [
  { id: 'pricing', label: 'Tarifs', icon: IconPricing },
  { id: 'scheduling', label: 'Horaires', icon: IconScheduling },
  { id: 'booking', label: 'Réservations', icon: IconBooking },
  { id: 'notifications', label: 'Notifications', icon: IconNotifications },
  { id: 'technical', label: 'Technique', icon: IconTechnical },
  { id: 'offdays', label: 'Jours Off', icon: IconOffDays }
]

const activeTab = ref('pricing')
const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const showSuccess = ref(false)
const showBusinessHoursModal = ref(false)

const formData = reactive({})
const originalData = ref({})

const dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']
const businessHours = reactive({
  0: null, 1: { open: '09:00', close: '18:00' }, 2: { open: '09:00', close: '18:00' },
  3: { open: '09:00', close: '18:00' }, 4: null, 5: { open: '09:00', close: '18:00' },
  6: { open: '10:00', close: '17:00' }
})

const smsCredits = reactive({
  loading: true, refreshing: false, configured: false,
  credits_left: 0, service_name: null, cached_at: null, error: null
})

// Off Days state
const offDays = ref([])
const offDaysLoading = ref(false)
const offDaysError = ref(null)
const newOffDay = reactive({ date: '', reason: '' })
const icsLinkCopied = ref(false)

// Calendar feed URL (computed based on token)
const calendarFeedUrl = computed(() => {
  const token = formData.calendar_feed_token || ''
  return offDaysApi.getCalendarFeedUrl(token)
})

const hasChanges = computed(() => JSON.stringify(formData) !== JSON.stringify(originalData.value))

onMounted(async () => {
  await Promise.all([loadSettings(), loadSmsCredits(), loadOffDays()])
})

async function loadSettings() {
  loading.value = true
  error.value = null
  try {
    const response = await settingsApi.getAll()
    const groups = response.data.data || response.data
    for (const category of groups) {
      for (const setting of category.settings) {
        formData[setting.key] = setting.value
        if (setting.key === 'business_hours' && setting.value) {
          const hours = typeof setting.value === 'string' ? JSON.parse(setting.value) : setting.value
          for (const day in hours) businessHours[day] = hours[day]
        }
      }
    }
    originalData.value = JSON.parse(JSON.stringify(formData))
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors du chargement'
  } finally {
    loading.value = false
  }
}

async function loadSmsCredits() {
  smsCredits.loading = true
  try {
    const response = await settingsApi.getSmsCredits()
    const data = response.data.data || response.data
    smsCredits.configured = data.configured
    smsCredits.credits_left = data.credits_left || 0
    smsCredits.service_name = data.service_name
  } catch (err) {
    smsCredits.error = err.response?.data?.message || 'Erreur'
  } finally {
    smsCredits.loading = false
  }
}

async function refreshSmsCredits() {
  smsCredits.refreshing = true
  try {
    const response = await settingsApi.refreshSmsCredits()
    const data = response.data.data || response.data
    smsCredits.credits_left = data.credits_left || 0
  } catch (err) {
    smsCredits.error = err.response?.data?.message || 'Erreur'
  } finally {
    smsCredits.refreshing = false
  }
}

function toggleDay(dayIndex) {
  businessHours[dayIndex] = businessHours[dayIndex] === null ? { open: '09:00', close: '18:00' } : null
}

function saveBusinessHours() {
  const hours = {}
  for (let i = 0; i <= 6; i++) hours[i] = businessHours[i]
  formData.business_hours = hours
  showBusinessHoursModal.value = false
}

async function saveSettings() {
  if (!hasChanges.value) return
  saving.value = true
  try {
    const changedSettings = {}
    for (const key in formData) {
      if (JSON.stringify(formData[key]) !== JSON.stringify(originalData.value[key])) {
        changedSettings[key] = formData[key]
      }
    }
    await settingsApi.update(changedSettings)
    originalData.value = JSON.parse(JSON.stringify(formData))
    showSuccess.value = true
    setTimeout(() => { showSuccess.value = false }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de l\'enregistrement'
  } finally {
    saving.value = false
  }
}

// Off Days functions
async function loadOffDays() {
  offDaysLoading.value = true
  offDaysError.value = null
  try {
    const response = await offDaysApi.getAll()
    offDays.value = response.data.data?.off_days || response.data.off_days || []
  } catch (err) {
    offDaysError.value = err.response?.data?.message || 'Erreur lors du chargement des jours off'
  } finally {
    offDaysLoading.value = false
  }
}

async function addOffDay() {
  if (!newOffDay.date) return
  try {
    await offDaysApi.create({
      date: newOffDay.date,
      reason: newOffDay.reason || null
    })
    newOffDay.date = ''
    newOffDay.reason = ''
    await loadOffDays()
  } catch (err) {
    offDaysError.value = err.response?.data?.message || 'Erreur lors de l\'ajout'
  }
}

async function deleteOffDay(id) {
  if (!confirm('Supprimer ce jour off ?')) return
  try {
    await offDaysApi.delete(id)
    await loadOffDays()
  } catch (err) {
    offDaysError.value = err.response?.data?.message || 'Erreur lors de la suppression'
  }
}

async function copyIcsLink() {
  try {
    await navigator.clipboard.writeText(calendarFeedUrl.value)
    icsLinkCopied.value = true
    setTimeout(() => { icsLinkCopied.value = false }, 2000)
  } catch (err) {
    console.error('Erreur copie:', err)
  }
}

function formatOffDayDate(dateStr) {
  const date = new Date(dateStr)
  return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}
</script>
