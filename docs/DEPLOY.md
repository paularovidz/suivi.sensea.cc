# Déploiement Automatique - GitHub Actions

## Architecture de déploiement

```
GitHub Push
    │
    ▼
GitHub Actions
    │
    ├── Build API Docker image → Push to GHCR
    ├── Build Frontend (npm build) → Deploy via SSH
    └── Build WWW (yarn build) → Deploy via SSH
    │
    ▼
VPS Hetzner
    │
    ├── Pull nouvelle image API → Restart container
    ├── Frontend dist → Servi par Nginx
    └── WWW dist → Servi par Nginx
```

## Prérequis

1. **Secrets GitHub** à configurer dans le repo (Settings → Secrets → Actions) :

| Secret | Description |
|--------|-------------|
| `VPS_HOST` | IP du VPS |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | Clé SSH privée pour deploy |
| `GHCR_TOKEN` | Token GitHub pour push images (ou utiliser GITHUB_TOKEN) |

2. **Clé SSH pour le déploiement** :

```bash
# Sur ta machine locale
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_deploy

# Copier la clé publique sur le VPS
ssh-copy-id -i ~/.ssh/github_deploy.pub deploy@<VPS_IP>

# Le contenu de ~/.ssh/github_deploy (clé privée) → Secret VPS_SSH_KEY
```

---

## Workflows GitHub Actions

### Workflow principal : `.github/workflows/deploy.yml`

```yaml
name: Deploy

on:
  push:
    branches: [main]
  workflow_dispatch:

env:
  REGISTRY: ghcr.io
  API_IMAGE_NAME: ${{ github.repository }}-api

jobs:
  # ============================================
  # Build et push l'image Docker de l'API
  # ============================================
  build-api:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Log in to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.API_IMAGE_NAME }}
          tags: |
            type=raw,value=latest
            type=sha,prefix=

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: ./api
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}

  # ============================================
  # Build le frontend Vue.js
  # ============================================
  build-frontend:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json

      - name: Install dependencies
        working-directory: frontend
        run: npm ci

      - name: Build
        working-directory: frontend
        run: npm run build
        env:
          VITE_API_URL: https://suivi.sensea.cc/api

      - name: Upload artifact
        uses: actions/upload-artifact@v4
        with:
          name: frontend-dist
          path: frontend/dist
          retention-days: 1

  # ============================================
  # Build le site vitrine Astro
  # ============================================
  build-www:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'yarn'
          cache-dependency-path: www/yarn.lock

      - name: Install dependencies
        working-directory: www
        run: yarn install --frozen-lockfile

      - name: Build
        working-directory: www
        run: yarn build

      - name: Upload artifact
        uses: actions/upload-artifact@v4
        with:
          name: www-dist
          path: www/dist
          retention-days: 1

  # ============================================
  # Déployer sur le VPS
  # ============================================
  deploy:
    runs-on: ubuntu-latest
    needs: [build-api, build-frontend, build-www]

    steps:
      - name: Download frontend artifact
        uses: actions/download-artifact@v4
        with:
          name: frontend-dist
          path: frontend-dist

      - name: Download www artifact
        uses: actions/download-artifact@v4
        with:
          name: www-dist
          path: www-dist

      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.VPS_SSH_KEY }}" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -H ${{ secrets.VPS_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy frontend
        run: |
          rsync -avz --delete \
            -e "ssh -i ~/.ssh/deploy_key" \
            frontend-dist/ \
            ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }}:/home/deploy/apps/snoezelen/frontend/dist/

      - name: Deploy www
        run: |
          rsync -avz --delete \
            -e "ssh -i ~/.ssh/deploy_key" \
            www-dist/ \
            ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }}:/home/deploy/apps/snoezelen/www/dist/

      - name: Deploy API (pull & restart)
        run: |
          ssh -i ~/.ssh/deploy_key ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} << 'EOF'
            cd /home/deploy/apps/snoezelen
            docker compose -f docker-compose.prod.yml pull api
            docker compose -f docker-compose.prod.yml up -d api
            docker exec snoezelen_api php /var/www/html/migrations/migrate.php
          EOF

      - name: Cleanup
        run: rm -f ~/.ssh/deploy_key
```

---

## Workflows optionnels

### Preview sur PR : `.github/workflows/preview.yml` (optionnel)

```yaml
name: Preview

on:
  pull_request:
    branches: [main]

jobs:
  build-check:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      # Test build frontend
      - name: Build frontend
        working-directory: frontend
        run: |
          npm ci
          npm run build

      # Test build www
      - name: Build www
        working-directory: www
        run: |
          yarn install --frozen-lockfile
          yarn build

      # Test API (lint, tests si présents)
      - name: API checks
        working-directory: api
        run: |
          composer install --no-dev
          # composer run lint (si configuré)
          # composer run test (si configuré)
```

---

## Configuration sur le VPS

### 1. Autoriser GitHub Container Registry

```bash
# Sur le VPS, en tant que deploy
docker login ghcr.io -u <GITHUB_USERNAME>
# Utiliser un Personal Access Token avec read:packages
```

### 2. Mettre à jour docker-compose.prod.yml

```yaml
api:
  image: ghcr.io/<GITHUB_USER>/<REPO>-api:latest
  # ... reste de la config
```

### 3. Créer les dossiers de déploiement

```bash
mkdir -p ~/apps/snoezelen/frontend/dist
mkdir -p ~/apps/snoezelen/www/dist
```

---

## Déploiement manuel

Si besoin de déployer manuellement :

```bash
# Sur le VPS
cd ~/apps/snoezelen

# Pull la dernière image
docker compose -f docker-compose.prod.yml pull

# Redémarrer
docker compose -f docker-compose.prod.yml up -d

# Migrations
docker exec snoezelen_api php /var/www/html/migrations/migrate.php
```

---

## Rollback

```bash
# Voir les tags disponibles
docker images ghcr.io/<USER>/<REPO>-api

# Utiliser un tag spécifique
docker compose -f docker-compose.prod.yml pull api:sha-abc1234
docker compose -f docker-compose.prod.yml up -d api
```

---

## Monitoring

### Voir les logs en temps réel

```bash
docker compose -f docker-compose.prod.yml logs -f
```

### Vérifier l'état des services

```bash
docker compose -f docker-compose.prod.yml ps
```

### Healthcheck

```bash
curl -I https://suivi.sensea.cc/api/health
curl -I https://sensea.cc
```
