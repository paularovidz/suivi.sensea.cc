<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private PHPMailer $mailer;

    private static function env(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = self::env('MAIL_HOST', 'mailhog');
        $this->mailer->SMTPAuth = false; // MailHog doesn't require auth
        $this->mailer->Username = self::env('MAIL_USER', '');
        $this->mailer->Password = self::env('MAIL_PASS', '');
        $this->mailer->SMTPSecure = false; // No encryption for MailHog
        $this->mailer->Port = (int)self::env('MAIL_PORT', '1025');
        $this->mailer->CharSet = 'UTF-8';

        // Default sender
        $this->mailer->setFrom(self::env('MAIL_FROM', 'noreply@sensea.cc'), self::env('MAIL_FROM_NAME', 'sensëa Snoezelen'));
    }

    public function sendMagicLink(string $email, string $firstName, string $token): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Votre lien de connexion - sensëa Snoezelen';

            $magicLink = self::env('FRONTEND_URL', 'http://localhost:5173') . '/auth/verify/' . $token;
            $expiryMinutes = 15;

            $this->mailer->Body = $this->getMagicLinkEmailBody($firstName, $magicLink, $expiryMinutes);
            $this->mailer->AltBody = $this->getMagicLinkEmailText($firstName, $magicLink, $expiryMinutes);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log('Mail sending failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getMagicLinkEmailBody(string $firstName, string $link, int $expiryMinutes): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion sensëa</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">sensëa Snoezelen</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 18px; color: #333;">Bonjour {$firstName},</p>
                            <p style="margin: 0 0 30px; font-size: 16px; color: #555; line-height: 1.6;">
                                Vous avez demandé à vous connecter à votre espace sensëa Snoezelen.
                                Cliquez sur le bouton ci-dessous pour accéder à votre compte :
                            </p>
                            <table role="presentation" style="margin: 0 auto 30px;">
                                <tr>
                                    <td style="border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <a href="{$link}" style="display: inline-block; padding: 16px 40px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none;">
                                            Se connecter
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 15px; font-size: 14px; color: #888; line-height: 1.5;">
                                Ce lien est valable pendant <strong>{$expiryMinutes} minutes</strong> et ne peut être utilisé qu'une seule fois.
                            </p>
                            <p style="margin: 0; font-size: 14px; color: #888; line-height: 1.5;">
                                Si vous n'avez pas demandé ce lien, vous pouvez ignorer cet email en toute sécurité.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f8f9fa; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #999;">
                                Cet email a été envoyé par sensëa Snoezelen.<br>
                                Pour des raisons de sécurité, ne partagez jamais ce lien.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function getMagicLinkEmailText(string $firstName, string $link, int $expiryMinutes): string
    {
        return <<<TEXT
Bonjour {$firstName},

Vous avez demandé à vous connecter à votre espace sensëa Snoezelen.

Cliquez sur le lien suivant pour accéder à votre compte :
{$link}

Ce lien est valable pendant {$expiryMinutes} minutes et ne peut être utilisé qu'une seule fois.

Si vous n'avez pas demandé ce lien, vous pouvez ignorer cet email en toute sécurité.

---
Cet email a été envoyé par sensëa Snoezelen.
Pour des raisons de sécurité, ne partagez jamais ce lien.
TEXT;
    }
}
