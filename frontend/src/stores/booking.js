import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { publicBookingApi } from '@/services/api'

export const useBookingStore = defineStore('booking', () => {
  // ========================================
  // STATE
  // ========================================

  // Wizard state
  const currentStep = ref(1)
  const totalSteps = 5

  // Step 1: Client type
  const isNewClient = ref(null) // true = first visit, false = returning

  // Step 2: Person selection
  const existingPersons = ref([])
  const selectedPersonId = ref(null)
  const newPerson = ref({
    firstName: '',
    lastName: ''
  })

  // Step 3: Date/Time
  const selectedDate = ref(null)
  const selectedTime = ref(null)
  const durationType = ref('regular') // 'discovery' or 'regular'
  const availableDates = ref([])
  const availableSlots = ref([])
  const currentMonth = ref(new Date().getMonth() + 1)
  const currentYear = ref(new Date().getFullYear())

  // Step 4: Contact info
  const clientInfo = ref({
    email: '',
    phone: '',
    firstName: '',
    lastName: '',
    clientType: 'personal', // 'personal' or 'professional'
    companyName: '',
    siret: ''
  })
  const gdprConsent = ref(false)
  const captchaToken = ref(null)

  // Existing client info (masked for security)
  const existingClientInfo = ref(null) // { email_masked, phone_masked, has_phone, gdpr_already_accepted, first_name, last_name, client_type, company_name }

  // Step 5: Confirmation
  const bookingResult = ref(null)

  // Schedule info
  const scheduleInfo = ref(null)
  const durationLabels = ref({})
  const emailConfirmationRequired = ref(false)

  // Loading states
  const loading = ref(false)
  const error = ref(null)

  // ========================================
  // GETTERS
  // ========================================

  const canGoNext = computed(() => {
    switch (currentStep.value) {
      case 1:
        return isNewClient.value !== null
      case 2:
        if (isNewClient.value) {
          return newPerson.value.firstName.trim() && newPerson.value.lastName.trim()
        }
        return selectedPersonId.value !== null || (newPerson.value.firstName.trim() && newPerson.value.lastName.trim())
      case 3:
        return selectedDate.value && selectedTime.value
      case 4:
        return (
          clientInfo.value.email.trim() &&
          clientInfo.value.firstName.trim() &&
          clientInfo.value.lastName.trim() &&
          gdprConsent.value
        )
      case 5:
        return true
      default:
        return false
    }
  })

  const personInfo = computed(() => {
    if (selectedPersonId.value) {
      const person = existingPersons.value.find(p => p.id === selectedPersonId.value)
      if (person) {
        return {
          firstName: person.first_name,
          lastName: person.last_name,
          id: person.id
        }
      }
    }
    return {
      firstName: newPerson.value.firstName,
      lastName: newPerson.value.lastName,
      id: null
    }
  })

  const bookingData = computed(() => {
    const data = {
      session_date: selectedDate.value && selectedTime.value
        ? `${selectedDate.value} ${selectedTime.value}:00`
        : null,
      duration_type: durationType.value,
      client_email: clientInfo.value.email.trim().toLowerCase(),
      client_phone: clientInfo.value.phone.trim() || null,
      client_first_name: clientInfo.value.firstName.trim(),
      client_last_name: clientInfo.value.lastName.trim(),
      person_first_name: personInfo.value.firstName,
      person_last_name: personInfo.value.lastName,
      person_id: personInfo.value.id,
      gdpr_consent: gdprConsent.value,
      client_type: clientInfo.value.clientType || 'personal'
    }

    // Add professional info if professional client
    if (clientInfo.value.clientType === 'professional') {
      data.company_name = clientInfo.value.companyName.trim() || null
      data.siret = clientInfo.value.siret.replace(/\s/g, '').trim() || null
    }

    // Add captcha token if present
    if (captchaToken.value) {
      data.captcha_token = captchaToken.value
    }

    return data
  })

  const durationInfo = computed(() => {
    const type = durationType.value
    // Use dynamic values from scheduleInfo if available
    if (scheduleInfo.value?.durations?.[type]) {
      const info = scheduleInfo.value.durations[type]
      return {
        display: info.display,
        blocked: info.blocked,
        label: type === 'discovery' ? 'Séance découverte (1h15)' : 'Séance classique (45min)'
      }
    }
    // Fallback defaults
    if (type === 'discovery') {
      return {
        display: 75,
        blocked: 90,
        label: 'Séance découverte (1h15)'
      }
    }
    return {
      display: 45,
      blocked: 65,
      label: 'Séance classique (45min)'
    }
  })

  // ========================================
  // ACTIONS
  // ========================================

  async function fetchScheduleInfo() {
    try {
      const response = await publicBookingApi.getSchedule()
      scheduleInfo.value = response.data.data
      durationLabels.value = response.data.data.duration_types
      emailConfirmationRequired.value = response.data.data.email_confirmation_required || false
    } catch (err) {
      console.error('Failed to fetch schedule:', err)
    }
  }

  async function checkEmail(email) {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.checkEmail(email)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la vérification'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPersonsByEmail(email) {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.getPersonsByEmail(email)
      const data = response.data.data

      existingPersons.value = data.persons || []

      // Store existing client info if available
      if (data.existing_client && data.client_info) {
        existingClientInfo.value = data.client_info
        // Pre-fill client info with the real names (not masked)
        clientInfo.value.firstName = data.client_info.first_name
        clientInfo.value.lastName = data.client_info.last_name
        // Pre-fill client type and company info
        clientInfo.value.clientType = data.client_info.client_type || 'personal'
        clientInfo.value.companyName = data.client_info.company_name || ''
        // Auto-accept GDPR if already accepted
        if (data.client_info.gdpr_already_accepted) {
          gdprConsent.value = true
        }
      } else {
        existingClientInfo.value = null
      }

      return existingPersons.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la récupération des personnes'
      existingPersons.value = []
      existingClientInfo.value = null
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchAvailableDates(year = currentYear.value, month = currentMonth.value) {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.getAvailableDates(year, month, durationType.value)
      availableDates.value = response.data.data.available_dates || []
      currentYear.value = year
      currentMonth.value = month
      return availableDates.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la récupération des dates'
      availableDates.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchAvailableSlots(date) {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.getAvailableSlots(date, durationType.value)
      availableSlots.value = response.data.data.slots || []
      return availableSlots.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la récupération des créneaux'
      availableSlots.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createBooking() {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.createBooking(bookingData.value)
      bookingResult.value = response.data.data
      return bookingResult.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la création de la réservation'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function confirmBooking(token) {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.confirmBooking(token)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la confirmation'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function cancelBooking(token) {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.cancelBooking(token)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de l\'annulation'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getBookingByToken(token) {
    loading.value = true
    error.value = null

    try {
      const response = await publicBookingApi.getBookingByToken(token)
      return response.data.data.booking
    } catch (err) {
      error.value = err.response?.data?.message || 'Réservation non trouvée'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Navigation
  function nextStep() {
    if (currentStep.value < totalSteps && canGoNext.value) {
      currentStep.value++
      saveToStorage()
    }
  }

  function prevStep() {
    if (currentStep.value > 1) {
      currentStep.value--
      saveToStorage()
    }
  }

  function goToStep(step) {
    if (step >= 1 && step <= totalSteps) {
      currentStep.value = step
      saveToStorage()
    }
  }

  // Storage persistence
  function saveToStorage() {
    const state = {
      currentStep: currentStep.value,
      isNewClient: isNewClient.value,
      selectedPersonId: selectedPersonId.value,
      newPerson: newPerson.value,
      selectedDate: selectedDate.value,
      selectedTime: selectedTime.value,
      durationType: durationType.value,
      clientInfo: clientInfo.value,
      gdprConsent: gdprConsent.value,
      existingPersons: existingPersons.value
    }
    try {
      localStorage.setItem('booking_wizard_state', JSON.stringify(state))
    } catch (e) {
      console.warn('Failed to save booking state:', e)
    }
  }

  function restoreFromStorage() {
    try {
      const saved = localStorage.getItem('booking_wizard_state')
      if (saved) {
        const state = JSON.parse(saved)
        currentStep.value = state.currentStep || 1
        isNewClient.value = state.isNewClient
        selectedPersonId.value = state.selectedPersonId
        newPerson.value = state.newPerson || { firstName: '', lastName: '' }
        selectedDate.value = state.selectedDate
        selectedTime.value = state.selectedTime
        durationType.value = state.durationType || 'regular'
        clientInfo.value = state.clientInfo || { email: '', phone: '', firstName: '', lastName: '', clientType: 'personal', companyName: '', siret: '' }
        gdprConsent.value = state.gdprConsent || false
        existingPersons.value = state.existingPersons || []
        return true
      }
    } catch (e) {
      console.warn('Failed to restore booking state:', e)
    }
    return false
  }

  function clearStorage() {
    localStorage.removeItem('booking_wizard_state')
  }

  // Reset
  function resetWizard() {
    currentStep.value = 1
    isNewClient.value = null
    existingPersons.value = []
    selectedPersonId.value = null
    newPerson.value = { firstName: '', lastName: '' }
    selectedDate.value = null
    selectedTime.value = null
    durationType.value = 'regular'
    availableDates.value = []
    availableSlots.value = []
    clientInfo.value = { email: '', phone: '', firstName: '', lastName: '', clientType: 'personal', companyName: '', siret: '' }
    gdprConsent.value = false
    captchaToken.value = null
    existingClientInfo.value = null
    bookingResult.value = null
    error.value = null
    clearStorage()
  }

  // Set captcha token
  function setCaptchaToken(token) {
    captchaToken.value = token
  }

  // Set duration type (changes availability)
  function setDurationType(type) {
    if (type !== durationType.value) {
      durationType.value = type
      // Reset date/time selection as slots change
      selectedDate.value = null
      selectedTime.value = null
      availableDates.value = []
      availableSlots.value = []
    }
  }

  // Reset date/time selection (step 3)
  function resetDateTimeSelection() {
    selectedDate.value = null
    selectedTime.value = null
    availableDates.value = []
    availableSlots.value = []
  }

  // Reset contact info (step 4) - for when changing client/identity
  function resetContactInfo() {
    clientInfo.value = {
      email: clientInfo.value.email, // Keep email as it's used for lookup
      phone: '',
      firstName: '',
      lastName: '',
      clientType: 'personal',
      companyName: '',
      siret: ''
    }
    gdprConsent.value = false
    existingClientInfo.value = null
  }

  // Reset all steps following person selection (steps 3, 4, 5)
  function resetFollowingSteps() {
    resetDateTimeSelection()
    resetContactInfo()
    bookingResult.value = null
  }

  return {
    // State
    currentStep,
    totalSteps,
    isNewClient,
    existingPersons,
    selectedPersonId,
    newPerson,
    selectedDate,
    selectedTime,
    durationType,
    availableDates,
    availableSlots,
    currentMonth,
    currentYear,
    clientInfo,
    gdprConsent,
    captchaToken,
    existingClientInfo,
    bookingResult,
    scheduleInfo,
    durationLabels,
    emailConfirmationRequired,
    loading,
    error,

    // Getters
    canGoNext,
    personInfo,
    bookingData,
    durationInfo,

    // Actions
    fetchScheduleInfo,
    checkEmail,
    fetchPersonsByEmail,
    fetchAvailableDates,
    fetchAvailableSlots,
    createBooking,
    confirmBooking,
    cancelBooking,
    getBookingByToken,
    nextStep,
    prevStep,
    goToStep,
    saveToStorage,
    restoreFromStorage,
    clearStorage,
    resetWizard,
    setDurationType,
    setCaptchaToken,
    resetDateTimeSelection,
    resetContactInfo,
    resetFollowingSteps
  }
})
