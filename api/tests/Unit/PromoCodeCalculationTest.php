<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les calculs de codes promo.
 * Ces tests vérifient la logique de calcul sans accès à la base de données.
 */
class PromoCodeCalculationTest extends TestCase
{
    // =========================================================================
    // TESTS CALCUL DE REMISE
    // =========================================================================

    /**
     * @dataProvider discountCalculationProvider
     */
    public function testCalculateDiscount(
        string $discountType,
        float $discountValue,
        float $originalPrice,
        float $expectedDiscount,
        float $expectedFinalPrice
    ): void {
        $result = $this->calculateDiscount([
            'discount_type' => $discountType,
            'discount_value' => $discountValue
        ], $originalPrice);

        $this->assertEquals($expectedDiscount, $result['discount_amount'], "Discount amount mismatch for {$discountType}");
        $this->assertEquals($expectedFinalPrice, $result['final_price'], "Final price mismatch for {$discountType}");
        $this->assertEquals($originalPrice, $result['original_price'], "Original price should be preserved");
    }

    public static function discountCalculationProvider(): array
    {
        return [
            // Percentage discounts
            'percentage_10_on_45' => ['percentage', 10, 45.0, 4.5, 40.5],
            'percentage_20_on_55' => ['percentage', 20, 55.0, 11.0, 44.0],
            'percentage_50_on_45' => ['percentage', 50, 45.0, 22.5, 22.5],
            'percentage_100_on_45' => ['percentage', 100, 45.0, 45.0, 0.0],

            // Fixed amount discounts
            'fixed_5_on_45' => ['fixed_amount', 5, 45.0, 5.0, 40.0],
            'fixed_10_on_55' => ['fixed_amount', 10, 55.0, 10.0, 45.0],
            'fixed_20_on_45' => ['fixed_amount', 20, 45.0, 20.0, 25.0],

            // Free session (100% off)
            'free_session_on_45' => ['free_session', 100, 45.0, 45.0, 0.0],
            'free_session_on_55' => ['free_session', 100, 55.0, 55.0, 0.0],

            // Edge cases - discount larger than price
            'fixed_50_on_45' => ['fixed_amount', 50, 45.0, 45.0, 0.0],
            'fixed_100_on_45' => ['fixed_amount', 100, 45.0, 45.0, 0.0],
        ];
    }

    public function testDiscountNeverGoesNegative(): void
    {
        // Fixed amount higher than price
        $result = $this->calculateDiscount([
            'discount_type' => 'fixed_amount',
            'discount_value' => 100
        ], 45.0);

        $this->assertEquals(0.0, $result['final_price'], "Final price should never be negative");
        $this->assertEquals(45.0, $result['discount_amount'], "Discount should be capped at original price");

        // Percentage over 100%
        $result = $this->calculateDiscount([
            'discount_type' => 'percentage',
            'discount_value' => 150
        ], 45.0);

        $this->assertEquals(0.0, $result['final_price'], "Final price should never be negative with >100% discount");
    }

    public function testRoundingPrecision(): void
    {
        // Test that values are properly rounded to 2 decimal places
        $result = $this->calculateDiscount([
            'discount_type' => 'percentage',
            'discount_value' => 33.33
        ], 45.0);

        // 45 * 0.3333 = 14.9985, should round to 15.0
        $this->assertEquals(15.0, $result['discount_amount'], "Discount should be rounded to 2 decimals");
        $this->assertEquals(30.0, $result['final_price'], "Final price should be rounded to 2 decimals");
    }

    // =========================================================================
    // TESTS VALIDATION
    // =========================================================================

    public function testValidateActivePromo(): void
    {
        $promo = $this->createPromo([
            'is_active' => true,
            'valid_from' => null,
            'valid_until' => null,
            'applies_to_discovery' => true,
            'applies_to_regular' => true,
            'target_client_type' => null,
            'target_user_id' => null,
            'max_uses_total' => null,
            'max_uses_per_user' => null
        ]);

        $result = $this->validatePromo($promo, 'regular');
        $this->assertTrue($result['valid'], "Active promo without restrictions should be valid");
    }

    public function testValidateInactivePromo(): void
    {
        $promo = $this->createPromo(['is_active' => false]);

        $result = $this->validatePromo($promo, 'regular');
        $this->assertFalse($result['valid'], "Inactive promo should be invalid");
        $this->assertStringContainsString('actif', $result['error']);
    }

    public function testValidatePromoDateRange(): void
    {
        // Not yet valid
        $promo = $this->createPromo([
            'is_active' => true,
            'valid_from' => (new \DateTime('+1 day'))->format('Y-m-d'),
            'valid_until' => null
        ]);

        $result = $this->validatePromo($promo, 'regular');
        $this->assertFalse($result['valid'], "Promo starting in future should be invalid");
        $this->assertStringContainsString('pas encore valide', $result['error']);

        // Expired
        $promo = $this->createPromo([
            'is_active' => true,
            'valid_from' => null,
            'valid_until' => (new \DateTime('-1 day'))->format('Y-m-d')
        ]);

        $result = $this->validatePromo($promo, 'regular');
        $this->assertFalse($result['valid'], "Expired promo should be invalid");
        $this->assertStringContainsString('expiré', $result['error']);
    }

    public function testValidatePromoSessionType(): void
    {
        // Only for discovery sessions
        $promo = $this->createPromo([
            'is_active' => true,
            'applies_to_discovery' => true,
            'applies_to_regular' => false
        ]);

        $result = $this->validatePromo($promo, 'discovery');
        $this->assertTrue($result['valid'], "Promo for discovery should be valid for discovery session");

        $result = $this->validatePromo($promo, 'regular');
        $this->assertFalse($result['valid'], "Promo for discovery only should be invalid for regular session");

        // Only for regular sessions
        $promo = $this->createPromo([
            'is_active' => true,
            'applies_to_discovery' => false,
            'applies_to_regular' => true
        ]);

        $result = $this->validatePromo($promo, 'regular');
        $this->assertTrue($result['valid'], "Promo for regular should be valid for regular session");

        $result = $this->validatePromo($promo, 'discovery');
        $this->assertFalse($result['valid'], "Promo for regular only should be invalid for discovery session");
    }

    public function testValidatePromoClientType(): void
    {
        // Only for personal clients
        $promo = $this->createPromo([
            'is_active' => true,
            'target_client_type' => 'personal'
        ]);

        $result = $this->validatePromo($promo, 'regular', null, 'personal');
        $this->assertTrue($result['valid'], "Personal promo should be valid for personal client");

        $result = $this->validatePromo($promo, 'regular', null, 'association');
        $this->assertFalse($result['valid'], "Personal promo should be invalid for association client");
        $this->assertStringContainsString('particuliers', $result['error']);

        // Only for associations
        $promo = $this->createPromo([
            'is_active' => true,
            'target_client_type' => 'association'
        ]);

        $result = $this->validatePromo($promo, 'regular', null, 'association');
        $this->assertTrue($result['valid'], "Association promo should be valid for association client");

        $result = $this->validatePromo($promo, 'regular', null, 'personal');
        $this->assertFalse($result['valid'], "Association promo should be invalid for personal client");
        $this->assertStringContainsString('associations', $result['error']);
    }

    public function testValidatePromoTargetUser(): void
    {
        $targetUserId = 'user-123';

        $promo = $this->createPromo([
            'is_active' => true,
            'target_user_id' => $targetUserId
        ]);

        // Correct user
        $result = $this->validatePromo($promo, 'regular', $targetUserId);
        $this->assertTrue($result['valid'], "Promo targeting specific user should be valid for that user");

        // Wrong user
        $result = $this->validatePromo($promo, 'regular', 'other-user');
        $this->assertFalse($result['valid'], "Promo targeting specific user should be invalid for other user");
        $this->assertStringContainsString('votre compte', $result['error']);

        // No user provided
        $result = $this->validatePromo($promo, 'regular', null);
        $this->assertFalse($result['valid'], "Promo targeting specific user should be invalid when no user");
    }

    public function testValidatePromoUsageLimits(): void
    {
        // Max total uses reached
        $promo = $this->createPromo([
            'is_active' => true,
            'max_uses_total' => 10
        ]);

        $result = $this->validatePromoWithUsage($promo, 'regular', null, null, 10);
        $this->assertFalse($result['valid'], "Promo at max total uses should be invalid");
        $this->assertStringContainsString('maximum', $result['error']);

        $result = $this->validatePromoWithUsage($promo, 'regular', null, null, 9);
        $this->assertTrue($result['valid'], "Promo below max total uses should be valid");

        // Max per user reached
        $promo = $this->createPromo([
            'is_active' => true,
            'max_uses_per_user' => 2
        ]);

        $result = $this->validatePromoWithUsage($promo, 'regular', 'user-123', null, 0, 2);
        $this->assertFalse($result['valid'], "Promo at max per-user uses should be invalid");
        $this->assertStringContainsString('déjà utilisé', $result['error']);

        $result = $this->validatePromoWithUsage($promo, 'regular', 'user-123', null, 0, 1);
        $this->assertTrue($result['valid'], "Promo below max per-user uses should be valid");
    }

    // =========================================================================
    // TESTS LABELS
    // =========================================================================

    /**
     * @dataProvider discountLabelProvider
     */
    public function testGetDiscountLabel(string $discountType, float $discountValue, string $expectedLabel): void
    {
        $label = $this->getDiscountLabel([
            'discount_type' => $discountType,
            'discount_value' => $discountValue
        ]);

        $this->assertEquals($expectedLabel, $label);
    }

    public static function discountLabelProvider(): array
    {
        return [
            'percentage_10' => ['percentage', 10, '-10%'],
            'percentage_25' => ['percentage', 25, '-25%'],
            'percentage_50' => ['percentage', 50, '-50%'],
            'fixed_5' => ['fixed_amount', 5, '-5,00 €'],
            'fixed_10' => ['fixed_amount', 10, '-10,00 €'],
            'fixed_15_50' => ['fixed_amount', 15.50, '-15,50 €'],
            'free_session' => ['free_session', 100, 'Gratuit'],
        ];
    }

    // =========================================================================
    // TESTS LOYALTY CODE
    // =========================================================================

    public function testGenerateLoyaltyCodeFormat(): void
    {
        // Test that loyalty codes have correct format
        $codePattern = '/^FIDEL-[A-HJ-NP-Z2-9]{6}$/';

        // Generate 10 codes to verify pattern and uniqueness
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = $this->generateLoyaltyCode();
            $this->assertMatchesRegularExpression($codePattern, $code, "Loyalty code should match FIDEL-XXXXXX pattern");
            $codes[] = $code;
        }

        // Check uniqueness (all codes should be different)
        $this->assertCount(10, array_unique($codes), "Generated codes should be unique");
    }

    public function testGenerateRandomCodeExcludesConfusingCharacters(): void
    {
        // Characters that should NOT appear: I, O, 0, 1
        $forbiddenChars = ['I', 'O', '0', '1'];

        for ($i = 0; $i < 50; $i++) {
            $code = $this->generateRandomCode(10);
            foreach ($forbiddenChars as $char) {
                $this->assertStringNotContainsString($char, $code, "Code should not contain confusing character: $char");
            }
        }
    }

    // =========================================================================
    // TESTS SÉLECTION MEILLEURE PROMO AUTOMATIQUE
    // =========================================================================

    public function testSelectBestAutomaticPromo(): void
    {
        $promos = [
            $this->createAutoPromo('percentage', 10, 45),  // 4.5€ off
            $this->createAutoPromo('fixed_amount', 5, 45), // 5€ off
            $this->createAutoPromo('percentage', 15, 45),  // 6.75€ off - BEST
        ];

        $best = $this->selectBestPromo($promos, 45);

        $this->assertEquals(15, $best['discount_value'], "Should select promo with highest discount amount");
        $this->assertEquals('percentage', $best['discount_type']);
    }

    public function testSelectBestPromoWithFreeSession(): void
    {
        $promos = [
            $this->createAutoPromo('percentage', 50, 45),   // 22.5€ off
            $this->createAutoPromo('free_session', 100, 45), // 45€ off - BEST
            $this->createAutoPromo('fixed_amount', 30, 45), // 30€ off
        ];

        $best = $this->selectBestPromo($promos, 45);

        $this->assertEquals('free_session', $best['discount_type'], "Free session should be selected when it's the best");
    }

    public function testSelectBestPromoComparesByActualDiscount(): void
    {
        // 20% of 55€ = 11€, but 10€ fixed = 10€
        // So for discovery (55€), 20% is better
        $promos = [
            $this->createAutoPromo('percentage', 20, 55),   // 11€ off on discovery
            $this->createAutoPromo('fixed_amount', 10, 55), // 10€ off on discovery
        ];

        $best = $this->selectBestPromo($promos, 55);
        $this->assertEquals('percentage', $best['discount_type'], "Should select by actual euro discount, not percentage value");

        // But for regular (45€), 20% = 9€, so 10€ fixed is better
        $promos = [
            $this->createAutoPromo('percentage', 20, 45),   // 9€ off on regular
            $this->createAutoPromo('fixed_amount', 10, 45), // 10€ off on regular
        ];

        $best = $this->selectBestPromo($promos, 45);
        $this->assertEquals('fixed_amount', $best['discount_type'], "Should select by actual euro discount for regular price");
    }

    // =========================================================================
    // HELPER METHODS - Simulent la logique métier sans DB
    // =========================================================================

    private function calculateDiscount(array $promo, float $originalPrice): array
    {
        $discountAmount = 0.0;

        switch ($promo['discount_type']) {
            case 'percentage':
                $discountAmount = $originalPrice * ((float)$promo['discount_value'] / 100);
                break;
            case 'fixed_amount':
                $discountAmount = (float)$promo['discount_value'];
                break;
            case 'free_session':
                $discountAmount = $originalPrice;
                break;
        }

        // Prix minimum 0€
        $finalPrice = max(0, $originalPrice - $discountAmount);
        $discountAmount = $originalPrice - $finalPrice;

        return [
            'original_price' => round($originalPrice, 2),
            'discount_amount' => round($discountAmount, 2),
            'final_price' => round($finalPrice, 2)
        ];
    }

    private function validatePromo(
        array $promo,
        string $durationType,
        ?string $userId = null,
        ?string $clientType = null
    ): array {
        return $this->validatePromoWithUsage($promo, $durationType, $userId, $clientType, 0, 0);
    }

    private function validatePromoWithUsage(
        array $promo,
        string $durationType,
        ?string $userId = null,
        ?string $clientType = null,
        int $totalUsage = 0,
        int $userUsage = 0
    ): array {
        // Check active
        if (!$promo['is_active']) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est plus actif'];
        }

        // Check dates
        $now = new \DateTime();
        if (!empty($promo['valid_from'])) {
            $validFrom = new \DateTime($promo['valid_from']);
            if ($now < $validFrom) {
                return ['valid' => false, 'error' => 'Ce code promo n\'est pas encore valide'];
            }
        }
        if (!empty($promo['valid_until'])) {
            $validUntil = new \DateTime($promo['valid_until']);
            if ($now > $validUntil) {
                return ['valid' => false, 'error' => 'Ce code promo a expiré'];
            }
        }

        // Check session type
        if ($durationType === 'discovery' && !$promo['applies_to_discovery']) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est pas valide pour les séances découverte'];
        }
        if ($durationType === 'regular' && !$promo['applies_to_regular']) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est pas valide pour les séances classiques'];
        }

        // Check client type
        if (!empty($promo['target_client_type']) && $clientType !== null) {
            if ($promo['target_client_type'] !== $clientType) {
                $label = $promo['target_client_type'] === 'personal' ? 'particuliers' : 'associations';
                return ['valid' => false, 'error' => "Ce code promo est réservé aux {$label}"];
            }
        }

        // Check target user
        if (!empty($promo['target_user_id'])) {
            if ($userId === null || $promo['target_user_id'] !== $userId) {
                return ['valid' => false, 'error' => 'Ce code promo n\'est pas valide pour votre compte'];
            }
        }

        // Check total usage
        if ($promo['max_uses_total'] !== null && $totalUsage >= $promo['max_uses_total']) {
            return ['valid' => false, 'error' => 'Ce code promo a atteint son nombre maximum d\'utilisations'];
        }

        // Check per-user usage
        if ($promo['max_uses_per_user'] !== null && $userId !== null && $userUsage >= $promo['max_uses_per_user']) {
            return ['valid' => false, 'error' => 'Vous avez déjà utilisé ce code promo le nombre de fois autorisé'];
        }

        return ['valid' => true, 'promo' => $promo];
    }

    private function getDiscountLabel(array $promo): string
    {
        $value = (float)$promo['discount_value'];

        switch ($promo['discount_type']) {
            case 'percentage':
                return '-' . number_format($value, 0) . '%';
            case 'fixed_amount':
                return '-' . number_format($value, 2, ',', ' ') . ' €';
            case 'free_session':
                return 'Gratuit';
            default:
                return '';
        }
    }

    private function generateLoyaltyCode(): string
    {
        return 'FIDEL-' . $this->generateRandomCode(6);
    }

    private function generateRandomCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Sans I, O, 0, 1
        $code = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }

        return $code;
    }

    private function createPromo(array $overrides = []): array
    {
        return array_merge([
            'id' => 'promo-' . uniqid(),
            'code' => 'TEST' . strtoupper(uniqid()),
            'name' => 'Test Promo',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'valid_from' => null,
            'valid_until' => null,
            'applies_to_discovery' => true,
            'applies_to_regular' => true,
            'target_client_type' => null,
            'target_user_id' => null,
            'max_uses_total' => null,
            'max_uses_per_user' => null,
            'application_mode' => 'manual'
        ], $overrides);
    }

    private function createAutoPromo(string $discountType, float $discountValue, float $originalPrice): array
    {
        $promo = $this->createPromo([
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'application_mode' => 'automatic'
        ]);

        // Pre-calculate discount for sorting
        $discount = $this->calculateDiscount($promo, $originalPrice);
        $promo['_calculated_discount'] = $discount['discount_amount'];

        return $promo;
    }

    private function selectBestPromo(array $promos, float $originalPrice): ?array
    {
        if (empty($promos)) {
            return null;
        }

        // Sort by calculated discount descending
        usort($promos, function ($a, $b) {
            return $b['_calculated_discount'] <=> $a['_calculated_discount'];
        });

        $best = $promos[0];
        unset($best['_calculated_discount']);

        return $best;
    }
}
