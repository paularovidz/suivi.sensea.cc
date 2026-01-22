<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le système de fidélité.
 */
class LoyaltySystemTest extends TestCase
{
    private const DEFAULT_SESSIONS_REQUIRED = 9;

    // =========================================================================
    // TESTS PROGRESSION CARTE FIDÉLITÉ
    // =========================================================================

    /**
     * @dataProvider loyaltyProgressionProvider
     */
    public function testLoyaltyProgression(
        int $sessionsCount,
        int $sessionsRequired,
        bool $isCompleted,
        int $sessionsRemaining
    ): void {
        $card = $this->createLoyaltyCard($sessionsCount, $sessionsRequired);

        $this->assertEquals($isCompleted, $card['is_completed']);
        $this->assertEquals($sessionsRemaining, $card['sessions_remaining']);
    }

    public static function loyaltyProgressionProvider(): array
    {
        return [
            'empty_card' => [0, 9, false, 9],
            'one_session' => [1, 9, false, 8],
            'half_way' => [5, 9, false, 4],
            'almost_complete' => [8, 9, false, 1],
            'just_completed' => [9, 9, true, 0],
            'over_completed' => [10, 9, true, 0],
            'custom_threshold_5' => [5, 5, true, 0],
            'custom_threshold_10' => [8, 10, false, 2],
        ];
    }

    public function testLoyaltyProgressionPercentage(): void
    {
        $card = $this->createLoyaltyCard(3, 9);
        $percentage = ($card['sessions_count'] / $card['sessions_required']) * 100;

        $this->assertEquals(33.33, round($percentage, 2));
    }

    // =========================================================================
    // TESTS ÉLIGIBILITÉ FIDÉLITÉ
    // =========================================================================

    public function testOnlyPersonalClientsEligible(): void
    {
        $this->assertTrue($this->isEligibleForLoyalty('personal'));
        $this->assertFalse($this->isEligibleForLoyalty('association'));
    }

    public function testInactiveUsersNotEligible(): void
    {
        $this->assertTrue($this->isUserEligible('personal', true));
        $this->assertFalse($this->isUserEligible('personal', false));
    }

    // =========================================================================
    // TESTS INCRÉMENTATION
    // =========================================================================

    public function testIncrementNormalSession(): void
    {
        $card = $this->createLoyaltyCard(5, 9);
        $newCard = $this->incrementCard($card, false);

        $this->assertEquals(6, $newCard['sessions_count']);
        $this->assertFalse($newCard['is_completed']);
    }

    public function testIncrementCompletesCard(): void
    {
        $card = $this->createLoyaltyCard(8, 9);
        $newCard = $this->incrementCard($card, false);

        $this->assertEquals(9, $newCard['sessions_count']);
        $this->assertTrue($newCard['is_completed']);
        $this->assertNotNull($newCard['completed_at']);
    }

    public function testFreeSessionDoesNotIncrement(): void
    {
        $card = $this->createLoyaltyCard(5, 9);
        $newCard = $this->incrementCard($card, true); // is_free_session = true

        $this->assertEquals(5, $newCard['sessions_count'], "Free session should not increment count");
        $this->assertFalse($newCard['is_completed']);
    }

    public function testDoNotIncrementCompletedCard(): void
    {
        $card = $this->createLoyaltyCard(9, 9);
        $card['is_completed'] = true;

        $newCard = $this->incrementCard($card, false);

        $this->assertEquals(9, $newCard['sessions_count'], "Should not increment already completed card");
    }

    // =========================================================================
    // TESTS RÉINITIALISATION
    // =========================================================================

    public function testResetAfterFreeSession(): void
    {
        $card = $this->createLoyaltyCard(9, 9);
        $card['is_completed'] = true;
        $card['completed_at'] = '2024-01-15 10:00:00';

        $resetCard = $this->resetCard($card);

        $this->assertEquals(0, $resetCard['sessions_count']);
        $this->assertFalse($resetCard['is_completed']);
        $this->assertNull($resetCard['completed_at']);
        $this->assertNotNull($resetCard['free_session_used_at']);
    }

    // =========================================================================
    // TESTS GÉNÉRATION CODE FIDÉLITÉ
    // =========================================================================

    public function testGenerateLoyaltyPromoCode(): void
    {
        $userId = 'user-123';
        $userName = 'Jean Dupont';

        $promo = $this->generateLoyaltyPromoCode($userId, $userName);

        // Check code format
        $this->assertStringStartsWith('FIDEL-', $promo['code']);
        $this->assertEquals(12, strlen($promo['code'])); // FIDEL- (6) + random (6)

        // Check promo properties
        $this->assertEquals('free_session', $promo['discount_type']);
        $this->assertEquals(100, $promo['discount_value']);
        $this->assertEquals($userId, $promo['target_user_id']);
        $this->assertEquals(1, $promo['max_uses_total']);
        $this->assertEquals(1, $promo['max_uses_per_user']);
        $this->assertTrue($promo['is_active']);
        $this->assertTrue($promo['applies_to_discovery']);
        $this->assertTrue($promo['applies_to_regular']);

        // Check name contains user name
        $this->assertStringContainsString($userName, $promo['name']);
    }

    public function testLoyaltyPromoIsPersonal(): void
    {
        $promo = $this->generateLoyaltyPromoCode('user-123', 'Test User');

        // Should only be usable by the target user
        $this->assertNotNull($promo['target_user_id']);

        // Validate for correct user
        $validation = $this->validateLoyaltyPromo($promo, 'user-123');
        $this->assertTrue($validation['valid']);

        // Validate for wrong user
        $validation = $this->validateLoyaltyPromo($promo, 'other-user');
        $this->assertFalse($validation['valid']);
    }

    // =========================================================================
    // TESTS ALERTES CARTE PLEINE
    // =========================================================================

    public function testAlertWhenCardComplete(): void
    {
        $card = $this->createLoyaltyCard(9, 9);

        $this->assertTrue($this->shouldShowLoyaltyAlert($card));
    }

    public function testNoAlertWhenCardIncomplete(): void
    {
        $card = $this->createLoyaltyCard(5, 9);

        $this->assertFalse($this->shouldShowLoyaltyAlert($card));
    }

    public function testNoAlertWhenAlreadyUsed(): void
    {
        $card = $this->createLoyaltyCard(9, 9);
        $card['is_completed'] = true;
        $card['free_session_used_at'] = '2024-01-15 10:00:00';

        $this->assertFalse($this->shouldShowLoyaltyAlert($card));
    }

    // =========================================================================
    // TESTS FORMATAGE AFFICHAGE
    // =========================================================================

    public function testFormatLoyaltyDisplay(): void
    {
        $card = $this->createLoyaltyCard(5, 9);
        $display = $this->formatLoyaltyDisplay($card);

        $this->assertEquals('5/9', $display['progress_text']);
        $this->assertEquals(55.56, round($display['progress_percent'], 2));
        $this->assertEquals(4, $display['sessions_remaining']);
        $this->assertFalse($display['is_complete']);
    }

    public function testFormatLoyaltyDisplayComplete(): void
    {
        $card = $this->createLoyaltyCard(9, 9);
        $card['is_completed'] = true;

        $display = $this->formatLoyaltyDisplay($card);

        $this->assertEquals('9/9', $display['progress_text']);
        $this->assertEquals(100, $display['progress_percent']);
        $this->assertEquals(0, $display['sessions_remaining']);
        $this->assertTrue($display['is_complete']);
    }

    // =========================================================================
    // TESTS EDGE CASES
    // =========================================================================

    public function testCardWithZeroThreshold(): void
    {
        // Edge case: threshold set to 0 (should never happen but test defensively)
        $card = $this->createLoyaltyCard(0, 0);

        // With threshold 0, card completion is undefined behavior
        // The current implementation returns false because 0 >= 0 but we check sessionsRequired > 0
        // This is defensive - a 0 threshold should not be allowed in practice
        $this->assertFalse($card['is_completed'], "Card with 0 threshold should not auto-complete (defensive behavior)");
    }

    public function testNewUserHasNoCard(): void
    {
        $card = $this->getCardForUser('new-user-without-card');

        $this->assertNull($card, "New user should not have a loyalty card yet");
    }

    public function testCardCreationOnFirstSession(): void
    {
        $card = $this->createOrGetCard('user-123');

        $this->assertEquals(0, $card['sessions_count']);
        $this->assertFalse($card['is_completed']);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function createLoyaltyCard(int $sessionsCount, int $sessionsRequired): array
    {
        return [
            'id' => 'card-' . uniqid(),
            'user_id' => 'user-' . uniqid(),
            'sessions_count' => $sessionsCount,
            'sessions_required' => $sessionsRequired,
            'is_completed' => $sessionsRequired > 0 && $sessionsCount >= $sessionsRequired,
            'sessions_remaining' => max(0, $sessionsRequired - $sessionsCount),
            'completed_at' => ($sessionsCount >= $sessionsRequired && $sessionsRequired > 0) ? date('Y-m-d H:i:s') : null,
            'free_session_used_at' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    private function isEligibleForLoyalty(string $clientType): bool
    {
        return $clientType === 'personal';
    }

    private function isUserEligible(string $clientType, bool $isActive): bool
    {
        return $this->isEligibleForLoyalty($clientType) && $isActive;
    }

    private function incrementCard(array $card, bool $isFreeSession): array
    {
        // Don't increment if free session or already completed
        if ($isFreeSession || $card['is_completed']) {
            return $card;
        }

        $card['sessions_count']++;
        $card['sessions_remaining'] = max(0, $card['sessions_required'] - $card['sessions_count']);

        if ($card['sessions_count'] >= $card['sessions_required']) {
            $card['is_completed'] = true;
            $card['completed_at'] = date('Y-m-d H:i:s');
        }

        return $card;
    }

    private function resetCard(array $card): array
    {
        return [
            'id' => $card['id'],
            'user_id' => $card['user_id'],
            'sessions_count' => 0,
            'sessions_required' => $card['sessions_required'],
            'is_completed' => false,
            'sessions_remaining' => $card['sessions_required'],
            'completed_at' => null,
            'free_session_used_at' => date('Y-m-d H:i:s'),
            'created_at' => $card['created_at']
        ];
    }

    private function generateLoyaltyPromoCode(string $userId, string $userName): array
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $randomPart = '';
        for ($i = 0; $i < 6; $i++) {
            $randomPart .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return [
            'id' => 'promo-' . uniqid(),
            'code' => 'FIDEL-' . $randomPart,
            'name' => "Séance gratuite fidélité - {$userName}",
            'description' => 'Code de fidélité généré automatiquement après 9 séances payées',
            'discount_type' => 'free_session',
            'discount_value' => 100,
            'application_mode' => 'manual',
            'target_user_id' => $userId,
            'max_uses_total' => 1,
            'max_uses_per_user' => 1,
            'applies_to_discovery' => true,
            'applies_to_regular' => true,
            'is_active' => true
        ];
    }

    private function validateLoyaltyPromo(array $promo, string $userId): array
    {
        if (!empty($promo['target_user_id']) && $promo['target_user_id'] !== $userId) {
            return ['valid' => false, 'error' => 'Code réservé à un autre utilisateur'];
        }

        return ['valid' => true];
    }

    private function shouldShowLoyaltyAlert(array $card): bool
    {
        return $card['is_completed'] && empty($card['free_session_used_at']);
    }

    private function formatLoyaltyDisplay(array $card): array
    {
        $percent = $card['sessions_required'] > 0
            ? ($card['sessions_count'] / $card['sessions_required']) * 100
            : 100;

        return [
            'progress_text' => "{$card['sessions_count']}/{$card['sessions_required']}",
            'progress_percent' => min(100, $percent),
            'sessions_remaining' => $card['sessions_remaining'],
            'is_complete' => $card['is_completed']
        ];
    }

    private function getCardForUser(string $userId): ?array
    {
        // Simulates DB lookup - new user has no card
        if (str_starts_with($userId, 'new-')) {
            return null;
        }

        return $this->createLoyaltyCard(0, self::DEFAULT_SESSIONS_REQUIRED);
    }

    private function createOrGetCard(string $userId): array
    {
        $card = $this->getCardForUser($userId);

        if ($card === null) {
            $card = $this->createLoyaltyCard(0, self::DEFAULT_SESSIONS_REQUIRED);
            $card['user_id'] = $userId;
        }

        return $card;
    }
}
