-- Migration 013: Systeme de fidelite
-- Date: 2026-01-21

-- Table des cartes de fidelite
CREATE TABLE IF NOT EXISTS loyalty_cards (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    sessions_count INT UNSIGNED DEFAULT 0 COMMENT 'Nombre de seances completees',
    is_completed TINYINT(1) DEFAULT 0 COMMENT 'Carte completee',
    completed_at DATETIME DEFAULT NULL COMMENT 'Date de completion',
    free_session_used_at DATETIME DEFAULT NULL COMMENT 'Date utilisation seance gratuite',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_card (user_id),
    CONSTRAINT fk_loyalty_cards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nouveaux settings pour la fidelite et les limites associations
INSERT INTO settings (`key`, `value`, `type`, `label`, `description`, `category`, `created_at`, `updated_at`) VALUES
('loyalty_sessions_required', '9', 'integer', 'Seances pour carte fidelite', 'Nombre de seances requises pour completer une carte de fidelite', 'loyalty', NOW(), NOW()),
('booking_max_per_ip_association', '20', 'integer', 'Max reservations par IP (Association)', 'Nombre maximum de reservations a venir par IP pour les associations', 'booking', NOW(), NOW()),
('booking_max_per_email_association', '20', 'integer', 'Max reservations par email (Association)', 'Nombre maximum de reservations a venir par email pour les associations', 'booking', NOW(), NOW())
ON DUPLICATE KEY UPDATE `key` = `key`;
