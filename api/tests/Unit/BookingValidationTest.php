<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la validation des réservations.
 */
class BookingValidationTest extends TestCase
{
    // =========================================================================
    // TESTS RATE LIMITING
    // =========================================================================

    /**
     * @dataProvider rateLimitProvider
     */
    public function testRateLimitingByClientType(
        string $clientType,
        int $currentBookings,
        int $maxAllowed,
        bool $shouldAllow
    ): void {
        $isAllowed = $this->checkRateLimit($currentBookings, $maxAllowed);
        $this->assertEquals($shouldAllow, $isAllowed);
    }

    public static function rateLimitProvider(): array
    {
        return [
            // Personal client limits (default 4)
            'personal_0_of_4' => ['personal', 0, 4, true],
            'personal_3_of_4' => ['personal', 3, 4, true],
            'personal_4_of_4' => ['personal', 4, 4, false],
            'personal_5_of_4' => ['personal', 5, 4, false],

            // Association limits (default 20)
            'association_0_of_20' => ['association', 0, 20, true],
            'association_10_of_20' => ['association', 10, 20, true],
            'association_19_of_20' => ['association', 19, 20, true],
            'association_20_of_20' => ['association', 20, 20, false],
            'association_25_of_20' => ['association', 25, 20, false],
        ];
    }

    public function testGetRateLimitsForPersonalClient(): void
    {
        $limits = $this->getRateLimits('personal');

        $this->assertEquals(4, $limits['max_per_ip']);
        $this->assertEquals(4, $limits['max_per_email']);
    }

    public function testGetRateLimitsForAssociation(): void
    {
        $limits = $this->getRateLimits('association');

        $this->assertEquals(20, $limits['max_per_ip']);
        $this->assertEquals(20, $limits['max_per_email']);
    }

    public function testCustomRateLimitsFromSettings(): void
    {
        $settings = [
            'booking_max_per_ip' => 5,
            'booking_max_per_email' => 6,
            'booking_max_per_ip_association' => 30,
            'booking_max_per_email_association' => 25
        ];

        $limitsPersonal = $this->getRateLimits('personal', $settings);
        $this->assertEquals(5, $limitsPersonal['max_per_ip']);
        $this->assertEquals(6, $limitsPersonal['max_per_email']);

        $limitsAssoc = $this->getRateLimits('association', $settings);
        $this->assertEquals(30, $limitsAssoc['max_per_ip']);
        $this->assertEquals(25, $limitsAssoc['max_per_email']);
    }

    // =========================================================================
    // TESTS VALIDATION EMAIL
    // =========================================================================

    /**
     * @dataProvider emailValidationProvider
     */
    public function testEmailValidation(string $email, bool $valid): void
    {
        $result = $this->validateEmail($email);
        $this->assertEquals($valid, $result, "Email '{$email}' validation failed");
    }

    public static function emailValidationProvider(): array
    {
        return [
            'valid_email' => ['test@example.com', true],
            'valid_email_with_subdomain' => ['user@mail.example.org', true],
            'valid_email_with_plus' => ['user+tag@example.com', true],
            'valid_email_with_numbers' => ['user123@example.com', true],
            'invalid_no_at' => ['testexample.com', false],
            'invalid_no_domain' => ['test@', false],
            'invalid_no_local' => ['@example.com', false],
            'invalid_spaces' => ['test @example.com', false],
            'invalid_double_at' => ['test@@example.com', false],
            'empty_email' => ['', false],
        ];
    }

    public function testEmailNormalization(): void
    {
        $email = '  Test@EXAMPLE.com  ';
        $normalized = $this->normalizeEmail($email);

        $this->assertEquals('test@example.com', $normalized, "Email should be trimmed and lowercased");
    }

    // =========================================================================
    // TESTS VALIDATION TÉLÉPHONE
    // =========================================================================

    /**
     * @dataProvider phoneValidationProvider
     */
    public function testPhoneValidation(string $phone, bool $valid): void
    {
        $result = $this->validatePhone($phone);
        $this->assertEquals($valid, $result, "Phone '{$phone}' validation failed");
    }

    public static function phoneValidationProvider(): array
    {
        return [
            'valid_french_mobile_06' => ['0612345678', true],
            'valid_french_mobile_07' => ['0712345678', true],
            'valid_with_spaces' => ['06 12 34 56 78', true],
            'valid_with_dots' => ['06.12.34.56.78', true],
            'valid_with_dashes' => ['06-12-34-56-78', true],
            'valid_with_international' => ['+33612345678', true],
            'valid_with_intl_spaces' => ['+33 6 12 34 56 78', true],
            'valid_landline' => ['0123456789', true],
            'invalid_too_short' => ['061234567', false],
            'invalid_too_long' => ['061234567890', false],
            'invalid_letters' => ['06123abcde', false],
            'empty_phone' => ['', true], // Phone is optional
        ];
    }

    // =========================================================================
    // TESTS VALIDATION NOMS
    // =========================================================================

    /**
     * @dataProvider nameValidationProvider
     */
    public function testNameValidation(string $name, int $minLength, bool $valid): void
    {
        $result = $this->validateName($name, $minLength);
        $this->assertEquals($valid, $result, "Name '{$name}' with min {$minLength} validation failed");
    }

    public static function nameValidationProvider(): array
    {
        return [
            'valid_name' => ['Jean', 2, true],
            'valid_composed_name' => ['Jean-Pierre', 2, true],
            'valid_accented_name' => ['Hélène', 2, true],
            'valid_apostrophe' => ["O'Brien", 2, true],
            'valid_exactly_2_chars' => ['Li', 2, true],
            'invalid_1_char' => ['X', 2, false],
            'invalid_empty' => ['', 2, false],
            'invalid_only_spaces' => ['   ', 2, false],
        ];
    }

    public function testNameTrimming(): void
    {
        $name = '  Jean  ';
        $trimmed = trim($name);

        $this->assertEquals('Jean', $trimmed);
        $this->assertTrue($this->validateName($trimmed, 2));
    }

    // =========================================================================
    // TESTS VALIDATION GDPR
    // =========================================================================

    public function testGdprConsentRequired(): void
    {
        $this->assertFalse($this->validateGdprConsent(false), "GDPR consent is mandatory");
        $this->assertTrue($this->validateGdprConsent(true), "Valid GDPR consent should pass");
    }

    // =========================================================================
    // TESTS VALIDATION DATE/HEURE
    // =========================================================================

    /**
     * @dataProvider dateValidationProvider
     */
    public function testDateValidation(string $date, bool $valid): void
    {
        $result = $this->validateDateFormat($date);
        $this->assertEquals($valid, $result, "Date '{$date}' validation failed");
    }

    public static function dateValidationProvider(): array
    {
        return [
            'valid_date' => ['2024-02-15', true],
            'valid_date_with_time' => ['2024-02-15 10:00:00', true],
            'valid_date_iso' => ['2024-02-15T10:00:00', true],
            'invalid_french_format' => ['15/02/2024', false],
            'invalid_text' => ['next monday', false],
            'invalid_empty' => ['', false],
        ];
    }

    public function testDateInFuture(): void
    {
        $today = new \DateTime('today');
        $tomorrow = (clone $today)->modify('+1 day');
        $yesterday = (clone $today)->modify('-1 day');

        $this->assertTrue($this->isDateInFuture($tomorrow), "Tomorrow should be in future");
        $this->assertFalse($this->isDateInFuture($yesterday), "Yesterday should not be in future");
    }

    public function testDateWithinBookingWindow(): void
    {
        $today = new \DateTime('today');
        $minAdvanceHours = 24;
        $maxAdvanceDays = 60;

        // Too soon (less than 24h)
        $tooSoon = (clone $today)->modify('+12 hours');
        $this->assertFalse($this->isDateWithinBookingWindow($tooSoon, $minAdvanceHours, $maxAdvanceDays));

        // Valid (between 24h and 60 days)
        $valid = (clone $today)->modify('+2 days');
        $this->assertTrue($this->isDateWithinBookingWindow($valid, $minAdvanceHours, $maxAdvanceDays));

        // Too far (more than 60 days)
        $tooFar = (clone $today)->modify('+90 days');
        $this->assertFalse($this->isDateWithinBookingWindow($tooFar, $minAdvanceHours, $maxAdvanceDays));
    }

    // =========================================================================
    // TESTS VALIDATION TYPE CLIENT
    // =========================================================================

    /**
     * @dataProvider clientTypeValidationProvider
     */
    public function testClientTypeValidation(string $type, bool $valid): void
    {
        $result = $this->validateClientType($type);
        $this->assertEquals($valid, $result, "Client type '{$type}' validation failed");
    }

    public static function clientTypeValidationProvider(): array
    {
        return [
            'valid_personal' => ['personal', true],
            'valid_association' => ['association', true],
            'invalid_type' => ['business', false],
            'invalid_empty' => ['', false],
            'invalid_professional' => ['professional', false], // Not a valid type
        ];
    }

    // =========================================================================
    // TESTS VALIDATION SIRET
    // =========================================================================

    /**
     * @dataProvider siretValidationProvider
     */
    public function testSiretValidation(string $siret, bool $valid): void
    {
        $result = $this->validateSiret($siret);
        $this->assertEquals($valid, $result, "SIRET '{$siret}' validation failed");
    }

    public static function siretValidationProvider(): array
    {
        return [
            'valid_siret' => ['12345678901234', true],
            'valid_with_spaces' => ['123 456 789 01234', true],
            'invalid_too_short' => ['1234567890123', false],
            'invalid_too_long' => ['123456789012345', false],
            'invalid_letters' => ['1234567890123A', false],
            'empty_siret' => ['', true], // SIRET is optional
        ];
    }

    // =========================================================================
    // TESTS MASQUAGE DONNÉES (SÉCURITÉ)
    // =========================================================================

    /**
     * @dataProvider emailMaskingProvider
     */
    public function testEmailMasking(string $email, string $expected): void
    {
        $masked = $this->maskEmail($email);
        $this->assertEquals($expected, $masked);
    }

    public static function emailMaskingProvider(): array
    {
        return [
            'standard_email' => ['test@gmail.com', 'tes****@gmail.com'],
            'short_local' => ['ab@example.com', 'ab****@example.com'],
            'long_local' => ['verylongemail@example.com', 'ver**********@example.com'],
        ];
    }

    /**
     * @dataProvider phoneMaskingProvider
     */
    public function testPhoneMasking(string $phone, string $expected): void
    {
        $masked = $this->maskPhone($phone);
        $this->assertEquals($expected, $masked);
    }

    public static function phoneMaskingProvider(): array
    {
        return [
            'standard_phone' => ['0612345678', '78'],
            'phone_with_spaces' => ['06 12 34 56 78', '78'],
            'international' => ['+33612345678', '78'],
        ];
    }

    // =========================================================================
    // TESTS VALIDATION COMPLÈTE BOOKING DATA
    // =========================================================================

    public function testValidBookingData(): void
    {
        $data = [
            'session_date' => '2024-02-15 10:00:00',
            'duration_type' => 'regular',
            'client_email' => 'test@example.com',
            'client_first_name' => 'Jean',
            'client_last_name' => 'Dupont',
            'person_first_name' => 'Marie',
            'person_last_name' => 'Dupont',
            'gdpr_consent' => true
        ];

        $errors = $this->validateBookingData($data);
        $this->assertEmpty($errors, "Valid booking data should have no errors");
    }

    public function testInvalidBookingDataMissingFields(): void
    {
        $data = [
            'session_date' => '',
            'duration_type' => '',
            'client_email' => '',
            'client_first_name' => '',
            'client_last_name' => '',
            'person_first_name' => '',
            'person_last_name' => '',
            'gdpr_consent' => false
        ];

        $errors = $this->validateBookingData($data);

        $this->assertArrayHasKey('session_date', $errors);
        $this->assertArrayHasKey('duration_type', $errors);
        $this->assertArrayHasKey('client_email', $errors);
        $this->assertArrayHasKey('client_first_name', $errors);
        $this->assertArrayHasKey('client_last_name', $errors);
        $this->assertArrayHasKey('person_first_name', $errors);
        $this->assertArrayHasKey('person_last_name', $errors);
        $this->assertArrayHasKey('gdpr_consent', $errors);
    }

    public function testInvalidBookingDataShortNames(): void
    {
        $data = [
            'session_date' => '2024-02-15 10:00:00',
            'duration_type' => 'regular',
            'client_email' => 'test@example.com',
            'client_first_name' => 'J', // Too short
            'client_last_name' => 'D',  // Too short
            'person_first_name' => 'M', // Too short
            'person_last_name' => 'D',  // Too short
            'gdpr_consent' => true
        ];

        $errors = $this->validateBookingData($data);

        $this->assertArrayHasKey('client_first_name', $errors);
        $this->assertArrayHasKey('client_last_name', $errors);
        $this->assertArrayHasKey('person_first_name', $errors);
        $this->assertArrayHasKey('person_last_name', $errors);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function checkRateLimit(int $currentBookings, int $maxAllowed): bool
    {
        return $currentBookings < $maxAllowed;
    }

    private function getRateLimits(string $clientType, array $settings = []): array
    {
        if ($clientType === 'association') {
            return [
                'max_per_ip' => $settings['booking_max_per_ip_association'] ?? 20,
                'max_per_email' => $settings['booking_max_per_email_association'] ?? 20
            ];
        }

        return [
            'max_per_ip' => $settings['booking_max_per_ip'] ?? 4,
            'max_per_email' => $settings['booking_max_per_email'] ?? 4
        ];
    }

    private function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function validatePhone(string $phone): bool
    {
        if (empty($phone)) {
            return true; // Phone is optional
        }

        // Remove spaces, dots, dashes
        $cleaned = preg_replace('/[\s.\-]/', '', $phone);

        // Handle international format
        if (str_starts_with($cleaned, '+33')) {
            $cleaned = '0' . substr($cleaned, 3);
        }

        // French phone number: 10 digits starting with 0
        return preg_match('/^0[1-9][0-9]{8}$/', $cleaned) === 1;
    }

    private function validateName(string $name, int $minLength): bool
    {
        $trimmed = trim($name);
        return strlen($trimmed) >= $minLength;
    }

    private function validateGdprConsent(bool $consent): bool
    {
        return $consent === true;
    }

    private function validateDateFormat(string $date): bool
    {
        if (empty($date)) {
            return false;
        }

        // Try various formats
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return true;
            }
        }

        return false;
    }

    private function isDateInFuture(\DateTime $date): bool
    {
        $today = new \DateTime('today');
        return $date > $today;
    }

    private function isDateWithinBookingWindow(\DateTime $date, int $minAdvanceHours, int $maxAdvanceDays): bool
    {
        $now = new \DateTime();
        $minDate = (clone $now)->modify("+{$minAdvanceHours} hours");
        $maxDate = (clone $now)->modify("+{$maxAdvanceDays} days");

        return $date >= $minDate && $date <= $maxDate;
    }

    private function validateClientType(string $type): bool
    {
        return in_array($type, ['personal', 'association'], true);
    }

    private function validateSiret(string $siret): bool
    {
        if (empty($siret)) {
            return true; // SIRET is optional
        }

        // Remove spaces
        $cleaned = preg_replace('/\s/', '', $siret);

        // SIRET: exactly 14 digits
        return preg_match('/^\d{14}$/', $cleaned) === 1;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '****@****.***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        $visibleChars = min(3, strlen($local));
        $masked = substr($local, 0, $visibleChars) . str_repeat('*', max(4, strlen($local) - $visibleChars));

        return $masked . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) < 2) {
            return '**';
        }

        return substr($digits, -2);
    }

    private function validateBookingData(array $data): array
    {
        $errors = [];

        // Required fields
        if (empty($data['session_date']) || !$this->validateDateFormat($data['session_date'])) {
            $errors['session_date'] = 'Date de séance invalide';
        }

        if (empty($data['duration_type']) || !in_array($data['duration_type'], ['discovery', 'regular'])) {
            $errors['duration_type'] = 'Type de séance invalide';
        }

        if (empty($data['client_email']) || !$this->validateEmail($data['client_email'])) {
            $errors['client_email'] = 'Email invalide';
        }

        if (empty($data['client_first_name']) || !$this->validateName($data['client_first_name'], 2)) {
            $errors['client_first_name'] = 'Le prénom doit contenir au moins 2 caractères';
        }

        if (empty($data['client_last_name']) || !$this->validateName($data['client_last_name'], 2)) {
            $errors['client_last_name'] = 'Le nom doit contenir au moins 2 caractères';
        }

        if (empty($data['person_first_name']) || !$this->validateName($data['person_first_name'], 2)) {
            $errors['person_first_name'] = 'Le prénom du bénéficiaire doit contenir au moins 2 caractères';
        }

        if (empty($data['person_last_name']) || !$this->validateName($data['person_last_name'], 2)) {
            $errors['person_last_name'] = 'Le nom du bénéficiaire doit contenir au moins 2 caractères';
        }

        if (!$this->validateGdprConsent($data['gdpr_consent'] ?? false)) {
            $errors['gdpr_consent'] = 'Le consentement RGPD est obligatoire';
        }

        return $errors;
    }
}
