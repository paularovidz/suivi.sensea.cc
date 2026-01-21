-- Migration: Settings table for back-office configuration
-- Date: 2026-01-20

-- Table des paramètres de configuration
CREATE TABLE IF NOT EXISTS `settings` (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    `type` ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
    `label` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `updated_by` CHAR(36) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (`category`),
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `settings` (`key`, `value`, `type`, `label`, `description`, `category`) VALUES
-- Booking settings
('booking_email_confirmation_required', '0', 'boolean', 'Validation email requise', 'Si activé, les réservations nécessitent une confirmation par email avant d\'être validées', 'booking'),
('booking_max_per_ip', '4', 'integer', 'Max réservations par IP', 'Nombre maximum de réservations à venir autorisées par adresse IP', 'booking'),
('booking_max_per_email', '4', 'integer', 'Max réservations par email', 'Nombre maximum de réservations à venir autorisées par adresse email', 'booking'),

-- Calendar settings
('calendar_cache_ttl', '300', 'integer', 'Durée cache calendrier (secondes)', 'Durée de mise en cache des événements Google Calendar en secondes', 'calendar'),

-- Captcha settings
('captcha_enabled', '0', 'boolean', 'Captcha activé', 'Activer le captcha invisible pour les réservations', 'security'),
('captcha_provider', 'hcaptcha', 'string', 'Fournisseur captcha', 'Service de captcha utilisé (hcaptcha ou recaptcha)', 'security'),
('captcha_site_key', '', 'string', 'Clé site captcha', 'Clé publique du service captcha', 'security'),
('captcha_secret_key', '', 'string', 'Clé secrète captcha', 'Clé secrète du service captcha', 'security'),

-- SMS settings
('sms_reminders_enabled', '1', 'boolean', 'Rappels SMS activés', 'Envoyer des rappels SMS la veille des rendez-vous', 'sms'),
('sms_sender_name', 'sensëa', 'string', 'Nom expéditeur SMS', 'Nom affiché comme expéditeur des SMS', 'sms')

ON DUPLICATE KEY UPDATE `key` = `key`;
