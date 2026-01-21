-- Migration: Scheduling settings for booking system
-- Date: 2026-01-20

-- Insert scheduling settings
INSERT INTO `settings` (`key`, `value`, `type`, `label`, `description`, `category`) VALUES
-- Session durations
('session_discovery_display_minutes', '75', 'integer', 'Durée affichée séance découverte (min)', 'Durée affichée au client pour une séance découverte', 'scheduling'),
('session_discovery_pause_minutes', '15', 'integer', 'Pause après séance découverte (min)', 'Temps de pause entre les séances découverte', 'scheduling'),
('session_regular_display_minutes', '45', 'integer', 'Durée affichée séance classique (min)', 'Durée affichée au client pour une séance classique', 'scheduling'),
('session_regular_pause_minutes', '20', 'integer', 'Pause après séance classique (min)', 'Temps de pause entre les séances classiques', 'scheduling'),

-- Business hours (JSON format)
('business_hours', '{
    "0": null,
    "1": {"open": "09:00", "close": "18:00"},
    "2": {"open": "09:00", "close": "18:00"},
    "3": {"open": "09:00", "close": "18:00"},
    "4": null,
    "5": {"open": "09:00", "close": "18:00"},
    "6": {"open": "10:00", "close": "17:00"}
}', 'json', 'Horaires d''ouverture', 'Horaires par jour de la semaine (0=Dimanche, 6=Samedi). null = fermé', 'scheduling'),

-- Lunch break
('lunch_break_start', '12:30', 'string', 'Début pause déjeuner', 'Heure de début de la pause déjeuner (HH:MM)', 'scheduling'),
('lunch_break_end', '13:30', 'string', 'Fin pause déjeuner', 'Heure de fin de la pause déjeuner (HH:MM)', 'scheduling'),

-- First slot time
('first_slot_time', '09:00', 'string', 'Premier créneau', 'Heure du premier créneau de la journée (HH:MM)', 'scheduling'),

-- Booking advance limits
('booking_min_advance_hours', '24', 'integer', 'Délai minimum réservation (heures)', 'Nombre d''heures minimum avant un rendez-vous', 'scheduling'),
('booking_max_advance_days', '60', 'integer', 'Délai maximum réservation (jours)', 'Nombre de jours maximum à l''avance pour réserver', 'scheduling')

ON DUPLICATE KEY UPDATE `key` = `key`;
