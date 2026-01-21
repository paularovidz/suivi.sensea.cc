<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Database;
use App\Utils\UUID;
use App\Utils\Encryption;

/**
 * Factory pour générer des données de test
 *
 * Usage: php database/Factory.php [--clean]
 *
 * Options:
 *   --clean  Supprime les données existantes avant de créer les nouvelles
 */
class Factory
{
    private \PDO $db;
    private array $users = [];
    private array $persons = [];
    private ?array $adminUser = null;

    // Prénoms français
    private array $firstNames = [
        'Marie', 'Jean', 'Pierre', 'Sophie', 'Lucas', 'Emma', 'Louis', 'Léa',
        'Gabriel', 'Chloé', 'Raphaël', 'Manon', 'Arthur', 'Camille', 'Hugo',
        'Sarah', 'Nathan', 'Jade', 'Théo', 'Louise', 'Mathis', 'Alice',
        'Jules', 'Inès', 'Ethan', 'Lina', 'Noah', 'Léna', 'Tom', 'Rose'
    ];

    // Noms de famille français
    private array $lastNames = [
        'Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit',
        'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel',
        'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent', 'Fournier', 'Morel',
        'Girard', 'Andre', 'Lefevre', 'Mercier', 'Dupont', 'Lambert', 'Bonnet'
    ];

    // Noms d'associations
    private array $associationNames = [
        'Association Soleil Levant',
        'Les Amis du Bien-être',
        'Fondation Sérénité',
        'Centre Harmonie',
        'Association Arc-en-ciel',
        'Les Jardins de la Paix',
        'Maison de l\'Espoir',
        'Association Lumière',
        'ESAT Les Papillons',
        'IME du Parc',
        'Foyer de Vie Les Tilleuls',
        'MAS Les Cèdres',
        'Association Handicap et Partage',
        'Centre Médico-Social du Val',
        'EHPAD Les Glycines'
    ];

    // Comportements possibles
    private array $behaviors = ['calm', 'agitated', 'tired', 'defensive', 'anxious', 'passive'];
    private array $behaviorsStart = ['calm', 'agitated', 'defensive', 'anxious', 'passive'];

    // Positions
    private array $positions = ['standing', 'lying', 'sitting', 'moving'];

    // Communications
    private array $communications = ['body', 'verbal', 'vocal'];

    // Fin de séance
    private array $sessionEnds = ['accepts', 'refuses', 'interrupts'];

    // Attitudes
    private array $attitudes = ['accepts', 'indifferent', 'refuses'];

    // Origines proposition
    private array $proposalOrigins = ['person', 'relative'];

    public function __construct()
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        // Charger les variables d'environnement
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                [$key, $value] = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }

        $this->db = Database::getInstance();
    }

    /**
     * Exécute la factory
     */
    public function run(bool $clean = false): void
    {
        echo "\n=== Factory de données de test ===\n\n";

        if ($clean) {
            $this->clean();
        }

        // Récupérer ou créer l'admin
        $this->adminUser = $this->getOrCreateAdmin();
        echo "Admin: {$this->adminUser['email']}\n\n";

        // Créer des utilisateurs
        echo "Création des utilisateurs...\n";
        $this->createUsers(20);
        echo "  -> " . count($this->users) . " utilisateurs créés\n\n";

        // Créer des personnes
        echo "Création des personnes...\n";
        $this->createPersons(40);
        echo "  -> " . count($this->persons) . " personnes créées\n\n";

        // Créer des réservations passées avec séances
        echo "Création des réservations passées (6 derniers mois)...\n";
        $pastCount = $this->createPastBookingsWithSessions();
        echo "  -> {$pastCount} réservations/séances créées\n\n";

        // Créer des séances pour aujourd'hui
        echo "Création des séances du jour...\n";
        $todayCount = $this->createTodaySessions();
        echo "  -> {$todayCount} séances créées pour aujourd'hui\n\n";

        // Créer des réservations futures
        echo "Création des réservations futures (60 prochains jours)...\n";
        $futureCount = $this->createFutureBookings();
        echo "  -> {$futureCount} réservations créées\n\n";

        echo "=== Terminé ===\n\n";
    }

    /**
     * Nettoie les données de test
     */
    private function clean(): void
    {
        echo "Nettoyage des données existantes...\n";

        // Supprimer dans l'ordre pour respecter les contraintes FK
        $this->db->exec("DELETE FROM session_proposals");
        $this->db->exec("DELETE FROM sessions WHERE id != ''");
        $this->db->exec("DELETE FROM bookings WHERE id != ''");
        $this->db->exec("DELETE FROM user_persons WHERE user_id != ''");
        $this->db->exec("DELETE FROM persons WHERE id != ''");
        $this->db->exec("DELETE FROM loyalty_cards WHERE id != ''");
        $this->db->exec("DELETE FROM users WHERE role != 'admin'");

        echo "  -> Données nettoyées\n\n";
    }

    /**
     * Récupère ou crée l'utilisateur admin
     */
    private function getOrCreateAdmin(): array
    {
        $stmt = $this->db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
        $admin = $stmt->fetch();

        if ($admin) {
            return $admin;
        }

        // Créer un admin par défaut
        $id = UUID::generate();
        $stmt = $this->db->prepare("
            INSERT INTO users (id, email, login, first_name, last_name, role, is_active, client_type, created_at, updated_at)
            VALUES (:id, :email, :login, :first_name, :last_name, 'admin', 1, 'personal', NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $id,
            'email' => 'bonjour@sensea.cc',
            'login' => 'celine',
            'first_name' => 'Céline',
            'last_name' => 'Delcloy'
        ]);

        return [
            'id' => $id,
            'email' => 'bonjour@sensea.cc',
            'first_name' => 'Céline',
            'last_name' => 'Delcloy'
        ];
    }

    /**
     * Crée des utilisateurs
     */
    private function createUsers(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $isAssociation = $i < 5; // 5 associations, le reste particuliers
            $firstName = $this->randomItem($this->firstNames);
            $lastName = $this->randomItem($this->lastNames);
            $email = strtolower($firstName . '.' . $lastName . $i . '@example.com');

            $id = UUID::generate();
            $stmt = $this->db->prepare("
                INSERT INTO users (id, email, login, first_name, last_name, phone, role, client_type, company_name, siret, is_active, created_at, updated_at)
                VALUES (:id, :email, :login, :first_name, :last_name, :phone, 'member', :client_type, :company_name, :siret, 1, NOW(), NOW())
            ");

            $companyName = $isAssociation ? $this->randomItem($this->associationNames) : null;
            $siret = $isAssociation ? $this->generateSiret() : null;
            $phone = $this->generatePhone();

            $stmt->execute([
                'id' => $id,
                'email' => $email,
                'login' => strtolower($firstName . $lastName . $i),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'client_type' => $isAssociation ? 'association' : 'personal',
                'company_name' => $companyName,
                'siret' => $siret
            ]);

            $this->users[] = [
                'id' => $id,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'client_type' => $isAssociation ? 'association' : 'personal',
                'company_name' => $companyName,
                'siret' => $siret
            ];

            // Créer une carte de fidélité pour les particuliers
            if (!$isAssociation) {
                $this->createLoyaltyCard($id, rand(0, 8));
            }
        }
    }

    /**
     * Crée une carte de fidélité
     */
    private function createLoyaltyCard(string $userId, int $sessionsCount): void
    {
        $id = UUID::generate();
        $stmt = $this->db->prepare("
            INSERT INTO loyalty_cards (id, user_id, sessions_count, is_completed, created_at)
            VALUES (:id, :user_id, :sessions_count, :is_completed, NOW())
        ");
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'sessions_count' => $sessionsCount,
            'is_completed' => $sessionsCount >= 9 ? 1 : 0
        ]);
    }

    /**
     * Crée des personnes et les assigne aux utilisateurs
     */
    private function createPersons(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->randomItem($this->firstNames);
            $lastName = $this->randomItem($this->lastNames);

            $id = UUID::generate();
            $birthDate = $this->randomBirthDate();

            $stmt = $this->db->prepare("
                INSERT INTO persons (id, first_name, last_name, birth_date, notes, created_at, updated_at)
                VALUES (:id, :first_name, :last_name, :birth_date, :notes, NOW(), NOW())
            ");
            $notes = rand(0, 1) ? "Notes pour {$firstName} {$lastName}" : null;
            $stmt->execute([
                'id' => $id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birthDate,
                'notes' => Encryption::encrypt($notes)
            ]);

            $this->persons[] = [
                'id' => $id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birthDate
            ];

            // Toujours assigner à l'admin
            $this->assignPersonToUser($id, $this->adminUser['id']);

            // Assigner aussi à un ou plusieurs autres utilisateurs
            $userCount = rand(1, 2);
            $assignedUsers = array_rand($this->users, min($userCount, count($this->users)));
            if (!is_array($assignedUsers)) {
                $assignedUsers = [$assignedUsers];
            }

            foreach ($assignedUsers as $userIndex) {
                $this->assignPersonToUser($id, $this->users[$userIndex]['id']);
            }
        }
    }

    /**
     * Assigne une personne à un utilisateur
     */
    private function assignPersonToUser(string $personId, string $userId): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_persons (user_id, person_id, created_at)
            VALUES (:user_id, :person_id, NOW())
        ");
        $stmt->execute([
            'user_id' => $userId,
            'person_id' => $personId
        ]);
    }

    /**
     * Génère les créneaux disponibles pour une journée
     * Utilise la même logique de "remplissage séquentiel" que AvailabilityService
     * mais en mémoire, sans requêtes BDD.
     *
     * Les créneaux sont calculés en remplissant la journée séquentiellement,
     * chaque créneau commençant après la fin du précédent (durée + pause).
     *
     * @return array Liste de créneaux avec ['time' => 'HH:MM', 'type' => 'regular'|'discovery']
     */
    private function generateDaySlots(): array
    {
        $slots = [];

        // Configuration des durées (identique à AvailabilityService)
        $discoveryDisplay = 75;  // Durée affichée séance découverte
        $discoveryPause = 15;    // Pause après découverte
        $regularDisplay = 45;    // Durée affichée séance classique
        $regularPause = 20;      // Pause après classique

        // Horaires (en minutes depuis minuit)
        $dayStart = 9 * 60;          // 09:00
        $lunchStart = 12 * 60 + 30;  // 12:30
        $lunchEnd = 13 * 60 + 30;    // 13:30
        $dayEnd = 18 * 60;           // 18:00

        $currentTime = $dayStart;

        while ($currentTime < $dayEnd) {
            // 20% de chance d'être une séance découverte
            $isDiscovery = rand(1, 5) === 1;
            $displayDuration = $isDiscovery ? $discoveryDisplay : $regularDisplay;
            $pauseDuration = $isDiscovery ? $discoveryPause : $regularPause;
            $blockedDuration = $displayDuration + $pauseDuration;

            // Calculer la fin de la séance (sans la pause)
            $sessionEnd = $currentTime + $displayDuration;

            // Vérifier si la séance dépasse la fin de journée
            if ($sessionEnd > $dayEnd) {
                break;
            }

            // Vérifier si la SÉANCE chevauche la pause déjeuner
            $overlapsLunch = ($currentTime < $lunchEnd && $sessionEnd > $lunchStart);

            if ($overlapsLunch) {
                // Sauter à la fin de la pause déjeuner
                $currentTime = $lunchEnd;
                continue;
            }

            // Ajouter le créneau
            $hours = intdiv($currentTime, 60);
            $minutes = $currentTime % 60;
            $slots[] = [
                'time' => sprintf('%02d:%02d', $hours, $minutes),
                'type' => $isDiscovery ? 'discovery' : 'regular'
            ];

            // Passer au créneau suivant (durée bloquée complète)
            $currentTime += $blockedDuration;

            // Si on tombe dans la pause déjeuner, sauter à la fin
            if ($currentTime >= $lunchStart && $currentTime < $lunchEnd) {
                $currentTime = $lunchEnd;
            }
        }

        return $slots;
    }

    /**
     * Crée des réservations passées avec leurs séances
     */
    private function createPastBookingsWithSessions(): int
    {
        $count = 0;
        $now = new \DateTime();
        // Définir le début de la journée actuelle pour exclure aujourd'hui
        $today = (clone $now)->setTime(0, 0, 0);
        $sixMonthsAgo = (clone $now)->modify('-6 months');

        // Générer des dates sur les 6 derniers mois
        $currentDate = clone $sixMonthsAgo;
        while ($currentDate < $today) {
            // Jours ouvrés seulement (lundi-samedi, pas jeudi ni dimanche)
            $dayOfWeek = (int) $currentDate->format('w');
            if ($dayOfWeek !== 0 && $dayOfWeek !== 4) { // Pas dimanche ni jeudi
                // Générer les créneaux disponibles pour cette journée
                $availableSlots = $this->generateDaySlots();
                shuffle($availableSlots);

                // Prendre 3 à 6 créneaux aléatoirement
                $slotsToUse = array_slice($availableSlots, 0, rand(3, min(6, count($availableSlots))));

                foreach ($slotsToUse as $slot) {
                    // 85% completed, 10% no_show, 5% cancelled
                    $rand = rand(1, 100);
                    if ($rand <= 85) {
                        $this->createPastBookingWithSession($currentDate, $slot);
                    } elseif ($rand <= 95) {
                        $this->createPastBookingNoShow($currentDate, $slot);
                    } else {
                        $this->createPastBookingCancelled($currentDate, $slot);
                    }
                    $count++;
                }
            }
            $currentDate->modify('+1 day');
        }

        return $count;
    }

    /**
     * Crée une réservation passée marquée comme absent (no_show)
     */
    private function createPastBookingNoShow(\DateTime $date, array $slot): void
    {
        $user = $this->randomItem($this->users);
        $person = $this->randomItem($this->persons);

        [$hour, $minute] = explode(':', $slot['time']);
        $sessionDate = clone $date;
        $sessionDate->setTime((int) $hour, (int) $minute);

        $isDiscovery = $slot['type'] === 'discovery';
        $durationDisplay = $isDiscovery ? 75 : 45;
        $durationBlocked = $isDiscovery ? 90 : 65;

        $bookingId = UUID::generate();
        $confirmationToken = bin2hex(random_bytes(32));
        $createdAt = (clone $sessionDate)->modify('-' . rand(1, 14) . ' days');

        $stmt = $this->db->prepare("
            INSERT INTO bookings (
                id, user_id, person_id, session_date, duration_type,
                duration_display_minutes, duration_blocked_minutes, status,
                client_email, client_phone, client_first_name, client_last_name,
                person_first_name, person_last_name, confirmation_token,
                confirmed_at, gdpr_consent, gdpr_consent_at, client_type,
                company_name, siret, created_at, updated_at
            ) VALUES (
                :id, :user_id, :person_id, :session_date, :duration_type,
                :duration_display, :duration_blocked, 'no_show',
                :client_email, :client_phone, :client_first_name, :client_last_name,
                :person_first_name, :person_last_name, :confirmation_token,
                :confirmed_at, 1, :gdpr_consent_at, :client_type,
                :company_name, :siret, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'id' => $bookingId,
            'user_id' => $user['id'],
            'person_id' => $person['id'],
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'duration_type' => $isDiscovery ? 'discovery' : 'regular',
            'duration_display' => $durationDisplay,
            'duration_blocked' => $durationBlocked,
            'client_email' => $user['email'],
            'client_phone' => $user['phone'],
            'client_first_name' => $user['first_name'],
            'client_last_name' => $user['last_name'],
            'person_first_name' => $person['first_name'],
            'person_last_name' => $person['last_name'],
            'confirmation_token' => $confirmationToken,
            'confirmed_at' => $createdAt->format('Y-m-d H:i:s'),
            'gdpr_consent_at' => $createdAt->format('Y-m-d H:i:s'),
            'client_type' => $user['client_type'],
            'company_name' => $user['company_name'],
            'siret' => $user['siret'],
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $sessionDate->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Crée une réservation passée annulée
     */
    private function createPastBookingCancelled(\DateTime $date, array $slot): void
    {
        $user = $this->randomItem($this->users);
        $person = $this->randomItem($this->persons);

        [$hour, $minute] = explode(':', $slot['time']);
        $sessionDate = clone $date;
        $sessionDate->setTime((int) $hour, (int) $minute);

        $isDiscovery = $slot['type'] === 'discovery';
        $durationDisplay = $isDiscovery ? 75 : 45;
        $durationBlocked = $isDiscovery ? 90 : 65;

        $bookingId = UUID::generate();
        $confirmationToken = bin2hex(random_bytes(32));
        $createdAt = (clone $sessionDate)->modify('-' . rand(1, 14) . ' days');

        $stmt = $this->db->prepare("
            INSERT INTO bookings (
                id, user_id, person_id, session_date, duration_type,
                duration_display_minutes, duration_blocked_minutes, status,
                client_email, client_phone, client_first_name, client_last_name,
                person_first_name, person_last_name, confirmation_token,
                confirmed_at, gdpr_consent, gdpr_consent_at, client_type,
                company_name, siret, created_at, updated_at
            ) VALUES (
                :id, :user_id, :person_id, :session_date, :duration_type,
                :duration_display, :duration_blocked, 'cancelled',
                :client_email, :client_phone, :client_first_name, :client_last_name,
                :person_first_name, :person_last_name, :confirmation_token,
                :confirmed_at, 1, :gdpr_consent_at, :client_type,
                :company_name, :siret, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'id' => $bookingId,
            'user_id' => $user['id'],
            'person_id' => $person['id'],
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'duration_type' => $isDiscovery ? 'discovery' : 'regular',
            'duration_display' => $durationDisplay,
            'duration_blocked' => $durationBlocked,
            'client_email' => $user['email'],
            'client_phone' => $user['phone'],
            'client_first_name' => $user['first_name'],
            'client_last_name' => $user['last_name'],
            'person_first_name' => $person['first_name'],
            'person_last_name' => $person['last_name'],
            'confirmation_token' => $confirmationToken,
            'confirmed_at' => $createdAt->format('Y-m-d H:i:s'),
            'gdpr_consent_at' => $createdAt->format('Y-m-d H:i:s'),
            'client_type' => $user['client_type'],
            'company_name' => $user['company_name'],
            'siret' => $user['siret'],
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $sessionDate->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Crée une réservation passée avec sa séance
     */
    private function createPastBookingWithSession(\DateTime $date, array $slot): void
    {
        $user = $this->randomItem($this->users);
        $person = $this->randomItem($this->persons);

        [$hour, $minute] = explode(':', $slot['time']);
        $sessionDate = clone $date;
        $sessionDate->setTime((int) $hour, (int) $minute);

        // Type de séance
        $isDiscovery = $slot['type'] === 'discovery';
        $durationDisplay = $isDiscovery ? 75 : 45;
        $durationBlocked = $isDiscovery ? 90 : 65;

        // Créer le booking (complété)
        $bookingId = UUID::generate();
        $confirmationToken = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare("
            INSERT INTO bookings (
                id, user_id, person_id, session_date, duration_type,
                duration_display_minutes, duration_blocked_minutes, status,
                client_email, client_phone, client_first_name, client_last_name,
                person_first_name, person_last_name, confirmation_token,
                confirmed_at, gdpr_consent, gdpr_consent_at, client_type,
                company_name, siret, created_at, updated_at
            ) VALUES (
                :id, :user_id, :person_id, :session_date, :duration_type,
                :duration_display, :duration_blocked, 'completed',
                :client_email, :client_phone, :client_first_name, :client_last_name,
                :person_first_name, :person_last_name, :confirmation_token,
                :confirmed_at, 1, :gdpr_consent_at, :client_type,
                :company_name, :siret, :created_at, :updated_at
            )
        ");

        $createdAt = (clone $sessionDate)->modify('-' . rand(1, 14) . ' days');

        $stmt->execute([
            'id' => $bookingId,
            'user_id' => $user['id'],
            'person_id' => $person['id'],
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'duration_type' => $isDiscovery ? 'discovery' : 'regular',
            'duration_display' => $durationDisplay,
            'duration_blocked' => $durationBlocked,
            'client_email' => $user['email'],
            'client_phone' => $user['phone'] ?? $this->generatePhone(),
            'client_first_name' => $user['first_name'],
            'client_last_name' => $user['last_name'],
            'person_first_name' => $person['first_name'],
            'person_last_name' => $person['last_name'],
            'confirmation_token' => $confirmationToken,
            'confirmed_at' => $createdAt->format('Y-m-d H:i:s'),
            'gdpr_consent_at' => $createdAt->format('Y-m-d H:i:s'),
            'client_type' => $user['client_type'],
            'company_name' => $user['company_name'],
            'siret' => $user['siret'],
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $sessionDate->format('Y-m-d H:i:s')
        ]);

        // Créer la séance
        $sessionId = UUID::generate();

        // Statuts de facturation aléatoires
        $isInvoiced = rand(0, 10) > 2; // 80% facturé
        $isPaid = $isInvoiced && rand(0, 10) > 3; // 70% des facturés sont payés
        $isFreeSession = rand(0, 20) === 0; // 5% gratuit

        $stmt = $this->db->prepare("
            INSERT INTO sessions (
                id, person_id, created_by, session_date, duration_minutes,
                sessions_per_month, behavior_start, proposal_origin, attitude_start,
                position, communication, session_end, behavior_end,
                wants_to_return, professional_notes, person_expression,
                is_invoiced, is_paid, is_free_session,
                booking_id, created_at, updated_at
            ) VALUES (
                :id, :person_id, :created_by, :session_date, :duration_minutes,
                :sessions_per_month, :behavior_start, :proposal_origin, :attitude_start,
                :position, :communication, :session_end, :behavior_end,
                :wants_to_return, :professional_notes, :person_expression,
                :is_invoiced, :is_paid, :is_free_session,
                :booking_id, :created_at, :updated_at
            )
        ");

        $communications = $this->randomSubset($this->communications, rand(1, 3));

        // Notes professionnelles aléatoires
        $professionalNotes = rand(0, 2) > 0 ? $this->randomItem([
            "Séance très positive, la personne était réceptive aux stimulations tactiles.",
            "Bonne progression par rapport à la dernière séance.",
            "A montré de l'intérêt pour les jeux de lumière.",
            "Séance calme, détente progressive observée.",
            "Communication non-verbale en amélioration.",
            "A apprécié particulièrement les textures douces.",
            null
        ]) : null;

        $personExpression = rand(0, 3) === 0 ? $this->randomItem([
            "Sourires fréquents pendant la séance.",
            "A verbalisé son bien-être.",
            "Demande de prolonger la séance.",
            "Détendu(e) en fin de séance.",
            null
        ]) : null;

        $stmt->execute([
            'id' => $sessionId,
            'person_id' => $person['id'],
            'created_by' => $this->adminUser['id'],
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'duration_minutes' => $durationDisplay,
            'sessions_per_month' => rand(1, 4),
            'behavior_start' => $this->randomItem($this->behaviorsStart),
            'proposal_origin' => $this->randomItem($this->proposalOrigins),
            'attitude_start' => $this->randomItem($this->attitudes),
            'position' => $this->randomItem($this->positions),
            'communication' => json_encode($communications),
            'session_end' => $this->randomItem($this->sessionEnds),
            'behavior_end' => $this->randomItem($this->behaviors),
            'wants_to_return' => rand(0, 10) > 1 ? 1 : (rand(0, 1) ? 0 : null),
            'professional_notes' => Encryption::encrypt($professionalNotes),
            'person_expression' => Encryption::encrypt($personExpression),
            'is_invoiced' => $isFreeSession ? 0 : ($isInvoiced ? 1 : 0),
            'is_paid' => $isFreeSession ? 0 : ($isPaid ? 1 : 0),
            'is_free_session' => $isFreeSession ? 1 : 0,
            'booking_id' => $bookingId,
            'created_at' => $sessionDate->format('Y-m-d H:i:s'),
            'updated_at' => $sessionDate->format('Y-m-d H:i:s')
        ]);

        // Mettre à jour le booking avec l'ID de session
        $stmt = $this->db->prepare("UPDATE bookings SET session_id = :session_id WHERE id = :id");
        $stmt->execute(['session_id' => $sessionId, 'id' => $bookingId]);
    }

    /**
     * Crée des séances pour aujourd'hui (confirmées avec booking)
     */
    private function createTodaySessions(): int
    {
        $count = 0;
        $today = new \DateTime();
        $dayOfWeek = (int) $today->format('w');

        // Si c'est dimanche ou jeudi, pas de séances
        if ($dayOfWeek === 0 || $dayOfWeek === 4) {
            return 0;
        }

        // Générer les créneaux disponibles pour aujourd'hui
        $availableSlots = $this->generateDaySlots();
        shuffle($availableSlots);

        // 3 à 5 séances aujourd'hui
        $slotsToUse = array_slice($availableSlots, 0, rand(3, min(5, count($availableSlots))));

        foreach ($slotsToUse as $slot) {
            $this->createTodayBookingWithSession($today, $slot);
            $count++;
        }

        return $count;
    }

    /**
     * Crée une réservation avec séance pour aujourd'hui
     */
    private function createTodayBookingWithSession(\DateTime $date, array $slot): void
    {
        $user = $this->randomItem($this->users);
        $person = $this->randomItem($this->persons);

        [$hour, $minute] = explode(':', $slot['time']);
        $sessionDate = clone $date;
        $sessionDate->setTime((int) $hour, (int) $minute);

        // Type de séance
        $isDiscovery = $slot['type'] === 'discovery';
        $durationDisplay = $isDiscovery ? 75 : 45;
        $durationBlocked = $isDiscovery ? 90 : 65;

        // Créer le booking (confirmé)
        $bookingId = UUID::generate();
        $confirmationToken = bin2hex(random_bytes(32));
        $createdAt = (clone $sessionDate)->modify('-' . rand(1, 7) . ' days');

        $stmt = $this->db->prepare("
            INSERT INTO bookings (
                id, user_id, person_id, session_date, duration_type,
                duration_display_minutes, duration_blocked_minutes, status,
                client_email, client_phone, client_first_name, client_last_name,
                person_first_name, person_last_name, confirmation_token,
                confirmed_at, gdpr_consent, gdpr_consent_at, client_type,
                company_name, siret, created_at, updated_at
            ) VALUES (
                :id, :user_id, :person_id, :session_date, :duration_type,
                :duration_display, :duration_blocked, 'confirmed',
                :client_email, :client_phone, :client_first_name, :client_last_name,
                :person_first_name, :person_last_name, :confirmation_token,
                :confirmed_at, 1, :gdpr_consent_at, :client_type,
                :company_name, :siret, :created_at, NOW()
            )
        ");

        $stmt->execute([
            'id' => $bookingId,
            'user_id' => $user['id'],
            'person_id' => $person['id'],
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'duration_type' => $isDiscovery ? 'discovery' : 'regular',
            'duration_display' => $durationDisplay,
            'duration_blocked' => $durationBlocked,
            'client_email' => $user['email'],
            'client_phone' => $user['phone'] ?? $this->generatePhone(),
            'client_first_name' => $user['first_name'],
            'client_last_name' => $user['last_name'],
            'person_first_name' => $person['first_name'],
            'person_last_name' => $person['last_name'],
            'confirmation_token' => $confirmationToken,
            'confirmed_at' => $createdAt->format('Y-m-d H:i:s'),
            'gdpr_consent_at' => $createdAt->format('Y-m-d H:i:s'),
            'client_type' => $user['client_type'],
            'company_name' => $user['company_name'],
            'siret' => $user['siret'],
            'created_at' => $createdAt->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Crée des réservations futures
     */
    private function createFutureBookings(): int
    {
        $count = 0;
        $now = new \DateTime();
        $endDate = (clone $now)->modify('+60 days');

        $currentDate = (clone $now)->modify('+1 day');
        while ($currentDate <= $endDate) {
            $dayOfWeek = (int) $currentDate->format('w');
            if ($dayOfWeek !== 0 && $dayOfWeek !== 4) { // Pas dimanche ni jeudi
                // Générer les créneaux disponibles pour cette journée
                $availableSlots = $this->generateDaySlots();
                shuffle($availableSlots);

                // Prendre 2 à 5 créneaux aléatoirement
                $slotsToUse = array_slice($availableSlots, 0, rand(2, min(5, count($availableSlots))));

                foreach ($slotsToUse as $slot) {
                    $this->createFutureBooking($currentDate, $slot);
                    $count++;
                }
            }
            $currentDate->modify('+1 day');
        }

        return $count;
    }

    /**
     * Crée une réservation future
     */
    private function createFutureBooking(\DateTime $date, array $slot): void
    {
        $user = $this->randomItem($this->users);
        $person = $this->randomItem($this->persons);

        [$hour, $minute] = explode(':', $slot['time']);
        $sessionDate = clone $date;
        $sessionDate->setTime((int) $hour, (int) $minute);

        // Type de séance
        $isDiscovery = $slot['type'] === 'discovery';
        $durationDisplay = $isDiscovery ? 75 : 45;
        $durationBlocked = $isDiscovery ? 90 : 65;

        // Statut aléatoire
        $statuses = ['pending', 'confirmed', 'confirmed', 'confirmed']; // Plus de confirmés
        $status = $this->randomItem($statuses);

        $bookingId = UUID::generate();
        $confirmationToken = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare("
            INSERT INTO bookings (
                id, user_id, person_id, session_date, duration_type,
                duration_display_minutes, duration_blocked_minutes, status,
                client_email, client_phone, client_first_name, client_last_name,
                person_first_name, person_last_name, confirmation_token,
                confirmed_at, gdpr_consent, gdpr_consent_at, client_type,
                company_name, siret, created_at, updated_at
            ) VALUES (
                :id, :user_id, :person_id, :session_date, :duration_type,
                :duration_display, :duration_blocked, :status,
                :client_email, :client_phone, :client_first_name, :client_last_name,
                :person_first_name, :person_last_name, :confirmation_token,
                :confirmed_at, 1, :gdpr_consent_at, :client_type,
                :company_name, :siret, NOW(), NOW()
            )
        ");

        $confirmedAt = $status === 'confirmed' ? (new \DateTime())->format('Y-m-d H:i:s') : null;

        $stmt->execute([
            'id' => $bookingId,
            'user_id' => $user['id'],
            'person_id' => $person['id'],
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'duration_type' => $isDiscovery ? 'discovery' : 'regular',
            'duration_display' => $durationDisplay,
            'duration_blocked' => $durationBlocked,
            'status' => $status,
            'client_email' => $user['email'],
            'client_phone' => $user['phone'] ?? $this->generatePhone(),
            'client_first_name' => $user['first_name'],
            'client_last_name' => $user['last_name'],
            'person_first_name' => $person['first_name'],
            'person_last_name' => $person['last_name'],
            'confirmation_token' => $confirmationToken,
            'confirmed_at' => $confirmedAt,
            'gdpr_consent_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'client_type' => $user['client_type'],
            'company_name' => $user['company_name'],
            'siret' => $user['siret']
        ]);
    }

    // === Helpers ===

    private function randomItem(array $array)
    {
        return $array[array_rand($array)];
    }

    private function randomSubset(array $array, int $count): array
    {
        shuffle($array);
        return array_slice($array, 0, min($count, count($array)));
    }

    private function generatePhone(): string
    {
        $prefixes = ['06', '07'];
        $prefix = $this->randomItem($prefixes);
        return $prefix . ' ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99);
    }

    private function generateSiret(): string
    {
        return str_pad((string) rand(10000000000000, 99999999999999), 14, '0', STR_PAD_LEFT);
    }

    private function randomBirthDate(): string
    {
        // Âge entre 5 et 80 ans
        $age = rand(5, 80);
        $date = new \DateTime();
        $date->modify("-{$age} years");
        $date->modify('-' . rand(0, 364) . ' days');
        return $date->format('Y-m-d');
    }
}

// Exécution si appelé directement
if (php_sapi_name() === 'cli' && realpath($argv[0]) === realpath(__FILE__)) {
    $clean = in_array('--clean', $argv);

    $factory = new Factory();
    $factory->run($clean);
}
