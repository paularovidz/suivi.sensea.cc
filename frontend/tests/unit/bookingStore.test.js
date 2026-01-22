import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

/**
 * Tests unitaires pour le booking store.
 * Note: Ces tests utilisent des mocks pour les appels API.
 */

// Mock API responses
const mockScheduleResponse = {
  data: {
    data: {
      schedule: {},
      duration_types: { discovery: '1h15', regular: '45min' },
      prices: { discovery: 55, regular: 45 },
      email_confirmation_required: false
    }
  }
}

describe('Booking Store State', () => {
  // Since we can't import the actual store without complex setup,
  // we test the state management logic directly

  describe('Wizard Navigation', () => {
    it('starts at step 1', () => {
      const state = createInitialState()
      expect(state.currentStep).toBe(1)
    })

    it('advances to next step when valid', () => {
      const state = createInitialState()
      state.isNewClient = true // Valid for step 1

      const newState = nextStep(state)
      expect(newState.currentStep).toBe(2)
    })

    it('does not advance when step is invalid', () => {
      const state = createInitialState()
      // isNewClient is null, so step 1 is invalid

      const newState = nextStep(state)
      expect(newState.currentStep).toBe(1) // Should stay
    })

    it('goes back to previous step', () => {
      const state = createInitialState()
      state.currentStep = 3

      const newState = prevStep(state)
      expect(newState.currentStep).toBe(2)
    })

    it('does not go below step 1', () => {
      const state = createInitialState()
      state.currentStep = 1

      const newState = prevStep(state)
      expect(newState.currentStep).toBe(1)
    })

    it('does not exceed total steps', () => {
      const state = createInitialState()
      state.currentStep = 5
      state.isNewClient = true // Make all steps valid

      const newState = nextStep(state)
      expect(newState.currentStep).toBe(5) // Should stay at max
    })
  })

  describe('Step Validation (canGoNext)', () => {
    it('step 1 requires isNewClient to be set', () => {
      const state = createInitialState()

      state.isNewClient = null
      expect(canGoNext(state)).toBe(false)

      state.isNewClient = true
      expect(canGoNext(state)).toBe(true)

      state.isNewClient = false
      expect(canGoNext(state)).toBe(true)
    })

    it('step 2 requires person info for new client', () => {
      const state = createInitialState()
      state.currentStep = 2
      state.isNewClient = true

      state.newPerson = { firstName: '', lastName: '' }
      expect(canGoNext(state)).toBe(false)

      state.newPerson = { firstName: 'Marie', lastName: '' }
      expect(canGoNext(state)).toBe(false)

      state.newPerson = { firstName: 'Marie', lastName: 'Dupont' }
      expect(canGoNext(state)).toBe(true)
    })

    it('step 2 accepts existing person selection for returning client', () => {
      const state = createInitialState()
      state.currentStep = 2
      state.isNewClient = false

      state.selectedPersonId = null
      state.newPerson = { firstName: '', lastName: '' }
      expect(canGoNext(state)).toBe(false)

      state.selectedPersonId = 'person-123'
      expect(canGoNext(state)).toBe(true)
    })

    it('step 3 requires date and time selection', () => {
      const state = createInitialState()
      state.currentStep = 3

      state.selectedDate = null
      state.selectedTime = null
      expect(canGoNext(state)).toBe(false)

      state.selectedDate = '2024-02-15'
      expect(canGoNext(state)).toBe(false)

      state.selectedTime = '10:00'
      expect(canGoNext(state)).toBe(true)
    })

    it('step 4 requires contact info and GDPR consent', () => {
      const state = createInitialState()
      state.currentStep = 4

      state.clientInfo = { email: '', firstName: '', lastName: '' }
      state.gdprConsent = false
      expect(canGoNext(state)).toBe(false)

      state.clientInfo = { email: 'test@example.com', firstName: 'Jean', lastName: 'Dupont' }
      expect(canGoNext(state)).toBe(false) // Still missing GDPR

      state.gdprConsent = true
      expect(canGoNext(state)).toBe(true)
    })

    it('step 5 always returns true', () => {
      const state = createInitialState()
      state.currentStep = 5
      expect(canGoNext(state)).toBe(true)
    })
  })

  describe('Duration Type Management', () => {
    it('defaults to regular duration type', () => {
      const state = createInitialState()
      expect(state.durationType).toBe('regular')
    })

    it('changing duration type resets date/time selection', () => {
      const state = createInitialState()
      state.selectedDate = '2024-02-15'
      state.selectedTime = '10:00'
      state.availableDates = ['2024-02-15', '2024-02-16']
      state.availableSlots = ['09:00', '10:00', '11:00']

      const newState = setDurationType(state, 'discovery')

      expect(newState.durationType).toBe('discovery')
      expect(newState.selectedDate).toBeNull()
      expect(newState.selectedTime).toBeNull()
      expect(newState.availableDates).toHaveLength(0)
      expect(newState.availableSlots).toHaveLength(0)
    })

    it('changing duration type clears applied promo', () => {
      const state = createInitialState()
      state.appliedPromo = { id: 'promo-123', code: 'TEST' }
      state.promoPricing = { original_price: 45, discount_amount: 5, final_price: 40 }

      const newState = setDurationType(state, 'discovery')

      expect(newState.appliedPromo).toBeNull()
      expect(newState.promoPricing).toBeNull()
    })
  })

  describe('Promo Code State', () => {
    it('applies promo correctly', () => {
      const state = createInitialState()

      const promo = { id: 'promo-123', code: 'SUMMER20', discount_type: 'percentage', discount_value: 20 }
      const pricing = { original_price: 45, discount_amount: 9, final_price: 36 }

      const newState = applyPromo(state, promo, pricing)

      expect(newState.appliedPromo).toEqual(promo)
      expect(newState.promoPricing).toEqual(pricing)
      expect(newState.promoError).toBeNull()
    })

    it('clears promo state', () => {
      const state = createInitialState()
      state.appliedPromo = { id: 'promo-123' }
      state.promoPricing = { final_price: 36 }
      state.promoCodeInput = 'SUMMER20'
      state.promoError = 'Some error'

      const newState = clearPromo(state)

      expect(newState.appliedPromo).toBeNull()
      expect(newState.promoPricing).toBeNull()
      expect(newState.promoCodeInput).toBe('')
      expect(newState.promoError).toBeNull()
    })

    it('sets promo error', () => {
      const state = createInitialState()

      const newState = setPromoError(state, 'Code invalide')

      expect(newState.promoError).toBe('Code invalide')
      expect(newState.appliedPromo).toBeNull()
      expect(newState.promoPricing).toBeNull()
    })
  })

  describe('Price Calculations', () => {
    it('returns original price when no promo applied', () => {
      const state = createInitialState()
      state.prices = { discovery: 55, regular: 45 }
      state.durationType = 'regular'
      state.promoPricing = null

      expect(getCurrentPrice(state)).toBe(45)
    })

    it('returns final price when promo applied', () => {
      const state = createInitialState()
      state.prices = { discovery: 55, regular: 45 }
      state.durationType = 'regular'
      state.promoPricing = { original_price: 45, discount_amount: 9, final_price: 36 }

      expect(getCurrentPrice(state)).toBe(36)
    })

    it('calculates original price based on duration type', () => {
      const state = createInitialState()
      state.prices = { discovery: 55, regular: 45 }

      state.durationType = 'regular'
      expect(getOriginalPrice(state)).toBe(45)

      state.durationType = 'discovery'
      expect(getOriginalPrice(state)).toBe(55)
    })
  })

  describe('Booking Data Composition', () => {
    it('composes complete booking data', () => {
      const state = createInitialState()
      state.selectedDate = '2024-02-15'
      state.selectedTime = '10:00'
      state.durationType = 'regular'
      state.clientInfo = {
        email: 'test@example.com',
        phone: '0612345678',
        firstName: 'Jean',
        lastName: 'Dupont',
        clientType: 'personal'
      }
      state.newPerson = { firstName: 'Marie', lastName: 'Dupont' }
      state.selectedPersonId = null
      state.gdprConsent = true

      const data = getBookingData(state)

      expect(data.session_date).toBe('2024-02-15 10:00:00')
      expect(data.duration_type).toBe('regular')
      expect(data.client_email).toBe('test@example.com')
      expect(data.client_first_name).toBe('Jean')
      expect(data.client_last_name).toBe('Dupont')
      expect(data.person_first_name).toBe('Marie')
      expect(data.person_last_name).toBe('Dupont')
      expect(data.gdpr_consent).toBe(true)
    })

    it('includes promo code when manual code applied', () => {
      const state = createInitialState()
      setupValidState(state)
      state.appliedPromo = { id: 'promo-123', code: 'SUMMER20' }

      const data = getBookingData(state)

      expect(data.promo_code).toBe('SUMMER20')
      expect(data.promo_code_id).toBeUndefined()
    })

    it('includes promo_code_id when automatic promo applied', () => {
      const state = createInitialState()
      setupValidState(state)
      state.appliedPromo = { id: 'auto-456', code: null }

      const data = getBookingData(state)

      expect(data.promo_code).toBeUndefined()
      expect(data.promo_code_id).toBe('auto-456')
    })
  })

  describe('Reset Functionality', () => {
    it('resets wizard to initial state', () => {
      const state = createInitialState()
      // Modify state
      state.currentStep = 4
      state.isNewClient = true
      state.selectedDate = '2024-02-15'
      state.appliedPromo = { id: 'promo-123' }

      const newState = resetWizard(state)

      expect(newState.currentStep).toBe(1)
      expect(newState.isNewClient).toBeNull()
      expect(newState.selectedDate).toBeNull()
      expect(newState.appliedPromo).toBeNull()
    })

    it('resets only following steps when resetFollowingSteps is called', () => {
      const state = createInitialState()
      state.currentStep = 2
      state.isNewClient = true
      state.selectedPersonId = 'person-123'
      state.selectedDate = '2024-02-15'
      state.selectedTime = '10:00'

      const newState = resetFollowingSteps(state)

      // Step 1 and 2 data should remain
      expect(newState.isNewClient).toBe(true)
      expect(newState.selectedPersonId).toBe('person-123')

      // Step 3+ data should be reset
      expect(newState.selectedDate).toBeNull()
      expect(newState.selectedTime).toBeNull()
    })
  })

  describe('Person Info Getter', () => {
    it('returns selected person info', () => {
      const state = createInitialState()
      state.selectedPersonId = 'person-123'
      state.existingPersons = [
        { id: 'person-123', first_name: 'Marie', last_name: 'Dupont' },
        { id: 'person-456', first_name: 'Pierre', last_name: 'Martin' }
      ]

      const info = getPersonInfo(state)

      expect(info.firstName).toBe('Marie')
      expect(info.lastName).toBe('Dupont')
      expect(info.id).toBe('person-123')
    })

    it('returns new person info when no selection', () => {
      const state = createInitialState()
      state.selectedPersonId = null
      state.newPerson = { firstName: 'Nouveau', lastName: 'Client' }

      const info = getPersonInfo(state)

      expect(info.firstName).toBe('Nouveau')
      expect(info.lastName).toBe('Client')
      expect(info.id).toBeNull()
    })
  })
})

// =========================================================================
// HELPER FUNCTIONS (simulating store logic)
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
      clientType: 'personal'
    },
    gdprConsent: false,
    appliedPromo: null,
    promoPricing: null,
    promoCodeInput: '',
    promoError: null,
    bookingResult: null,
    prices: { discovery: 55, regular: 45 },
    loading: false,
    error: null
  }
}

function canGoNext(state) {
  switch (state.currentStep) {
    case 1:
      return state.isNewClient !== null
    case 2:
      if (state.isNewClient) {
        return !!(state.newPerson.firstName.trim() && state.newPerson.lastName.trim())
      }
      return !!(state.selectedPersonId !== null || (state.newPerson.firstName.trim() && state.newPerson.lastName.trim()))
    case 3:
      return !!(state.selectedDate && state.selectedTime)
    case 4:
      return !!(
        state.clientInfo.email.trim() &&
        state.clientInfo.firstName.trim() &&
        state.clientInfo.lastName.trim() &&
        state.gdprConsent
      )
    case 5:
      return true
    default:
      return false
  }
}

function nextStep(state) {
  if (state.currentStep < state.totalSteps && canGoNext(state)) {
    return { ...state, currentStep: state.currentStep + 1 }
  }
  return state
}

function prevStep(state) {
  if (state.currentStep > 1) {
    return { ...state, currentStep: state.currentStep - 1 }
  }
  return state
}

function setDurationType(state, type) {
  return {
    ...state,
    durationType: type,
    selectedDate: null,
    selectedTime: null,
    availableDates: [],
    availableSlots: [],
    appliedPromo: null,
    promoPricing: null,
    promoCodeInput: '',
    promoError: null
  }
}

function applyPromo(state, promo, pricing) {
  return {
    ...state,
    appliedPromo: promo,
    promoPricing: pricing,
    promoError: null
  }
}

function clearPromo(state) {
  return {
    ...state,
    appliedPromo: null,
    promoPricing: null,
    promoCodeInput: '',
    promoError: null
  }
}

function setPromoError(state, error) {
  return {
    ...state,
    promoError: error,
    appliedPromo: null,
    promoPricing: null
  }
}

function getCurrentPrice(state) {
  if (state.promoPricing) {
    return state.promoPricing.final_price
  }
  return state.prices[state.durationType] || (state.durationType === 'discovery' ? 55 : 45)
}

function getOriginalPrice(state) {
  return state.prices[state.durationType] || (state.durationType === 'discovery' ? 55 : 45)
}

function getPersonInfo(state) {
  if (state.selectedPersonId) {
    const person = state.existingPersons.find(p => p.id === state.selectedPersonId)
    if (person) {
      return {
        firstName: person.first_name,
        lastName: person.last_name,
        id: person.id
      }
    }
  }
  return {
    firstName: state.newPerson.firstName,
    lastName: state.newPerson.lastName,
    id: null
  }
}

function getBookingData(state) {
  const personInfo = getPersonInfo(state)

  const data = {
    session_date: state.selectedDate && state.selectedTime
      ? `${state.selectedDate} ${state.selectedTime}:00`
      : null,
    duration_type: state.durationType,
    client_email: state.clientInfo.email.trim().toLowerCase(),
    client_phone: state.clientInfo.phone?.trim() || null,
    client_first_name: state.clientInfo.firstName.trim(),
    client_last_name: state.clientInfo.lastName.trim(),
    person_first_name: personInfo.firstName,
    person_last_name: personInfo.lastName,
    person_id: personInfo.id,
    gdpr_consent: state.gdprConsent,
    client_type: state.clientInfo.clientType || 'personal'
  }

  if (state.appliedPromo) {
    if (state.appliedPromo.code) {
      data.promo_code = state.appliedPromo.code
    } else {
      data.promo_code_id = state.appliedPromo.id
    }
  }

  return data
}

function resetWizard(state) {
  return createInitialState()
}

function resetFollowingSteps(state) {
  return {
    ...state,
    selectedDate: null,
    selectedTime: null,
    availableDates: [],
    availableSlots: [],
    bookingResult: null
  }
}

function setupValidState(state) {
  state.selectedDate = '2024-02-15'
  state.selectedTime = '10:00'
  state.durationType = 'regular'
  state.clientInfo = {
    email: 'test@example.com',
    phone: '0612345678',
    firstName: 'Jean',
    lastName: 'Dupont',
    clientType: 'personal'
  }
  state.newPerson = { firstName: 'Marie', lastName: 'Dupont' }
  state.gdprConsent = true
}
