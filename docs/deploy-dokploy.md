# Déploiement du backend sur Dokploy

Ce guide décrit les réglages à saisir à la main dans Dokploy. Il ne contient aucune valeur secrète : les valeurs de départ sont dans `.secrets/backend.env` et `.secrets/sopi-db.txt` (fichiers locaux, ignorés par git).

## 1. Projet

Créer un projet nommé **Sopi**. Il contiendra trois services : la base `sopi-db`, le backend, puis plus tard le frontend.

## 2. Base de données PostgreSQL 16

| Réglage | Valeur |
|---|---|
| Type | PostgreSQL |
| Nom (nom d'application / hôte interne) | `sopi-db` |
| Version de l'image | `postgres:16` |
| Nom de la base | `sopi` |
| Utilisateur | `sopi` |
| Mot de passe | voir `.secrets/sopi-db.txt` |
| Port externe | ne pas l'exposer (accès interne uniquement) |

Après création, relever l'**hôte interne** affiché par Dokploy (« Internal Host »). Si ce n'est pas `sopi-db`, corriger `DB_HOST` dans les variables du backend.

## 3. Application backend

| Réglage | Valeur |
|---|---|
| Source | Git (fournisseur GitHub ou dépôt public) |
| Dépôt | `abdou-drame/sopi` (`https://github.com/abdou-drame/sopi`) |
| Branche | `main` |
| Build Path | `/backend` |
| Type de build | **Nixpacks** (aucun Dockerfile) |
| Port exposé | `80` (nginx de Nixpacks écoute sur `$PORT`, 80 par défaut) |
| Déploiement automatique | activé (« Autodeploy » : redéploiement à chaque push sur `main`) |

La configuration de build et de démarrage est dans `backend/nixpacks.toml` :
- PHP 8.3, extensions déduites de `composer.json` (dont `pdo_pgsql`) ;
- build : `composer install --no-dev`, création des dossiers `storage` et `bootstrap/cache`, droits d'écriture ;
- démarrage : `php artisan migrate --force`, `config:cache`, `route:cache`, puis nginx + php-fpm.

### Variables d'environnement

Coller le contenu de `.secrets/backend.env` dans l'onglet « Environment » du service. Rôle de chaque variable :

| Variable | Rôle |
|---|---|
| `APP_NAME`, `APP_VERSION` | Nom et version renvoyés par `/api/v1/health` |
| `APP_ENV=production`, `APP_DEBUG=false` | Mode production, aucun détail d'erreur exposé |
| `APP_KEY` | Clé de chiffrement Laravel (**secret**) |
| `APP_URL` | URL publique HTTPS du backend (à corriger avec le vrai domaine) |
| `APP_TIMEZONE=Africa/Dakar`, `APP_LOCALE=fr`, `APP_FALLBACK_LOCALE=fr` | Fuseau et langue |
| `LOG_CHANNEL=stderr`, `LOG_LEVEL` | Journaux visibles dans les logs Dokploy |
| `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` | Connexion à `sopi-db` |
| `DB_PASSWORD` | Mot de passe PostgreSQL (**secret**) |
| `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=array` | Pilotes cache, files d'attente, sessions |
| `PAYMENT_DRIVER` | `fake` tant que DexPay n'est pas branché, puis `dexpay` |
| `DEXPAY_BASE_URL`, `DEXPAY_API_KEY`, `DEXPAY_WEBHOOK_SECRET` | Paiement DexPay (**secrets**, vides pour l'instant) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | E-mail SMTP (`log` tant que le SMTP n'est pas configuré) |
| `FCM_CREDENTIALS` | Identifiants Firebase pour les notifications push (**secret**, vide pour l'instant) |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Premier administrateur créé par le seeder (**secret**, vides pour l'instant) |
| `ACOMPTE_RATE`, `COMMISSION_RATE`, `SLOT_LOCK_MINUTES` | Valeurs initiales des règles métier (30 %, 10 %, 5 min) |
| `NIXPACKS_PHP_VERSION=8.3` | Force PHP 8.3 au build |

### Domaine HTTPS

Onglet « Domains » : ajouter le domaine du backend, port `80`, **HTTPS activé** avec certificat Let's Encrypt. Le DNS du domaine doit pointer vers l'IP du serveur Dokploy. Reporter ensuite l'URL dans `APP_URL` et redéployer.

## 4. Premier déploiement et vérification

1. Démarrer `sopi-db` et attendre qu'elle soit « running ».
2. Lancer « Deploy » sur le backend et suivre les logs : le build doit finir sans erreur, puis les migrations (`cache`, `jobs`) s'exécutent au démarrage.
3. Vérifier : `GET https://<domaine>/api/v1/health` doit répondre HTTP 200 avec :

```json
{ "status": "ok", "app": "Sopi", "version": "0.1.0", "database": "ok", "time": "..." }
```

Si `database` vaut `"erreur"` (HTTP 503) : contrôler `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD` et que le backend est dans le même réseau que `sopi-db`.

## 5. Sécurité

- Le dépôt est public : ne jamais committer de secrets. Les valeurs vont uniquement dans Dokploy.
- Ne pas exposer le port PostgreSQL à l'extérieur.
