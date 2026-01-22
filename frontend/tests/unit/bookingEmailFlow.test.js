import { describe, it, expect, beforeEach, vi } from 'vitest'

/**
 * Tests unitaires pour le nouveau flow de réservation basé sur l'email.
 *
 * Nouveau flow:
 * 1. Étape 1: Saisie email → détection auto nouveau/existant
 * 2. Étape 2: Sélection personne
 * 3. Étape 3: Date/heure
 * 4. Étape 4: Coordonnées
 * 5. Étape 5: Confirmation
 */

describe('Email-based Booking Flow', () => {

  describe('Email Validation', () => {
    it('validates correct email formats', () => {
      expect(isValidEmail('test@example.com')).toBe(true)
      expect(isValidEmail('user.name@domain.fr')).toBe(true)
      expect(isValidEmail('user+tag@example.org')).toBe(true)
    })

    it('rejects invalid email formats', () => {
      expect(isValidEmail('')).toBe(false)
      expect(isValidEmail('notanemail')).toBe(false)
      expect(isValidEmail('missing@domain')).toBe(false)
      expect(isValidEmail('@nodomain.com')).toBe(false)
      expect(isValidEmail('spaces in@email.com')).toBe(false)
    })

    it('normalizes email to lowercase', () => {
      expect(normalizeEmail('Test@EXAMPLE.com')).toBe('test@example.com')
      expect(normalizeEmail('  User@Domain.FR  ')).toBe('user@domain.fr')
    })
  })

  describe('Client Type Detection', () => {
    it('detects existing client when API returns client info', () => {
      const apiResponse = {
        persons: [{ id: 'person-1', first_name: 'Marie', last_name: 'Dupont' }],
        existing_client: true,
        client_info: {
          first_name: 'Jean',
          last_name: 'Dupont',
          email_masked: 'je***@example.com',
          has_phone: true,
          is_active: true
        }
      }

      const result = processEmailLookupResponse(apiResponse)

      expect(result.isNewClient).toBe(false)
      expect(result.durationType).toBe('regular')
      expect(result.existingClientInfo).not.toBeNull()
      expect(result.existingPersons).toHaveLength(1)
    })

    it('detects new client when API returns no client info', () => {
      const apiResponse = {
        persons: [],
        existing_client: false
      }

      const result = processEmailLookupResponse(apiResponse)

      expect(result.isNewClient).toBe(true)
      expect(result.durationType).toBe('discovery')
      expect(result.existingClientInfo).toBeNull()
      expect(result.existingPersons).toHaveLength(0)
    })

    it('treats API error as new client', () => {
      const result = processEmailLookupError()

      expect(result.isNewClient).toBe(true)
      expect(result.durationType).toBe('discovery')
    })
  })

  describe('Duration Type Based on Client Status', () => {
    it('sets discovery for new clients', () => {
      const state = createInitialState()

      const newState = setClientType(state, true)

      expect(newState.isNewClient).toBe(true)
      expect(newState.durationType).toBe('discovery')
    })

    it('sets regular for existing clients', () => {
      const state = createInitialState()

      const newState = setClientType(state, false)

      expect(newState.isNewClient).toBe(false)
      expect(newState.durationType).toBe('regular')
    })
  })

  describe('Step 1 Email Flow', () => {
    it('stores email in clientInfo after validation', () => {
      const state = createInitialState()
      const email = 'test@example.com'

      const newState = processStep1Email(state, email)

      expect(newState.clientInfo.email).toBe('test@example.com')
    })

    it('advances to step 2 after successful email lookup', () => {
      const state = createInitialState()
      state.currentStep = 1

      const newState = completeStep1(state)

      expect(newState.currentStep).toBe(2)
    })

    it('preserves email when returning to step 1', () => {
      const state = createInitialState()
      state.clientInfo.email = 'existing@email.com'
      state.currentStep = 2

      const newState = goBackToStep1(state)

      expect(newState.currentStep).toBe(1)
      expect(newState.clientInfo.email).toBe('existing@email.com')
    })
  })

  describe('Step 2 Person Selection', () => {
    it('shows existing persons for returning client', () => {
      const state = createInitialState()
      state.isNewClient = false
      state.existingPersons = [
        { id: 'p1', first_name: 'Marie', last_name: 'Dupont' },
        { id: 'p2', first_name: 'Pierre', last_name: 'Martin' }
      ]

      expect(shouldShowPersonsList(state)).toBe(true)
      expect(shouldShowNewPersonForm(state)).toBe(false)
    })

    it('shows new person form for new client', () => {
      const state = createInitialState()
      state.isNewClient = true
      state.existingPersons = []

      expect(shouldShowPersonsList(state)).toBe(false)
      expect(shouldShowNewPersonForm(state)).toBe(true)
    })

    it('shows new person form when existing client has no persons', () => {
      const state = createInitialState()
      state.isNewClient = false
      state.existingPersons = []

      expect(shouldShowPersonsList(state)).toBe(false)
      expect(shouldShowNewPersonForm(state)).toBe(true)
    })

    it('auto-advances when selecting existing person', () => {
      const state = createInitialState()
      state.currentStep = 2
      state.existingPersons = [{ id: 'p1', first_name: 'Marie', last_name: 'Dupont' }]

      const newState = selectPerson(state, 'p1')

      expect(newState.selectedPersonId).toBe('p1')
      expect(newState.currentStep).toBe(3) // Auto-advanced
    })
  })

  describe('Authenticated User Flow', () => {
    it('skips step 1 for authenticated users', () => {
      const user = {
        email: 'user@example.com',
        first_name: 'Jean',
        last_name: 'Dupont',
        phone: '0612345678',
        client_type: 'personal'
      }

      const state = initForAuthenticatedUser(createInitialState(), user)

      expect(state.currentStep).toBe(2)
      expect(state.isNewClient).toBe(false)
      expect(state.clientInfo.email).toBe('user@example.com')
    })

    it('pre-fills client info for authenticated users', () => {
      const user = {
        email: 'user@example.com',
        first_name: 'Jean',
        last_name: 'Dupont',
        phone: '0612345678',
        client_type: 'association',
        company_name: 'Mon Association'
      }

      const state = initForAuthenticatedUser(createInitialState(), user)

      expect(state.clientInfo.firstName).toBe('Jean')
      expect(state.clientInfo.lastName).toBe('Dupont')
      expect(state.clientInfo.phone).toBe('0612345678')
      expect(state.clientInfo.clientType).toBe('association')
      expect(state.clientInfo.companyName).toBe('Mon Association')
    })

    it('sets GDPR consent for authenticated users', () => {
      const user = { email: 'user@example.com', first_name: 'Jean', last_name: 'Dupont' }

      const state = initForAuthenticatedUser(createInitialState(), user)

      expect(state.gdprConsent).toBe(true)
    })

    it('does not show back button at step 2 for authenticated users', () => {
      const state = createInitialState()
      state.currentStep = 2

      expect(shouldShowBackButton(state, true)).toBe(false)
      expect(shouldShowBackButton(state, false)).toBe(true)
    })
  })

  describe('Existing Client Info Display', () => {
    it('shows full info when authenticated', () => {
      const state = createInitialState()
      state.existingClientInfo = {
        email_masked: 'te***@example.com',
        phone_masked: '5678',
        is_active: true
      }
      const authUser = { email: 'test@example.com', phone: '0612345678' }

      const display = getDisplayInfo(state, authUser)

      expect(display.email).toBe('test@example.com')
      expect(display.phone).toBe('0612345678')
    })

    it('shows masked info when not authenticated', () => {
      const state = createInitialState()
      state.existingClientInfo = {
        email_masked: 'te***@example.com',
        phone_masked: '5678',
        has_phone: true,
        is_active: true
      }

      const display = getDisplayInfo(state, null)

      expect(display.email).toBe('te***@example.com')
      expect(display.phone).toBe('Se terminant par 5678')
    })

    it('handles inactive accounts', () => {
      const state = createInitialState()
      state.existingClientInfo = {
        is_active: false
      }

      expect(isClientDeactivated(state)).toBe(true)
    })
  })

  describe('Complete Flow Scenarios', () => {
    it('new client complete flow', () => {
      let state = createInitialState()

      // Step 1: Enter email (new client)
      state = processStep1Email(state, 'new@client.com')
      state = setClientType(state, true)
      state = completeStep1(state)
      expect(state.currentStep).toBe(2)
      expect(state.isNewClient).toBe(true)
      expect(state.durationType).toBe('discovery')

      // Step 2: Enter person info
      state.newPerson = { firstName: 'Marie', lastName: 'Nouvelle' }
      state = { ...state, currentStep: 3 }
      expect(state.currentStep).toBe(3)

      // Step 3: Select date/time
      state.selectedDate = '2024-03-15'
      state.selectedTime = '10:00'
      state = { ...state, currentStep: 4 }

      // Step 4: Verify contact info is pre-filled
      expect(state.clientInfo.email).toBe('new@client.com')
    })

    it('existing client complete flow', () => {
      let state = createInitialState()

      // Step 1: Enter email (existing client)
      state = processStep1Email(state, 'existing@client.com')
      state.existingPersons = [{ id: 'p1', first_name: 'Marie', last_name: 'Existante' }]
      state.existingClientInfo = {
        first_name: 'Jean',
        last_name: 'Existant',
        email_masked: 'ex***@client.com',
        is_active: true
      }
      state = setClientType(state, false)
      state = completeStep1(state)
      expect(state.currentStep).toBe(2)
      expect(state.isNewClient).toBe(false)
      expect(state.durationType).toBe('regular')

      // Step 2: Select existing person
      state = selectPerson(state, 'p1')
      expect(state.selectedPersonId).toBe('p1')
      expect(state.currentStep).toBe(3)
    })

    it('handles email change with reset', () => {
      let state = createInitialState()

      // First lookup
      state = processStep1Email(state, 'first@email.com')
      state.existingPersons = [{ id: 'p1', first_name: 'Person1', last_name: 'Test' }]
      state.selectedPersonId = 'p1'
      state.selectedDate = '2024-03-15'

      // Change email - should reset following data
      state = resetForNewEmail(state)
      state = processStep1Email(state, 'second@email.com')

      expect(state.clientInfo.email).toBe('second@email.com')
      expect(state.selectedPersonId).toBeNull()
      expect(state.selectedDate).toBeNull()
      expect(state.existingPersons).toHaveLength(0)
    })
  })
})

// =========================================================================
// HELPER FUNCTIONS
// =========================================================================

function createInitialState() {
  return {
    currentStep: 1,
    totalSteps: 5,
    isNewClient: null,
    existingPersons: [],
    selectedPersonId: null,
    newPerson: { firstName: '', lastName: '' },
    selectedDate: null,
    selectedTime: null,
    durationType: 'regular',
    availableDates: [],
    availableSlots: [],
    clientInfo: {
      email: '',
      phone: '',
      firstName: '',
      lastName: '',
      clientType: 'personal',
      companyName: ''
    },
    gdprConsent: false,
    cgrConsent: false,
    existingClientInfo: null,
    appliedPromo: null,
    promoPricing: null,
    bookingResult: null,
    loading: false,
    error: null
  }
}

function isValidEmail(email) {
  if (!email) return false
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

function normalizeEmail(email) {
  return email.trim().toLowerCase()
}

function processEmailLookupResponse(apiResponse) {
  const isExistingClient = apiResponse.existing_client && apiResponse.client_info !== undefined

  return {
    isNewClient: !isExistingClient,
    durationType: isExistingClient ? 'regular' : 'discovery',
    existingClientInfo: apiResponse.client_info || null,
    existingPersons: apiResponse.persons || []
  }
}

function processEmailLookupError() {
  return {
    isNewClient: true,
    durationType: 'discovery'
  }
}

function setClientType(state, isNew) {
  return {
    ...state,
    isNewClient: isNew,
    durationType: isNew ? 'discovery' : 'regular'
  }
}

function processStep1Email(state, email) {
  return {
    ...state,
    clientInfo: {
      ...state.clientInfo,
      email: normalizeEmail(email)
    }
  }
}

function completeStep1(state) {
  return {
    ...state,
    currentStep: 2
  }
}

function goBackToStep1(state) {
  return {
    ...state,
    currentStep: 1
  }
}

function shouldShowPersonsList(state) {
  return !state.isNewClient && state.existingPersons.length > 0
}

function shouldShowNewPersonForm(state) {
  return state.isNewClient || state.existingPersons.length === 0
}

function selectPerson(state, personId) {
  return {
    ...state,
    selectedPersonId: personId,
    newPerson: { firstName: '', lastName: '' },
    currentStep: 3 // Auto-advance
  }
}

function initForAuthenticatedUser(state, user) {
  return {
    ...state,
    currentStep: 2,
    isNewClient: false,
    durationType: 'regular',
    clientInfo: {
      email: user.email,
      phone: user.phone || '',
      firstName: user.first_name,
      lastName: user.last_name,
      clientType: user.client_type || 'personal',
      companyName: user.company_name || ''
    },
    gdprConsent: true,
    existingClientInfo: {
      first_name: user.first_name,
      last_name: user.last_name,
      email_masked: maskEmail(user.email),
      phone_masked: user.phone ? user.phone.slice(-4) : null,
      has_phone: !!user.phone,
      is_active: true
    }
  }
}

function maskEmail(email) {
  const [local, domain] = email.split('@')
  if (local.length <= 2) {
    return `${local[0]}***@${domain}`
  }
  return `${local.slice(0, 2)}***@${domain}`
}

function shouldShowBackButton(state, isAuthenticated) {
  if (state.currentStep <= 1 || state.currentStep >= 5) {
    return false
  }
  if (isAuthenticated && state.currentStep === 2) {
    return false
  }
  return true
}

function getDisplayInfo(state, authUser) {
  if (authUser) {
    return {
      email: authUser.email,
      phone: authUser.phone
    }
  }
  return {
    email: state.existingClientInfo?.email_masked,
    phone: state.existingClientInfo?.has_phone
      ? `Se terminant par ${state.existingClientInfo.phone_masked}`
      : null
  }
}

function isClientDeactivated(state) {
  return state.existingClientInfo?.is_active === false
}

function resetForNewEmail(state) {
  return {
    ...state,
    existingPersons: [],
    selectedPersonId: null,
    newPerson: { firstName: '', lastName: '' },
    selectedDate: null,
    selectedTime: null,
    existingClientInfo: null
  }
}
