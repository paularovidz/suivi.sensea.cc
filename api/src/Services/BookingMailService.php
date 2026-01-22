<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Config\Database;

/**
 * Service d'envoi d'emails pour les réservations
 */
class BookingMailService
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
        $this->mailer->SMTPAuth = !empty(self::env('MAIL_USER'));
        $this->mailer->Username = self::env('MAIL_USER', '');
        $this->mailer->Password = self::env('MAIL_PASS', '');

        $port = (int) self::env('MAIL_PORT', '1025');
        $this->mailer->Port = $port;

        // Encryption based on port
        if ($port === 465) {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($port === 587) {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mailer->SMTPSecure = false;
            $this->mailer->SMTPAutoTLS = false;
        }

        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->setFrom(
            self::env('MAIL_FROM', 'noreply@sensea.cc'),
            self::env('MAIL_FROM_NAME', 'sensëa Snoezelen')
        );
    }

    /**
     * Envoie l'email de confirmation au client
     */
    public function sendClientConfirmation(array $booking): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($booking['client_email'], "{$booking['client_first_name']} {$booking['client_last_name']}");

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Confirmez votre rendez-vous - sensëa Snoezelen';

            $confirmLink = self::env('FRONTEND_URL', 'http://localhost:5173') . '/booking/confirm/' . $booking['confirmation_token'];
            $cancelLink = self::env('FRONTEND_URL', 'http://localhost:5173') . '/booking/cancel/' . $booking['confirmation_token'];

            $this->mailer->Body = $this->getClientConfirmationHtml($booking, $confirmLink, $cancelLink);
            $this->mailer->AltBody = $this->getClientConfirmationText($booking, $confirmLink, $cancelLink);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log('BookingMailService - Client confirmation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les emails de tous les utilisateurs admin actifs
     */
    private function getAdminEmails(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT email, first_name, last_name FROM users WHERE role = 'admin' AND is_active = 1");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Envoie la notification aux administrateurs (sans ICS - déjà synchronisé via Google Calendar)
     */
    public function sendAdminNotification(array $booking): bool
    {
        try {
            $admins = $this->getAdminEmails();
            if (empty($admins)) {
                error_log('BookingMailService - No admin users found in database');
                return false;
            }

            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            foreach ($admins as $admin) {
                $this->mailer->addAddress($admin['email'], "{$admin['first_name']} {$admin['last_name']}");
            }

            $this->mailer->isHTML(true);

            $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');
            $this->mailer->Subject = "Nouvelle réservation - {$booking['person_first_name']} {$booking['person_last_name']} - {$dateFormatted}";

            $this->mailer->Body = $this->getAdminNotificationHtml($booking);
            $this->mailer->AltBody = $this->getAdminNotificationText($booking);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log('BookingMailService - Admin notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie l'email de rappel au client
     */
    public function sendReminderEmail(array $booking): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($booking['client_email'], "{$booking['client_first_name']} {$booking['client_last_name']}");

            $this->mailer->isHTML(true);

            $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');
            $this->mailer->Subject = "Rappel : Votre séance demain - {$dateFormatted}";

            $this->mailer->Body = $this->getReminderHtml($booking);
            $this->mailer->AltBody = $this->getReminderText($booking);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log('BookingMailService - Reminder failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie l'email d'annulation
     */
    public function sendCancellationEmail(array $booking): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($booking['client_email'], "{$booking['client_first_name']} {$booking['client_last_name']}");

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Annulation de votre rendez-vous - sensëa Snoezelen';

            $this->mailer->Body = $this->getCancellationHtml($booking);
            $this->mailer->AltBody = $this->getCancellationText($booking);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log('BookingMailService - Cancellation email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie l'email de confirmation finale (après clic sur le lien)
     * Inclut une invitation calendrier native (boutons Accepter/Refuser dans Gmail, Outlook, etc.)
     */
    public function sendBookingConfirmedEmail(array $booking): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($booking['client_email'], "{$booking['client_first_name']} {$booking['client_last_name']}");

            // Générer l'invitation calendrier (METHOD:REQUEST pour avoir Accepter/Refuser)
            $attendee = [
                ['email' => $booking['client_email'], 'name' => "{$booking['client_first_name']} {$booking['client_last_name']}"]
            ];
            $icsContent = ICSGeneratorService::generateCalendarInvitation($booking, $attendee);

            // Envoyer comme invitation calendrier native (pas en pièce jointe)
            $this->mailer->Ical = $icsContent;

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Rendez-vous confirmé - sensëa Snoezelen';

            $this->mailer->Body = $this->getBookingConfirmedHtml($booking);
            $this->mailer->AltBody = $this->getBookingConfirmedText($booking);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log('BookingMailService - Booking confirmed email failed: ' . $e->getMessage());
            return false;
        }
    }

    // ========== Templates HTML ==========

    private function getClientConfirmationHtml(array $booking, string $confirmLink, string $cancelLink): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y');
        $timeFormatted = (new \DateTime($booking['session_date']))->format('H:i');
        $type = $booking['duration_type'] === 'discovery' ? 'Séance découverte' : 'Séance classique';
        $duration = $booking['duration_display_minutes'] . ' minutes';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                            <p style="margin: 0 0 20px; font-size: 18px; color: #333;">Bonjour {$booking['client_first_name']},</p>
                            <p style="margin: 0 0 30px; font-size: 16px; color: #555; line-height: 1.6;">
                                Votre demande de rendez-vous a bien été enregistrée. Veuillez confirmer votre réservation en cliquant sur le bouton ci-dessous.
                            </p>

                            <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <h3 style="margin: 0 0 15px; color: #333; font-size: 16px;">Détails du rendez-vous</h3>
                                <table style="width: 100%; font-size: 14px; color: #555;">
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Date :</strong></td>
                                        <td style="padding: 5px 0;">{$dateFormatted}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Heure :</strong></td>
                                        <td style="padding: 5px 0;">{$timeFormatted}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Type :</strong></td>
                                        <td style="padding: 5px 0;">{$type}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Durée :</strong></td>
                                        <td style="padding: 5px 0;">{$duration}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Pour :</strong></td>
                                        <td style="padding: 5px 0;">{$booking['person_first_name']} {$booking['person_last_name']}</td>
                                    </tr>
                                </table>
                            </div>

                            <div style="background-color: #e8f4fd; border: 1px solid #b8daff; border-radius: 8px; padding: 15px; margin-bottom: 30px;">
                                <p style="margin: 0; font-size: 14px; color: #004085;">
                                    <strong>Conseil :</strong> Pensez à vous habiller confortablement pour la séance, idéalement une tenue de sport ou des vêtements souples.
                                </p>
                            </div>

                            <table role="presentation" style="margin: 0 auto 20px;">
                                <tr>
                                    <td style="border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <a href="{$confirmLink}" style="display: inline-block; padding: 16px 40px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none;">
                                            Confirmer mon rendez-vous
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 15px; font-size: 14px; color: #888; text-align: center;">
                                <a href="{$cancelLink}" style="color: #888;">Annuler ce rendez-vous</a>
                            </p>

                            <p style="margin: 30px 0 0; font-size: 14px; color: #888; line-height: 1.5;">
                                Ce lien est valable 24 heures. Sans confirmation de votre part, le créneau sera libéré.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f8f9fa; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #999;">
                                sensëa Snoezelen<br>
                                Cet email a été envoyé suite à votre demande de rendez-vous.
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

    private function getClientConfirmationText(array $booking, string $confirmLink, string $cancelLink): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');
        $type = $booking['duration_type'] === 'discovery' ? 'Séance découverte' : 'Séance classique';

        return <<<TEXT
Bonjour {$booking['client_first_name']},

Votre demande de rendez-vous a bien été enregistrée.

DÉTAILS DU RENDEZ-VOUS
----------------------
Date : {$dateFormatted}
Type : {$type}
Durée : {$booking['duration_display_minutes']} minutes
Pour : {$booking['person_first_name']} {$booking['person_last_name']}

CONSEIL : Pensez à vous habiller confortablement pour la séance, idéalement une tenue de sport ou des vêtements souples.

Pour confirmer votre rendez-vous, cliquez sur ce lien :
{$confirmLink}

Pour annuler :
{$cancelLink}

Ce lien est valable 24 heures.

---
sensëa Snoezelen
TEXT;
    }

    private function getAdminNotificationHtml(array $booking): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');
        $type = $booking['duration_type'] === 'discovery' ? 'Séance découverte' : 'Séance classique';
        $status = $booking['status'] === 'pending' ? 'En attente de confirmation client' : 'Confirmé';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f4f7fa;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px;">
        <h2 style="color: #667eea; margin-top: 0;">Nouvelle réservation</h2>

        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;"><strong>Date :</strong></td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">{$dateFormatted}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;"><strong>Type :</strong></td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">{$type} ({$booking['duration_display_minutes']} min)</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;"><strong>Bénéficiaire :</strong></td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">{$booking['person_first_name']} {$booking['person_last_name']}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;"><strong>Contact :</strong></td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">{$booking['client_first_name']} {$booking['client_last_name']}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;"><strong>Email :</strong></td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;"><a href="mailto:{$booking['client_email']}">{$booking['client_email']}</a></td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;"><strong>Téléphone :</strong></td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">{$booking['client_phone']}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Statut :</strong></td>
                <td style="padding: 10px 0;">{$status}</td>
            </tr>
        </table>

    </div>
</body>
</html>
HTML;
    }

    private function getAdminNotificationText(array $booking): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');
        $type = $booking['duration_type'] === 'discovery' ? 'Séance découverte' : 'Séance classique';

        return <<<TEXT
NOUVELLE RÉSERVATION
====================

Date : {$dateFormatted}
Type : {$type} ({$booking['duration_display_minutes']} min)

Bénéficiaire : {$booking['person_first_name']} {$booking['person_last_name']}
Contact : {$booking['client_first_name']} {$booking['client_last_name']}
Email : {$booking['client_email']}
Téléphone : {$booking['client_phone']}

Statut : En attente de confirmation client
TEXT;
    }

    private function getReminderHtml(array $booking): string
    {
        $timeFormatted = (new \DateTime($booking['session_date']))->format('H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">Rappel de rendez-vous</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 18px; color: #333;">Bonjour {$booking['client_first_name']},</p>
                            <p style="margin: 0 0 20px; font-size: 16px; color: #555; line-height: 1.6;">
                                Nous vous rappelons votre séance Snoezelen <strong>demain à {$timeFormatted}</strong>.
                            </p>
                            <p style="margin: 0 0 20px; font-size: 16px; color: #555;">
                                Pour : <strong>{$booking['person_first_name']} {$booking['person_last_name']}</strong>
                            </p>
                            <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                                <p style="margin: 0; font-size: 14px; color: #856404;">
                                    <strong>Rappel :</strong> Pensez à vous habiller confortablement, idéalement une tenue de sport ou des vêtements souples.
                                </p>
                            </div>
                            <p style="margin: 0; font-size: 14px; color: #888;">
                                À demain !<br>L'équipe sensëa Snoezelen
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

    private function getReminderText(array $booking): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');

        return <<<TEXT
Bonjour {$booking['client_first_name']},

Nous vous rappelons votre séance Snoezelen demain.

Date : {$dateFormatted}
Pour : {$booking['person_first_name']} {$booking['person_last_name']}

RAPPEL : Pensez à vous habiller confortablement, idéalement une tenue de sport ou des vêtements souples.

À demain !
L'équipe sensëa Snoezelen
TEXT;
    }

    private function getCancellationHtml(array $booking): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px;">
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background: #dc3545; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">Rendez-vous annulé</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 16px; color: #333;">Bonjour {$booking['client_first_name']},</p>
                            <p style="margin: 0 0 20px; font-size: 16px; color: #555;">
                                Votre rendez-vous du <strong>{$dateFormatted}</strong> pour <strong>{$booking['person_first_name']} {$booking['person_last_name']}</strong> a bien été annulé.
                            </p>
                            <p style="margin: 0; font-size: 14px; color: #888;">
                                N'hésitez pas à reprendre rendez-vous quand vous le souhaitez.<br>
                                L'équipe sensëa Snoezelen
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

    private function getCancellationText(array $booking): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');

        return <<<TEXT
Bonjour {$booking['client_first_name']},

Votre rendez-vous du {$dateFormatted} pour {$booking['person_first_name']} {$booking['person_last_name']} a bien été annulé.

N'hésitez pas à reprendre rendez-vous quand vous le souhaitez.

L'équipe sensëa Snoezelen
TEXT;
    }

    private function getBookingConfirmedHtml(array $booking): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y');
        $timeFormatted = (new \DateTime($booking['session_date']))->format('H:i');
        $type = $booking['duration_type'] === 'discovery' ? 'Séance découverte' : 'Séance classique';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px;">
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">Rendez-vous confirmé !</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 18px; color: #333;">Bonjour {$booking['client_first_name']},</p>
                            <p style="margin: 0 0 30px; font-size: 16px; color: #555;">
                                Votre rendez-vous est maintenant confirmé. Nous avons hâte de vous accueillir !
                            </p>

                            <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <table style="width: 100%; font-size: 14px; color: #555;">
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Date :</strong></td>
                                        <td style="padding: 5px 0;">{$dateFormatted}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Heure :</strong></td>
                                        <td style="padding: 5px 0;">{$timeFormatted}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Type :</strong></td>
                                        <td style="padding: 5px 0;">{$type}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0;"><strong>Pour :</strong></td>
                                        <td style="padding: 5px 0;">{$booking['person_first_name']} {$booking['person_last_name']}</td>
                                    </tr>
                                </table>
                            </div>

                            <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 30px;">
                                <p style="margin: 0; font-size: 14px; color: #856404;">
                                    <strong>Conseil :</strong> Pensez à vous habiller confortablement pour la séance, idéalement une tenue de sport ou des vêtements souples.
                                </p>
                            </div>

                            <p style="margin: 0; font-size: 14px; color: #888;">
                                Cet email contient une invitation calendrier. Vous pouvez l'accepter directement depuis Gmail, Outlook ou votre application mail.<br><br>
                                À bientôt !<br>
                                L'équipe sensëa Snoezelen
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

    private function getBookingConfirmedText(array $booking): string
    {
        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');
        $type = $booking['duration_type'] === 'discovery' ? 'Séance découverte' : 'Séance classique';

        return <<<TEXT
Bonjour {$booking['client_first_name']},

Votre rendez-vous est maintenant confirmé !

DÉTAILS
-------
Date : {$dateFormatted}
Type : {$type}
Pour : {$booking['person_first_name']} {$booking['person_last_name']}

CONSEIL : Pensez à vous habiller confortablement pour la séance, idéalement une tenue de sport ou des vêtements souples.

Cet email contient une invitation calendrier que vous pouvez accepter depuis votre application mail.

À bientôt !
L'équipe sensëa Snoezelen
TEXT;
    }
}
