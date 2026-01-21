-- Suivi Snoezelen - Booking System
-- Migration 006

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: bookings (Réservations de séances)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) DEFAULT NULL COMMENT 'Compte utilisateur créé (inactif pour nouveaux clients)',
    `person_id` CHAR(36) DEFAULT NULL COMMENT 'Personne bénéficiaire de la séance',
    `session_id` CHAR(36) DEFAULT NULL COMMENT 'Session créée automatiquement le jour du RDV',

    -- Détails du rendez-vous
    `session_date` DATETIME NOT NULL,
    `duration_type` ENUM('discovery', 'regular') NOT NULL DEFAULT 'regular',
    `duration_display_minutes` INT UNSIGNED NOT NULL COMMENT '75 pour découverte, 45 pour normale',
    `duration_blocked_minutes` INT UNSIGNED NOT NULL COMMENT '90 pour découverte, 60 pour normale',

    -- Statut de la réservation
    `status` ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'pending',

    -- Informations client (celui qui réserve)
    `client_email` VARCHAR(255) NOT NULL,
    `client_phone` VARCHAR(20) DEFAULT NULL,
    `client_first_name` VARCHAR(100) NOT NULL,
    `client_last_name` VARCHAR(100) NOT NULL,

    -- Informations personne bénéficiaire (peut être différente du client)
    `person_first_name` VARCHAR(100) NOT NULL,
    `person_last_name` VARCHAR(100) NOT NULL,

    -- Confirmation
    `confirmation_token` VARCHAR(255) NOT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,

    -- Rappels
    `reminder_sms_sent_at` DATETIME DEFAULT NULL,
    `reminder_email_sent_at` DATETIME DEFAULT NULL,

    -- RGPD
    `gdpr_consent` TINYINT(1) NOT NULL DEFAULT 0,
    `gdpr_consent_at` DATETIME DEFAULT NULL,

    -- Notes admin
    `admin_notes` TEXT DEFAULT NULL,

    -- Métadonnées
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_bookings_token` (`confirmation_token`),
    KEY `idx_bookings_user` (`user_id`),
    KEY `idx_bookings_person` (`person_id`),
    KEY `idx_bookings_session` (`session_id`),
    KEY `idx_bookings_date` (`session_date`),
    KEY `idx_bookings_status` (`status`),
    KEY `idx_bookings_email` (`client_email`),
    KEY `idx_bookings_pending_date` (`status`, `session_date`),
    CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bookings_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bookings_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: booking_calendar_cache (Cache des événements iCal Google)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `booking_calendar_cache` (
    `id` CHAR(36) NOT NULL,
    `event_uid` VARCHAR(255) NOT NULL COMMENT 'UID unique de l événement iCal',
    `summary` VARCHAR(500) DEFAULT NULL COMMENT 'Titre de l événement',
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NOT NULL,
    `is_all_day` TINYINT(1) NOT NULL DEFAULT 0,
    `last_fetched_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_calendar_cache_uid` (`event_uid`),
    KEY `idx_calendar_cache_dates` (`start_datetime`, `end_datetime`),
    KEY `idx_calendar_cache_fetched` (`last_fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sms_logs (Historique des SMS envoyés)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sms_logs` (
    `id` CHAR(36) NOT NULL,
    `booking_id` CHAR(36) DEFAULT NULL,
    `phone_number` VARCHAR(20) NOT NULL,
    `message_type` ENUM('reminder', 'confirmation', 'cancellation', 'custom') NOT NULL,
    `message_content` TEXT NOT NULL,
    `provider` VARCHAR(50) DEFAULT 'ovh' COMMENT 'Fournisseur SMS utilisé',
    `provider_message_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID du message côté fournisseur',
    `provider_response` JSON DEFAULT NULL COMMENT 'Réponse complète du fournisseur',
    `status` ENUM('pending', 'sent', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
    `sent_at` DATETIME DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `cost_credits` DECIMAL(10,4) DEFAULT NULL COMMENT 'Coût en crédits SMS',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_sms_logs_booking` (`booking_id`),
    KEY `idx_sms_logs_status` (`status`),
    KEY `idx_sms_logs_type` (`message_type`),
    KEY `idx_sms_logs_created` (`created_at`),
    CONSTRAINT `fk_sms_logs_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Add booking_id reference to sessions table
-- --------------------------------------------------------
ALTER TABLE `sessions`
    ADD COLUMN `booking_id` CHAR(36) DEFAULT NULL COMMENT 'Réservation à l origine de cette séance' AFTER `id`,
    ADD KEY `idx_sessions_booking` (`booking_id`),
    ADD CONSTRAINT `fk_sessions_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
