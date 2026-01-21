-- Suivi Snoezelen - Seed Admin User
-- Migration 002
-- Note: Change the email and details for your admin user

-- Insert default admin user (you'll receive a magic link to login)
INSERT INTO `users` (`id`, `email`, `login`, `first_name`, `last_name`, `role`, `is_active`)
VALUES (
    UUID(),
    'bonjour@sensea.cc',
    'celine',
    'Céline',
    'Delcloy',
    'admin',
    1
);

-- Insert some default sensory proposals
INSERT INTO `sensory_proposals` (`id`, `title`, `type`, `description`, `created_by`, `is_global`) VALUES
(UUID(), 'Stimulation corps entier avec un foulard', 'tactile', 'Passage doux d''un foulard soyeux sur l''ensemble du corps', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Massage des mains', 'tactile', 'Massage doux des mains avec huile ou crème', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Balle à picots', 'tactile', 'Stimulation avec une balle à picots sur différentes parties du corps', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Couverture lestée', 'proprioceptive', 'Enveloppement dans une couverture lestée pour un effet contenant', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Hamac', 'proprioceptive', 'Balancement doux dans un hamac', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Fibres optiques', 'visual', 'Exploration visuelle de fibres optiques lumineuses', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Colonne à bulles', 'visual', 'Observation de la colonne à bulles colorées', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Projecteur d''étoiles', 'visual', 'Projection d''étoiles au plafond', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Musique relaxante', 'auditory', 'Écoute de musique douce et apaisante', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Sons de la nature', 'auditory', 'Écoute de sons naturels (eau, oiseaux, forêt)', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Instruments de musique doux', 'auditory', 'Manipulation d''instruments comme le bâton de pluie ou le carillon', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Diffuseur d''huiles essentielles lavande', 'olfactory', 'Diffusion d''huile essentielle de lavande pour la relaxation', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Sachets odorants', 'olfactory', 'Exploration de différents sachets parfumés', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Dégustation de textures', 'gustatory', 'Exploration de différentes textures alimentaires', (SELECT id FROM users WHERE login = 'celine'), 1),
(UUID(), 'Dégustation sucrée/salée', 'gustatory', 'Découverte de saveurs sucrées et salées', (SELECT id FROM users WHERE login = 'celine'), 1);
