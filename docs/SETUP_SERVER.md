# Setup Serveur - VPS Hetzner

Guide de déploiement pour le projet Snoezelen (API + Dashboard + Site vitrine).

## Prérequis

- VPS Hetzner CX23 (4 Go RAM, 2 vCPU, 40 Go SSD)
- Ubuntu 24.04 ou Debian 12
- Domaines pointant vers le VPS :
  - `sensea.cc` → Site vitrine
  - `suivi.sensea.cc` → Dashboard + API

---

## 1. Première connexion SSH

```bash
ssh root@<IP_DU_VPS>
```

## 2. Mise à jour du système

```bash
apt update && apt upgrade -y
apt install -y curl git wget nano ufw fail2ban htop
```

## 3. Créer un utilisateur non-root

```bash
# Créer l'utilisateur
adduser deploy

# Ajouter aux sudoers
usermod -aG sudo deploy

# Copier la clé SSH
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

## 4. Sécuriser SSH

```bash
nano /etc/ssh/sshd_config
```

Modifier ces lignes :
```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```

Redémarrer SSH :
```bash
systemctl restart sshd
```

**IMPORTANT** : Avant de fermer la session, teste la connexion avec le nouvel utilisateur dans un autre terminal :
```bash
ssh deploy@<IP_DU_VPS>
```

## 5. Configurer le firewall (UFW)

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw enable
ufw status
```

## 6. Installer Docker

```bash
# Installer Docker
curl -fsSL https://get.docker.com | sh

# Ajouter l'utilisateur deploy au groupe docker
usermod -aG docker deploy

# Activer Docker au démarrage
systemctl enable docker
systemctl start docker

# Vérifier
docker --version
docker compose version
```

**Déconnecte-toi et reconnecte-toi** pour que le groupe docker soit pris en compte.

## 7. Installer Nginx (reverse proxy sur l'hôte)

```bash
apt install -y nginx
systemctl enable nginx
```

## 8. Installer Certbot (Let's Encrypt)

```bash
apt install -y certbot python3-certbot-nginx
```

## 9. Créer la structure du projet

```bash
# En tant que deploy
su - deploy

# Créer les dossiers
mkdir -p ~/apps/snoezelen
mkdir -p ~/apps/snoezelen/api/uploads
cd ~/apps/snoezelen
```

## 10. Configuration Nginx

### Site vitrine (sensea.cc)

```bash
sudo nano /etc/nginx/sites-available/sensea.cc
```

```nginx
server {
    listen 80;
    server_name sensea.cc www.sensea.cc;

    root /home/deploy/apps/snoezelen/www/dist;
    index index.html;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    # Cache static assets
    location /_astro/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location /images/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ $uri.html =404;
    }

    # Trailing slash
    rewrite ^([^.]*[^/])$ $1/ permanent;
}
```

### Dashboard + API (suivi.sensea.cc)

```bash
sudo nano /etc/nginx/sites-available/suivi.sensea.cc
```

```nginx
server {
    listen 80;
    server_name suivi.sensea.cc;

    # Dashboard (fichiers statiques)
    root /home/deploy/apps/snoezelen/frontend/dist;
    index index.html;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    # API - proxy vers container Docker
    location /api/ {
        proxy_pass http://127.0.0.1:8080/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Uploads
        client_max_body_size 15M;
    }

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

### Activer les sites

```bash
sudo ln -s /etc/nginx/sites-available/sensea.cc /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/suivi.sensea.cc /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

## 11. SSL avec Let's Encrypt

```bash
sudo certbot --nginx -d sensea.cc -d www.sensea.cc
sudo certbot --nginx -d suivi.sensea.cc
```

Certbot modifie automatiquement les configs Nginx pour HTTPS.

## 12. Docker Compose Production

Créer le fichier `/home/deploy/apps/snoezelen/docker-compose.prod.yml` :

```yaml
services:
  db:
    image: mariadb:10.11
    container_name: snoezelen_db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASS}
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - snoezelen_network
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 5

  api:
    image: ghcr.io/<GITHUB_USER>/snoezelen-api:latest
    container_name: snoezelen_api
    restart: unless-stopped
    env_file:
      - .env
    volumes:
      - ./api/uploads:/var/www/html/uploads
    ports:
      - "127.0.0.1:8080:80"
    depends_on:
      db:
        condition: service_healthy
    networks:
      - snoezelen_network

volumes:
  db_data:

networks:
  snoezelen_network:
    driver: bridge
```

## 13. Fichier .env production

Créer `/home/deploy/apps/snoezelen/.env` :

```bash
# Database
DB_ROOT_PASSWORD=<STRONG_PASSWORD>
DB_NAME=snoezelen_db
DB_USER=snoezelen
DB_PASS=<STRONG_PASSWORD>
DB_HOST=db
DB_CHARSET=utf8mb4

# JWT (générer avec: openssl rand -hex 32)
JWT_SECRET=<RANDOM_64_CHARS>
JWT_REFRESH_SECRET=<RANDOM_64_CHARS>

# Encryption (générer avec: openssl rand -hex 32)
ENCRYPTION_KEY=<RANDOM_64_CHARS>

# Mail
MAIL_HOST=<SMTP_HOST>
MAIL_PORT=587
MAIL_USER=<SMTP_USER>
MAIL_PASS=<SMTP_PASS>
MAIL_FROM=noreply@sensea.cc
MAIL_FROM_NAME=sensëa Snoezelen

# URLs
APP_URL=https://suivi.sensea.cc/api
FRONTEND_URL=https://suivi.sensea.cc

# Environment
ENV=production
DEBUG=false
APP_TIMEZONE=Europe/Paris

# Google Calendar
GOOGLE_ICAL_URL=<ICAL_URL>

# OVH SMS (optionnel)
OVH_SMS_APP_KEY=
OVH_SMS_APP_SECRET=
OVH_SMS_CONSUMER_KEY=
OVH_SMS_SERVICE_NAME=
```

## 14. Générer les secrets

```bash
# JWT Secret
openssl rand -hex 32

# JWT Refresh Secret
openssl rand -hex 32

# Encryption Key
openssl rand -hex 32

# DB Passwords
openssl rand -base64 24
```

## 15. Premier déploiement manuel (test)

```bash
cd ~/apps/snoezelen

# Cloner le repo (ou copier les fichiers)
git clone https://github.com/<USER>/<REPO>.git .

# Copier le .env
# (déjà créé à l'étape 13)

# Build et démarrer
docker compose -f docker-compose.prod.yml up -d

# Exécuter les migrations
docker exec snoezelen_api php /var/www/html/migrations/migrate.php

# Vérifier les logs
docker compose -f docker-compose.prod.yml logs -f
```

## 16. Cron jobs

```bash
crontab -e
```

Ajouter :
```cron
# Tâches booking (rappels, nettoyage, refresh calendrier)
*/15 * * * * docker exec snoezelen_api php /var/www/html/cron/booking-tasks.php >> /home/deploy/logs/cron.log 2>&1

# Backup BDD quotidien
0 3 * * * docker exec snoezelen_db mysqldump -u root -p${DB_ROOT_PASSWORD} snoezelen_db | gzip > /home/deploy/backups/db-$(date +\%Y\%m\%d).sql.gz
```

Créer les dossiers de logs/backups :
```bash
mkdir -p ~/logs ~/backups
```

## 17. Commandes utiles

```bash
# Voir les logs
docker compose -f docker-compose.prod.yml logs -f api

# Redémarrer un service
docker compose -f docker-compose.prod.yml restart api

# Mettre à jour l'image et redéployer
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d

# Accéder au container
docker exec -it snoezelen_api bash
docker exec -it snoezelen_db mysql -u root -p
```

---

## Checklist

- [ ] VPS créé avec IPv4
- [ ] DNS configuré (sensea.cc, suivi.sensea.cc)
- [ ] SSH sécurisé (clé, pas de root, pas de password)
- [ ] Firewall UFW actif
- [ ] Docker installé
- [ ] Nginx configuré
- [ ] SSL Let's Encrypt actif
- [ ] .env production créé
- [ ] Containers démarrés
- [ ] Migrations exécutées
- [ ] Cron jobs configurés
- [ ] GitHub Actions configuré (voir DEPLOY.md)
