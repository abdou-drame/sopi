# Sopi

Plateforme de réservation de services de proximité (mécanique, coiffure, pressing) à Keur Massar (Sénégal). Les clients réservent un créneau auprès d'un prestataire et versent un acompte de 30 % en ligne ; le solde se règle sur place.

Le cahier des charges complet est dans [docs/cahier-des-charges.pdf](docs/cahier-des-charges.pdf). Les conventions de développement sont dans [CLAUDE.md](CLAUDE.md).

## Structure du monorepo

| Dossier | Contenu | État |
|---|---|---|
| `backend/` | API REST Laravel 12 (`/api/v1`), PostgreSQL, Sanctum, Pest | en cours (Phase 0) |
| `admin-web/` | Back-office administrateur : React 18, Vite, TypeScript, Tailwind | à venir |
| `mobile/` | Application client + prestataire : Flutter 3 (APK Android) | à venir |
| `docs/` | Cahier des charges, décisions d'architecture | — |
| `.github/workflows/` | CI (tests) et construction de l'APK | à venir |

## Lancer chaque partie

### Backend (`backend/`)

Prérequis : PHP 8.3 (8.2 minimum en local), Composer, PostgreSQL 16.

```bash
cd backend
composer install
cp .env.example .env        # puis renseigner DB_* (valeurs factices par défaut)
php artisan key:generate
php artisan migrate
php artisan serve           # http://localhost:8000/api/v1/health
```

Tests : `./vendor/bin/pest` (les tests utilisent SQLite en mémoire, aucune base PostgreSQL requise).

### Back-office (`admin-web/`) — à venir

```bash
cd admin-web && npm install && npm run dev
```

### Mobile (`mobile/`) — à venir

```bash
cd mobile && flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

## Déploiement

Le backend, le back-office et la base PostgreSQL sont hébergés sur Dokploy (build Nixpacks, redéploiement à chaque push sur `main`). Les secrets se configurent dans les variables d'environnement de Dokploy, jamais dans le dépôt.

## Licence

[MIT](LICENSE)
