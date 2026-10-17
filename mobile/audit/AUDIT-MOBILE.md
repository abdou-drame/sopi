# Audit mobile Sopi — lecture seule

Branche `feature/p4c-audit`, créée depuis `main` (`e74fe38`, qui contient 4b-6b et le backend 4.3).
Aucun code, aucune configuration modifiés. Aucun secret lu ni écrit. Chaque affirmation cite fichier et ligne ; ce qui n'a pas pu être vérifié est écrit « non vérifié ».

> La synthèse (partie 4) sera placée en tête de ce document à la fin de l'audit.
> Parties livrées pour l'instant : **1**. À venir : 2 (maquettes), 3 (qualité), 4 (synthèse).

Notation des chemins : `B:` = `backend/`, `M:` = `mobile/lib/`.

---

# PARTIE 1 — Routes de l'API contre écrans

## 1.1 Inventaire des routes non-admin et de leur appelant mobile

Source : `B:routes/api.php`. Le client HTTP a pour base `/api/v1`. Les routes `admin/*` (l. 62-106) sont hors périmètre mobile.

### Publiques

| Méthode et chemin | Rôle | Appel mobile (dépôt:ligne) | Écran / action |
|---|---|---|---|
| GET `/health` (l. 49) | aucun | `M:features/accueil/etat_api.dart:35` | indicateur d'état de l'API |
| GET `/metiers` (l. 52) | aucun | `M:features/reservation/depot_reservation.dart:94` | `metiersProvider` : filtres client (`liste_prestataires_page.dart:126`, `fiche_prestataire_page.dart:161`), inscription prestataire (`etape_activite_page.dart:108`), suivi (`suivi_dossier_page.dart:53`) |
| GET `/quartiers` (l. 53) | aucun | `depot_reservation.dart:102` | `quartiersProvider` : `etape_activite_page.dart:109`, `widgets/feuilles_filtres.dart:160` |
| POST `/prestataires/inscription` (l. 55) | aucun | `M:features/inscription_prestataire/depot_inscription.dart:54` | `etape_justificatifs_page.dart:111` |
| GET `/suivi/{jeton}` (l. 56) | aucun | `depot_inscription.dart:66` | `suivi_dossier_page.dart:27` |
| POST `/webhooks/paiement` (l. 59) | serveur à serveur | aucun | normal (a) |
| POST `/auth/inscription` (l. 210) | aucun | `M:features/auth/session.dart:105` via `cheminInscription` (`M:core/api/client_api.dart:8`) | `auth/inscription_page.dart:86` |
| POST `/auth/connexion` (l. 211) | aucun | `session.dart:105` via `cheminConnexion` (`client_api.dart:7`) | page de connexion |
| POST `/auth/mot-de-passe-oublie` (l. 212) | aucun | `session.dart:105` via `cheminMotDePasseOublie` (`client_api.dart:9`) | `auth/mot_de_passe_oublie_page.dart` |
| POST `/auth/reinitialiser-mot-de-passe` (l. 213) | aucun | aucun | page web du backend (voir 1.2) |

### Authentifiées, tous rôles

| Méthode et chemin | Appel mobile | Écran / action |
|---|---|---|
| GET `/auth/moi` (l. 217) | aucun (le mobile utilise `/me`) | doublon de `/me`, voir 1.2 |
| POST `/auth/deconnexion` (l. 216) | `session.dart:132` | « Se déconnecter » (client et prestataire) |
| GET `/me` (l. 188) | `session.dart:65` ; `depot_reservation.dart:88` | restauration de session ; prénom de l'accueil (`accueil_client_page.dart:44`) |
| PUT `/me` (l. 189) | `M:features/compte/depot_compte.dart:82` | `compte/modifier_profil_page.dart:79` |
| PUT `/me/mot-de-passe` (l. 190) | `depot_compte.dart:99` | `compte/changer_mot_de_passe_page.dart:77` |
| PUT `/me/jeton-push` (l. 191) | aucun | **absent** (voir 1.2) |
| DELETE `/me/jeton-push` (l. 192) | aucun | **absent** |
| POST `/appareils` (l. 197) | aucun | **absent** |
| DELETE `/appareils/{jeton}` (l. 198) | aucun | **absent** |
| GET `/notifications` (l. 203) | `depot_compte.dart:61` | `compte/notifications_page.dart` |
| GET `/notifications/non-lues/compteur` (l. 204) | `depot_reservation.dart:108` | pastille de cloche : `accueil_client_page.dart:209`, `prestataire/tableau_page.dart:129` |
| POST `/notifications/lire-tout` (l. 205) | `depot_compte.dart:72` | `notifications_page.dart:39` |
| POST `/notifications/{id}/lire` (l. 206) | `depot_compte.dart:68` | `notifications_page.dart:60` |

### Client (`role:client`)

| Méthode et chemin | Appel mobile | Écran / action |
|---|---|---|
| GET `/prestataires` (l. 111) | `depot_reservation.dart:125` | `reservation/liste_prestataires_controleur.dart:60,99`, accueil `accueil_client_page.dart:46` |
| GET `/prestataires/{id}` (l. 112) | `depot_reservation.dart:141` | `fiche_prestataire_page.dart:23` ; `accueil_client_page.dart:55` |
| GET `/prestataires/{id}/photos/{photo}` (l. 113) | URL fournie par l'API, chargée par `reservation/widgets/photo_prestataire.dart` | galerie de la fiche |
| GET `/prestataires/{id}/agenda` (l. 114) | `depot_reservation.dart:160` | `choix_creneau_page.dart:90` |
| GET `/prestataires/{id}/avis` (l. 115) | `depot_reservation.dart:147` | `fiche_prestataire_page.dart:29`, `avis_prestataire_page.dart:28` |
| POST `/creneaux/{id}/verrou` (l. 119) | `depot_reservation.dart:170` | `choix_creneau_page.dart:157` |
| DELETE `/creneaux/{id}/verrou` (l. 120) | `depot_reservation.dart:175` | `choix_creneau_page.dart:79,151` |
| POST `/reservations` (l. 124) | `depot_reservation.dart:183` | `choix_creneau_page.dart:197` |
| GET `/reservations` (l. 127) | `depot_reservation.dart:114,192` | `reservations_controleur.dart:69,104` ; prochaine réservation `accueil_client_page.dart:49` |
| GET `/reservations/{id}` (l. 128) | `depot_reservation.dart:199` | `detail_reservation_page.dart:136`, `paiement_page.dart:139,296`, `avis_page.dart:128`, `compte/litige_page.dart:169` |
| POST `/reservations/{id}/annuler` (l. 129) | `depot_reservation.dart:223` | `reservation/annulation_reservation.dart:63` |
| POST `/reservations/{id}/payer` (l. 132) | `depot_reservation.dart:228` | `paiement_page.dart:94` |
| POST `/reservations/{id}/avis` (l. 135) | `depot_reservation.dart:216` | `avis_page.dart:92` |
| POST `/reservations/{id}/litiges` (l. 138) | `depot_compte.dart:39` | `compte/litige_page.dart:127` |
| GET `/litiges` (l. 139) | `depot_compte.dart:46` | `compte/signalements_pages.dart` (liste) |
| GET `/litiges/{id}` (l. 140) | `depot_compte.dart:53` | `signalements_pages.dart:106` |
| GET `/litiges/{id}/preuves/{preuve}` (l. 141) | `depot_compte.dart:111` | `preuveProvider` (`depot_compte.dart:126`) |

### Prestataire (`prestataire`, préfixe `/prestataire`)

| Méthode et chemin | Appel mobile | Écran / action |
|---|---|---|
| GET `/prestations` (l. 146) | `depot_prestataire.dart:192` | `services_page.dart:26`, `service_form_page.dart:32` |
| POST `/prestations` (l. 147) | `depot_prestataire.dart:205` | `service_form_page.dart:130` |
| PATCH `/prestations/{id}` (l. 148) | `depot_prestataire.dart:220` | `service_form_page.dart:142` (modification, activation) |
| GET `/photos` (l. 150) | `depot_prestataire.dart:94` | `photos_page.dart:123` |
| GET `/photos/{photo}` (l. 151) | `depot_prestataire.dart:105` | vignettes `photos_page.dart:230` |
| POST `/photos` (l. 152) | `depot_prestataire.dart:124` | `photos_page.dart:72` |
| DELETE `/photos/{photo}` (l. 153) | `depot_prestataire.dart:128` | `photos_page.dart:110` |
| GET `/agenda` (l. 155) | `depot_prestataire.dart:234` | `disponibilites_page.dart:65` |
| POST `/agenda/plages` (l. 156) | `depot_prestataire.dart:246` | `plage_horaire_page.dart:100` |
| POST `/agenda/indisponibilites` (l. 157) | `depot_prestataire.dart:267` | `indisponibilite_page.dart:118` |
| DELETE `/agenda/creneaux/{id}` (l. 158) | `depot_prestataire.dart:258` | `disponibilites_page.dart:51` |
| GET `/reservations` (l. 161) | `depot_prestataire.dart:134` | `tableau_page.dart:308` et onglets de demandes |
| GET `/reservations/{id}` (l. 162) | `depot_prestataire.dart:145` | `detail_demande_page.dart:47` et pages d'action |
| POST `…/accepter` (l. 163) | `depot_prestataire.dart:151` | `prestataire/actions_demande.dart:25` |
| POST `…/refuser` (l. 164) | `depot_prestataire.dart:158` | `actions_demande.dart:46` (motif : `refus_motif_page.dart`) |
| POST `…/demarrer` (l. 165) | `depot_prestataire.dart:167` | `prestation_page.dart:36` |
| POST `…/annuler` (l. 166) | `depot_prestataire.dart:184` | `actions_demande.dart:67` |
| POST `…/terminer` (l. 167) | `depot_prestataire.dart:176` | `actions_demande.dart:91` |
| GET `/solde` (l. 170) | `depot_prestataire.dart:33` | `portefeuille_page.dart:82`, `retrait_page.dart:122`, `tableau_page.dart:204` |
| GET `/portefeuille/mouvements` (l. 171) | `depot_prestataire.dart:39` | `portefeuille_page.dart:36` |
| GET `/retraits` (l. 172) | aucun | **écran absent** (voir 1.2) |
| GET `/retraits/{id}` (l. 173) | aucun | **écran absent** |
| POST `/retraits` (l. 174) | `depot_prestataire.dart:51` | `retrait_page.dart:79` |
| GET `/avis` (l. 177) | `depot_prestataire.dart:63` | `avis_recus_page.dart:19`, note du profil `depot_prestataire.dart:389` |
| POST `/avis/{id}/signaler` (l. 178) | aucun | **aucun appel** (voir 1.2) |
| GET `/litiges` (l. 181) | `depot_prestataire.dart:68` | `litiges_recus_page.dart:30` |
| GET `/litiges/{id}` (l. 182) | `depot_prestataire.dart:73` | `litige_reponse_page.dart:87` |
| GET `/litiges/{id}/preuves/{preuve}` (l. 183) | `depot_prestataire.dart:86` | `preuveLitigeProvider` (`depot_prestataire.dart:365`) |
| POST `/litiges/{id}/reponse` (l. 184) | `depot_prestataire.dart:78` | `litige_reponse_page.dart:58` |

**Bilan** : 69 routes non-admin au total (comptage manuel de `api.php`) ; 59 ont un appelant mobile identifié ; 10 n'en ont pas (détail ci-dessous). Le décompte est fait à la main à partir de `api.php` ; je n'ai pas lancé `php artisan route:list` (non vérifié par commande).

## 1.2 Routes sans appel mobile : classement

### (a) Normal

| Route | Raison |
|---|---|
| POST `/webhooks/paiement` (l. 59) | appelée par DexPay, pas par l'application. |
| POST `/auth/reinitialiser-mot-de-passe` (l. 213) | le lien envoyé par e-mail ouvre une page web du backend (`B:app/Services/ReinitialisationMotDePasseService.php:24-27`), pas l'application. Voir aussi l'écran provisoire `/reinitialisation` en 1.4. |
| GET `/auth/moi` (l. 217) | doublon de `/me` ; le mobile n'utilise que `/me` (`session.dart:65`). Sans conséquence. |

### (b) Manque côté mobile

| Route | Constat | Gravité probable |
|---|---|---|
| PUT/DELETE `/me/jeton-push` (l. 191-192) et POST/DELETE `/appareils` (l. 197-198) | **Aucune notification push dans le mobile.** Pas de `firebase_messaging` dans `mobile/pubspec.yaml`, aucun code d'enregistrement d'appareil dans `M:` (recherche `firebase`, `appareils`, `jeton-push` : zéro résultat). Le cahier des charges demande e-mail + push. | **Élevée** : les clients ne reçoivent aucune alerte de réponse, d'acceptation ou de rappel de paiement hors de l'application ouverte. Le compteur et la liste de notifications existent (in-app seulement). |
| POST `/prestataire/avis/{id}/signaler` (l. 178) | Aucun bouton « Signaler » sur `avis_recus_page.dart`. Écart connu et accepté : à lister en partie 4 comme décision, pas comme anomalie. | Moyenne |
| GET `/prestataire/retraits` et `/prestataire/retraits/{id}` (l. 172-173) | Aucun écran d'historique des retraits ni de détail. Le portefeuille liste les mouvements (`portefeuille_page.dart:36`) mais pas les retraits eux-mêmes. L'écran « retrait en cours » (sans maquette) est un écart connu. À confirmer : un retrait `ECHOUE` est visible via les mouvements (recrédit) mais le statut `EN_COURS` / `REUSSI` n'est pas consultable. | Moyenne (à discuter) |

### (c) À discuter

| Point | Constat |
|---|---|
| `/application/version` | **La route n'existe pas dans le backend** (`api.php` ne la contient pas). Aucune vérification de version minimale côté mobile non plus. Rien à brancher tant que le backend ne l'expose pas. À décider : veut-on une mise à jour forcée de l'APK ? |
| Route de paiement et retour | `POST /reservations/{id}/payer` renvoie une URL de session ; le mobile exige `https` (`paiement_page.dart:98-101`), l'ouvre dans le navigateur (`:103`) puis **interroge** `GET /reservations/{id}` à intervalle fixe (`_demarrerVerification`, `paiement_page.dart:122-150`) jusqu'à CONFIRMEE ou échec/délai. Aucun lien profond : la route `/paiement/retour` (`routeur.dart:297`) est un écran provisoire jamais navigué (voir 1.4). Le retour fonctionne, mais par interrogation. À discuter : si DexPay redirige vers une URL de retour, que doit-elle ouvrir ? |
| `GET /quartiers` | `B:app/Http/Controllers/Api/V1/QuartierController.php` ne renvoie que les quartiers **des prestataires actifs** (chaînes). Le mobile s'en sert aussi pour la **liste de choix du quartier à l'inscription d'un prestataire** (`M:features/inscription_prestataire/etape_activite_page.dart:109,176-235`). Conséquence : tant qu'aucun prestataire n'est actif dans un quartier, il n'est pas proposé à un nouveau prestataire de ce quartier ; sur une base vide, la liste est vide. À notre connaissance non vérifié sur la base de production. Classé **BLOQUANT possible** pour l'inscription prestataire (voir partie 4). |

## 1.3 Sens inverse : appels mobile sans route, ou différences de contrat

Tous les chemins appelés par le mobile (recensés au 1.1, depuis `_dio.get/post/put/patch/delete` dans `M:`) correspondent à une route existante avec la même méthode. Aucun appel sans route.

Contrôle des contrats (validations de `B:app/Http/Requests/**`, Resources de `B:app/Http/Resources/*`) :

- Noms de champs envoyés : cohérents avec les FormRequests pour `connexion`, `inscription`, `reservations`, `avis`, `litiges`, `retraits`, `prestations`, `agenda/plages`, `agenda/indisponibilites`, `refuser`, `annuler`, `demarrer`, `litiges/{id}/reponse`, `me`, `me/mot-de-passe`, `prestataires/inscription` (multipart).
- Clés de réponse lues : camelCase conformes aux Resources.
- Listes paginées : `{data, links, meta}` lu via `meta.current_page` / `meta.last_page` ; détails non enveloppés dans `data` (conforme au backend).
- Aucune divergence de méthode, de chemin ni de nom de champ trouvée.

Réserves :
- **Longueur minimale du motif de litige** : le backend impose 3 à 150 caractères (FormRequest du litige client). Le mobile propose une liste fixe `motifsLitige` (`M:features/compte/litige_page.dart:387`) ; la longueur ne peut donc pas être mauvaise, mais la liste est **dupliquée en dur** et doit rester alignée avec le backend. Non vérifié : que chaque valeur de `motifsLitige` respecte bien 3-150 (je n'ai pas relu la liste entière).
- **Réponse du prestataire au litige** : le mobile affiche l'erreur 422 du serveur sous le champ (test `avis_litiges_test.dart`), ce qui est correct ; aucune règle de longueur n'est dupliquée côté mobile pour ce champ.
- `GET /auth/moi` vs `/me` : le mobile lit `/me` ; pas d'écart de format constaté, non comparé champ à champ.

## 1.4 Boutons inactifs et routes provisoires

Routes provisoires restantes dans `M:core/routing/routeur.dart` (helper `_provisoire`, l. 133-143, affiche `EcranProvisoire`) :

| Chemin | Ligne | Navigué par le code ? | Constat |
|---|---|---|---|
| `/` (nom « Sopi ») | 160-164 | non (point d'entrée, redirigé selon la session) | à confirmer en partie 3 : `redirect` renvoie vers l'accueil/la connexion ; l'écran provisoire n'apparaît normalement jamais. |
| `/reinitialisation` | 185 | non : aucune navigation vers `Chemins.reinitialisation` hors `chemins.dart:89,102` (listes de routes publiques) | écran mort. Le lien de réinitialisation envoyé par e-mail ouvre la page web du backend, pas cette route. À supprimer ou à brancher ; `mot_de_passe_oublie_page.dart:142` ne l'utilise pas. |
| `/reservations/:id/annulation` | 273-277 | **non** : `Chemins.annulation(id)` (`chemins.dart:39`) n'est jamais appelée ; l'annulation client passe par `annulation_reservation.dart:63` (feuille/écran dédié) | route et helper morts, écran provisoire inatteignable en usage normal. |
| `/paiement/retour` | 297 | **non** : `Chemins.retourPaiement` (`chemins.dart:36`) jamais navigué | voir 1.2 (c) : le retour de paiement passe par l'interrogation. Provisoire inatteignable. |

Total : **4 routes provisoires restantes** (dont 3 mortes, 1 point d'entrée à vérifier).

Boutons et actions inactifs : la recherche de `onPressed: null`, `onTap: () {}`, `TODO` et `FIXME` dans `M:` est faite en partie 3 avec l'analyse statique ; aucun bouton sans effet n'a été relevé en lisant les écrans de la partie 1 (les tuiles d'ajout et d'action sont désactivées uniquement pendant un envoi : `photos_page.dart:177`). Non exhaustif : « non vérifié » jusqu'à la partie 3.

## 1.5 Valeurs et règles métier en dur dans le mobile

Principe du CLAUDE.md : aucune règle métier côté mobile. Relevé :

| Valeur | Où | Devrait venir de | Gravité |
|---|---|---|---|
| URL d'API par défaut `https://sopi-api.duckdns.org/api/v1` | `M:core/config/environnement.dart:8` (utilisée en repli, `:16`) | `--dart-define=API_BASE_URL` seul ; l'adresse de production est publiée dans un dépôt public et sert de valeur par défaut | VISIBLE (pas un secret, mais un défaut risqué : un build sans define pointe sur la production) |
| E-mail de support | `environnement.dart:22` : `String.fromEnvironment('SUPPORT_EMAIL')`, **sans valeur en dur** | dart-define : conforme | OK (les écrans masquent le bouton si vide : `compte_restreint_page.dart:80`, `aide_page.dart:52`) |
| Délai de réponse 12 h et de paiement 2 h | `compte/aide_page.dart:15`, `reservation/paiement_page.dart:906` (« 2 heures ») | API (paramètre), ou texte neutre | VISIBLE |
| Acompte « 30 % » | `aide_page.dart:19` | API : l'acompte est déjà fourni en montant (`acomptePrestation`, `depot_reservation.dart:233`) ; le pourcentage ne doit pas être écrit | VISIBLE |
| Annulation « 2 heures avant » | `aide_page.dart:23`, `reservation/annulation_reservation.dart:153`, `paiement_page.dart:537` (« 2 h ») | API (délai d'annulation) | VISIBLE |
| Verrou « 5 minutes » | `reservation/choix_creneau_page.dart:453` | API : `verrouiller` renvoie l'expiration (`VerrouCreneau`) ; le texte devrait en dériver | VISIBLE |
| Blocage « 15 minutes » après échecs de connexion | `auth/connexion_page.dart:108` | message du serveur (429/423) | MINEUR |
| Longueurs maximales : description de litige 1000 ; commentaire d'avis 1000 | `compte/modeles_litige.dart:14` ; `reservation/avis_page.dart:31` | limites du backend (validations) | MINEUR (le serveur reste l'autorité, le 422 est affiché) |
| Montants retrait min 1 000 / max 500 000 FCFA | recherche `1 ?000\|500 ?000` : aucun résultat dans `M:` | — | OK : les bornes ne sont pas en dur, le serveur décide |
| Taux de commission (10 %) | aucun résultat | — | OK |

Note : les écarts « pas de pourcentage (30 %, 10 %) ni de « 2 h » » figurent dans la liste des écarts connus de la partie 2 pour les **maquettes** ; ici ils sont relevés parce que le **code** les contient encore (textes d'aide et de paiement) et que les textes en dur doivent être alignés avec l'API ou neutralisés. À arbitrer en partie 4.

### Points à vérifier en partie 3
- `EcranProvisoire` jamais affiché en navigation réelle (aucun test d'intégration ne le prouve).
- Aucun secret : le dépôt ne contient que l'URL publique de l'API et aucune clé (recherche rapide `key|secret|token` sur `environnement.dart` : aucune valeur sensible lue). Contrôle complet en partie 3.
