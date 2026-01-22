import { describe, it, expect } from 'vitest'

/**
 * Tests unitaires pour la validation des données de réservation.
 */
describe('Booking Data Validation', () => {
  // =========================================================================
  // TESTS VALIDATION EMAIL
  // =========================================================================

  describe('Email Validation', () => {
    it.each([
      ['test@example.com', true],
      ['user@mail.example.org', true],
      ['user+tag@example.com', true],
      ['user123@example.com', true],
      ['testexample.com', false],
      ['test@', false],
      ['@example.com', false],
      ['test @example.com', false],
      ['test@@example.com', false],
      ['', false],
    ])('validates email "%s" as %s', (email, isValid) => {
      expect(validateEmail(email)).toBe(isValid)
    })

    it('normalizes email to lowercase', () => {
      expect(normalizeEmail('  Test@EXAMPLE.com  ')).toBe('test@example.com')
    })
  })

  // =========================================================================
  // TESTS VALIDATION TÉLÉPHONE
  // =========================================================================

  describe('Phone Validation', () => {
    it.each([
      ['0612345678', true],
      ['0712345678', true],
      ['06 12 34 56 78', true],
      ['06.12.34.56.78', true],
      ['06-12-34-56-78', true],
      ['+33612345678', true],
      ['+33 6 12 34 56 78', true],
      ['0123456789', true],
      ['061234567', false], // Too short
      ['061234567890', false], // Too long
      ['06123abcde', false],
      ['', true], // Phone is optional
    ])('validates phone "%s" as %s', (phone, isValid) => {
      expect(validatePhone(phone)).toBe(isValid)
    })
  })

  // =========================================================================
  // TESTS VALIDATION NOMS
  // =========================================================================

  describe('Name Validation', () => {
    it.each([
      ['Jean', 2, true],
      ['Jean-Pierre', 2, true],
      ['Hélène', 2, true],
      ["O'Brien", 2, true],
      ['Li', 2, true],
      ['X', 2, false],
      ['', 2, false],
      ['   ', 2, false],
    ])('validates name "%s" with min length %d as %s', (name, minLength, isValid) => {
      expect(validateName(name, minLength)).toBe(isValid)
    })

    it('trims whitespace before validation', () => {
      expect(validateName('  Jean  ', 2)).toBe(true)
    })
  })

  // =========================================================================
  // TESTS VALIDATION GDPR
  // =========================================================================

  describe('GDPR Consent Validation', () => {
    it('requires GDPR consent to be true', () => {
      expect(validateGdprConsent(false)).toBe(false)
      expect(validateGdprConsent(true)).toBe(true)
      expect(validateGdprConsent(null)).toBe(false)
      expect(validateGdprConsent(undefined)).toBe(false)
    })
  })

  // =========================================================================
  // TESTS VALIDATION DATE
  // =========================================================================

  describe('Date Validation', () => {
    it.each([
      ['2024-02-15', true],
      ['2024-02-15 10:00:00', true],
      ['2024-02-15T10:00:00', true],
      ['15/02/2024', false],
      ['next monday', false],
      ['', false],
    ])('validates date format "%s" as %s', (date, isValid) => {
      expect(validateDateFormat(date)).toBe(isValid)
    })

    it('checks if date is in future', () => {
      const tomorrow = new Date()
      tomorrow.setDate(tomorrow.getDate() + 1)
      const yesterday = new Date()
      yesterday.setDate(yesterday.getDate() - 1)

      expect(isDateInFuture(tomorrow)).toBe(true)
      expect(isDateInFuture(yesterday)).toBe(false)
    })

    it('checks if date is within booking window', () => {
      const now = new Date()
      const minAdvanceHours = 24
      const maxAdvanceDays = 60

      // Too soon (12 hours)
      const tooSoon = new Date(now.getTime() + 12 * 60 * 60 * 1000)
      expect(isDateWithinBookingWindow(tooSoon, minAdvanceHours, maxAdvanceDays)).toBe(false)

      // Valid (2 days)
      const valid = new Date(now.getTime() + 2 * 24 * 60 * 60 * 1000)
      expect(isDateWithinBookingWindow(valid, minAdvanceHours, maxAdvanceDays)).toBe(true)

      // Too far (90 days)
      const tooFar = new Date(now.getTime() + 90 * 24 * 60 * 60 * 1000)
      expect(isDateWithinBookingWindow(tooFar, minAdvanceHours, maxAdvanceDays)).toBe(false)
    })
  })

  // =========================================================================
  // TESTS VALIDATION TYPE CLIENT
  // =========================================================================

  describe('Client Type Validation', () => {
    it.each([
      ['personal', true],
      ['association', true],
      ['business', false],
      ['', false],
      ['professional', false],
    ])('validates client type "%s" as %s', (type, isValid) => {
      expect(validateClientType(type)).toBe(isValid)
    })
  })

  // =========================================================================
  // TESTS VALIDATION SIRET
  // =========================================================================

  describe('SIRET Validation', () => {
    it.each([
      ['12345678901234', true],
      ['123 456 789 01234', true],
      ['1234567890123', false], // Too short
      ['123456789012345', false], // Too long
      ['1234567890123A', false],
      ['', true], // SIRET is optional
    ])('validates SIRET "%s" as %s', (siret, isValid) => {
      expect(validateSiret(siret)).toBe(isValid)
    })
  })

  // =========================================================================
  // TESTS VALIDATION COMPLÈTE
  // =========================================================================

  describe('Complete Booking Validation', () => {
    it('validates correct booking data', () => {
      const data = {
        session_date: '2024-02-15 10:00:00',
        duration_type: 'regular',
        client_email: 'test@example.com',
        client_first_name: 'Jean',
        client_last_name: 'Dupont',
        person_first_name: 'Marie',
        person_last_name: 'Dupont',
        gdpr_consent: true
      }

      const errors = validateBookingData(data)
      expect(Object.keys(errors)).toHaveLength(0)
    })

    it('returns errors for invalid data', () => {
      const data = {
        session_date: '',
        duration_type: '',
        client_email: 'invalid',
        client_first_name: 'J',
        client_last_name: 'D',
        person_first_name: 'M',
        person_last_name: 'D',
        gdpr_consent: false
      }

      const errors = validateBookingData(data)

      expect(errors.session_date).toBeDefined()
      expect(errors.duration_type).toBeDefined()
      expect(errors.client_email).toBeDefined()
      expect(errors.client_first_name).toBeDefined()
      expect(errors.client_last_name).toBeDefined()
      expect(errors.person_first_name).toBeDefined()
      expect(errors.person_last_name).toBeDefined()
      expect(errors.gdpr_consent).toBeDefined()
    })

    it('validates association data with SIRET', () => {
      const data = {
        session_date: '2024-02-15 10:00:00',
        duration_type: 'regular',
        client_email: 'assoc@example.com',
        client_first_name: 'Jean',
        client_last_name: 'Dupont',
        person_first_name: 'Marie',
        person_last_name: 'Dupont',
        gdpr_consent: true,
        client_type: 'association',
        company_name: 'Association Test',
        siret: '12345678901234'
      }

      const errors = validateBookingData(data)
      expect(Object.keys(errors)).toHaveLength(0)
    })
  })
})

describe('Rate Limiting', () => {
  // =========================================================================
  // TESTS LIMITES PAR TYPE CLIENT
  // =========================================================================

  describe('Client Type Limits', () => {
    it('returns correct limits for personal clients', () => {
      const limits = getRateLimits('personal')
      expect(limits.max_per_ip).toBe(4)
      expect(limits.max_per_email).toBe(4)
    })

    it('returns correct limits for associations', () => {
      const limits = getRateLimits('association')
      expect(limits.max_per_ip).toBe(20)
      expect(limits.max_per_email).toBe(20)
    })

    it('uses custom settings when provided', () => {
      const settings = {
        booking_max_per_ip: 5,
        booking_max_per_email: 6,
        booking_max_per_ip_association: 30,
        booking_max_per_email_association: 25
      }

      const personalLimits = getRateLimits('personal', settings)
      expect(personalLimits.max_per_ip).toBe(5)
      expect(personalLimits.max_per_email).toBe(6)

      const assocLimits = getRateLimits('association', settings)
      expect(assocLimits.max_per_ip).toBe(30)
      expect(assocLimits.max_per_email).toBe(25)
    })
  })

  describe('Rate Limit Check', () => {
    it.each([
      [0, 4, true],
      [3, 4, true],
      [4, 4, false],
      [5, 4, false],
      [0, 20, true],
      [19, 20, true],
      [20, 20, false],
    ])('allows booking when current=%d and max=%d is %s', (current, max, shouldAllow) => {
      expect(checkRateLimit(current, max)).toBe(shouldAllow)
    })
  })
})

describe('Booking Data Composition', () => {
  it('composes booking data without promo', () => {
    const clientInfo = {
      email: 'test@example.com',
      phone: '0612345678',
      firstName: 'Jean',
      lastName: 'Dupont',
      clientType: 'personal'
    }

    const personInfo = {
      firstName: 'Marie',
      lastName: 'Dupont',
      id: null
    }

    const data = composeBookingData({
      selectedDate: '2024-02-15',
      selectedTime: '10:00',
      durationType: 'regular',
      clientInfo,
      personInfo,
      gdprConsent: true,
      appliedPromo: null
    })

    expect(data.session_date).toBe('2024-02-15 10:00:00')
    expect(data.duration_type).toBe('regular')
    expect(data.client_email).toBe('test@example.com')
    expect(data.client_phone).toBe('0612345678')
    expect(data.client_first_name).toBe('Jean')
    expect(data.client_last_name).toBe('Dupont')
    expect(data.person_first_name).toBe('Marie')
    expect(data.person_last_name).toBe('Dupont')
    expect(data.gdpr_consent).toBe(true)
    expect(data.promo_code).toBeUndefined()
    expect(data.promo_code_id).toBeUndefined()
  })

  it('composes booking data with manual promo code', () => {
    const data = composeBookingData({
      selectedDate: '2024-02-15',
      selectedTime: '10:00',
      durationType: 'regular',
      clientInfo: { email: 'test@example.com', firstName: 'Jean', lastName: 'Dupont', clientType: 'personal' },
      personInfo: { firstName: 'Marie', lastName: 'Dupont', id: null },
      gdprConsent: true,
      appliedPromo: { id: 'promo-123', code: 'SUMMER20' }
    })

    expect(data.promo_code).toBe('SUMMER20')
    expect(data.promo_code_id).toBeUndefined()
  })

  it('composes booking data with automatic promo', () => {
    const data = composeBookingData({
      selectedDate: '2024-02-15',
      selectedTime: '10:00',
      durationType: 'regular',
      clientInfo: { email: 'test@example.com', firstName: 'Jean', lastName: 'Dupont', clientType: 'personal' },
      personInfo: { firstName: 'Marie', lastName: 'Dupont', id: null },
      gdprConsent: true,
      appliedPromo: { id: 'auto-456', code: null }
    })

    expect(data.promo_code).toBeUndefined()
    expect(data.promo_code_id).toBe('auto-456')
  })

  it('includes person_id when selecting existing person', () => {
    const data = composeBookingData({
      selectedDate: '2024-02-15',
      selectedTime: '10:00',
      durationType: 'regular',
      clientInfo: { email: 'test@example.com', firstName: 'Jean', lastName: 'Dupont', clientType: 'personal' },
      personInfo: { firstName: 'Marie', lastName: 'Dupont', id: 'person-789' },
      gdprConsent: true,
      appliedPromo: null
    })

    expect(data.person_id).toBe('person-789')
  })
})

describe('Duration Info', () => {
  it('returns correct info for discovery session', () => {
    const info = getDurationInfo('discovery', { discovery: 55, regular: 45 })

    expect(info.display).toBe(75)
    expect(info.blocked).toBe(90)
    expect(info.price).toBe(55)
  })

  it('returns correct info for regular session', () => {
    const info = getDurationInfo('regular', { discovery: 55, regular: 45 })

    expect(info.display).toBe(45)
    expect(info.blocked).toBe(65)
    expect(info.price).toBe(45)
  })

  it('calculates pause duration correctly', () => {
    const discoveryInfo = getDurationInfo('discovery', {})
    const regularInfo = getDurationInfo('regular', {})

    expect(discoveryInfo.blocked - discoveryInfo.display).toBe(15) // 15min pause
    expect(regularInfo.blocked - regularInfo.display).toBe(20) // 20min pause
  })
})

// =========================================================================
// HELPER FUNCTIONS
// =========================================================================

function validateEmail(email) {
  if (!email) return false
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return regex.test(email)
}

function normalizeEmail(email) {
  return email.trim().toLowerCase()
}

function validatePhone(phone) {
  if (!phone || phone === '') return true // Optional

  const cleaned = phone.replace(/[\s.\-]/g, '')
  let normalized = cleaned

  if (normalized.startsWith('+33')) {
    normalized = '0' + normalized.slice(3)
  }

  return /^0[1-9][0-9]{8}$/.test(normalized)
}

function validateName(name, minLength) {
  if (!name) return false
  const trimmed = name.trim()
  return trimmed.length >= minLength
}

function validateGdprConsent(consent) {
  return consent === true
}

function validateDateFormat(date) {
  if (!date) return false
  const formats = [
    /^\d{4}-\d{2}-\d{2}$/,
    /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/,
    /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/
  ]
  return formats.some(regex => regex.test(date))
}

function isDateInFuture(date) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return date > today
}

function isDateWithinBookingWindow(date, minAdvanceHours, maxAdvanceDays) {
  const now = new Date()
  const minDate = new Date(now.getTime() + minAdvanceHours * 60 * 60 * 1000)
  const maxDate = new Date(now.getTime() + maxAdvanceDays * 24 * 60 * 60 * 1000)
  return date >= minDate && date <= maxDate
}

function validateClientType(type) {
  return ['personal', 'association'].includes(type)
}

function validateSiret(siret) {
  if (!siret || siret === '') return true // Optional
  const cleaned = siret.replace(/\s/g, '')
  return /^\d{14}$/.test(cleaned)
}

function validateBookingData(data) {
  const errors = {}

  if (!data.session_date || !validateDateFormat(data.session_date)) {
    errors.session_date = 'Date de séance invalide'
  }

  if (!data.duration_type || !['discovery', 'regular'].includes(data.duration_type)) {
    errors.duration_type = 'Type de séance invalide'
  }

  if (!data.client_email || !validateEmail(data.client_email)) {
    errors.client_email = 'Email invalide'
  }

  if (!validateName(data.client_first_name, 2)) {
    errors.client_first_name = 'Le prénom doit contenir au moins 2 caractères'
  }

  if (!validateName(data.client_last_name, 2)) {
    errors.client_last_name = 'Le nom doit contenir au moins 2 caractères'
  }

  if (!validateName(data.person_first_name, 2)) {
    errors.person_first_name = 'Le prénom du bénéficiaire doit contenir au moins 2 caractères'
  }

  if (!validateName(data.person_last_name, 2)) {
    errors.person_last_name = 'Le nom du bénéficiaire doit contenir au moins 2 caractères'
  }

  if (!validateGdprConsent(data.gdpr_consent)) {
    errors.gdpr_consent = 'Le consentement RGPD est obligatoire'
  }

  return errors
}

function getRateLimits(clientType, settings = {}) {
  if (clientType === 'association') {
    return {
      max_per_ip: settings.booking_max_per_ip_association || 20,
      max_per_email: settings.booking_max_per_email_association || 20
    }
  }
  return {
    max_per_ip: settings.booking_max_per_ip || 4,
    max_per_email: settings.booking_max_per_email || 4
  }
}

function checkRateLimit(current, max) {
  return current < max
}

function composeBookingData({
  selectedDate,
  selectedTime,
  durationType,
  clientInfo,
  personInfo,
  gdprConsent,
  appliedPromo
}) {
  const data = {
    session_date: `${selectedDate} ${selectedTime}:00`,
    duration_type: durationType,
    client_email: clientInfo.email.trim().toLowerCase(),
    client_phone: clientInfo.phone?.trim() || null,
    client_first_name: clientInfo.firstName.trim(),
    client_last_name: clientInfo.lastName.trim(),
    person_first_name: personInfo.firstName,
    person_last_name: personInfo.lastName,
    person_id: personInfo.id,
    gdpr_consent: gdprConsent,
    client_type: clientInfo.clientType || 'personal'
  }

  if (appliedPromo) {
    if (appliedPromo.code) {
      data.promo_code = appliedPromo.code
    } else {
      data.promo_code_id = appliedPromo.id
    }
  }

  return data
}

function getDurationInfo(type, prices) {
  if (type === 'discovery') {
    return {
      display: 75,
      blocked: 90,
      price: prices.discovery || 55
    }
  }
  return {
    display: 45,
    blocked: 65,
    price: prices.regular || 45
  }
}
