<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests des permissions pour le système de documents
 * Vérifie que les membres peuvent uploader sur leur propre compte
 * et supprimer uniquement les documents qu'ils ont uploadés
 */
class DocumentPermissionTest extends TestCase
{
    /**
     * Simule la logique de permission d'upload
     */
    private function canUpload(bool $isAdmin, string $type, string $entityId, string $currentUserId): bool
    {
        if ($isAdmin) {
            return true;
        }
        // Utilisateur peut uniquement uploader sur son propre compte user
        return $type === 'user' && $entityId === $currentUserId;
    }

    /**
     * Simule la logique de permission de suppression
     */
    private function canDelete(bool $isAdmin, string $documentUploadedBy, string $currentUserId): bool
    {
        if ($isAdmin) {
            return true;
        }
        // Utilisateur peut uniquement supprimer les documents qu'il a uploadés
        return $documentUploadedBy === $currentUserId;
    }

    // ==========================================
    // TESTS UPLOAD - ADMIN
    // ==========================================

    public function testAdminCanUploadToOwnAccount(): void
    {
        $this->assertTrue($this->canUpload(true, 'user', 'admin-id', 'admin-id'));
    }

    public function testAdminCanUploadToOtherUserAccount(): void
    {
        $this->assertTrue($this->canUpload(true, 'user', 'other-user-id', 'admin-id'));
    }

    public function testAdminCanUploadToPerson(): void
    {
        $this->assertTrue($this->canUpload(true, 'person', 'person-id', 'admin-id'));
    }

    public function testAdminCanUploadToSession(): void
    {
        $this->assertTrue($this->canUpload(true, 'session', 'session-id', 'admin-id'));
    }

    // ==========================================
    // TESTS UPLOAD - MEMBRE
    // ==========================================

    public function testMemberCanUploadToOwnAccount(): void
    {
        $userId = 'member-id';
        $this->assertTrue($this->canUpload(false, 'user', $userId, $userId));
    }

    public function testMemberCannotUploadToOtherUserAccount(): void
    {
        $this->assertFalse($this->canUpload(false, 'user', 'other-user-id', 'member-id'));
    }

    public function testMemberCannotUploadToPerson(): void
    {
        $this->assertFalse($this->canUpload(false, 'person', 'person-id', 'member-id'));
    }

    public function testMemberCannotUploadToSession(): void
    {
        $this->assertFalse($this->canUpload(false, 'session', 'session-id', 'member-id'));
    }

    // ==========================================
    // TESTS DELETE - ADMIN
    // ==========================================

    public function testAdminCanDeleteOwnUpload(): void
    {
        $this->assertTrue($this->canDelete(true, 'admin-id', 'admin-id'));
    }

    public function testAdminCanDeleteOtherUserUpload(): void
    {
        $this->assertTrue($this->canDelete(true, 'other-user-id', 'admin-id'));
    }

    // ==========================================
    // TESTS DELETE - MEMBRE
    // ==========================================

    public function testMemberCanDeleteOwnUpload(): void
    {
        $memberId = 'member-id';
        $this->assertTrue($this->canDelete(false, $memberId, $memberId));
    }

    public function testMemberCannotDeleteOtherUserUpload(): void
    {
        $this->assertFalse($this->canDelete(false, 'other-user-id', 'member-id'));
    }

    public function testMemberCannotDeleteAdminUpload(): void
    {
        $this->assertFalse($this->canDelete(false, 'admin-id', 'member-id'));
    }

    // ==========================================
    // TESTS EDGE CASES
    // ==========================================

    public function testUploadWithEmptyUserId(): void
    {
        $this->assertFalse($this->canUpload(false, 'user', 'some-id', ''));
    }

    public function testDeleteWithEmptyUserId(): void
    {
        $this->assertFalse($this->canDelete(false, 'uploader-id', ''));
    }

    public function testUploadToUnknownType(): void
    {
        // Un type inconnu ne devrait pas être autorisé pour un membre
        $this->assertFalse($this->canUpload(false, 'unknown', 'entity-id', 'member-id'));
    }

    /**
     * @dataProvider uploadScenariosProvider
     */
    public function testUploadScenarios(bool $isAdmin, string $type, string $entityId, string $userId, bool $expected): void
    {
        $this->assertEquals($expected, $this->canUpload($isAdmin, $type, $entityId, $userId));
    }

    public static function uploadScenariosProvider(): array
    {
        $memberId = 'member-123';
        $adminId = 'admin-456';
        $otherUserId = 'other-789';
        $personId = 'person-abc';
        $sessionId = 'session-def';

        return [
            // Admin scenarios
            'admin_own_user' => [true, 'user', $adminId, $adminId, true],
            'admin_other_user' => [true, 'user', $otherUserId, $adminId, true],
            'admin_person' => [true, 'person', $personId, $adminId, true],
            'admin_session' => [true, 'session', $sessionId, $adminId, true],

            // Member scenarios - allowed
            'member_own_user' => [false, 'user', $memberId, $memberId, true],

            // Member scenarios - denied
            'member_other_user' => [false, 'user', $otherUserId, $memberId, false],
            'member_person' => [false, 'person', $personId, $memberId, false],
            'member_session' => [false, 'session', $sessionId, $memberId, false],
        ];
    }

    /**
     * @dataProvider deleteScenariosProvider
     */
    public function testDeleteScenarios(bool $isAdmin, string $uploadedBy, string $currentUserId, bool $expected): void
    {
        $this->assertEquals($expected, $this->canDelete($isAdmin, $uploadedBy, $currentUserId));
    }

    public static function deleteScenariosProvider(): array
    {
        $memberId = 'member-123';
        $adminId = 'admin-456';
        $otherUserId = 'other-789';

        return [
            // Admin can delete anything
            'admin_delete_own' => [true, $adminId, $adminId, true],
            'admin_delete_member' => [true, $memberId, $adminId, true],
            'admin_delete_other' => [true, $otherUserId, $adminId, true],

            // Member can only delete own uploads
            'member_delete_own' => [false, $memberId, $memberId, true],
            'member_delete_admin' => [false, $adminId, $memberId, false],
            'member_delete_other' => [false, $otherUserId, $memberId, false],
        ];
    }
}
