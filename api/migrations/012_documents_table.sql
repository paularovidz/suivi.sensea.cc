-- Migration 012: Table documents pour les pieces jointes polymorphiques
-- Date: 2026-01-21

CREATE TABLE IF NOT EXISTS documents (
    id CHAR(36) NOT NULL PRIMARY KEY,
    documentable_type ENUM('user', 'person') NOT NULL COMMENT 'Type entite liee',
    documentable_id CHAR(36) NOT NULL COMMENT 'ID entite liee',
    filename VARCHAR(255) NOT NULL COMMENT 'Nom fichier sur disque (UUID.ext)',
    original_name VARCHAR(255) NOT NULL COMMENT 'Nom fichier original',
    mime_type VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL COMMENT 'Taille en octets',
    uploaded_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_documentable (documentable_type, documentable_id),
    INDEX idx_uploaded_by (uploaded_by),
    CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
