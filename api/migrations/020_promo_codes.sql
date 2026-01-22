-- Migration 020: Système de codes promotionnels
-- Permet des remises manuelles (avec code) et automatiques

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table des codes promotionnels
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS promo_codes (
    id CHAR(36) NOT NULL,
    code VARCHAR(50) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    discount_type ENUM('percentage', 'fixed_amount', 'free_session') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    application_mode ENUM('manual', 'automatic') NOT NULL DEFAULT 'manual',
    target_user_id CHAR(36) DEFAULT NULL,
    target_client_type ENUM('personal', 'association') DEFAULT NULL,
    max_uses_total INT UNSIGNED DEFAULT NULL,
    max_uses_per_user INT UNSIGNED DEFAULT NULL,
    valid_from DATETIME DEFAULT NULL,
    valid_until DATETIME DEFAULT NULL,
    applies_to_discovery TINYINT(1) NOT NULL DEFAULT 1,
    applies_to_regular TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_promo_codes_code (code),
    KEY idx_promo_codes_active (is_active),
    KEY idx_promo_codes_mode (application_mode),
    KEY idx_promo_codes_target_user (target_user_id),
    KEY idx_promo_codes_dates (valid_from, valid_until),
    CONSTRAINT fk_promo_codes_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_promo_codes_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table des utilisations de codes promo
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS promo_code_usages (
    id CHAR(36) NOT NULL,
    promo_code_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    user_id CHAR(36) DEFAULT NULL,
    original_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    final_price DECIMAL(10,2) NOT NULL,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_promo_usages_code (promo_code_id),
    KEY idx_promo_usages_session (session_id),
    KEY idx_promo_usages_user (user_id),
    KEY idx_promo_usages_date (used_at),
    CONSTRAINT fk_promo_usages_code FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_promo_usages_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_promo_usages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ajouter les champs promo à la table sessions
-- --------------------------------------------------------

ALTER TABLE sessions ADD COLUMN promo_code_id CHAR(36) DEFAULT NULL AFTER is_free_session;
ALTER TABLE sessions ADD COLUMN original_price DECIMAL(10,2) DEFAULT NULL AFTER promo_code_id;
ALTER TABLE sessions ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT NULL AFTER original_price;

ALTER TABLE sessions ADD KEY idx_sessions_promo (promo_code_id);
ALTER TABLE sessions ADD CONSTRAINT fk_sessions_promo_code FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
