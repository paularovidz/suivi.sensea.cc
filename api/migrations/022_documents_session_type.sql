-- Migration 022: Ajout du type 'session' pour les documents
-- Permet d'attacher des documents (factures, etc.) directement aux séances

SET NAMES utf8mb4;

-- Modifier l'enum documentable_type pour inclure 'session'
ALTER TABLE documents
MODIFY COLUMN documentable_type ENUM('user', 'person', 'session') NOT NULL COMMENT 'Type entite liee';
