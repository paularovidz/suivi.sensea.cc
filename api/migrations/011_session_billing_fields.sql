-- Migration 011: Ajouter les champs de facturation aux sessions
-- Date: 2026-01-21

-- Ajouter les champs de facturation
ALTER TABLE sessions
ADD COLUMN is_invoiced TINYINT(1) DEFAULT 0 COMMENT 'Seance facturee' AFTER next_session_proposals,
ADD COLUMN is_paid TINYINT(1) DEFAULT 0 COMMENT 'Seance payee' AFTER is_invoiced,
ADD COLUMN is_free_session TINYINT(1) DEFAULT 0 COMMENT 'Seance gratuite (fidelite)' AFTER is_paid;

-- Index pour filtrer les seances non facturees/non payees
ALTER TABLE sessions ADD INDEX idx_billing (is_invoiced, is_paid);
ALTER TABLE sessions ADD INDEX idx_free_session (is_free_session);
