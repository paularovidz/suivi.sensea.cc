-- Migration 010: Renommer client_type 'professional' en 'association'
-- Date: 2026-01-21

-- ============================================
-- Table USERS
-- ============================================

-- Ajouter une nouvelle colonne avec le nouvel ENUM
ALTER TABLE users ADD COLUMN client_type_new ENUM('personal', 'association') DEFAULT 'personal' AFTER role;

-- Copier les données en convertissant 'professional' en 'association'
UPDATE users SET client_type_new = CASE
    WHEN client_type = 'professional' THEN 'association'
    WHEN client_type = 'personal' THEN 'personal'
    ELSE 'personal'
END;

-- Supprimer l'ancienne colonne et son index
ALTER TABLE users DROP INDEX IF EXISTS idx_client_type;
ALTER TABLE users DROP COLUMN client_type;

-- Renommer la nouvelle colonne
ALTER TABLE users CHANGE COLUMN client_type_new client_type ENUM('personal', 'association') DEFAULT 'personal';

-- Recréer l'index
ALTER TABLE users ADD INDEX idx_client_type (client_type);

-- ============================================
-- Table BOOKINGS
-- ============================================

-- Ajouter une nouvelle colonne avec le nouvel ENUM
ALTER TABLE bookings ADD COLUMN client_type_new ENUM('personal', 'association') DEFAULT 'personal' AFTER gdpr_consent_at;

-- Copier les données en convertissant 'professional' en 'association'
UPDATE bookings SET client_type_new = CASE
    WHEN client_type = 'professional' THEN 'association'
    WHEN client_type = 'personal' THEN 'personal'
    ELSE 'personal'
END;

-- Supprimer l'ancienne colonne
ALTER TABLE bookings DROP COLUMN client_type;

-- Renommer la nouvelle colonne
ALTER TABLE bookings CHANGE COLUMN client_type_new client_type ENUM('personal', 'association') DEFAULT 'personal';
