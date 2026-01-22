<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Session;
use App\Models\Setting;
use App\Models\User;
use App\Models\Person;
use App\Models\PromoCode;
use App\Services\AvailabilityService;
use App\Services\BookingMailService;
use App\Services\CaptchaService;
use App\Services\ICSGeneratorService;
use App\Services\SMSService;
use App\Utils\Response;
use App\Utils\Validator;

/**
 * Contrôleur pour les endpoints publics de réservation (sans authentification)
 */
class PublicBookingController
{
    /**
     * GET /public/availability/next
     * Retourne le nombre de jours avant le prochain créneau disponible
     */
    public function getNextAvailability(): void
    {
        $timezone = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');
        $today = new \DateTime('today', $timezone);
        $now = new \DateTime('now', $timezone);

        // Si on est après 23h, considérer le jour comme terminé
        if ((int) $now->format('H') >= 23) {
            $today->modify('+1 day');
        }

        // Chercher dans les 90 prochains jours
        $maxDays = 90;
        $daysUntilNext = null;

        for ($i = 0; $i < $maxDays; $i++) {
            $checkDate = (clone $today)->modify("+{$i} days");

            // Vérifier si le jour est ouvert
            if (!AvailabilityService::isDayOpen($checkDate)) {
                continue;
            }

            // Vérifier s'il y a des créneaux disponibles (type regular par défaut)
            $slots = AvailabilityService::getAvailableSlots($checkDate, AvailabilityService::TYPE_REGULAR);

            if (!empty($slots)) {
                $daysUntilNext = $i;
                break;
            }
        }

        Response::success([
            'days_until_next' => $daysUntilNext,
            'next_date' => $daysUntilNext !== null ? (clone $today)->modify("+{$daysUntilNext} days")->format('Y-m-d') : null,
            'available' => $daysUntilNext !== null
        ]);
    }

    /**
     * GET /public/availability/schedule
     * Récupère les informations générales sur les horaires
     */
    public function getSchedule(): void
    {
        $schedule = AvailabilityService::getScheduleInfo();
        $durations = AvailabilityService::getDurationLabels();

        // Ajouter les prix des séances par type de client
        $prices = [
            'discovery' => Setting::getInteger('session_discovery_price', 55),
            'regular' => Setting::getInteger('session_regular_price', 45)
        ];

        $pricesAssociation = [
            'discovery' => Setting::getInteger('session_discovery_price_association', 50),
            'regular' => Setting::getInteger('session_regular_price_association', 40)
        ];

        // Délais de réservation
        $bookingDelays = [
            'personal' => Setting::getInteger('booking_max_advance_days', 60),
            'association' => Setting::getInteger('booking_max_advance_days_association', 90)
        ];

        Response::success([
            'schedule' => $schedule,
            'duration_types' => $durations,
            'prices' => $prices,
            'prices_by_client_type' => [
                'personal' => $prices,
                'association' => $pricesAssociation
            ],
            'booking_delays' => $bookingDelays,
            'email_confirmation_required' => Setting::getBoolean('booking_email_confirmation_required', false)
        ]);
    }

    /**
     * GET /public/availability/dates?year=2024&month=1&type=regular&client_type=personal
     * Récupère les dates disponibles pour un mois
     */
    public function getAvailableDates(): void
    {
        $year = (int) ($_GET['year'] ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('n'));
        $type = $_GET['type'] ?? AvailabilityService::TYPE_REGULAR;
        $clientType = $_GET['client_type'] ?? User::CLIENT_TYPE_PERSONAL;

        // Validation
        if ($month < 1 || $month > 12) {
            Response::validationError(['month' => 'Mois invalide']);
        }

        if (!in_array($type, [AvailabilityService::TYPE_DISCOVERY, AvailabilityService::TYPE_REGULAR])) {
            Response::validationError(['type' => 'Type de séance invalide']);
        }

        // Valider le client_type
        if (!in_array($clientType, User::CLIENT_TYPES)) {
            $clientType = User::CLIENT_TYPE_PERSONAL;
        }

        // Ne pas permettre de remonter trop loin dans le passé
        $now = new \DateTime();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');

        if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
            Response::success([
                'year' => $year,
                'month' => $month,
                'type' => $type,
                'available_dates' => []
            ]);
            return;
        }

        // Limiter selon le type de client
        $maxAdvanceDays = $clientType === User::CLIENT_TYPE_ASSOCIATION
            ? Setting::getInteger('booking_max_advance_days_association', 90)
            : Setting::getInteger('booking_max_advance_days', 60);

        $maxDate = (clone $now)->modify("+{$maxAdvanceDays} days");
        $requestedDate = new \DateTime("$year-$month-01");

        if ($requestedDate > $maxDate) {
            Response::success([
                'year' => $year,
                'month' => $month,
                'type' => $type,
                'available_dates' => [],
                'message' => "Les réservations sont ouvertes jusqu'à {$maxAdvanceDays} jours à l'avance"
            ]);
            return;
        }

        $availableDates = AvailabilityService::getAvailableDates($year, $month, $type, $maxAdvanceDays);

        Response::success([
            'year' => $year,
            'month' => $month,
            'type' => $type,
            'available_dates' => $availableDates
        ]);
    }

    /**
     * GET /public/availability/slots?date=2024-01-25&type=regular
     * Récupère les créneaux disponibles pour une date
     */
    public function getAvailableSlots(): void
    {
        $dateStr = $_GET['date'] ?? '';
        $type = $_GET['type'] ?? AvailabilityService::TYPE_REGULAR;

        // Validation
        if (empty($dateStr)) {
            Response::validationError(['date' => 'Date requise']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            Response::validationError(['date' => 'Format de date invalide (YYYY-MM-DD attendu)']);
        }

        if (!in_array($type, [AvailabilityService::TYPE_DISCOVERY, AvailabilityService::TYPE_REGULAR])) {
            Response::validationError(['type' => 'Type de séance invalide']);
        }

        try {
            $date = new \DateTime($dateStr);
        } catch (\Exception $e) {
            Response::validationError(['date' => 'Date invalide']);
        }

        // Vérifier que la date n'est pas dans le passé
        $today = new \DateTime('today');
        if ($date < $today) {
            Response::success([
                'date' => $dateStr,
                'type' => $type,
                'slots' => [],
                'message' => 'Cette date est passée'
            ]);
            return;
        }

        $durations = AvailabilityService::getDurations($type);
        $slots = AvailabilityService::getAvailableSlots($date, $type);

        Response::success([
            'date' => $dateStr,
            'type' => $type,
            'duration_display_minutes' => $durations['display'],
            'duration_blocked_minutes' => $durations['blocked'],
            'slots' => $slots
        ]);
    }

    /**
     * POST /public/bookings/check-email
     * Vérifie si un email existe déjà (utilisateur existant)
     */
    public function checkEmail(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator->required('email')->email('email');
        $errors = $validator->validate();

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $email = strtolower(trim($data['email']));

        // Chercher dans les utilisateurs existants
        $user = User::findByEmail($email);
        $exists = $user !== null;

        Response::success([
            'exists' => $exists,
            'has_account' => $exists,
            'user_id' => $exists ? $user['id'] : null
        ]);
    }

    /**
     * GET /public/bookings/persons?email=xxx
     * Récupère les personnes associées à un utilisateur via son email
     * Retourne aussi les infos client MASQUÉES pour sécurité
     */
    public function getPersonsByEmail(): void
    {
        $email = $_GET['email'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => 'Email valide requis']);
        }

        // Chercher l'utilisateur par email
        $user = User::findByEmail($email);

        if (!$user) {
            Response::success([
                'persons' => [],
                'existing_client' => false
            ]);
            return;
        }

        // Récupérer les personnes liées à cet utilisateur
        $persons = User::getPersons($user['id']);

        // Formater pour le frontend
        $formattedPersons = array_map(function ($person) {
            return [
                'id' => $person['id'],
                'first_name' => $person['first_name'],
                'last_name' => $person['last_name']
            ];
        }, $persons);

        // Retourner les infos client MASQUÉES
        Response::success([
            'persons' => $formattedPersons,
            'existing_client' => true,
            'client_info' => [
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email_masked' => $this->maskEmail($user['email']),
                'phone_masked' => $this->maskPhone($user['phone']),
                'has_phone' => !empty($user['phone']),
                'gdpr_already_accepted' => true, // Un client existant a déjà accepté le RGPD
                'client_type' => $user['client_type'] ?? User::CLIENT_TYPE_PERSONAL,
                'client_type_label' => User::CLIENT_TYPE_LABELS[$user['client_type'] ?? User::CLIENT_TYPE_PERSONAL] ?? 'Particulier',
                'company_name' => $user['company_name'] ?? null,
                'has_company' => !empty($user['company_name']),
                'is_active' => (bool) ($user['is_active'] ?? true)
            ]
        ]);
    }

    /**
     * Masque un email pour l'affichage public
     * test@gmail.com -> tes****@gmail.com
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '****@****.***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Garder les 3 premiers caractères du local, le reste en étoiles
        $visibleChars = min(3, strlen($local));
        $masked = substr($local, 0, $visibleChars) . str_repeat('*', max(4, strlen($local) - $visibleChars));

        return $masked . '@' . $domain;
    }

    /**
     * Masque un téléphone pour l'affichage public
     * 0612345678 -> se terminant par 78
     */
    private function maskPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Nettoyer le numéro (garder uniquement les chiffres)
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) < 2) {
            return null;
        }

        // Retourner les 2 derniers chiffres
        return substr($digits, -2);
    }

    /**
     * POST /public/bookings
     * Crée une nouvelle réservation
     */
    public function createBooking(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validation de base
        $validator = new Validator($data);
        $validator
            ->required('session_date')
            ->required('duration_type')
            ->inArray('duration_type', Session::TYPES)
            ->required('client_email')
            ->email('client_email')
            ->phone('client_phone')
            ->required('client_first_name')
            ->minLength('client_first_name', 2, 'Le prénom doit contenir au moins 2 caractères')
            ->required('client_last_name')
            ->minLength('client_last_name', 2, 'Le nom doit contenir au moins 2 caractères')
            ->required('person_first_name')
            ->minLength('person_first_name', 2, 'Le prénom du bénéficiaire doit contenir au moins 2 caractères')
            ->required('person_last_name')
            ->minLength('person_last_name', 2, 'Le nom du bénéficiaire doit contenir au moins 2 caractères')
            ->required('gdpr_consent');

        $errors = $validator->validate();

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Vérifier le consentement RGPD
        if (!$data['gdpr_consent']) {
            Response::validationError(['gdpr_consent' => 'Le consentement RGPD est obligatoire']);
        }

        // Vérifier le captcha si activé
        if (Setting::getBoolean('captcha_enabled', false)) {
            $captchaToken = $data['captcha_token'] ?? '';
            if (empty($captchaToken)) {
                Response::validationError(['captcha_token' => 'Vérification captcha requise']);
            }

            if (!CaptchaService::verify($captchaToken)) {
                Response::error('Vérification captcha échouée', 400);
            }
        }

        // Rate limiting: vérifier le nombre de réservations par IP (anti-spam)
        $clientEmail = strtolower(trim($data['client_email']));
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

        // Vérifier si l'utilisateur est une association (limites différentes)
        $existingUser = User::findByEmail($clientEmail);
        $isAssociation = $existingUser && ($existingUser['client_type'] ?? User::CLIENT_TYPE_PERSONAL) === User::CLIENT_TYPE_ASSOCIATION;

        // Limite IP différente selon le type de client
        $maxPerIp = $isAssociation
            ? Setting::getInteger('booking_max_per_ip_association', 20)
            : Setting::getInteger('booking_max_per_ip', 4);

        if ($clientIp) {
            $bookingsByIp = Session::countUpcomingByIp($clientIp);
            if ($bookingsByIp >= $maxPerIp) {
                Response::error("Vous avez atteint le nombre maximum de réservations à venir ({$maxPerIp}). Veuillez annuler une réservation existante ou patienter.", 429);
            }
        }

        // Valider le créneau
        try {
            $sessionDate = new \DateTime($data['session_date']);
        } catch (\Exception $e) {
            Response::validationError(['session_date' => 'Date/heure invalide']);
            return;
        }

        // Vérifier le délai max de réservation selon le type de client
        $maxAdvanceDays = $isAssociation
            ? Setting::getInteger('booking_max_advance_days_association', 90)
            : Setting::getInteger('booking_max_advance_days', 60);

        $maxDate = (new \DateTime())->modify("+{$maxAdvanceDays} days");
        if ($sessionDate > $maxDate) {
            Response::validationError(['session_date' => "Les réservations sont limitées à {$maxAdvanceDays} jours à l'avance"]);
        }

        $slotErrors = AvailabilityService::validateSlot($sessionDate, $data['duration_type']);
        if (!empty($slotErrors)) {
            Response::validationError(['session_date' => implode(', ', $slotErrors)]);
        }

        // Gestion de l'utilisateur: chercher ou créer
        $user = User::findByEmail($clientEmail);
        $userId = null;

        // Déterminer le type de client (personal par défaut)
        $clientType = $data['client_type'] ?? User::CLIENT_TYPE_PERSONAL;
        if (!in_array($clientType, User::CLIENT_TYPES)) {
            $clientType = User::CLIENT_TYPE_PERSONAL;
        }

        // Récupérer les infos entreprise si association
        $companyName = ($clientType === User::CLIENT_TYPE_ASSOCIATION && !empty($data['company_name']))
            ? trim($data['company_name']) : null;
        $siret = ($clientType === User::CLIENT_TYPE_ASSOCIATION && !empty($data['siret']))
            ? trim($data['siret']) : null;

        if (!$user) {
            // Créer un nouvel utilisateur (role member, inactif par défaut pour sécurité)
            $userId = User::create([
                'email' => $clientEmail,
                'first_name' => trim($data['client_first_name']),
                'last_name' => trim($data['client_last_name']),
                'phone' => !empty($data['client_phone']) ? trim($data['client_phone']) : null,
                'role' => 'member',
                'is_active' => false, // Inactif jusqu'à validation admin
                'client_type' => $clientType,
                'company_name' => $companyName,
                'siret' => $siret
            ]);
        } else {
            $userId = $user['id'];

            // Si l'utilisateur n'a pas de téléphone mais en fournit un, le mettre à jour
            $updateData = [];
            if (empty($user['phone']) && !empty($data['client_phone'])) {
                $updateData['phone'] = trim($data['client_phone']);
            }

            // Mettre à jour le type de client si fourni et différent
            if (!empty($data['client_type']) && $data['client_type'] !== $user['client_type']) {
                $updateData['client_type'] = $clientType;
                $updateData['company_name'] = $companyName;
                $updateData['siret'] = $siret;
            }

            if (!empty($updateData)) {
                User::update($userId, $updateData);
            }

            // Récupérer le client_type depuis l'utilisateur existant si non fourni
            if (empty($data['client_type'])) {
                $clientType = $user['client_type'] ?? User::CLIENT_TYPE_PERSONAL;
                $companyName = $user['company_name'] ?? null;
                $siret = $user['siret'] ?? null;
            }
        }

        // Gestion de la personne: chercher ou créer
        $personId = null;

        if (!empty($data['person_id'])) {
            // Personne existante sélectionnée
            $personId = $data['person_id'];
        } else {
            // Créer une nouvelle personne
            $personId = Person::create([
                'first_name' => trim($data['person_first_name']),
                'last_name' => trim($data['person_last_name'])
            ]);

            // Lier la personne à l'utilisateur
            Person::assignToUser($personId, $userId);
        }

        // Vérifier la limite de séances par personne (4 max en parallèle)
        $maxPerPerson = Setting::getInteger('booking_max_per_person', 4);
        $bookingsByPerson = Session::countUpcomingByPerson($personId);
        if ($bookingsByPerson >= $maxPerPerson) {
            Response::error("Cette personne a déjà {$maxPerPerson} séance(s) à venir. Veuillez annuler une réservation existante ou attendre qu'une séance soit passée.", 429);
        }

        // Récupérer le prix de la séance selon le type de client
        $originalPrice = Session::getPriceForType($data['duration_type'], $clientType);
        $price = $originalPrice;
        $promoCodeId = null;
        $discountAmount = null;
        $appliedPromo = null;

        // Gestion du code promo
        if (!empty($data['promo_code']) || !empty($data['promo_code_id'])) {
            // Valider le code promo
            if (!empty($data['promo_code'])) {
                $validation = PromoCode::validate(
                    $data['promo_code'],
                    $data['duration_type'],
                    $userId,
                    $clientType
                );
            } else {
                // Promo automatique passée par ID
                $promo = PromoCode::findById($data['promo_code_id']);
                if ($promo) {
                    $validation = PromoCode::validatePromo(
                        $promo,
                        $data['duration_type'],
                        $userId,
                        $clientType
                    );
                } else {
                    $validation = ['valid' => false, 'error' => 'Code promo invalide'];
                }
            }

            if ($validation['valid']) {
                $appliedPromo = $validation['promo'];
                $promoCodeId = $appliedPromo['id'];

                // Calculer la remise
                $discount = PromoCode::calculateDiscount($appliedPromo, $originalPrice);
                $price = $discount['final_price'];
                $discountAmount = $discount['discount_amount'];
            }
            // Si le code n'est pas valide, on continue sans remise (pas d'erreur)
        } else {
            // Vérifier s'il y a une promo automatique applicable
            $autoPromo = PromoCode::findApplicableAutomatic($data['duration_type'], $userId, $clientType);
            if ($autoPromo) {
                $appliedPromo = $autoPromo;
                $promoCodeId = $autoPromo['id'];

                // Calculer la remise
                $discount = PromoCode::calculateDiscount($autoPromo, $originalPrice);
                $price = $discount['final_price'];
                $discountAmount = $discount['discount_amount'];
            }
        }

        // Préparer les données de la réservation
        // Les infos client/personne sont récupérées via JOINs avec users/persons
        $bookingData = [
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'duration_type' => $data['duration_type'],
            'price' => $price,
            'user_id' => $userId,
            'person_id' => $personId,
            'gdpr_consent' => true,
            'ip_address' => $clientIp,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'promo_code_id' => $promoCodeId,
            'original_price' => $promoCodeId ? $originalPrice : null,
            'discount_amount' => $discountAmount
        ];

        // Vérifier si la validation email est requise
        $emailConfirmationRequired = Setting::getBoolean('booking_email_confirmation_required', false);

        // Créer la réservation
        $sessionId = Session::createReservation($bookingData);

        // Enregistrer l'utilisation du code promo
        if ($promoCodeId && $appliedPromo) {
            PromoCode::recordUsage(
                $promoCodeId,
                $sessionId,
                $originalPrice,
                $discountAmount,
                $price,
                $userId,
                $clientIp
            );
        }

        $booking = Session::findById($sessionId);

        // Envoyer l'email de confirmation au client
        $mailService = new BookingMailService();

        if ($emailConfirmationRequired) {
            // Mode avec validation email: envoyer email de confirmation avec lien
            $mailService->sendClientConfirmation($booking);
            $message = 'Réservation créée. Veuillez vérifier votre email pour confirmer.';
        } else {
            // Mode sans validation email: confirmer automatiquement
            Session::confirm($booking['id']);
            $booking = Session::findById($booking['id']);
            $mailService->sendBookingConfirmedEmail($booking);
            $message = 'Réservation confirmée. Un email de confirmation vous a été envoyé.';

            // Envoyer SMS de confirmation si configuré
            if (SMSService::isConfigured() && !empty($booking['client_phone'])) {
                SMSService::sendConfirmation($booking);
            }
        }

        // Envoyer l'invitation calendrier aux admins
        $mailService->sendAdminNotification($booking);

        Response::success([
            'id' => $sessionId,
            'confirmation_token' => $booking['confirmation_token'],
            'requires_confirmation' => $emailConfirmationRequired,
            'message' => $message,
            'promo_applied' => $promoCodeId !== null,
            'pricing' => $promoCodeId ? [
                'original_price' => $originalPrice,
                'discount_amount' => $discountAmount,
                'final_price' => $price
            ] : null
        ], 'Réservation enregistrée', 201);
    }

    /**
     * GET /public/bookings/confirm/{token}
     * Confirme une réservation via le token
     */
    public function confirmBooking(string $token): void
    {
        $booking = Session::findByToken($token);

        if (!$booking) {
            Response::notFound('Réservation non trouvée ou lien expiré');
        }

        // Vérifier le statut
        if ($booking['status'] === Session::STATUS_CONFIRMED) {
            Response::success([
                'already_confirmed' => true,
                'booking' => $this->formatBookingForClient($booking)
            ], 'Cette réservation est déjà confirmée');
            return;
        }

        if ($booking['status'] === Session::STATUS_CANCELLED) {
            Response::error('Cette réservation a été annulée', 400);
        }

        if ($booking['status'] !== Session::STATUS_PENDING) {
            Response::error('Cette réservation ne peut plus être confirmée', 400);
        }

        // Vérifier que le créneau est toujours disponible
        $sessionDate = new \DateTime($booking['session_date']);
        $slotErrors = AvailabilityService::validateSlot($sessionDate, $booking['duration_type']);

        if (!empty($slotErrors)) {
            Response::error('Ce créneau n\'est plus disponible. Veuillez effectuer une nouvelle réservation.', 409);
        }

        // Confirmer la réservation
        Session::confirm($booking['id']);

        // Recharger pour avoir les données à jour
        $booking = Session::findById($booking['id']);

        // Envoyer l'email de confirmation finale avec le fichier ICS
        $mailService = new BookingMailService();
        $mailService->sendBookingConfirmedEmail($booking);

        // Envoyer un SMS de confirmation si configuré
        if (SMSService::isConfigured() && !empty($booking['client_phone'])) {
            SMSService::sendConfirmation($booking);
        }

        Response::success([
            'confirmed' => true,
            'booking' => $this->formatBookingForClient($booking)
        ], 'Rendez-vous confirmé');
    }

    /**
     * POST /public/bookings/cancel/{token}
     * Annule une réservation via le token
     */
    public function cancelBooking(string $token): void
    {
        $booking = Session::findByToken($token);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        // Vérifier le statut
        if ($booking['status'] === Session::STATUS_CANCELLED) {
            Response::success([
                'already_cancelled' => true
            ], 'Cette réservation est déjà annulée');
            return;
        }

        if ($booking['status'] === Session::STATUS_COMPLETED) {
            Response::error('Cette séance a déjà eu lieu', 400);
        }

        // Annuler
        Session::cancel($booking['id']);

        // Envoyer l'email d'annulation
        $mailService = new BookingMailService();
        $mailService->sendCancellationEmail($booking);

        Response::success([
            'cancelled' => true
        ], 'Rendez-vous annulé');
    }

    /**
     * GET /public/bookings/{token}
     * Récupère les détails d'une réservation via le token
     */
    public function getBookingByToken(string $token): void
    {
        $booking = Session::findByToken($token);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        Response::success([
            'booking' => $this->formatBookingForClient($booking)
        ]);
    }

    /**
     * GET /public/bookings/{token}/ics
     * Télécharge le fichier ICS pour une réservation
     */
    public function downloadICS(string $token): void
    {
        $booking = Session::findByToken($token);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        if ($booking['status'] !== Session::STATUS_CONFIRMED) {
            Response::error('Seules les réservations confirmées peuvent être exportées', 400);
        }

        $icsContent = ICSGeneratorService::generateBookingEvent($booking);
        $headers = ICSGeneratorService::getDownloadHeaders('sensea-rdv.ics');

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $icsContent;
        exit;
    }

    /**
     * Formate une réservation pour l'affichage client (sans données sensibles)
     */
    private function formatBookingForClient(array $booking): array
    {
        $clientType = $booking['client_type'] ?? 'personal';

        return [
            'id' => $booking['id'],
            'session_date' => $booking['session_date'],
            'duration_type' => $booking['duration_type'],
            'duration_type_label' => Session::LABELS['duration_type'][$booking['duration_type']] ?? $booking['duration_type'],
            'duration_display_minutes' => $booking['duration_display_minutes'],
            'status' => $booking['status'],
            'status_label' => Session::LABELS['status'][$booking['status']] ?? $booking['status'],
            'person_first_name' => $booking['person_first_name'],
            'person_last_name' => $booking['person_last_name'],
            'client_first_name' => $booking['client_first_name'],
            'client_last_name' => $booking['client_last_name'],
            'client_email' => $booking['client_email'],
            'client_type' => $clientType,
            'client_type_label' => Session::LABELS['client_type'][$clientType] ?? 'Particulier',
            'company_name' => $booking['company_name'] ?? null,
            'confirmed_at' => $booking['confirmed_at'],
            'created_at' => $booking['created_at']
        ];
    }
}
