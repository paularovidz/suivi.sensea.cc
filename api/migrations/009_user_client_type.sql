-- Migration 009: Add client type (personal/professional) to users
-- Date: 2026-01-21

-- Add client_type column (personal = particulier, professional = professionnel)
ALTER TABLE users
ADD COLUMN client_type ENUM('personal', 'professional') DEFAULT 'personal' AFTER role;

-- Add company fields for professional clients
ALTER TABLE users
ADD COLUMN company_name VARCHAR(255) DEFAULT NULL AFTER client_type,
ADD COLUMN siret VARCHAR(14) DEFAULT NULL AFTER company_name;

-- Add index for client_type for filtering
ALTER TABLE users ADD INDEX idx_client_type (client_type);

-- Also add client_type to bookings for historical tracking
ALTER TABLE bookings
ADD COLUMN client_type ENUM('personal', 'professional') DEFAULT 'personal' AFTER gdpr_consent_at,
ADD COLUMN company_name VARCHAR(255) DEFAULT NULL AFTER client_type,
ADD COLUMN siret VARCHAR(14) DEFAULT NULL AFTER company_name;
