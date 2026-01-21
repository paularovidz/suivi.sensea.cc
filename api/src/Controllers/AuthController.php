<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\MagicLink;
use App\Models\RefreshToken;
use App\Services\JWTService;
use App\Services\MailService;
use App\Services\AuditService;
use App\Middleware\AuthMiddleware;
use App\Utils\Response;
use App\Utils\Validator;

class AuthController
{
    public function requestMagicLink(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator->required('email')->email('email');
        $validator->validate();

        $email = strtolower(trim($data['email']));

        // Rate limit: max 3 requests per hour per email
        $recentRequests = MagicLink::countRecentRequestsByEmail($email, 60);
        if ($recentRequests >= 3) {
            Response::error('Trop de demandes de connexion. Veuillez patienter avant de réessayer.', 429);
        }

        $user = User::findByEmail($email);

        // Always return success to prevent email enumeration
        if (!$user || !$user['is_active']) {
            // Log attempt for security monitoring
            AuditService::log(null, 'magic_link_request_unknown_email', 'user', null, null, ['email' => $email]);

            // Fake delay to prevent timing attacks
            usleep(random_int(100000, 300000));

            Response::success(null, 'Si cette adresse email est associée à un compte, vous recevrez un lien de connexion.');
        }

        // Generate magic link
        $token = MagicLink::create($user['id'], AuditService::getClientIp());

        // Send email
        $mailService = new MailService();
        $sent = $mailService->sendMagicLink($user['email'], $user['first_name'], $token);

        AuditService::log($user['id'], 'magic_link_requested', 'user', $user['id']);

        if (!$sent) {
            error_log("Failed to send magic link email to: {$email}");
        }

        Response::success(null, 'Si cette adresse email est associée à un compte, vous recevrez un lien de connexion.');
    }

    public function verifyMagicLink(string $token): void
    {
        if (empty($token)) {
            Response::error('Token manquant', 400);
        }

        $magicLinkData = MagicLink::verify($token);

        if (!$magicLinkData) {
            AuditService::log(null, 'magic_link_invalid', null, null, null, ['token_prefix' => substr($token, 0, 8)]);
            Response::error('Lien invalide ou expiré. Veuillez demander un nouveau lien de connexion.', 401);
        }

        // Mark as used
        MagicLink::markAsUsed($magicLinkData['id']);

        $userId = $magicLinkData['user_id'];
        $user = User::findById($userId);

        if (!$user || !$user['is_active']) {
            Response::error('Compte désactivé', 401);
        }

        // Generate tokens
        $accessToken = JWTService::generateAccessToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        $refreshToken = RefreshToken::create(
            $user['id'],
            AuditService::getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        AuditService::log($user['id'], 'login_success', 'user', $user['id']);

        Response::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => JWTService::getAccessTokenExpiry(),
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'login' => $user['login'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role'],
                'client_type' => $user['client_type'] ?? 'personal',
                'company_name' => $user['company_name'] ?? null,
                'siret' => $user['siret'] ?? null
            ]
        ], 'Connexion réussie');
    }

    public function refresh(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['refresh_token'])) {
            Response::error('Refresh token manquant', 400);
        }

        $tokenData = RefreshToken::verify($data['refresh_token']);

        if (!$tokenData) {
            Response::unauthorized('Refresh token invalide ou expiré');
        }

        $user = User::findById($tokenData['user_id']);

        if (!$user || !$user['is_active']) {
            RefreshToken::revoke($data['refresh_token']);
            Response::unauthorized('Compte désactivé');
        }

        // Check if there's an impersonation state to preserve from the old access token
        $impersonatorId = null;
        $oldToken = JWTService::extractTokenFromHeader();
        if ($oldToken) {
            $oldPayload = JWTService::verifyAccessToken($oldToken);
            if ($oldPayload && !empty($oldPayload['impersonator_id'])) {
                $impersonatorId = $oldPayload['impersonator_id'];
            }
        }

        // Rotate refresh token
        $newRefreshToken = RefreshToken::rotate(
            $data['refresh_token'],
            AuditService::getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        // Generate new access token (preserve impersonation state if present)
        $tokenPayload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        if ($impersonatorId) {
            $tokenPayload['impersonator_id'] = $impersonatorId;
        }
        $accessToken = JWTService::generateAccessToken($tokenPayload);

        Response::success([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => JWTService::getAccessTokenExpiry()
        ], 'Token rafraîchi');
    }

    public function logout(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!empty($data['refresh_token'])) {
            RefreshToken::revoke($data['refresh_token']);
        }

        // Try to get user from access token for audit
        $token = JWTService::extractTokenFromHeader();
        if ($token) {
            $payload = JWTService::verifyAccessToken($token);
            if ($payload) {
                AuditService::log($payload['user_id'] ?? null, 'logout', 'user', $payload['user_id'] ?? null);
            }
        }

        Response::success(null, 'Déconnexion réussie');
    }

    /**
     * Impersonate another user (admin only)
     * Creates a new token with the target user's permissions but stores the original admin's ID
     */
    public function impersonate(string $userId): void
    {
        AuthMiddleware::requireAdmin();

        $admin = AuthMiddleware::getCurrentUser();

        // Cannot impersonate yourself
        if ($admin['id'] === $userId) {
            Response::error('Vous ne pouvez pas vous impersoner vous-même', 400);
        }

        // Get the target user
        $targetUser = User::findById($userId);
        if (!$targetUser || !$targetUser['is_active']) {
            Response::notFound('Utilisateur non trouvé ou inactif');
        }

        // Generate new access token with impersonation info
        $accessToken = JWTService::generateAccessToken([
            'user_id' => $targetUser['id'],
            'email' => $targetUser['email'],
            'role' => $targetUser['role'],
            'impersonator_id' => $admin['id'] // Store the original admin's ID
        ]);

        // Create a new refresh token for the impersonated session
        $refreshToken = RefreshToken::create(
            $targetUser['id'],
            AuditService::getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        // Audit log
        AuditService::log(
            $admin['id'],
            'impersonation_started',
            'user',
            $targetUser['id'],
            null,
            [
                'admin_id' => $admin['id'],
                'admin_email' => $admin['email'],
                'target_user_id' => $targetUser['id'],
                'target_user_email' => $targetUser['email']
            ]
        );

        Response::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => JWTService::getAccessTokenExpiry(),
            'user' => [
                'id' => $targetUser['id'],
                'email' => $targetUser['email'],
                'login' => $targetUser['login'],
                'first_name' => $targetUser['first_name'],
                'last_name' => $targetUser['last_name'],
                'role' => $targetUser['role'],
                'client_type' => $targetUser['client_type'] ?? 'personal',
                'company_name' => $targetUser['company_name'] ?? null,
                'siret' => $targetUser['siret'] ?? null
            ],
            'impersonating' => true,
            'impersonator' => [
                'id' => $admin['id'],
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name']
            ]
        ], 'Impersonation démarrée');
    }

    /**
     * Stop impersonating and return to the original admin account
     */
    public function stopImpersonate(): void
    {
        AuthMiddleware::handle();

        // Get impersonator ID from current token
        $token = JWTService::extractTokenFromHeader();
        $payload = JWTService::verifyAccessToken($token);

        if (!$payload || empty($payload['impersonator_id'])) {
            Response::error('Vous n\'êtes pas en mode impersonation', 400);
        }

        $impersonatorId = $payload['impersonator_id'];
        $currentUserId = $payload['user_id'];

        // Get the original admin user
        $admin = User::findById($impersonatorId);
        if (!$admin || !$admin['is_active']) {
            Response::error('Compte administrateur non trouvé ou inactif', 400);
        }

        // Verify the admin is still an admin (security check)
        if ($admin['role'] !== 'admin') {
            Response::error('Le compte original n\'a plus les droits administrateur', 403);
        }

        // Generate new tokens for the original admin
        $accessToken = JWTService::generateAccessToken([
            'user_id' => $admin['id'],
            'email' => $admin['email'],
            'role' => $admin['role']
        ]);

        $refreshToken = RefreshToken::create(
            $admin['id'],
            AuditService::getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        // Audit log
        AuditService::log(
            $admin['id'],
            'impersonation_ended',
            'user',
            $currentUserId,
            null,
            [
                'admin_id' => $admin['id'],
                'admin_email' => $admin['email'],
                'impersonated_user_id' => $currentUserId
            ]
        );

        Response::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => JWTService::getAccessTokenExpiry(),
            'user' => [
                'id' => $admin['id'],
                'email' => $admin['email'],
                'login' => $admin['login'],
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name'],
                'role' => $admin['role'],
                'client_type' => $admin['client_type'] ?? 'personal',
                'company_name' => $admin['company_name'] ?? null,
                'siret' => $admin['siret'] ?? null
            ]
        ], 'Retour au compte administrateur');
    }
}
