# sensëa - Suivi Snoezelen

Application de suivi des séances Snoezelen (thérapie sensorielle).

## Prérequis

- [Docker](https://www.docker.com/get-started) et Docker Compose
- Make (optionnel, mais recommandé)

## Installation rapide

```bash
# Cloner et lancer
make install
```

Ou sans Make :

```bash
cp .env.example .env
docker compose up -d
```

## URLs

| Service | URL | Description |
|---------|-----|-------------|
| Frontend | http://localhost:5173 | Application Vue.js |
| API | http://localhost:8080 | API PHP |
| MailHog | http://localhost:8025 | Interface emails (dev) |
| phpMyAdmin | http://localhost:8081 | Gestion BDD |

## Première connexion

1. Ouvrir http://localhost:5173
2. Entrer l'email : `bonjour@sensea.cc`
3. Cliquer sur "Recevoir le lien de connexion"
4. Ouvrir MailHog (http://localhost:8025) pour voir l'email
5. Cliquer sur le lien de connexion

## Commandes utiles

```bash
make start      # Démarrer
make stop       # Arrêter
make restart    # Redémarrer
make logs       # Voir les logs
make shell-api  # Shell dans l'API
make db-shell   # Shell MySQL
make clean      # Tout supprimer (volumes inclus)
make rebuild    # Rebuild des images
```

## Base de données

### Migrations

Exécuter les migrations après chaque démarrage ou mise à jour :

```bash
make migrate
# ou
docker exec snoezelen_api php /var/www/html/migrations/migrate.php
```

### Factory (données de test)

Générer des données de test réalistes (utilisateurs, personnes, réservations, séances) :

```bash
# Ajouter des données (conserve les existantes)
make seed

# Nettoyer et recréer toutes les données
make seed-clean
```

La factory crée :
- 8 utilisateurs (2 associations, 6 particuliers)
- 15 personnes (bénéficiaires)
- Réservations des 3 derniers mois avec séances (facturées/payées)
- Réservations des 35 prochains jours

## Structure du projet

```
├── api/                 # Backend PHP
│   ├── src/
│   │   ├── Controllers/ # Contrôleurs API REST
│   │   ├── Models/      # Modèles de données
│   │   ├── Middleware/  # Auth, CORS, Security
│   │   └── Services/    # JWT, Mail, Audit
│   ├── migrations/      # Scripts SQL
│   └── public/          # Point d'entrée
│
├── frontend/            # Frontend Vue.js 3
│   ├── src/
│   │   ├── components/  # Composants réutilisables
│   │   ├── views/       # Pages
│   │   ├── stores/      # Pinia stores
│   │   └── services/    # Appels API
│   └── public/
│
├── docker-compose.yml   # Configuration Docker
└── Makefile            # Commandes raccourcies
```

## Fonctionnalités

### Authentification
- Connexion par magic link (pas de mot de passe)
- Tokens JWT avec refresh automatique
- Rate limiting sur les demandes de connexion

### Gestion des personnes
- Création/modification/suppression (admin)
- Attribution aux professionnels
- Historique des séances

### Séances Snoezelen
- Formulaire complet avec :
  - Comportement début/fin
  - Position et communication
  - Propositions sensorielles avec appréciation
  - Notes privées chiffrées

### Propositions sensorielles
- 6 types : Tactile, Visuelle, Olfactive, Gustative, Auditive, Proprioceptive
- Recherche et création à la volée
- Propositions globales (admin) ou personnelles

### Sécurité
- Chiffrement AES-256 des données sensibles
- Audit de toutes les actions
- CORS strict, headers de sécurité

## Configuration production

1. Générer des secrets sécurisés :
```bash
openssl rand -hex 32  # Pour JWT_SECRET
openssl rand -hex 32  # Pour JWT_REFRESH_SECRET
openssl rand -hex 32  # Pour ENCRYPTION_KEY
```

2. Configurer le `.env` avec les vrais identifiants SMTP

3. Mettre `ENV=production` et `DEBUG=false`

## Licence

Propriétaire - sensëa
