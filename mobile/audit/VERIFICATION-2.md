# VÉRIFICATION 2 — écrans, boutons, appels d'API, contre le vrai backend

Branche `feature/p4e-verification` (depuis `main` = `8528da0`). Tout s'est passé en local (`127.0.0.1`), sur une base SQLite jetable, avec `PAYMENT_DRIVER=fake` : aucune clé réelle, aucun appel à la production ni à DexPay.

## 1. Synthèse

| Mesure | Résultat |
|---|---|
| Maquettes `.dc.html` rapprochées d'un écran Dart | 54 / 54 |
| Écrans Dart inventoriés (gestes) | 47 fichiers, 261 gestes (`audit/inventaire-interactions.md`) |
| Sites d'appel d'API qui modifient des données, relus à la main | 37 (liste obtenue par `grep` des méthodes de dépôt hors `depot_*.dart`) |
| Anomalies trouvées / corrigées (partie A) | 2 familles : 14 actions sans garde synchrone de double envoi, 2 suppressions sans verrou — **toutes corrigées** (commit `8f71e0d`) |
| Parcours d'intégration (partie B) | **47 tests, 47 OK**, 0 ÉCART de format, 2 points NON TESTABLES (voir 3.3) |
| Écarts visuels mesurables (partie C) | 2105 valeurs comparées ; 8 VISIBLES (dont 1 vrai écart, 7 faux positifs rendus explicites) **corrigés** ; 61 MINEURS restants, non corrigés |
| `flutter analyze` | aucun problème |
| `flutter test` (suite normale, les tests d'intégration sont exclus) | 536 tests, tous au vert |
| Tests d'intégration | 47 / 47 au vert sur base vierge (voir 3.1 pour les relancer) |

## 2. Partie A — éléments interactifs

### Méthode
1. `audit/inventaire_interactions.mjs` repère les gestes (`onPressed`, `onTap`, `onChanged`, `onRefresh`…) de chaque écran avec le numéro de ligne, la méthode appelée et des motifs (garde, erreur, navigation). Il ne comprend pas le code : ses colonnes sont des **signaux**.
2. Les 37 sites qui appellent une méthode de dépôt modifiant des données ont été **lus un par un** (écran, garde, `catch`, message affiché, navigation).

### Ce qui est conforme (relu)
- **Chargement visible** sur tous les envois : bouton en `chargement:` / désactivé pendant l'envoi (connexion `connexion_page.dart:64`, inscription `inscription_page.dart:81`, mot de passe oublié `mot_de_passe_oublie_page.dart:71`, litige, avis, annulation, retrait, services, plage, indisponibilité, refus, terminer, acceptation, paiement `paiement_page.dart:568`, code de démarrage `code_saisie.dart:486`).
- **Erreurs affichées** : chaque envoi convertit l'exception par `ErreurApi.depuis` et affiche `message` en français ; 422 → message par champ (`ErreurValidation.premierMessage`), 429 → blocage avec délai (connexion, inscription, mot de passe oublié, code), 401 → retour à la connexion (`session.dart:140`), réseau coupé → « Pas de connexion. » (`erreurs.dart:3`), 403/404/409/5xx → message du serveur.
- **Navigation après succès** présente sur les envois qui en ont une (connexion / inscription par changement d'état de session, refus → retour, terminer → crédit puis tableau, etc.).
- **Déconnexion** (`session.dart:130-137`) : efface la session locale même si l'appel échoue.

### Anomalies et corrections (commit `8f71e0d`)
| # | Anomalie | Où | Correction |
|---|---|---|---|
| A1 | Pas de garde **synchrone** contre un second appui dans la même image : seule la désactivation du bouton (au rendu suivant) protégeait | changer mot de passe, modifier profil, litige, avis, annulation, paiement, service, plage, indisponibilité, refus, terminer, accepter (détail et liste), « tout marquer lu » (14 méthodes) | `if (_enCours) return;` en tête de l'envoi |
| A2 | Suppression d'une photo : aucun verrou ni indicateur, une double suppression produisait une erreur 404 affichée | `prestataire/photos_page.dart` | ensemble `_suppressions` (une seule requête par photo) |
| A3 | Retrait d'un créneau : même cas | `prestataire/disponibilites_page.dart` | ensemble `_retraitsEnCours` |

### Limites de la partie A
- Le script ne voit pas les appels faits via des fonctions utilitaires ; la matrice complète (47 écrans × chaque geste) n'est pas écrite à la main : elle est **générée** (`inventaire-interactions.md`) et complétée par la relecture ci-dessus.
- Les gestes purement de navigation (retour, onglets, liens) n'appellent pas l'API ; leur effet n'est vérifié que par les tests existants.
- Les gardes ajoutées n'ont **pas de test de concurrence dédié** (l'adaptateur de test répond instantanément). La suite existante reste verte.
- Écarts connus restant : notifications — l'échec de `marquerLue` est silencieux (commentaire à `notifications_page.dart:62`) : choix assumé, pas une anomalie.

## 3. Partie B — contrat réel

### 3.1 Dispositif
- `test/integration/lancer_backend_local.sh <port>` : écrit un `backend/.env` **ignoré par Git** avec des valeurs factices (`PAYMENT_DRIVER=fake`, `DEXPAY_SECRET_KEY=secret-factice-local-uniquement`, `MAIL_MAILER=log`, SQLite jetable dans le dossier temporaire), refuse d'écraser un `.env` qui n'est pas le sien, migre, crée l'administrateur local (`admin-local@sopi.invalid`), démarre `php artisan serve`.
- `test/integration/harnais.dart` : acteurs Riverpod avec **le vrai `dioProvider`** et les vrais dépôts ; l'URL vient de `--dart-define=API_URL`. Les tests sont ignorés si l'URL ne commence pas par `http://127.0.0.1`.
- Lancement : `bash test/integration/lancer_backend_local.sh 8765` puis `flutter test test/integration/parcours_integration.dart --dart-define=API_URL=http://127.0.0.1:8765/api/v1 --dart-define=BACKEND_DIR=<chemin du backend>`. **La base doit être vierge** : relancer le script avant chaque exécution (une 2ᵉ exécution échoue sur « téléphone déjà utilisé »). Fichier nommé `*_integration.dart` : exclu de `flutter test`.
- **Paiement** : le serveur est en `fake` ; `FakePaiementService` renvoie une URL `https://paiement.fake.invalid/FAKE-…`. Le test extrait la référence et envoie le **vrai webhook** `POST /webhooks/paiement`, signé HMAC-SHA256 avec le secret local (`harnais.dart:76-99`). La vérification de signature et l'idempotence du serveur sont donc réellement exercées ; le prestataire de paiement ne l'est pas.
- **Retrait** : `verser()` du faux service réussit sans réseau. Le résultat « DexPay » est simulé **côté serveur local**.
- Validation d'administrateur : appels directs `GET /admin/prestataires/demandes`, `POST …/accepter`, `POST …/refuser` avec l'administrateur local.
- Les limites de débit (cache fichier) sont remises à zéro par `php artisan cache:clear` entre les parcours.

### 3.2 Résultat par parcours
| Parcours (test) | Classement |
|---|---|
| Métiers, quartiers (liste de recherche et `?tous=1`) — P01a-b | OK |
| Inscription client, doublon de téléphone (422 par champ), déconnexion, connexion, mauvais mot de passe, mot de passe oublié jusqu'à l'envoi — P02a-e | OK |
| Inscription prestataire avec justificatifs, 422 sans justificatif, suivi « en attente », « accepté » (admin local), « refusé » avec motif, jeton inconnu 404 — P03a-e | OK |
| Services (ajout, modification, désactivation, 422 prix), photos (ajout, lecture, suppression), créneaux (ajout, retrait), indisponibilité — P04a-f | OK |
| Recherche (texte, quartier, tri, prix, note, pagination, vide), fiche, acompte, avis d'un prestataire, 404 — P05a-e | OK |
| Verrou et libération, réservation, acceptation, paiement (webhook), code à 6 caractères, doublon refusé — P06a-c | OK |
| Démarrage : mauvais code 422, trop d'essais 429, bon code — P06d-f | OK |
| Terminer (crédit), solde et mouvements, retrait trop bas 422, retrait valide, retrait > solde 422 — P07a-d | OK |
| Avis puis double avis refusé, avis visibles des deux côtés, litige avec photo, réponse du prestataire, 2ᵉ réponse refusée (409) — P08a-c | OK |
| Annulation client, annulation prestataire (motif), motif vide refusé, refus avec motif, listes par groupe — P09a-f | OK |
| Notifications (liste, compteur, lecture, tout lire) pour les deux rôles — P10a-b | OK |
| Profil (modification), mot de passe (mauvais actuel puis succès, reconnexion), jeton révoqué → session expirée — P11a-c | OK |
| Retrait qui **échoue** (statut ECHOUE, recrédit) | **NON TESTABLE** : le faux service réussit toujours ; couvert seulement par les tests unitaires |
| Délais automatiques (12 h sans réponse, 2 h sans paiement), annulation par indisponibilité avec réservation confirmée (remboursement), notification push | **NON TESTABLE** localement dans ce dispositif (scheduler et FCM non exécutés) |

### 3.3 Écarts de format
Aucun écart de format entre le mobile et le serveur n'a été trouvé sur ces parcours. Vérifié notamment : les montants (acompte 1 800, commission 600, crédit 1 200 sur un tarif de 6 000), les statuts parsés (`StatutReservation`), le code de démarrage `^[A-Z0-9]{6}$`, les pages (`pagination.page`). Deux corrections de **mes propres attentes** (pas du mobile) : `montantTotal` est le tarif (6 000) et non l'acompte ; `note_min` exclut les prestataires sans avis (voir 4).

## 4. Corrections backend à valider (non faites)
1. **`note_min=0` exclut les prestataires sans avis** (`RechercheService.php:65-67` : `avg(note) >= 0` est faux quand la moyenne est NULL). Le mobile n'envoie jamais 0 (`FiltresPrestataires.noteMin` nul par défaut), donc aucun effet visible aujourd'hui. À décider : ignorer `note_min` ≤ 0.
2. Les tests backend locaux comptent 36 échecs préexistants (PHP 8.2 local pour un projet 8.3) : hors périmètre, non investigués.

## 5. Partie C — conformité visuelle

`node audit/comparer_maquettes.mjs` (depuis `mobile/`) → `audit/ecarts-visuels.md` et `.json`. Pour chaque maquette : border-radius, padding, gap, font-size, font-weight, hauteurs/largeurs de composants (16 à 120 px) et couleurs hex, recherchés dans l'écran, puis dans son dossier et `core/`.

| | Valeurs | Dans l'écran | Ailleurs (core/, dossier) | Absentes |
|---|---:|---:|---:|---:|
| Avant correction | 2105 | 746 | 1290 | 69 (8 VISIBLES) |
| Après correction | 2105 | 754 | 1290 | **61 (0 VISIBLE, 61 MINEURS)** |

- **Corrigé (commit `9c47904`)** : `Creneau` utilisait `EnTeteDegrade` en 30/36 alors que la maquette impose 28/34 → paramètres `tailleTitre` / `interligneTitre` (`en_tete_degrade.dart`) et `choix_creneau_page.dart`. Pour `Paiement`, `PaiementOk`, `PaiementEchec`, `DemandeEnvoyee`, `Detail*` la taille 28 venait implicitement du thème Material (`headlineMedium`) : rendue **explicite** (`fontSize: 28`) pour ne plus dépendre d'une valeur par défaut.
- **Restants (61 MINEURS, non corrigés)** : majoritairement des largeurs de 100 px (18), des rayons de 25 px (15, faux positifs probables : les boutons ont un rayon `hauteur / 2`, `boutons.dart:62`), puis des rayons isolés (5, 7, 11, 17, 21, 23, 52, 56), 2 hauteurs de 96 et 56, 1 padding de 36. Détail par écran dans `ecarts-visuels.md`. Aucun n'a été jugé « sûr » à corriger sans comparaison visuelle.
- Les écarts BLOQUANTS : **0** (aucune maquette sans écran, aucun fichier introuvable).

### Ce que cette méthode ne garantit pas
- Le **rendu réel** : une valeur présente dans le Dart peut s'appliquer à un autre élément (la recherche est par contexte de ligne, pas par widget) ; une valeur absente peut venir d'une constante ou du thème.
- Les **polices** : Plus Jakarta Sans est chargée à l'exécution par `google_fonts` (`theme_sopi.dart:31`) ; aucun rendu de glyphes n'a été comparé.
- Les positions (`top/left` absolus des maquettes contre `Column`/`Padding`), les icônes, les ombres, les dégradés, les animations.
- Les valeurs `em`/`%` des maquettes ne sont pas extraites.

## 6. Non vérifié, et pourquoi
| Point | Raison |
|---|---|
| Rendu sur téléphone (tailles, polices, clavier, sélecteur de photo natif) | aucun appareil ni émulateur utilisé |
| Paiement réel DexPay | interdit (aucune clé réelle) ; seul le faux service et le webhook signé sont exercés |
| Notifications push (FCM) | explicitement exclues de cette mission |
| Scheduler (12 h, 2 h, verrous expirés) | non exécuté pendant les parcours |
| Retrait en échec avec recrédit de bout en bout | le faux service réussit toujours |
| Backend de production (`/api/v1` déployé) | interdit |

## 7. Fichiers ajoutés ou modifiés
- Tests : `test/integration/{lancer_backend_local.sh,harnais.dart,parcours_integration.dart}` ; `pubspec.yaml` (+ `crypto` en dev, pour la signature du faux webhook).
- Audit : `audit/{comparer_maquettes.mjs,ecarts-visuels.md,ecarts-visuels.json,inventaire_interactions.mjs,inventaire-interactions.md,inventaire-interactions.json,VERIFICATION-2.md}`.
- Code : `core/widgets/en_tete_degrade.dart` ; `reservation/{choix_creneau,paiement,demande_envoyee,detail_reservation,annulation_reservation,avis}_page.dart` ; `compte/{changer_mot_de_passe,litige,modifier_profil,notifications}_page.dart` ; `prestataire/{indisponibilite,plage_horaire,service_form,refus_motif,terminer_prestation,detail_demande,photos,disponibilites}_page.dart` ; `prestataire/widgets/composants_prestataire.dart`.
- Commits : `ec5dd8f` (intégration), `9c47904` (visuel), `8f71e0d` (double envoi), plus celui de ce rapport. Rien n'est fusionné.
