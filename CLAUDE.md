# CLAUDE.md — Projet Sopi

Tu es développeur sur **Sopi**, plateforme de réservation de services de proximité (mécanique, coiffure, pressing) à Keur Massar (Sénégal). Un architecte (Claude, en conversation séparée) donne les prompts dans l'ordre. Tu exécutes chaque prompt, tu vérifies, puis tu fais un rapport. Le cahier des charges complet est dans `docs/cahier-des-charges.pdf` : c'est la source de vérité fonctionnelle (RF01 à RF23, RG01 à RG35). En cas de doute, relis-le avant de décider.

## Principes de travail
- Un prompt = une étape. Ne fais rien au-delà de l'étape demandée.
- Rien n'est « terminé » tant que : les tests passent, le déploiement automatique fonctionne, et (pour le mobile) l'APK se construit.
- Termine toujours par un rapport : fichiers créés, commandes lancées, résultats des tests, ce qui reste à faire, problèmes rencontrés. N'invente jamais un résultat : si une commande échoue, dis-le.
- Si une information manque, applique la valeur par défaut de ce fichier et signale-le dans le rapport. Ne bloque pas.
- Code, noms de variables et commentaires en français pour le métier (Reservation, Creneau...), anglais pour le technique si c'est la convention du framework.

## Organisation du travail
- Ordre : Phase 0 fondations → Phase 1 backend (modules M1 à M11) → Phase 2 back-office web → Phase 3 application mobile → Phase 4 qualité → Phase 5 livraison. Le web et le mobile ne sont jamais développés en parallèle.
- Les tables de la base de données sont créées module par module (migrations, modèles, factories et seeders du module seulement). Pas de migration pour un module qui n'a pas encore été demandé.
- Chaque module est découpé en sous-prompts (ex. M1.a, M1.b...). Tu ne fais JAMAIS l'étape suivante sans que l'architecte te la donne. Un sous-prompt = une seule tâche.
- Tout ce qui est développé doit rester déployable : le backend, le web et la base sont hébergés sur Dokploy (3 services : bdd PostgreSQL, backend, frontend) et se redéploient à chaque push sur `main` ; l'APK est construit par GitHub Actions et publié sur GitHub Releases à chaque push sur `main` qui touche `mobile/`. Sur une branche `feature/*`, GitHub Actions lance les tests et construit l'APK mais ne publie pas la release.
- Le web et le mobile consomment la même API `/api/v1`. Aucune règle métier dans le web ni dans le mobile.

## Structure du dépôt (monorepo public)
```
backend/     Laravel 11 (API REST)
admin-web/   React 18 + Vite + TypeScript + Tailwind (back-office administrateur)
mobile/      Flutter 3 (application client + prestataire, Android APK)
docs/        cahier-des-charges.pdf, décisions d'architecture
.github/workflows/   CI (tests) et build de l'APK
```

## RÈGLES DE SÉCURITÉ (le dépôt est PUBLIC)
- Ne JAMAIS committer : `.env`, clés DexPay, accès SMTP, clés privées Firebase, `*.jks`/`*.keystore`, `key.properties`, mots de passe, tokens. Vérifie `.gitignore` avant chaque commit.
- Les secrets du serveur vont dans les variables d'environnement de Dokploy. Les secrets de build de l'APK vont dans les GitHub Secrets.
- Fournis des fichiers `.env.example` avec des valeurs factices uniquement.
- Le mot de passe du premier administrateur vient de `ADMIN_PASSWORD` (variable d'environnement), jamais du code.
- Si tu constates qu'un secret a été committé, arrête-toi et signale-le immédiatement.

## Stack et décisions
- **Backend :** PHP 8.3, Laravel 11, PostgreSQL 16, Eloquent. Authentification par **Laravel Sanctum** (jetons). Mots de passe Bcrypt. Tests avec **Pest**. Files d'attente : driver `database`. Tâches planifiées : scheduler Laravel.
- **Clés primaires UUID** partout. Montants en `decimal(12,2)`, devise **XOF (FCFA)**. Dates stockées en UTC, fuseau applicatif `Africa/Dakar`.
- **API :** préfixe `/api/v1`, JSON uniquement, messages d'erreur en français. Format d'erreur : `{ "message": "...", "errors": { "champ": ["..."] } }`. Pagination `?page=` (20 par page). Documentation OpenAPI (Scribe ou L5-Swagger) générée à chaque module.
- **Architecture backend :** Controllers fins → FormRequests (validation) → Services métier (ReservationService, PaiementService, PortefeuilleService...) → Models. Autorisations par Policies et middleware de rôle (`client`, `prestataire`, `admin`).
- **Interfaces d'isolation :** `PaiementService` (créer session, vérifier signature du webhook, verser) et `NotificationService` (e-mail + push). Implémentations : `DexPayPaiementService`, `FakePaiementService` (pilotée par `PAYMENT_DRIVER=fake|dexpay`), `LaravelNotificationService`.
- **Back-office :** React 18, Vite, TypeScript, Tailwind, React Router, TanStack Query, client HTTP unique avec jeton Sanctum. Administrateur uniquement.
- **Mobile :** Flutter 3, Dart 3, Riverpod, dio, go_router, firebase_messaging. Un seul code pour client et prestataire, l'interface dépend du rôle. L'URL de l'API est injectée au build : `--dart-define=API_BASE_URL=...`.
- **Hébergement :** Dokploy, déploiement automatique à chaque `push` sur `main`, HTTPS. Pas de Dockerfile écrit à la main : build via Nixpacks (ajoute `nixpacks.toml` si nécessaire). Migrations lancées au déploiement : `php artisan migrate --force`.
- **Workflow Git :** une branche par étape (`feature/...`), tests au vert, fusion dans `main` (= production). Commits en français, clairs.

## Règles métier essentielles (détail complet dans le PDF)
- Comptes : statuts EN_ATTENTE_VALIDATION, ACTIF, REFUSE, SUSPENDU, BANNI. Seul un compte ACTIF s'authentifie. 5 échecs de connexion → blocage temporaire (15 min).
- Réservation : EN_ATTENTE → ACCEPTEE (en attente de paiement) → CONFIRMEE → EN_COURS → TERMINEE ; issues REFUSEE, ANNULEE.
- Verrou de créneau : atomique (transaction + `lockForUpdate` ou mise à jour conditionnelle). Durée par défaut : **5 minutes**.
- Le prestataire répond sous **12 h** sinon ANNULEE + créneau libéré. Après acceptation, le client paie sous **2 h** sinon ANNULEE + créneau libéré.
- Acompte = **30 %** du tarif. Commission plateforme = **10 %** du tarif (paramètre stocké en base, valeurs initiales via env `ACOMPTE_RATE`, `COMMISSION_RATE`). À TERMINEE : crédit au portefeuille = acompte − commission. Le solde (70 %) est payé sur place.
- Paiement : le montant est TOUJOURS calculé côté serveur. Webhook : signature HMAC-SHA256 vérifiée, traitement idempotent, jamais de confiance au client.
- Code de réservation : 6 caractères alphanumériques majuscules, généré au paiement réussi ; le prestataire le saisit pour passer EN_COURS. Mauvais code → refus.
- Annulation par le client : autorisée jusqu'à **2 h avant** le début du créneau ; l'acompte payé n'est pas remboursé. Annulation par le prestataire (ou indisponibilité confirmée) : remboursement intégral (paiement REMBOURSE).
- Retrait : montant ≤ solde, minimum **1 000 FCFA**, maximum **500 000 FCFA** par retrait ; débit immédiat, retrait EN_COURS puis REUSSI/ECHOUE ; en cas d'échec le montant est recrédité. Opérations financières en transaction.
- Avis uniquement sur réservation TERMINEE. Litiges : OUVERT → CLOTURE. Sanctions : AVERTISSEMENT, SUSPENSION_TEMPORAIRE, BANNISSEMENT_DEFINITIF, historisées.
- L'administrateur ne reçoit ni e-mail ni push : bannières d'alerte sur son tableau de bord.
- Notifications : e-mail (SMTP) + push (FCM). Ne jamais révéler l'identité complète du client au prestataire avant confirmation.

## Tâches planifiées (scheduler, toutes les minutes)
- Annuler les réservations EN_ATTENTE sans réponse après 12 h.
- Annuler les réservations ACCEPTEE non payées après 2 h.
- Libérer les verrous de créneaux expirés.

## Variables d'environnement (noms attendus)
`APP_ENV, APP_KEY, APP_URL, DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, PAYMENT_DRIVER, DEXPAY_BASE_URL, DEXPAY_API_KEY, DEXPAY_WEBHOOK_SECRET, MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, FCM_CREDENTIALS (JSON ou chemin), ADMIN_EMAIL, ADMIN_PASSWORD, ACOMPTE_RATE=0.30, COMMISSION_RATE=0.10, SLOT_LOCK_MINUTES=5`

## Données de départ (seeders)
- 1 administrateur (depuis `ADMIN_EMAIL` / `ADMIN_PASSWORD`).
- Métiers : Mécanique, Coiffure, Pressing.
- Services : Mécanique (Vidange, Diagnostic, Freinage, Pneumatiques) ; Coiffure (Coupe homme, Coupe femme, Tresses, Coloration) ; Pressing (Chemise, Nettoyage à sec, Repassage, Couette).
- Quartiers de Keur Massar : liste de départ modifiable par l'administrateur (Keur Massar Nord, Keur Massar Sud, Malika, Jaxaay, Yeumbeul Nord, Yeumbeul Sud) — à vérifier.

## Définition de « terminé » pour un prompt
1. Le code fait ce que le prompt demande, ni plus ni moins.
2. Tests automatisés écrits et au vert (`./vendor/bin/pest`, `npm test`, `flutter test` selon la partie).
3. Aucun secret dans le dépôt.
4. Le déploiement automatique sur Dokploy fonctionne (backend/back-office) ou l'APK se construit (mobile).
5. Rapport final honnête.
