# Audit mobile Sopi — lecture seule

Branche `feature/p4c-audit`, créée depuis `main` (`e74fe38`, qui contient 4b-6b et le backend 4.3).
Aucun code, aucune configuration modifiés. Aucun secret lu ni écrit. Chaque affirmation cite fichier et ligne ; ce qui n'a pas pu être vérifié est écrit « non vérifié ».

> La synthèse (partie 4) sera placée en tête de ce document à la fin de l'audit.
> Parties livrées pour l'instant : **1, 2, 3**. À venir : 4 (synthèse).

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

---

# Partie 2 — Maquettes contre code

## 2.0 Méthode et limites (à lire avant les résultats)

- **54 fichiers** `.dc.html` dans `mobile/maquettes/maquettes/` (le `LISEZ-MOI.txt` annonce « 35 écrans » : il est périmé). Chacun a été rapproché d'un fichier Dart (tableau 2.1). **Aucune maquette sans écran** : 54/54 ont un écran. Plusieurs maquettes sont des **états** d'un même écran (Code*/Prestation, Detail*/DetailReservation, Paiement*/Paiement, Retrait*/Retrait, Dossier*/SuiviDossier, ConnexionBloquee/Connexion).
- **Dépistage automatique** (scripts dans le scratchpad, non versionnés) : pour chaque maquette, extraction des textes, des couleurs hexadécimales, des `font-size`, `font-weight`, `border-radius` et des hauteurs 36-64 px, puis recherche dans le Dart de l'écran et des widgets partagés (`core/`). Limites : (1) un texte n'est « absent » que s'il n'est pas retrouvé tel quel, et les **données d'exemple** des maquettes (Salon Fatou, 3 500 FCFA, dates) sont dynamiques dans l'app : ignorées, elles ne sont pas des écarts ; (2) les rayons des boutons valent `hauteur / 2` (`core/widgets/boutons.dart:62`), donc le dépistage signale à tort des rayons 23, 25, 27, 28 : **faux positifs** ; (3) les positions `top/left` des maquettes sont absolues alors que le Dart utilise des `Column`/`Padding` : comparaison **non vérifiée** pixel par pixel.
- **Lecture manuelle** du code pour : connexion, inscription, photos, profil prestataire, en-têtes et boutons partagés. Les autres écrans ont été passés au dépistage seulement ; ce qui n'a pas pu être confirmé est écrit « non vérifié ».
- Rien n'a été lancé sur appareil ni en capture d'écran : **le rendu réel n'a pas été comparé visuellement**. Les écarts ci-dessous sont des écarts de code contre maquette, pas de capture contre capture.

Légende : **BLOQUANT** = change le sens ou empêche d'utiliser ; **VISIBLE** = un utilisateur le remarque en comparant ; **MINEUR** = détail.

## 2.1 Correspondance maquette → écran

| Maquette | Écran Dart (`M:lib/features/`) |
|---|---|
| Connexion, ConnexionBloquee | `auth/connexion_page.dart` |
| Inscription | `auth/inscription_page.dart` |
| MotDePasse | `auth/mot_de_passe_oublie_page.dart` |
| Suspendu | `auth/compte_restreint_page.dart` |
| PrestaInscription1 / 2, PrestaCompte | `inscription_prestataire/etape_activite_page.dart` / `etape_justificatifs_page.dart` / `etape_informations_page.dart` |
| SuiviDossier, DossierRefuse | `inscription_prestataire/suivi_dossier_page.dart` |
| Main | `reservation/accueil_client_page.dart` |
| Prestataires | `reservation/liste_prestataires_page.dart` |
| Fiche, AvisPrestataire | `reservation/fiche_prestataire_page.dart`, `avis_prestataire_page.dart` |
| Creneau, DemandeEnvoyee | `reservation/choix_creneau_page.dart`, `demande_envoyee_page.dart` |
| Reservations | `reservation/mes_reservations_page.dart` |
| DetailEnAttente / DetailAPayer / DetailReservation | `reservation/detail_reservation_page.dart` |
| Paiement, PaiementOk, PaiementEchec | `reservation/paiement_page.dart` |
| Annulation, Avis | `reservation/annulation_reservation.dart`, `avis_page.dart` |
| Profil, ModifierProfil, ChangerMotDePasse | `compte/profil_client_page.dart`, `modifier_profil_page.dart`, `changer_mot_de_passe_page.dart` |
| Notifications, Aide, Litige | `compte/notifications_page.dart`, `aide_page.dart`, `litige_page.dart` |
| Tableau, Agenda | `prestataire/tableau_page.dart`, `agenda_page.dart` |
| PrestaReservations, PrestaDetailDemande, RefusMotif | `prestataire/reservations_prestataire_page.dart`, `detail_demande_page.dart`, `refus_motif_page.dart` |
| Prestation, CodeVerification, CodeValide, CodeFaux | `prestataire/prestation_page.dart` (+ `core/widgets/code_saisie/code_saisie.dart`) |
| PrestaTerminer | `prestataire/terminer_prestation_page.dart` |
| Services, ServiceForm | `prestataire/services_page.dart`, `service_form_page.dart` |
| Disponibilites, PlageHoraire, Conge | `prestataire/disponibilites_page.dart`, `plage_horaire_page.dart`, `indisponibilite_page.dart` |
| Portefeuille, Retrait, RetraitEchec | `prestataire/portefeuille_page.dart`, `retrait_page.dart` |
| PrestaAvis, PrestaLitiges, PrestaLitigeReponse | `prestataire/avis_recus_page.dart`, `litiges_recus_page.dart`, `litige_reponse_page.dart` |
| PrestaPhotos, PrestaProfil | `prestataire/photos_page.dart`, `profil_prestataire_page.dart` |

## 2.2 Écarts systématiques (touchent presque tous les écrans)

| # | Écart | Maquette | Dart | Gravité |
|---|---|---|---|---|
| S1 | **Titre d'en-tête : 28 pt au lieu de 30 pt**, interligne 1,15 (≈ 32 pt) au lieu de 36 pt. Le titre à deux lignes (« Créer votre / compte client ») utilise `headlineMedium` du thème Material, dont la taille par défaut est 28 | 30 px, lh 36 (ex. `Inscription.dc.html:29`, `Aide:28`, `Notifications:29`, `Reservations:22`, `Portefeuille:22`, `ModifierProfil:28`…) | `core/widgets/en_tete_degrade.dart:236-249` (`headlineMedium` + `height: 1.15`, aucune taille imposée) ; `core/theme/theme_sopi.dart:90` (poids seul) | VISIBLE (tous les écrans à en-tête dégradé) |
| S2 | **Texte des boutons : graisse 800 au lieu de 700** (taille 16 correcte) | `Inscription:55` (fw 700) | `core/widgets/boutons.dart:66-69` (`FontWeight.w800`) | MINEUR |
| S3 | **Titre « Connexion » à 32 pt** dans la maquette | `Connexion:27`, `ConnexionBloquee:27` | non confirmé dans `auth/connexion_page.dart` (le dépistage ne retrouve pas `fontSize: 32` dans cet écran ; il existe à `prestataire/profil_prestataire_page.dart:98`) : **non vérifié** | VISIBLE (à confirmer) |
| S4 | **Police chargée à l'exécution** par `google_fonts` (Plus Jakarta Sans), non embarquée dans `assets/` : au premier lancement hors ligne, repli sur la police système | `LISEZ-MOI` : Plus Jakarta Sans | `core/theme/theme_sopi.dart:31` ; seul fichier à importer `google_fonts` (`:2`) | VISIBLE (hors ligne, 1er lancement) ; non vérifié sur appareil |
| S5 | **Couleurs en dur hors du thème central** | `#94a3b8`, `#eef2fa` | `prestataire/disponibilites_page.dart:243` (`0xFF94A3B8`) ; `prestataire/profil_prestataire_page.dart:288` (`0xFFEEF2FA`), `:324` (`0xFF94A3B8`). Recensement limité aux 54 écrans | MINEUR |
| S6 | **Centralisation** : couleurs et dimensions dans `core/theme/couleurs.dart`, `dimensions.dart`, `theme_sopi.dart` ; widgets partagés dans `core/widgets/`. Les couleurs des écrans viennent du thème **sauf S5**. En revanche, les tailles de texte sont souvent écrites en dur : `fontSize: 30` dans `compte/litige_page.dart:220`, `compte/mise_en_page_formulaire.dart:85`, `prestataire/agenda_page.dart:165,173`, `prestataire/tableau_page.dart:179,222`, `reservation/accueil_client_page.dart:278`, `reservation/avis_page.dart:185` | — | — | MINEUR (dette de maintenance) |

Remarque sur S1 : les écrans qui posent `fontSize: 30` à la main (S6) respectent la maquette ; ceux qui passent par `EnTeteDegrade` non. Le titre n'est donc **pas homogène** d'un écran à l'autre.

## 2.3 Écarts par écran (hors S1-S6)

Seuls les écarts **confirmés par lecture ou par recherche textuelle** figurent ici. Pour les maquettes non listées, le dépistage n'a trouvé que S1/S2 et des données d'exemple ; leur conformité sur le reste (ordre, icônes, espacements) est **non vérifiée**.

| Maquette (ligne) | Écran (ligne) | Écart | Gravité |
|---|---|---|---|
| `Inscription:43` « E-mail (facultatif, utile pour récupérer votre compte) » | `auth/inscription_page.dart:175` « E-mail (facultatif) » | texte raccourci | MINEUR |
| `PrestaCompte:46` « E-mail (pour le suivi de votre dossier) » | `inscription_prestataire/etape_informations_page.dart` : libellé non retrouvé | texte absent | MINEUR |
| `CodeFaux:47` « …Après 5 essais, … » | `prestataire/prestation_page.dart:33` : l'erreur vient de l'API ; la mention des 5 essais n'est qu'un commentaire (`depot_prestataire.dart:164`) | dépend du message serveur (429) : non vérifié côté API | MINEUR |
| `CodeValide:37` « Le code est bon. Vous pouvez démarrer la prestation. » | `prestation_page.dart:46` : animation « Code validé » puis relecture ; phrase non retrouvée | texte absent | MINEUR |
| `CodeVerification:38` « Un instant, nous confirmons le code du client. » | texte non retrouvé dans `prestation_page.dart` (la phrase analogue pour le paiement est à `paiement_page.dart:654`) | texte absent | MINEUR |
| `RefusMotif:38` « 3 caractères minimum » | `prestataire/refus_motif_page.dart` : mention absente ; la contrainte n'est pas annoncée | texte absent | MINEUR |
| `Avis:49` « Votre avis est modéré avant d'être affiché. » | `reservation/avis_page.dart:33-34` : phrase remplacée volontairement (l'avis est publié tout de suite, d'après le commentaire du code) | change une promesse faite à l'utilisateur ; écart documenté dans le code mais **absent de la liste des écarts acceptés** : à confirmer par l'architecte | VISIBLE |
| `Prestataires:34` « Rechercher un salon, un service… » | `reservation/liste_prestataires_page.dart:336` « Rechercher un prestataire… » | texte différent | MINEUR |
| `Disponibilites:27` texte d'introduction | `prestataire/disponibilites_page.dart:79` « Vos créneaux des prochaines semaines. Touchez la croix… » | texte différent | MINEUR |
| `Portefeuille:28` « Retrait de 1 000 à 500 000 FCFA » | `prestataire/portefeuille_page.dart:85` : construit à partir des limites du serveur | conforme (dynamique) ; format du premier nombre non vérifié | MINEUR |
| `PlageHoraire:32` « pendant 4 semaines (8 au maximum) » | `prestataire/plage_horaire_page.dart:42,255` | conforme ; la borne 8 est en dur (`:26`) | MINEUR |
| Rayons non retrouvés : `Main:29` 5, `Notifications:42` 5, `DetailAPayer:50` 7, `Litige:36` 19, `Creneau:67` 23, `Fiche:111` 23, `Profil:23` 48, `PaiementOk:23` 56 | — | boutons : faux positifs (rayon = hauteur / 2). Pour les autres valeurs : non confirmées dans le Dart, **non vérifié**, à contrôler sur capture | MINEUR |
| `Connexion`, `PrestaPhotos:25`, `Profil:53` | `auth/connexion_page.dart`, `auth/widgets/mise_en_page_acces.dart`, `prestataire/photos_page.dart:198`, `compte/profil_client_page.dart:200` | lus : textes présents (blocage 15 min à `connexion_page.dart:108`, version lue par `package_info_plus`), structure à deux panneaux pleine page conforme | — |

**Bloquants trouvés en partie 2 : 0.** Aucun écart ne change le sens d'un écran ni n'empêche d'utiliser l'app.

### États particuliers dessinés dans les maquettes

ConnexionBloquee, CodeVerification, CodeValide, CodeFaux, PaiementOk, PaiementEchec, RetraitEchec, DossierRefuse, Suspendu, DetailEnAttente ont chacun une branche ou un écran correspondant (tableau 2.1). Le contenu de l'état `RetraitEchec` n'a pas été lu en détail : **non vérifié**. Les états vide / chargement / erreur non dessinés dans les maquettes sont audités en partie 3.

## 2.4 Écarts connus et acceptés (listés, non comptés comme anomalies)

1. Quartier choisi par une feuille de sélection.
2. Tuile « ajouter une photo » avec bordure pleine.
3. Titres de notifications en gras selon lu / non lu.
4. Confirmation avant la déconnexion.
5. Écran « retrait en cours » sans maquette.
6. Compteur de demandes du tableau de bord : 3 au lieu de 2.
7. Pas de pourcentages (30 %, 10 %) ni de « 2 h », et libellés « Il reste … » dérivés de `reponseAvant` (maquettes `Detail*:36,40,43`, `Paiement:43`, `PrestaDetailDemande:30,32,33`, `PrestaTerminer:30`, `Prestation:41`, `CodeFaux:41`).

Le dépistage confirme seulement le nº 7 (les textes « (30 %) » et « (10 %) » sont absents du Dart). Les autres sont déclarés par l'architecte et n'ont pas été réexaminés.


---

# Partie 3 — Qualité

## 3.1 Analyse statique et tests (exécutés dans le worktree d'audit, `mobile/`)

| Commande | Résultat |
|---|---|
| `flutter analyze` | **Aucun problème** (« No issues found! », 24,6 s). |
| `flutter test` (suite complète) | **531 tests, tous au vert** (« All tests passed! », 4 min 23 s). |

Aucun code n'a été modifié pour obtenir ces résultats. Remarque : `flutter pub get` signale 45 paquets avec une version plus récente incompatible avec les contraintes (information, pas une erreur).

## 3.2 Dépendances (`mobile/pubspec.yaml`)

Utilisation comptée par fichiers de `lib/` important `package:<nom>/` :

| Dépendance | Fichiers | Constat |
|---|---|---|
| `flutter_riverpod` | 68 | utilisée |
| `go_router` | 41 | utilisée |
| `dio` | 10 | utilisée |
| `intl` | 7 | utilisée |
| `flutter_secure_storage`, `google_fonts`, `url_launcher`, `image_picker`, `package_info_plus`, `file_picker` | 1-2 chacune | utilisées |
| `flutter_localizations` | 1 (`main.dart:29`) | utilisée |
| `cupertino_icons` | **0** (aucun `CupertinoIcons` dans `lib/`) | **inutilisée** : MINEUR. Les `GlobalCupertinoLocalizations` de `main.dart:29` viennent de `flutter_localizations`, pas de ce paquet |
| `firebase_messaging` | absent | attendu par le CLAUDE.md pour le push ; voir 1.2 (b) |

Doublons : aucune dépendance en double dans `pubspec.yaml`.

## 3.3 Code mort

- `lib/core/animation/chiffres.dart` (`CompteurAnime`, `BarreAnimee`) : **aucun import** dans `lib/` (recherche du nom du fichier dans tous les `import`). Non vérifié si des tests l'utilisent. MINEUR.
- `lib/core/widgets/carte_sopi.dart` (`CarteSopi`) : jamais importé par `lib/`, seulement testé (`test/core/widgets_test.dart:129-149`). Code mort en production. MINEUR.
- `main.dart` : point d'entrée, non importé (normal).
- **Doublon de widget** : `core/widgets/champ_texte.dart` (`ChampTexte`) et `core/widgets/champ_formulaire.dart` (`ChampFormulaire`) font la même chose (champ avec bascule afficher / masquer le mot de passe : `champ_formulaire.dart:122-135`, `champ_texte.dart:85-95`). `ChampFormulaire` est utilisé par 11 fichiers, `ChampTexte` par un seul. MINEUR (dette).
- Méthode de recherche : rapprochement du nom de fichier, pas une analyse de classes inutilisées à l'intérieur d'un fichier importé ; il peut rester du code mort non détecté.

## 3.4 Boutons inactifs, TODO, écran provisoire

| Recherche (`onPressed: null`, `onTap: () {}`, `onPressed: () {}`, `TODO`, `FIXME`) | Résultat |
|---|---|
| `TODO` / `FIXME` / `onTap: () {}` / `onPressed: () {}` | **Aucun** dans `lib/`. |
| `onPressed: null` | 1 seul : `features/auth/mot_de_passe_oublie_page.dart:142` — **voulu** : bouton « Renvoyer dans N s » désactivé pendant le délai de renvoi ; ce n'est pas un bouton mort. |
| `EcranProvisoire` | défini `core/routing/ecrans_provisoires.dart:6`, utilisé par `_provisoire` (`routeur.dart:133-143`) et en direct à `routeur.dart:163` (route `/`). |

**`EcranProvisoire` est-il jamais affiché ?** Sur les 4 routes provisoires de 1.4 : `/reinitialisation`, `/reservations/:id/annulation` et `/paiement/retour` ne sont jamais ciblées par une navigation (aucun appel à `Chemins.reinitialisation`, `annulation`, `retourPaiement` en dehors de `chemins.dart`) : **inaccessibles par l'interface**. La route `/` (`routeur.dart:160-164`) est le point d'entrée : son affichage dépend de la redirection au démarrage ; **non vérifié** qu'elle redirige toujours avant le rendu (aucun test d'intégration ne le prouve). Un lien profond externe (`/reinitialisation?...`) afficherait en revanche l'écran provisoire : **non testé**.

## 3.5 Gestion des erreurs, états vides, double envoi

Méthode : recherche des pages qui lisent un `AsyncValue` (`.when`, `hasError`, `hasValue`) et comptage des mentions « Réessayer » / composants d'erreur, puis lecture des cas à 0 ou 1.

| Constat | Détail | Gravité |
|---|---|---|
| `portefeuille_page.dart` : l'erreur de solde n'a pas de bouton « Réessayer », mais un texte cliquable | `:109-119` : « Solde indisponible. Toucher pour réessayer. » dans un `InkWell` (cible = une ligne de texte de 13 pt, blanc sur fond dégradé). La relance existe ; la découvrabilité et la taille de cible sont faibles. L'historique du portefeuille n'a pas été vérifié séparément (**non vérifié**) | MINEUR |
| `retrait_page.dart` : aucune mention « Réessayer » | l'état d'erreur de chargement du solde (`solde.hasValue` testé à `:127`) n'a pas été lu au-delà de `:134` ; **non vérifié** ce qui s'affiche quand le chargement échoue (le bouton est-il bloqué ? le message vient-il du serveur ?) | à vérifier |
| Double envoi | Les envois de formulaire lus portent une garde : `connexion_page.dart:34,65` (`_envoi`), `retrait_page.dart:36,56` (`_enCours`), bouton en mode `chargement` (`:314`). Seul `onPressed: () async` hors formulaire : `suivi_dossier_page.dart:176` (« Contacter le support », ouvre la messagerie, sans effet de bord métier) : sans garde mais sans risque. Les autres écrans (paiement, litige, avis, annulation, service, disponibilités) n'ont **pas** été relus un par un pour cette garde : **non vérifié**, couvert en partie par les tests de la suite | non vérifié |
| États vide | Présents là où des tests les couvrent (ex. « Aucun avis pour le moment. » `test/features/reservation/fiche_prestataire_page_test.dart:121-125,199-206`). Les autres écrans de liste n'ont pas été systématiquement relus | non vérifié |

Je n'ai donc **pas** pu établir la liste complète des écrans sans état vide ou sans « Réessayer » ; seuls `portefeuille_page.dart` et `retrait_page.dart` sont remontés par le dépistage et ont été lus partiellement.

## 3.6 Accessibilité (contrôle de code, pas de lecteur d'écran ni de capture)

| Point | Constat | Gravité |
|---|---|---|
| Boutons-icônes sans libellé | Les deux `IconButton` du code (bascule du mot de passe, `champ_formulaire.dart:122`, `champ_texte.dart:85`) ont un `tooltip`. 44 occurrences de `tooltip:`/`Semantics(` pour 60 `IconButton`/`GestureDetector`/`InkWell`. Les cibles « croix » des créneaux (`disponibilites_page.dart:264-273`) et des photos (`photos_page.dart:249-257`) ont un `Semantics`. Les autres `GestureDetector`/`InkWell` n'ont pas été tous relus : **non vérifié** | non vérifié |
| Cibles tactiles trop petites | Croix de suppression de créneau : **26 × 26 px** (`disponibilites_page.dart:272-273`) ; bouton de suppression de photo : hauteur **26 px** (`photos_page.dart:257`) ; cases / pastilles 22 × 22 (`etape_activite_page.dart:143-144,185-186`) ; lien « Solde indisponible… » : une ligne de 13 pt. Le seuil usuel est 44-48 px | VISIBLE (croix créneau et photo) / MINEUR |
| Petits textes | 12 usages de `fontSize: 11` (`notifications_page.dart:178`, `agenda_page.dart:243,431,503`, `tableau_page.dart:333`, `choix_creneau_page.dart:502`, `fiche_prestataire_page.dart:109,395,682`, `widgets/composants.dart:253`, `widgets/liste_avis.dart:218`, `accueil_client_page.dart:616`) | MINEUR |
| Contraste (calculé sur les couleurs de `core/theme/couleurs.dart`) | `texteSecondaire` `#4A5678` sur blanc : 7,26 ✔ ; `texteDiscret` `#64708F` sur blanc : 4,93 ✔, sur `#F7F9FF` : 4,69 ✔ ; `erreurTexte` `#B91C1C` : 6,47 ✔ ; `succesTexte` `#15803D` sur `#DCFCE7` : 4,57 ✔ (seuil 4,5) ; **`#94A3B8` sur blanc : 2,56 ✘** (en dur : `disponibilites_page.dart:243`, `profil_prestataire_page.dart:324`) | VISIBLE si ce gris porte du texte lisible (icône ou texte : **non vérifié** ce que colorie `0xFF94A3B8`) |
| Texte clair sur fond clair | Non recensé de façon exhaustive ; blanc sur dégradé bleu des en-têtes non calculé | non vérifié |

## 3.7 Secrets (dépôt public)

- Fichiers suivis par Git (`git ls-files`) correspondant à `.env`, `*.jks`, `*.keystore`, `key.properties`, `google-services`, `firebase*.json`, `service-account` : **aucun**.
- `.gitignore` (racine) exclut `.env`, `.env.*` (sauf `.env.example`), `*.jks`, `*.keystore`, `key.properties`, `google-services.json` (`.gitignore:2-9`).
- Recherche de motifs de clés (`AKIA…`, clé privée PEM, `sk_live_`, `AIza…`, `password|secret|api_key|token = <16+ caractères>`) sur tout le dépôt suivi, hors verrous, `resources/views` et PDF : 4 occurrences, **toutes factices ou de code** (`admin-web/src/auth/connexion.test.tsx:45` valeur de test « mot-de-passe-factice » ; `backend/tests/.../PayerReservationTest.php:304` et `WebhookPaiementTest.php:73` : constantes de test ; `mobile/lib/features/auth/inscription_page.dart:62` : nom de champ). **Aucun secret trouvé.**
- Limites : la recherche couvre l'arbre courant, **pas l'historique** Git (non vérifié) ; aucune valeur de secret n'est reproduite dans ce rapport.

