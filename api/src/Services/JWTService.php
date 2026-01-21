<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class JWTService
{
    private const ACCESS_TOKEN_EXPIRY = 900; // 15 minutes
    private const REFRESH_TOKEN_EXPIRY = 604800; // 7 days

    private static function env(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function generateAccessToken(array $payload): string
    {
        $issuedAt = time();
        $payload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::ACCESS_TOKEN_EXPIRY,
            'type' => 'access'
        ]);

        return JWT::encode($payload, self::env('JWT_SECRET'), 'HS256');
    }

    public static function generateRefreshToken(array $payload): string
    {
        $issuedAt = time();
        $payload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::REFRESH_TOKEN_EXPIRY,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)) // Unique token ID for revocation
        ]);

        return JWT::encode($payload, self::env('JWT_REFRESH_SECRET'), 'HS256');
    }

    public static function verifyAccessToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::env('JWT_SECRET'), 'HS256'));
            $payload = (array)$decoded;

            if (($payload['type'] ?? '') !== 'access') {
                return null;
            }

            return $payload;
        } catch (ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            error_log('JWT verification error: ' . $e->getMessage());
            return null;
        }
    }

    public static function verifyRefreshToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::env('JWT_REFRESH_SECRET'), 'HS256'));
            $payload = (array)$decoded;

            if (($payload['type'] ?? '') !== 'refresh') {
                return null;
            }

            return $payload;
        } catch (ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            error_log('JWT refresh verification error: ' . $e->getMessage());
            return null;
        }
    }

    public static function extractTokenFromHeader(): ?string
    {
        // First check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Also check Apache-specific header (CGI mode)
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Fallback to query parameter (for document view/download in new tabs)
        if (!empty($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    public static function getAccessTokenExpiry(): int
    {
        return self::ACCESS_TOKEN_EXPIRY;
    }

    public static function getRefreshTokenExpiry(): int
    {
        return self::REFRESH_TOKEN_EXPIRY;
    }
}
