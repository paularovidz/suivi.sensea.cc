-- Migration 021: Ajout du type de client "Friends & Family"
-- Les Friends & Family sont techniquement comme des particuliers mais avec un type distinct pour les remises

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Modifier l'enum client_type dans la table users
-- --------------------------------------------------------

ALTER TABLE users
MODIFY COLUMN client_type ENUM('personal', 'association', 'friends_family') DEFAULT 'personal';

-- --------------------------------------------------------
-- Modifier l'enum target_client_type dans promo_codes
-- --------------------------------------------------------

ALTER TABLE promo_codes
MODIFY COLUMN target_client_type ENUM('personal', 'association', 'friends_family') DEFAULT NULL;
