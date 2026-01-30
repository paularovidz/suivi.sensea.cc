-- Migration 027: Off Days (Jours Off)
-- Système de gestion des jours de fermeture

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table des jours de fermeture
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `off_days` (
    `id` CHAR(36) NOT NULL,
    `date` DATE NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL COMMENT 'Raison de la fermeture (optionnel)',
    `created_by` CHAR(36) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_off_days_date` (`date`),
    KEY `idx_off_days_created_by` (`created_by`),
    CONSTRAINT `fk_off_days_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Setting pour le token du flux calendrier
-- --------------------------------------------------------

INSERT INTO `settings` (`key`, `value`, `type`, `label`, `description`, `category`) VALUES
('calendar_feed_token', '', 'string', 'Token calendrier ICS', 'Token de sécurité pour le flux calendrier (laisser vide pour accès public)', 'calendar')
ON DUPLICATE KEY UPDATE `key` = `key`;

SET FOREIGN_KEY_CHECKS = 1;
