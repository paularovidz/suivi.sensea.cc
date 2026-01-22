<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique de pricing des sessions.
 */
class SessionPricingTest extends TestCase
{
    private const TYPE_DISCOVERY = 'discovery';
    private const TYPE_REGULAR = 'regular';

    private const DEFAULT_DISCOVERY_PRICE = 55;
    private const DEFAULT_REGULAR_PRICE = 45;

    // =========================================================================
    // TESTS PRICING DE BASE
    // =========================================================================

    public function testGetPriceForDiscoverySession(): void
    {
        $price = $this->getPriceForType(self::TYPE_DISCOVERY);
        $this->assertEquals(self::DEFAULT_DISCOVERY_PRICE, $price);
    }

    public function testGetPriceForRegularSession(): void
    {
        $price = $this->getPriceForType(self::TYPE_REGULAR);
        $this->assertEquals(self::DEFAULT_REGULAR_PRICE, $price);
    }

    public function testCustomPriceOverridesDefault(): void
    {
        $customPrice = 60;
        $price = $this->getPriceForType(self::TYPE_DISCOVERY, [
            'session_discovery_price' => $customPrice
        ]);
        $this->assertEquals($customPrice, $price);
    }

    // =========================================================================
    // TESTS APPLICATION CODE PROMO AU PRIX
    // =========================================================================

    public function testPriceWithPercentagePromo(): void
    {
        $originalPrice = self::DEFAULT_REGULAR_PRICE;
        $promo = ['discount_type' => 'percentage', 'discount_value' => 20];

        $result = $this->applyPromoToPrice($originalPrice, $promo);

        $this->assertEquals(45, $result['original_price']);
        $this->assertEquals(9, $result['discount_amount']); // 20% of 45
        $this->assertEquals(36, $result['final_price']);
    }

    public function testPriceWithFixedAmountPromo(): void
    {
        $originalPrice = self::DEFAULT_DISCOVERY_PRICE;
        $promo = ['discount_type' => 'fixed_amount', 'discount_value' => 15];

        $result = $this->applyPromoToPrice($originalPrice, $promo);

        $this->assertEquals(55, $result['original_price']);
        $this->assertEquals(15, $result['discount_amount']);
        $this->assertEquals(40, $result['final_price']);
    }

    public function testPriceWithFreeSessionPromo(): void
    {
        $originalPrice = self::DEFAULT_REGULAR_PRICE;
        $promo = ['discount_type' => 'free_session', 'discount_value' => 100];

        $result = $this->applyPromoToPrice($originalPrice, $promo);

        $this->assertEquals(45, $result['original_price']);
        $this->assertEquals(45, $result['discount_amount']);
        $this->assertEquals(0, $result['final_price']);
    }

    public function testPriceNeverGoesNegative(): void
    {
        $originalPrice = self::DEFAULT_REGULAR_PRICE;
        $promo = ['discount_type' => 'fixed_amount', 'discount_value' => 100]; // More than price

        $result = $this->applyPromoToPrice($originalPrice, $promo);

        $this->assertEquals(45, $result['original_price']);
        $this->assertEquals(45, $result['discount_amount']); // Capped at original
        $this->assertEquals(0, $result['final_price']); // Never negative
    }

    // =========================================================================
    // TESTS DURÉES DE SESSION
    // =========================================================================

    /**
     * @dataProvider sessionDurationProvider
     */
    public function testSessionDurations(
        string $type,
        int $expectedDisplay,
        int $expectedBlocked
    ): void {
        $durations = $this->getDurations($type);

        $this->assertEquals($expectedDisplay, $durations['display'], "Display duration mismatch for {$type}");
        $this->assertEquals($expectedBlocked, $durations['blocked'], "Blocked duration mismatch for {$type}");
    }

    public static function sessionDurationProvider(): array
    {
        return [
            'discovery' => [self::TYPE_DISCOVERY, 75, 90],  // 1h15 + 15min pause = 1h30
            'regular' => [self::TYPE_REGULAR, 45, 65],      // 45min + 20min pause = 1h05
        ];
    }

    public function testPauseCalculation(): void
    {
        $discoveryDurations = $this->getDurations(self::TYPE_DISCOVERY);
        $discoveryPause = $discoveryDurations['blocked'] - $discoveryDurations['display'];
        $this->assertEquals(15, $discoveryPause, "Discovery pause should be 15 minutes");

        $regularDurations = $this->getDurations(self::TYPE_REGULAR);
        $regularPause = $regularDurations['blocked'] - $regularDurations['display'];
        $this->assertEquals(20, $regularPause, "Regular pause should be 20 minutes");
    }

    // =========================================================================
    // TESTS BOOKING DATA COMPOSITION
    // =========================================================================

    public function testBookingDataWithoutPromo(): void
    {
        $bookingData = $this->composeBookingData([
            'session_date' => '2024-02-15 10:00:00',
            'duration_type' => self::TYPE_REGULAR,
            'user_id' => 'user-123',
            'person_id' => 'person-456',
            'gdpr_consent' => true,
        ], null);

        $this->assertEquals(self::DEFAULT_REGULAR_PRICE, $bookingData['price']);
        $this->assertNull($bookingData['promo_code_id']);
        $this->assertNull($bookingData['original_price']);
        $this->assertNull($bookingData['discount_amount']);
    }

    public function testBookingDataWithPromo(): void
    {
        $promo = [
            'id' => 'promo-123',
            'discount_type' => 'percentage',
            'discount_value' => 20
        ];

        $bookingData = $this->composeBookingData([
            'session_date' => '2024-02-15 10:00:00',
            'duration_type' => self::TYPE_REGULAR,
            'user_id' => 'user-123',
            'person_id' => 'person-456',
            'gdpr_consent' => true,
        ], $promo);

        $this->assertEquals(36, $bookingData['price']); // 45 - 20%
        $this->assertEquals('promo-123', $bookingData['promo_code_id']);
        $this->assertEquals(45, $bookingData['original_price']);
        $this->assertEquals(9, $bookingData['discount_amount']);
    }

    public function testBookingDataPreservesAllFields(): void
    {
        $bookingData = $this->composeBookingData([
            'session_date' => '2024-02-15 14:30:00',
            'duration_type' => self::TYPE_DISCOVERY,
            'user_id' => 'user-abc',
            'person_id' => 'person-xyz',
            'gdpr_consent' => true,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0'
        ], null);

        $this->assertEquals('2024-02-15 14:30:00', $bookingData['session_date']);
        $this->assertEquals(self::TYPE_DISCOVERY, $bookingData['duration_type']);
        $this->assertEquals('user-abc', $bookingData['user_id']);
        $this->assertEquals('person-xyz', $bookingData['person_id']);
        $this->assertTrue($bookingData['gdpr_consent']);
        $this->assertEquals('192.168.1.1', $bookingData['ip_address']);
        $this->assertEquals('Mozilla/5.0', $bookingData['user_agent']);
    }

    // =========================================================================
    // TESTS STATUTS DE SESSION
    // =========================================================================

    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', 'pending');
        $this->assertEquals('confirmed', 'confirmed');
        $this->assertEquals('completed', 'completed');
        $this->assertEquals('cancelled', 'cancelled');
        $this->assertEquals('no_show', 'no_show');
    }

    /**
     * @dataProvider statusTransitionProvider
     */
    public function testStatusTransitionValid(string $from, string $to, bool $valid): void
    {
        $result = $this->isValidStatusTransition($from, $to);
        $this->assertEquals($valid, $result, "Transition from {$from} to {$to} should be " . ($valid ? 'valid' : 'invalid'));
    }

    public static function statusTransitionProvider(): array
    {
        return [
            // From pending
            ['pending', 'confirmed', true],
            ['pending', 'cancelled', true],
            ['pending', 'completed', false],
            ['pending', 'no_show', false],

            // From confirmed
            ['confirmed', 'completed', true],
            ['confirmed', 'cancelled', true],
            ['confirmed', 'no_show', true],
            ['confirmed', 'pending', false],

            // From completed - no transitions allowed
            ['completed', 'pending', false],
            ['completed', 'confirmed', false],
            ['completed', 'cancelled', false],

            // From cancelled - no transitions allowed
            ['cancelled', 'pending', false],
            ['cancelled', 'confirmed', false],
            ['cancelled', 'completed', false],
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getPriceForType(string $durationType, array $settings = []): int
    {
        $key = $durationType === self::TYPE_DISCOVERY
            ? 'session_discovery_price'
            : 'session_regular_price';

        if (isset($settings[$key])) {
            return (int)$settings[$key];
        }

        return $durationType === self::TYPE_DISCOVERY
            ? self::DEFAULT_DISCOVERY_PRICE
            : self::DEFAULT_REGULAR_PRICE;
    }

    private function applyPromoToPrice(float $originalPrice, array $promo): array
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

        $finalPrice = max(0, $originalPrice - $discountAmount);
        $discountAmount = $originalPrice - $finalPrice;

        return [
            'original_price' => round($originalPrice, 2),
            'discount_amount' => round($discountAmount, 2),
            'final_price' => round($finalPrice, 2)
        ];
    }

    private function getDurations(string $type): array
    {
        if ($type === self::TYPE_DISCOVERY) {
            return ['display' => 75, 'blocked' => 90];
        }
        return ['display' => 45, 'blocked' => 65];
    }

    private function composeBookingData(array $data, ?array $promo): array
    {
        $durationType = $data['duration_type'];
        $originalPrice = $this->getPriceForType($durationType);

        $bookingData = [
            'session_date' => $data['session_date'],
            'duration_type' => $durationType,
            'user_id' => $data['user_id'],
            'person_id' => $data['person_id'],
            'gdpr_consent' => $data['gdpr_consent'],
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'promo_code_id' => null,
            'original_price' => null,
            'discount_amount' => null,
            'price' => $originalPrice
        ];

        if ($promo) {
            $discount = $this->applyPromoToPrice($originalPrice, $promo);
            $bookingData['promo_code_id'] = $promo['id'];
            $bookingData['original_price'] = (int)$discount['original_price'];
            $bookingData['discount_amount'] = (int)$discount['discount_amount'];
            $bookingData['price'] = (int)$discount['final_price'];
        }

        return $bookingData;
    }

    private function isValidStatusTransition(string $from, string $to): bool
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled', 'no_show'],
            'completed' => [],
            'cancelled' => [],
            'no_show' => []
        ];

        return in_array($to, $validTransitions[$from] ?? []);
    }
}
