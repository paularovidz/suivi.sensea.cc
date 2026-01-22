import { describe, it, expect, beforeEach } from 'vitest'

/**
 * Tests unitaires pour la logique des codes promo côté frontend.
 * Ces tests vérifient les calculs et validations sans appels API.
 */
describe('PromoCode Calculations', () => {
  // =========================================================================
  // TESTS CALCUL DE REMISE
  // =========================================================================

  describe('calculateDiscount', () => {
    it.each([
      // Percentage discounts
      ['percentage', 10, 45, 4.5, 40.5],
      ['percentage', 20, 55, 11, 44],
      ['percentage', 50, 45, 22.5, 22.5],
      ['percentage', 100, 45, 45, 0],

      // Fixed amount discounts
      ['fixed_amount', 5, 45, 5, 40],
      ['fixed_amount', 10, 55, 10, 45],
      ['fixed_amount', 20, 45, 20, 25],

      // Free session
      ['free_session', 100, 45, 45, 0],
      ['free_session', 100, 55, 55, 0],

      // Edge cases - discount larger than price
      ['fixed_amount', 50, 45, 45, 0],
      ['fixed_amount', 100, 45, 45, 0],
    ])('calculates %s discount of %d on %d€ correctly', (type, value, price, expectedDiscount, expectedFinal) => {
      const result = calculateDiscount({ discount_type: type, discount_value: value }, price)

      expect(result.discount_amount).toBe(expectedDiscount)
      expect(result.final_price).toBe(expectedFinal)
      expect(result.original_price).toBe(price)
    })

    it('never returns negative final price', () => {
      const result = calculateDiscount({ discount_type: 'fixed_amount', discount_value: 100 }, 45)
      expect(result.final_price).toBe(0)
      expect(result.final_price).toBeGreaterThanOrEqual(0)
    })

    it('rounds values to 2 decimal places', () => {
      const result = calculateDiscount({ discount_type: 'percentage', discount_value: 33.33 }, 45)
      // 45 * 0.3333 = 14.9985, should round properly
      expect(Number.isInteger(result.discount_amount * 100)).toBe(true) // Check 2 decimal precision
    })
  })

  // =========================================================================
  // TESTS LABELS
  // =========================================================================

  describe('getDiscountLabel', () => {
    it.each([
      ['percentage', 10, '-10%'],
      ['percentage', 25, '-25%'],
      ['percentage', 50, '-50%'],
      ['fixed_amount', 5, '-5,00 €'],
      ['fixed_amount', 10, '-10,00 €'],
      ['fixed_amount', 15.5, '-15,50 €'],
      ['free_session', 100, 'Gratuit'],
    ])('formats %s discount of %d as "%s"', (type, value, expectedLabel) => {
      const label = getDiscountLabel({ discount_type: type, discount_value: value })
      expect(label).toBe(expectedLabel)
    })
  })

  // =========================================================================
  // TESTS PRIX SESSIONS
  // =========================================================================

  describe('Session Pricing', () => {
    it('returns correct price for discovery session', () => {
      const price = getPriceForType('discovery', { discovery: 55, regular: 45 })
      expect(price).toBe(55)
    })

    it('returns correct price for regular session', () => {
      const price = getPriceForType('regular', { discovery: 55, regular: 45 })
      expect(price).toBe(45)
    })

    it('uses default prices when not configured', () => {
      const discoveryPrice = getPriceForType('discovery', {})
      const regularPrice = getPriceForType('regular', {})

      expect(discoveryPrice).toBe(55)
      expect(regularPrice).toBe(45)
    })
  })

  // =========================================================================
  // TESTS APPLICATION PROMO AU BOOKING
  // =========================================================================

  describe('Promo Application to Booking', () => {
    it('correctly applies manual promo code', () => {
      const promo = {
        id: 'promo-123',
        code: 'SUMMER20',
        discount_type: 'percentage',
        discount_value: 20
      }

      const result = applyPromoToBooking(promo, 45)

      expect(result.promo_code).toBe('SUMMER20')
      expect(result.promo_code_id).toBeUndefined()
      expect(result.original_price).toBe(45)
      expect(result.discount_amount).toBe(9)
      expect(result.final_price).toBe(36)
    })

    it('correctly applies automatic promo (no code)', () => {
      const promo = {
        id: 'auto-promo-456',
        code: null,
        discount_type: 'fixed_amount',
        discount_value: 10
      }

      const result = applyPromoToBooking(promo, 55)

      expect(result.promo_code).toBeUndefined()
      expect(result.promo_code_id).toBe('auto-promo-456')
      expect(result.original_price).toBe(55)
      expect(result.discount_amount).toBe(10)
      expect(result.final_price).toBe(45)
    })

    it('handles free session promo', () => {
      const promo = {
        id: 'loyalty-promo',
        code: 'FIDEL-ABC123',
        discount_type: 'free_session',
        discount_value: 100
      }

      const result = applyPromoToBooking(promo, 45)

      expect(result.final_price).toBe(0)
      expect(result.discount_amount).toBe(45)
    })

    it('returns null pricing when no promo', () => {
      const result = applyPromoToBooking(null, 45)

      expect(result).toBeNull()
    })
  })

  // =========================================================================
  // TESTS COMPARAISON PROMOS
  // =========================================================================

  describe('Best Promo Selection', () => {
    it('selects promo with highest euro discount', () => {
      const promos = [
        { discount_type: 'percentage', discount_value: 10 }, // 4.5€ off
        { discount_type: 'fixed_amount', discount_value: 5 }, // 5€ off - BEST
        { discount_type: 'percentage', discount_value: 8 }, // 3.6€ off
      ]

      const best = selectBestPromo(promos, 45)
      expect(best.discount_type).toBe('fixed_amount')
      expect(best.discount_value).toBe(5)
    })

    it('selects free session over other promos', () => {
      const promos = [
        { discount_type: 'percentage', discount_value: 50 }, // 22.5€ off
        { discount_type: 'free_session', discount_value: 100 }, // 45€ off - BEST
        { discount_type: 'fixed_amount', discount_value: 30 }, // 30€ off
      ]

      const best = selectBestPromo(promos, 45)
      expect(best.discount_type).toBe('free_session')
    })

    it('compares percentage vs fixed based on actual euro amount', () => {
      // For 55€ session: 20% = 11€, 10€ fixed = 10€, so 20% is better
      const promos = [
        { discount_type: 'percentage', discount_value: 20 }, // 11€ off
        { discount_type: 'fixed_amount', discount_value: 10 }, // 10€ off
      ]

      let best = selectBestPromo(promos, 55)
      expect(best.discount_type).toBe('percentage')

      // For 45€ session: 20% = 9€, 10€ fixed = 10€, so fixed is better
      best = selectBestPromo(promos, 45)
      expect(best.discount_type).toBe('fixed_amount')
    })

    it('returns null for empty promo list', () => {
      const best = selectBestPromo([], 45)
      expect(best).toBeNull()
    })
  })
})

describe('PromoCode Validation', () => {
  // =========================================================================
  // TESTS FORMAT CODE
  // =========================================================================

  describe('Code Format Validation', () => {
    it.each([
      ['SUMMER20', true],
      ['summer20', true], // Should normalize to uppercase
      ['ABC123', true],
      ['FIDEL-ABC123', true],
      ['', false],
      ['   ', false],
    ])('validates code format "%s" as %s', (code, isValid) => {
      const result = validateCodeFormat(code)
      expect(result).toBe(isValid)
    })

    it('normalizes code to uppercase', () => {
      const normalized = normalizePromoCode('summer20')
      expect(normalized).toBe('SUMMER20')
    })

    it('trims whitespace from code', () => {
      const normalized = normalizePromoCode('  SUMMER20  ')
      expect(normalized).toBe('SUMMER20')
    })
  })

  // =========================================================================
  // TESTS VALIDATION FRONTEND
  // =========================================================================

  describe('Frontend Validation', () => {
    it('validates promo input is not empty', () => {
      expect(validatePromoInput('')).toEqual({ valid: false, error: 'Veuillez entrer un code' })
      expect(validatePromoInput('   ')).toEqual({ valid: false, error: 'Veuillez entrer un code' })
    })

    it('passes valid promo code', () => {
      expect(validatePromoInput('SUMMER20')).toEqual({ valid: true, code: 'SUMMER20' })
    })

    it('normalizes code before validation', () => {
      const result = validatePromoInput('  summer20  ')
      expect(result.valid).toBe(true)
      expect(result.code).toBe('SUMMER20')
    })
  })
})

describe('Loyalty Promo Code', () => {
  it('recognizes loyalty code format', () => {
    expect(isLoyaltyCode('FIDEL-ABC123')).toBe(true)
    expect(isLoyaltyCode('FIDEL-XYZ789')).toBe(true)
    expect(isLoyaltyCode('SUMMER20')).toBe(false)
    expect(isLoyaltyCode('ABC-123456')).toBe(false)
  })

  it('loyalty code should provide free session', () => {
    const promo = {
      code: 'FIDEL-ABC123',
      discount_type: 'free_session',
      discount_value: 100
    }

    const result = calculateDiscount(promo, 45)
    expect(result.final_price).toBe(0)
  })
})

// =========================================================================
// HELPER FUNCTIONS (simulating frontend logic)
// =========================================================================

function calculateDiscount(promo, originalPrice) {
  let discountAmount = 0

  switch (promo.discount_type) {
    case 'percentage':
      discountAmount = originalPrice * (promo.discount_value / 100)
      break
    case 'fixed_amount':
      discountAmount = promo.discount_value
      break
    case 'free_session':
      discountAmount = originalPrice
      break
  }

  const finalPrice = Math.max(0, originalPrice - discountAmount)
  discountAmount = originalPrice - finalPrice

  return {
    original_price: Math.round(originalPrice * 100) / 100,
    discount_amount: Math.round(discountAmount * 100) / 100,
    final_price: Math.round(finalPrice * 100) / 100
  }
}

function getDiscountLabel(promo) {
  const value = promo.discount_value

  switch (promo.discount_type) {
    case 'percentage':
      return `-${Math.round(value)}%`
    case 'fixed_amount':
      return `-${value.toFixed(2).replace('.', ',')} €`
    case 'free_session':
      return 'Gratuit'
    default:
      return ''
  }
}

function getPriceForType(type, prices) {
  if (type === 'discovery') {
    return prices.discovery || 55
  }
  return prices.regular || 45
}

function applyPromoToBooking(promo, originalPrice) {
  if (!promo) return null

  const discount = calculateDiscount(promo, originalPrice)

  const result = {
    original_price: discount.original_price,
    discount_amount: discount.discount_amount,
    final_price: discount.final_price
  }

  if (promo.code) {
    result.promo_code = promo.code
  } else {
    result.promo_code_id = promo.id
  }

  return result
}

function selectBestPromo(promos, originalPrice) {
  if (!promos || promos.length === 0) return null

  // Calculate discount for each promo
  const withDiscounts = promos.map(promo => ({
    ...promo,
    _discount: calculateDiscount(promo, originalPrice).discount_amount
  }))

  // Sort by discount amount descending
  withDiscounts.sort((a, b) => b._discount - a._discount)

  const best = { ...withDiscounts[0] }
  delete best._discount

  return best
}

function validateCodeFormat(code) {
  if (!code || typeof code !== 'string') return false
  const trimmed = code.trim()
  return trimmed.length > 0
}

function normalizePromoCode(code) {
  if (!code) return ''
  return code.trim().toUpperCase()
}

function validatePromoInput(code) {
  if (!code || !code.trim()) {
    return { valid: false, error: 'Veuillez entrer un code' }
  }

  return { valid: true, code: normalizePromoCode(code) }
}

function isLoyaltyCode(code) {
  if (!code) return false
  return code.startsWith('FIDEL-')
}
