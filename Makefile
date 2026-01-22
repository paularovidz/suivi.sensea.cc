.PHONY: help install start stop restart logs shell-api shell-frontend db-shell clean rebuild migrate migrate-fresh seed seed-clean cron cron-sessions cron-reminders cron-calendar cron-cleanup test test-back test-front

# Default target
help:
	@echo "sensëa Snoezelen - Commandes disponibles:"
	@echo ""
	@echo "  make install    - Installation initiale (copie .env, build, start)"
	@echo "  make start      - Démarrer tous les conteneurs"
	@echo "  make stop       - Arrêter tous les conteneurs"
	@echo "  make restart    - Redémarrer tous les conteneurs"
	@echo "  make logs       - Voir les logs de tous les conteneurs"
	@echo "  make logs-api   - Voir les logs de l'API"
	@echo "  make logs-front - Voir les logs du frontend"
	@echo ""
	@echo "  make shell-api      - Shell dans le conteneur API"
	@echo "  make shell-frontend - Shell dans le conteneur frontend"
	@echo "  make db-shell       - Shell MySQL"
	@echo ""
	@echo "  make migrate       - Exécuter les migrations"
	@echo "  make migrate-fresh - Réinitialiser la BDD et relancer toutes les migrations"
	@echo "  make seed          - Ajouter des données de test"
	@echo "  make seed-clean    - Nettoyer et recréer les données de test"
	@echo ""
	@echo "  make cron          - Lancer toutes les tâches cron"
	@echo "  make cron-sessions - Créer les sessions depuis les réservations du jour"
	@echo "  make cron-reminders- Envoyer les rappels pour demain"
	@echo "  make cron-calendar - Rafraîchir le cache calendrier Google"
	@echo "  make cron-cleanup  - Nettoyer les réservations expirées"
	@echo ""
	@echo "  make test       - Lancer tous les tests (backend + frontend)"
	@echo "  make test-back  - Lancer les tests backend (PHPUnit)"
	@echo "  make test-front - Lancer les tests frontend (Vitest)"
	@echo ""
	@echo "  make clean      - Supprimer les conteneurs et volumes"
	@echo "  make rebuild    - Rebuild complet des images"
	@echo ""
	@echo "URLs:"
	@echo "  Frontend:    http://localhost:5173"
	@echo "  API:         http://localhost:8080"
	@echo "  MailHog:     http://localhost:8025"
	@echo "  phpMyAdmin:  http://localhost:8081"

# Installation initiale
install:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "✓ Fichier .env créé"; \
	fi
	@docker compose build
	@docker compose up -d
	@echo ""
	@echo "✓ Installation terminée!"
	@echo ""
	@echo "L'application est accessible sur:"
	@echo "  - Frontend:   http://localhost:5173"
	@echo "  - API:        http://localhost:8080"
	@echo "  - MailHog:    http://localhost:8025 (pour voir les emails)"
	@echo "  - phpMyAdmin: http://localhost:8081"
	@echo ""
	@echo "Compte admin par défaut: bonjour@sensea.cc"
	@echo "Demandez un magic link sur la page de login pour vous connecter."

# Démarrer
start:
	@docker compose up -d
	@echo "✓ Conteneurs démarrés"
	@echo "  Frontend: http://localhost:5173"
	@echo "  API:      http://localhost:8080"

# Arrêter
stop:
	@docker compose down
	@echo "✓ Conteneurs arrêtés"

# Redémarrer
restart: stop start

# Logs
logs:
	@docker compose logs -f

logs-api:
	@docker compose logs -f api

logs-front:
	@docker compose logs -f frontend

# Shells
shell-api:
	@docker compose exec api bash

shell-frontend:
	@docker compose exec frontend sh

db-shell:
	@docker compose exec db mysql -u snoezelen -psnoezelen_secret snoezelen_db

# Nettoyage
clean:
	@docker compose down -v --remove-orphans
	@echo "✓ Conteneurs et volumes supprimés"

# Rebuild complet
rebuild:
	@docker compose down
	@docker compose build --no-cache
	@docker compose up -d
	@echo "✓ Rebuild terminé"

# Base de données
migrate:
	@docker exec snoezelen_api php /var/www/html/migrations/migrate.php

migrate-fresh:
	@echo "⚠️  Réinitialisation de la base de données..."
	@docker exec snoezelen_api php /var/www/html/migrations/migrate.php --fresh
	@echo "✓ Base de données réinitialisée"

seed:
	@docker exec snoezelen_api php /var/www/html/database/seed.php
	@echo "✓ Données de test ajoutées"

seed-clean:
	@docker exec snoezelen_api php /var/www/html/database/seed.php --clean
	@echo "✓ Données de test recréées"

# Tâches cron
cron:
	@docker exec snoezelen_api php /var/www/html/cron/booking-tasks.php all

cron-sessions:
	@docker exec snoezelen_api php /var/www/html/cron/booking-tasks.php create-sessions

cron-reminders:
	@docker exec snoezelen_api php /var/www/html/cron/booking-tasks.php send-reminders

cron-calendar:
	@docker exec snoezelen_api php /var/www/html/cron/booking-tasks.php refresh-calendar

cron-cleanup:
	@docker exec snoezelen_api php /var/www/html/cron/booking-tasks.php cleanup-expired

# Tests
test: test-back test-front
	@echo "✓ Tous les tests passent"

test-back:
	@echo "🧪 Exécution des tests backend (PHPUnit)..."
	@cd api && vendor/bin/phpunit --testdox

test-front:
	@echo "🧪 Exécution des tests frontend (Vitest)..."
	@cd frontend && npm run test:run
