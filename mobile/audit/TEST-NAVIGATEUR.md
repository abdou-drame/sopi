# Test navigateur 1 — rapport (partiel)

Branche `feature/p4f-test-navigateur`, non fusionnée. App Flutter web (release) pilotée par Playwright (Chrome, 390x844) contre un backend Laravel local jetable (127.0.0.1, SQLite, `PAYMENT_DRIVER=fake`). Aucun appel à la production, aucune clé réelle. Scripts : `mobile/test_navigateur/`. Captures : `mobile/test_navigateur/captures/` (ignorées par git).

**Ce test est partiel** : les parcours A à E1 sont faits. E2, F, G et la comparaison visuelle avec les maquettes n'ont pas été réalisés (arrêt demandé pour limiter la consommation).

## Parcours réussis
- A : inscription prestataire (dossier, pièces jointes).
- B : gestion prestataire (services, disponibilités, périodes).
- C1 (17 contrôles) : inscription client, connexion refusée/valide, recherche, tri, filtres quartier/note/prix, texte libre.
- C2 (21) : fiche, créneau avec verrou, réservation, acceptation, paiement, webhook signé (signature invalide refusée 4xx, rejeu idempotent), échec puis réessai, retour dans l'app, réservation confirmée avec code.
- D / D2 (8 + 8) : demandes, acceptation, refus avec motif, annulation prestataire (remboursée), annulation client (acompte non remboursé), onglet Annulées.
- E1 : mauvais code refusé, réservation inchangée, bon code → `en_cours`, écran « Terminer la prestation ».

## Échecs / points non résolus
- **Frappe tronquée sur le code de démarrage** : à la première saisie sur l'écran d'un 2e rendez-vous, seuls 3 caractères sur 6 sont retenus (reproductible, 3 exécutions sur 3). Le code n'est donc pas envoyé. Cause non établie (simulation de clavier de Playwright ou perte de focus après un rechargement de l'écran). À vérifier sur téléphone.
- Conséquence : le blocage « Trop de tentatives » (5 essais/min) n'a pas pu être vérifié par ce parcours (il l'avait été lors d'un essai antérieur avec 7 essais : blocage au 7e).
- Les assertions correspondantes sont des observations, pas des vérifications.

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
