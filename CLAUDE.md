# Suivi Séances Snoezelen - Documentation Projet

## Concept

Application de dashboard pour le suivi de séances Snoezelen (thérapie sensorielle utilisée en milieu paramédical). Les données sont sensibles et nécessitent une sécurité maximale.

## Architecture

```
/
├── api/                    # Backend PHP
│   ├── config/            # Configuration (DB, mail, sécurité)
│   ├── src/
│   │   ├── Controllers/   # Contrôleurs API REST
│   │   ├── Models/        # Modèles de données
│   │   ├── Middleware/    # Authentification, CORS, Rate limiting
│   │   ├── Services/      # Logique métier (Mail, Auth)
│   │   └── Utils/         # Helpers, validation
│   ├── migrations/        # Scripts SQL
│   └── public/            # Point d'entrée (index.php)
│
└── frontend/              # Frontend VueJS 3
    ├── src/
    │   ├── components/    # Composants réutilisables
    │   ├── views/         # Pages
    │   ├── stores/        # Pinia stores
    │   ├── router/        # Vue Router
    │   ├── services/      # Appels API
    │   └── composables/   # Logique réutilisable
    └── public/
```

## Modèle de données

### Users (Comptes professionnels)
- `id` (UUID)
- `email` (unique, requis)
- `login` (unique, requis)
- `first_name` (requis)
- `last_name` (requis)
- `phone` (optionnel)
- `role` (enum: 'member', 'admin')
- `client_type` (enum: 'personal', 'association') - Particulier ou Association
- `company_name` (optionnel) - Nom de l'association si association
- `siret` (optionnel) - N° SIRET si association (14 chiffres)
- `is_active` (boolean)
- `created_at`, `updated_at`

### Persons (Personnes suivies)
- `id` (UUID)
- `first_name`, `last_name`
- `birth_date`
- `notes` (texte chiffré - notes générales)
- `created_at`, `updated_at`

### UserPersons (Liaison N-N)
- `user_id`, `person_id`
- `created_at`

### SensoryProposals (Propositions sensorielles)
- `id` (UUID)
- `title` (ex: "Stimulation corps entier avec un foulard")
- `type` (enum: 'tactile', 'visual', 'olfactory', 'gustatory', 'auditory', 'proprioceptive')
- `description` (optionnel)
- `created_by` (FK user_id)
- `is_global` (boolean - visible par tous si true)
- `created_at`, `updated_at`

### Sessions (Séances Snoezelen)
- `id` (UUID)
- `person_id` (FK)
- `created_by` (FK user_id)
- `session_date` (datetime)
- `duration_minutes` (int)
- `sessions_per_month` (int - nombre de séances/mois)

#### Début de séance
- `behavior_start` (enum: 'calm', 'agitated', 'defensive', 'anxious', 'passive')
- `proposal_origin` (enum: 'person', 'relative')
- `attitude_start` (enum: 'accepts', 'indifferent', 'refuses')

#### Pendant la séance
- `position` (enum: 'standing', 'lying', 'sitting', 'moving')
- `communication` (JSON array: ['body', 'verbal', 'vocal'])

#### Fin de séance
- `session_end` (enum: 'accepts', 'refuses', 'interrupts')
- `behavior_end` (enum: 'calm', 'agitated', 'tired', 'defensive', 'anxious', 'passive')
- `wants_to_return` (boolean nullable - true=oui, false=non, null=non renseigné)

#### Notes privées (chiffrées)
- `professional_notes` (texte chiffré - impressions du professionnel)
- `person_expression` (texte chiffré - impressions et expression de la personne)

#### Facturation
- `is_invoiced` (boolean) - Séance facturée
- `is_paid` (boolean) - Séance payée
- `is_free_session` (boolean) - Séance gratuite (fidélité)

- `created_at`, `updated_at`

### SessionProposals (Propositions utilisées dans une séance)
- `id` (UUID)
- `session_id` (FK)
- `sensory_proposal_id` (FK)
- `appreciation` (enum: 'negative', 'neutral', 'positive')
- `order` (int - ordre d'affichage)
- `created_at`

### Bookings (Réservations publiques)
- `id` (UUID)
- `user_id` (FK, nullable - lié après création compte)
- `person_id` (FK, nullable - lié après création personne)
- `session_id` (FK, nullable - lié après création session)
- `session_date` (datetime)
- `duration_type` (enum: 'discovery', 'regular')
- `duration_display_minutes` (int: 75 ou 45)
- `duration_blocked_minutes` (int: 90 ou 60)
- `status` (enum: 'pending', 'confirmed', 'cancelled', 'completed', 'no_show')
- `client_email`, `client_phone`, `client_first_name`, `client_last_name`
- `person_first_name`, `person_last_name`
- `confirmation_token` (hash sécurisé)
- `confirmed_at`, `gdpr_consent`, `gdpr_consent_at`
- `client_type` (enum: 'personal', 'association') - Type de client
- `company_name` (optionnel) - Nom de l'association si association
- `siret` (optionnel) - N° SIRET si association
- `reminder_sms_sent_at`, `reminder_email_sent_at`
- `ip_address`, `user_agent`
- `created_at`, `updated_at`

### Settings (Configuration back-office)
- `key` (VARCHAR PRIMARY KEY)
- `value` (TEXT)
- `type` (enum: 'string', 'integer', 'boolean', 'json')
- `label`, `description`, `category`
- `updated_by` (FK user_id)
- `created_at`, `updated_at`

### BookingCalendarCache (Cache iCal Google Calendar)
- `id` (UUID)
- `event_uid` (UNIQUE)
- `summary`, `start_datetime`, `end_datetime`, `is_all_day`
- `last_fetched_at`

### SmsLogs (Historique SMS)
- `id` (UUID)
- `booking_id` (FK)
- `phone_number`, `message_type`, `message_content`
- `provider`, `provider_message_id`, `provider_response`
- `status`, `sent_at`, `error_message`
- `created_at`

### MagicLinks
- `id` (UUID)
- `user_id` (FK)
- `token` (hash sécurisé)
- `expires_at`
- `used_at`
- `ip_address`
- `created_at`

### AuditLogs
- `id` (UUID)
- `user_id`
- `action`
- `entity_type`, `entity_id`
- `old_values`, `new_values` (JSON)
- `ip_address`
- `user_agent`
- `created_at`

### Documents (Fichiers joints - admin only)
- `id` (UUID)
- `documentable_type` (enum: 'user', 'person') - Type entité parente
- `documentable_id` (UUID) - ID de l'entité parente
- `filename` (string) - Nom fichier sur disque (UUID.ext)
- `original_name` (string) - Nom original du fichier
- `mime_type` (string) - Type MIME (image/jpeg, application/pdf, etc.)
- `size` (int) - Taille en octets
- `uploaded_by` (FK user_id)
- `created_at`

**Types autorisés** : image/jpeg, image/png, image/gif, image/webp, application/pdf
**Taille max** : 10 Mo
**Stockage** : `api/uploads/documents/YYYY/MM/filename.ext`

### LoyaltyCards (Cartes de fidélité - particuliers uniquement)
- `id` (UUID)
- `user_id` (FK, UNIQUE)
- `sessions_count` (int) - Nombre de séances comptabilisées
- `is_completed` (boolean) - Carte complète (atteint le seuil)
- `completed_at` (datetime) - Date de complétion
- `free_session_used_at` (datetime) - Date d'utilisation séance gratuite
- `created_at`

**Logique fidélité** :
- Seuil configurable (défaut: 9 séances via setting `loyalty_sessions_required`)
- Uniquement pour les clients de type 'personal' (pas les associations)
- Incrémente à chaque nouvelle séance (sauf si `is_free_session=true`)
- Quand carte pleine : alerte lors création séance, toggle "Séance gratuite" disponible
- Après utilisation séance gratuite : reset pour nouvelle carte

## Enums et valeurs

### Types de propositions sensorielles
| Valeur | Label FR |
|--------|----------|
| tactile | Tactile |
| visual | Visuelle |
| olfactory | Olfactive |
| gustatory | Gustative |
| auditory | Auditive |
| proprioceptive | Proprioceptive |

### Comportement (début/fin)
| Valeur | Label FR |
|--------|----------|
| calm | Calme |
| agitated | Agité |
| defensive | Défensif |
| anxious | Inquiet |
| passive | Passif (apathique) |
| tired | Fatigué (fin uniquement) |

### Origine de la proposition
| Valeur | Label FR |
|--------|----------|
| person | La personne elle-même |
| relative | Un proche |

### Attitude début
| Valeur | Label FR |
|--------|----------|
| accepts | Accepte la séance |
| indifferent | Indifférente |
| refuses | Refuse |

### Position pendant séance
| Valeur | Label FR |
|--------|----------|
| standing | Debout |
| lying | Allongée |
| sitting | Assise |
| moving | Se déplace |

### Communication
| Valeur | Label FR |
|--------|----------|
| body | Corporelle |
| verbal | Verbale |
| vocal | Vocale |

### Fin de séance
| Valeur | Label FR |
|--------|----------|
| accepts | Accepte |
| refuses | Refuse |
| interrupts | Interrompt la séance |

### Appréciation proposition
| Valeur | Label FR |
|--------|----------|
| negative | Apprécié négativement |
| neutral | Neutralité |
| positive | Apprécié positivement |

## Sécurité (CRITIQUE)

### Authentification
- Magic link uniquement (pas de mot de passe)
- Token JWT avec refresh token
- Expiration courte (15min access, 7j refresh)
- Rate limiting sur demande de magic link

### Protection API
- Toutes les routes protégées sauf `/auth/request-magic-link` et `/auth/verify`
- CORS strict (domaine frontend uniquement)
- Headers de sécurité (CSP, X-Frame-Options, etc.)
- Validation stricte des entrées
- Prepared statements (PDO)
- Chiffrement AES-256 des données sensibles (notes, observations)

### Sessions
- Tokens stockés en httpOnly cookies
- Rotation des tokens
- Invalidation sur changement IP significatif

## Rôles et Permissions

### Member
- Voir les personnes qui lui sont assignées
- CRUD séances pour ses personnes
- CRUD propositions sensorielles (les siennes + globales en lecture)
- Voir son profil

### Admin
- Tout ce que peut faire Member
- CRUD tous les utilisateurs
- Assigner personnes aux utilisateurs
- CRUD toutes les personnes
- CRUD toutes les propositions sensorielles
- Marquer propositions comme globales
- Voir audit logs
- Statistiques globales

## API Endpoints

### Auth
- `POST /auth/request-magic-link` - Demande magic link
- `GET /auth/verify/{token}` - Vérifie et connecte
- `POST /auth/refresh` - Rafraîchit le token
- `POST /auth/logout` - Déconnexion

### Users (Admin only sauf GET /me)
- `GET /users` - Liste
- `GET /users/{id}` - Détail
- `GET /users/me` - Profil connecté
- `POST /users` - Création
- `PUT /users/{id}` - Modification
- `DELETE /users/{id}` - Désactivation
- `GET /users/{id}/loyalty` - Carte de fidélité (admin ou user lui-même)

### Persons
- `GET /persons` - Liste (filtrée selon rôle)
- `GET /persons/{id}` - Détail
- `POST /persons` - Création (admin)
- `PUT /persons/{id}` - Modification
- `DELETE /persons/{id}` - Suppression (admin)

### Sensory Proposals
- `GET /sensory-proposals` - Liste (globales + personnelles)
- `GET /sensory-proposals/{id}` - Détail
- `POST /sensory-proposals` - Création
- `PUT /sensory-proposals/{id}` - Modification
- `DELETE /sensory-proposals/{id}` - Suppression
- `GET /sensory-proposals/search?q=...&type=...` - Recherche

### Sessions
- `GET /sessions` - Liste (filtrée)
- `GET /sessions/{id}` - Détail
- `GET /persons/{id}/sessions` - Sessions d'une personne
- `POST /sessions` - Création
- `PUT /sessions/{id}` - Modification
- `DELETE /sessions/{id}` - Suppression

### Stats (Admin)
- `GET /stats/dashboard` - Statistiques globales

### Bookings - Public (sans authentification)
- `GET /public/availability/schedule` - Horaires d'ouverture
- `GET /public/availability/dates?year=&month=&type=` - Dates disponibles
- `GET /public/availability/slots?date=&type=` - Créneaux d'une date
- `POST /public/bookings/check-email` - Vérifie si email existe
- `GET /public/bookings/persons?email=` - Personnes d'un email
- `POST /public/bookings` - Créer réservation
- `GET /public/bookings/confirm/{token}` - Confirmer réservation
- `POST /public/bookings/cancel/{token}` - Annuler réservation
- `GET /public/bookings/{token}` - Détails réservation
- `GET /public/bookings/{token}/ics` - Télécharger ICS

### Bookings - Admin (authentification requise)
- `GET /bookings` - Liste réservations
- `GET /bookings/stats` - Statistiques
- `GET /bookings/pending-sessions` - RDV sans session
- `GET /bookings/{id}` - Détail
- `PUT /bookings/{id}` - Modifier
- `PATCH /bookings/{id}/status` - Changer statut
- `DELETE /bookings/{id}` - Supprimer
- `POST /bookings/{id}/reminder` - Envoyer rappel
- `POST /bookings/{id}/create-session` - Créer session depuis booking

### Settings (Admin)
- `GET /settings` - Tous les paramètres groupés
- `GET /settings/category/{category}` - Paramètres d'une catégorie
- `PUT /settings` - Modifier paramètres
- `GET /settings/sms-credits` - Crédits SMS OVH restants

### Documents (Admin only)
- `GET /documents/{type}/{id}` - Liste documents (type = 'user' ou 'person')
- `POST /documents/{type}/{id}` - Upload document (multipart/form-data)
- `GET /documents/{id}/download` - Télécharger document
- `GET /documents/{id}/view` - Visualiser document (inline)
- `DELETE /documents/{id}` - Supprimer document

## Stack Technique

### Backend
- PHP 8.2+
- PDO MySQL/MariaDB
- Composer pour dépendances
- PHPMailer pour emails
- firebase/php-jwt pour JWT

### Frontend
- Vue 3 + Composition API
- Vite
- Pinia (store)
- Vue Router
- Tailwind CSS
- Axios

## Variables d'environnement

### API (.env)
```
DB_HOST=localhost
DB_NAME=snoezelen_db
DB_USER=
DB_PASS=
DB_CHARSET=utf8mb4

JWT_SECRET=
JWT_REFRESH_SECRET=
ENCRYPTION_KEY=

MAIL_HOST=
MAIL_PORT=587
MAIL_USER=
MAIL_PASS=
MAIL_FROM=noreply@sensea.cc

APP_URL=https://suivi.sensea.cc
FRONTEND_URL=https://suivi.sensea.cc

ENV=production
DEBUG=false
APP_TIMEZONE=Europe/Paris

# Google Calendar (iCal)
GOOGLE_ICAL_URL=https://calendar.google.com/calendar/ical/xxx/basic.ics

# OVH SMS (optionnel)
OVH_SMS_APP_KEY=
OVH_SMS_APP_SECRET=
OVH_SMS_CONSUMER_KEY=
OVH_SMS_SERVICE_NAME=

# Note: BOOKING_ADMIN_EMAILS est obsolète
# Les notifications admin sont envoyées à tous les users avec role='admin' en BDD
```

### Frontend (.env)
```
VITE_API_URL=https://suivi.sensea.cc/api
```

## Commandes utiles

```bash
# Backend
cd api && composer install
php migrations/migrate.php

# Frontend
cd frontend && npm install
npm run dev      # Développement
npm run build    # Production
```

## Docker

### Démarrage
```bash
docker compose up -d
```

### Exécuter les migrations
**IMPORTANT** : Après chaque `docker compose up`, exécuter les migrations pour appliquer les nouveaux scripts SQL :
```bash
docker exec snoezelen_api php /var/www/html/migrations/migrate.php
```

### Factory de données de test
La factory permet de générer des données de test réalistes :
- 8 utilisateurs (2 associations, 6 particuliers avec cartes fidélité)
- 15 personnes (bénéficiaires) assignées aux utilisateurs
- Réservations passées (3 derniers mois) avec séances liées (facturées/payées variables)
- Réservations futures (35 prochains jours)

```bash
# Ajouter des données de test (conserve les existantes)
docker exec snoezelen_api php /var/www/html/database/seed.php

# Nettoyer et recréer toutes les données de test
docker exec snoezelen_api php /var/www/html/database/seed.php --clean
```

**Fichiers** :
- `api/database/Factory.php` - Classe Factory avec toute la logique de génération
- `api/database/seed.php` - Script d'exécution

### Autres commandes utiles
```bash
# Logs
docker compose logs -f api

# Accès shell container API
docker exec -it snoezelen_api bash

# Rebuild après modification Dockerfile
docker compose up -d --build
```

## Système de Réservation (Booking)

### Horaires d'ouverture
| Jour | Horaires |
|------|----------|
| Lundi | 9h - 18h |
| Mardi | 9h - 18h |
| Mercredi | 9h - 18h |
| Jeudi | **FERMÉ** |
| Vendredi | 9h - 18h |
| Samedi | 10h - 17h |
| Dimanche | **FERMÉ** |

Pause déjeuner: 12h30 - 13h30

### Types de séances
| Type | Affiché | Pause après | Total bloqué |
|------|---------|-------------|--------------|
| discovery | 1h15 (75 min) | 15 min | 1h30 (90 min) |
| regular | 45 min | 20 min | 1h05 (65 min) |

**Créneaux dynamiques** : Les créneaux sont générés automatiquement à partir de l'heure du premier créneau (configurable). Chaque créneau suivant commence après la fin du créneau précédent + pause. La pause déjeuner est respectée.

Exemple pour séances classiques (45min + 20min pause) : 9h00, 10h05, 11h10, 13h30, 14h35, 15h40, 16h45

### Flux de réservation
1. Type client (nouveau / déjà venu)
2. Sélection personne (bénéficiaire)
3. Date/heure (calendrier + créneaux)
4. Coordonnées + RGPD
5. Confirmation

### Double vérification disponibilité
- Calendrier Google iCal (cache 5min configurable)
- Réservations en BDD

### Paramètres configurables (Settings)

#### Catégorie: booking
| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| booking_email_confirmation_required | boolean | false | Si activé, email de confirmation requis |
| booking_max_per_ip | integer | 4 | Max RDV à venir par IP (particuliers) |
| booking_max_per_email | integer | 4 | Max RDV à venir par email (particuliers) |
| booking_max_per_ip_association | integer | 20 | Max RDV à venir par IP (associations) |
| booking_max_per_email_association | integer | 20 | Max RDV à venir par email (associations) |

#### Catégorie: loyalty
| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| loyalty_sessions_required | integer | 9 | Nombre de séances pour carte complète |

#### Catégorie: scheduling (Horaires et durées)
| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| session_discovery_display_minutes | integer | 75 | Durée affichée séance découverte |
| session_discovery_pause_minutes | integer | 15 | Pause après séance découverte |
| session_regular_display_minutes | integer | 45 | Durée affichée séance classique |
| session_regular_pause_minutes | integer | 20 | Pause après séance classique |
| business_hours | json | {...} | Horaires d'ouverture par jour (0=Dim, 6=Sam) |
| lunch_break_start | string | 12:30 | Début pause déjeuner (HH:MM) |
| lunch_break_end | string | 13:30 | Fin pause déjeuner (HH:MM) |
| first_slot_time | string | 09:00 | Heure du premier créneau (HH:MM) |
| booking_min_advance_hours | integer | 24 | Délai minimum avant RDV (heures) |
| booking_max_advance_days | integer | 60 | Délai maximum pour réserver (jours) |

#### Catégorie: calendar
| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| calendar_cache_ttl | integer | 300 | Cache calendrier en secondes |

#### Catégorie: security
| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| captcha_enabled | boolean | false | Activer captcha invisible |
| captcha_provider | string | hcaptcha | hcaptcha ou recaptcha |
| captcha_site_key | string | | Clé publique captcha |
| captcha_secret_key | string | | Clé secrète captcha |

#### Catégorie: sms
| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| sms_reminders_enabled | boolean | true | Envoi rappels SMS |
| sms_sender_name | string | sensëa | Nom expéditeur SMS |

### Cron jobs
```bash
# Toutes les tâches (toutes les 15 min)
*/15 * * * * php /path/to/api/cron/booking-tasks.php

# Ou tâches spécifiques:
0 6 * * * php .../booking-tasks.php create-sessions  # Sessions du jour
0 18 * * * php .../booking-tasks.php send-reminders  # Rappels demain
*/5 * * * * php .../booking-tasks.php refresh-calendar  # Cache iCal
```

## Notes importantes

1. **Pas d'inscription publique** - Seuls les admins créent les comptes
2. **Données chiffrées** - Notes privées chiffrées en base (professional_notes, person_expression)
3. **Audit complet** - Toutes les actions sont loguées
4. **RGPD** - Prévoir export et suppression données
5. **Snoezelen** - Thérapie sensorielle, environnements contrôlés (lumière, son, odeurs, textures)
6. **Propositions sensorielles** - Peuvent être créées à la volée lors d'une séance, recherche autocomplete
7. **Réservation publique** - Wizard 5 étapes, validation email optionnelle, rate limiting par IP/email
8. **Embed booking** - `/booking/embed` intégrable en iframe avec paramètres de personnalisation (primaryColor, hideTitle, bgColor)
9. **Types de clients** - Particuliers (personal) ou Associations (association). Les associations ont des limites de réservation plus élevées (20 vs 4)
10. **Système de fidélité** - Carte de fidélité pour particuliers uniquement. Après 9 séances (configurable), séance gratuite offerte. Visible dans l'espace membre et dans la page admin utilisateur
11. **Documents** - Upload images/PDF pour utilisateurs et personnes (admin only). Stockés dans `api/uploads/documents/`

## Services Backend

### CalendarService
Parse les fichiers iCal Google Calendar, gère le cache avec TTL configurable.

### AvailabilityService
Calcule les créneaux disponibles selon horaires d'ouverture, calendrier Google et réservations BDD.

### BookingMailService
Emails transactionnels: confirmation client, notification admin, rappel, annulation.
**Note** : Les notifications admin sont envoyées automatiquement à tous les utilisateurs avec `role='admin'` en base de données (pas de configuration env).

### ICSGeneratorService
Génère fichiers .ics pour calendrier.

### SMSService
Intégration OVH SMS avec signature API. Envoi rappels, confirmations, annulations.

### CaptchaService
Vérification hCaptcha ou reCAPTCHA invisible.
