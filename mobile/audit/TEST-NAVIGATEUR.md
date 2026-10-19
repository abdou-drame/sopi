# Test navigateur 1 — rapport (partiel)

Branche `feature/p4f-test-navigateur`, non fusionnée. App Flutter web (release) pilotée par Playwright (Chrome, 390x844) contre un backend Laravel local jetable (127.0.0.1, SQLite, `PAYMENT_DRIVER=fake`). Aucun appel à la production, aucune clé réelle. Scripts : `mobile/test_navigateur/`. Captures : `mobile/test_navigateur/captures/` (ignorées par git).

**Ce test est partiel** : les parcours A à E1 sont faits. E2, F, G et la comparaison visuelle avec les maquettes n'ont pas été réalisés (arrêt demandé pour limiter la consommation).

## Parcours réussis
- A : inscription prestataire (dossier, pièces jointes).
- B : gestion prestataire (services, disponibilités, périodes).
- C1 (17 contrôles) : inscription client, connexion refusée/valide, recherche, tri, filtres quartier/note/prix, texte libre.
- C2 (21) : fiche, créneau avec verrou, réservation, acceptation, paiement, webhook signé (signature invalide refusée 4xx, rejeu idempotent), échec puis réessai, retour dans l'app, réservation confirmée avec code.
- D / D2 (8 + 8) : demandes, acceptation, refus avec motif, annulation prestataire (remboursée), annulation client (acompte non remboursé), onglet Annulées.
- E1 (exécution du 18 oct.) : mauvais code refusé, réservation inchangée, bon code → `en_cours`, écran « Terminer la prestation ».

## Défaut du code de démarrage : élucidé, ce n'est pas un bug de l'app
- Symptôme : le champ ne gardait que 3 caractères sur 6 (« B A D ») avec le code d'essai `BAD111`.
- Cause : le formateur de l'app (`FormateurCode`, `lib/core/widgets/code_saisie/code_saisie.dart`) retire tout caractère hors alphabet (sans 0, O, 1, I, L), comme le serveur. Les « 1 » de `BAD111` étaient donc supprimés. Aucun contrôleur recréé, aucun focus qui saute, aucun rebuild fautif.
- Vérification : tests widget ajoutés dans `mobile/test/core/code_saisie_test.dart` (frappe caractère par caractère, 6 d'un coup, minuscules, `BAD111`) : tous verts, aucune correction de l'app nécessaire.
- Le parcours E1 utilise désormais des codes valides (`BAD222`…) et le contrôle « Trop de tentatives » (au-delà de 5 essais par minute) y est de nouveau un vrai contrôle.
- **Limite honnête : cette version de E1 n'a pas été rejouée en exécution complète** après la correction. Le blocage avait été constaté lors d'un essai antérieur (7e essai). À revérifier lors d'un prochain passage.

## Parcours : dates et lancement
- Les créneaux semés suivent la date du jour. Les jours codés en dur dans les parcours (« Lun. 19 »…) ont été remplacés par des dates calculées (`etiqJour`, `etiqDate` dans `lib.mjs`) ; ces parcours modifiés n'ont pas été rejoués après le changement de date.
- Les parcours doivent être lancés en premier plan : en arrière-plan, l'app web ne s'affiche pas dans Chrome (aucun contrôle exécuté).

## Écarts constatés, non corrigés
- Le toast « Service enregistré » recouvre le bouton « Ajouter un service ».
- Sur une ligne de service, seule l'icône crayon est tactile.
- « 0 prestataires disponibles » : pluriel à 0.
- Filtre note ≥ 4 : les prestataires sans avis sont exclus (`RechercheService.php:65-67`). Comportement backend, à décider.
- Le filtre prix 2 000–10 000 renvoie 3 prestataires (affichage « À partir de »).
- Périodes inversées : validation locale puis confirmation, le serveur tranche (422).
- Aucun écart visuel n'a été corrigé : la comparaison avec les maquettes n'a pas été faite.

## Confidentialité
Le prestataire voit le client sous la forme « Awa N. » avant confirmation.

## Non testable en navigateur
Clavier réel du téléphone, toucher, polices du téléphone, notifications push, caméra (galerie seulement), ouverture réelle de Wave/Orange Money (`launchUrl` appelé sans geste utilisateur : Chrome headless n'ouvre aucun onglet ; le retour dans l'app a été simulé par `visibilitychange`), paiement réel.

## Hors périmètre signalé
36 échecs de tests backend préexistants (PHP 8.2 local). `php artisan serve` plante parfois (`Undefined array key 1`) : contourné par `php -S`.

## Vérifications
`flutter analyze` : aucun problème. `flutter test` : 536 tests verts. Backend local arrêté, `backend/.env` jetable et comptes supprimés. Aucun secret dans le dépôt.
