# Correspondance maquettes → fichiers Dart (p4q)

Chemins relatifs à `mobile/lib/`. Une maquette = un état d'écran : plusieurs maquettes peuvent partager un fichier Dart.

## Authentification et inscriptions

| Maquette | Fichier Dart |
|---|---|
| Orientation | features/auth/orientation_page.dart |
| Connexion | features/auth/connexion_page.dart |
| ConnexionBloquee | features/auth/connexion_page.dart (état « bloqué ») |
| Inscription | features/auth/inscription_page.dart |
| MotDePasse | features/auth/mot_de_passe_oublie_page.dart |
| PrestaInscription1 | features/inscription_prestataire/etape_activite_page.dart |
| PrestaCompte | features/inscription_prestataire/etape_informations_page.dart |
| PrestaInscription2 | features/inscription_prestataire/etape_justificatifs_page.dart |
| SuiviDossier | features/inscription_prestataire/suivi_dossier_page.dart |
| DossierRefuse | features/inscription_prestataire/suivi_dossier_page.dart (`_DossierRefuse`) |
| Suspendu | features/auth/compte_restreint_page.dart |
| ChangerMotDePasse | features/compte/changer_mot_de_passe_page.dart |

## Parcours client

| Maquette | Fichier Dart |
|---|---|
| Main | features/reservation/accueil_client_page.dart |
| Prestataires | features/reservation/liste_prestataires_page.dart |
| Fiche | features/reservation/fiche_prestataire_page.dart |
| Creneau | features/reservation/choix_creneau_page.dart |
| DemandeEnvoyee | features/reservation/demande_envoyee_page.dart |
| Paiement | features/reservation/paiement_page.dart |
| PaiementOk | features/reservation/paiement_page.dart (`_Succes`) |
| PaiementEchec | features/reservation/paiement_page.dart (`_Echec`) |
| Reservations | features/reservation/mes_reservations_page.dart |
| DetailEnAttente | features/reservation/detail_reservation_page.dart (statut EN_ATTENTE) |
| DetailAPayer | features/reservation/detail_reservation_page.dart (statut ACCEPTEE) |
| DetailReservation | features/reservation/detail_reservation_page.dart (CONFIRMEE et suivantes) |
| Annulation | features/reservation/annulation_reservation.dart (feuille) |
| Avis | features/reservation/avis_page.dart |
| Litige | features/compte/litige_page.dart |
| Notifications | features/compte/notifications_page.dart |
| Profil | features/compte/profil_client_page.dart |
| ModifierProfil | features/compte/modifier_profil_page.dart |
| Aide | features/compte/aide_page.dart |
| AvisPrestataire | features/reservation/avis_prestataire_page.dart + widgets/liste_avis.dart |

## Parcours prestataire

| Maquette | Fichier Dart |
|---|---|
| Tableau | features/prestataire/tableau_page.dart |
| PrestaReservations | features/prestataire/reservations_prestataire_page.dart |
| PrestaDetailDemande | features/prestataire/detail_demande_page.dart |
| RefusMotif | features/prestataire/refus_motif_page.dart |
| Agenda | features/prestataire/agenda_page.dart |
| Prestation | features/prestataire/prestation_page.dart |
| CodeVerification | features/prestataire/prestation_page.dart + core/widgets/code_saisie (vérification) |
| CodeValide | idem (état « code valide ») |
| CodeFaux | idem (état « code faux ») |
| PrestaTerminer | features/prestataire/terminer_prestation_page.dart |
| Services | features/prestataire/services_page.dart |
| ServiceForm | features/prestataire/service_form_page.dart |
| Disponibilites | features/prestataire/disponibilites_page.dart |
| Conge | features/prestataire/indisponibilite_page.dart |
| PlageHoraire | features/prestataire/plage_horaire_page.dart |
| Portefeuille | features/prestataire/portefeuille_page.dart |
| Retrait | features/prestataire/retrait_page.dart |
| RetraitEchec | features/prestataire/retrait_page.dart (`_Confirmation`, état échec) |
| PrestaAvis | features/prestataire/avis_recus_page.dart + widgets/liste_avis.dart |
| PrestaLitiges | features/prestataire/litiges_recus_page.dart |
| PrestaLitigeReponse | features/prestataire/litige_reponse_page.dart |
| PrestaProfil | features/prestataire/profil_prestataire_page.dart |
| PrestaPhotos | features/prestataire/photos_page.dart |

## (a) Maquettes sans écran dédié dans l'app

Aucune maquette n'est totalement absente : les 55 trouvent un fichier. Cas particuliers (état d'un écran, pas un fichier propre) : ConnexionBloquee, DossierRefuse, PaiementOk, PaiementEchec, DetailEnAttente, DetailAPayer, CodeValide, CodeFaux, RetraitEchec, Annulation (feuille modale). À confirmer à la lecture de chaque écran.

## (b) Écrans de l'app sans maquette (non touchés)

- Démarrage (`Chemins.demarrage`, écran de lancement dans core/routing/routeur.dart)
- État de l'API (features/accueil/accueil_page.dart, `AccueilPage`)
- Mes signalements et Détail d'un signalement client (features/compte/signalements_pages.dart)
- Feuilles de filtre Quartier / Note / Prix / Tri (features/reservation/widgets/feuilles_filtres.dart)
- Feuilles de choix de source photo (compte/litige_page.dart, inscription_prestataire/etape_justificatifs_page.dart, prestataire/photos_page.dart), feuille de choix de quartier (etape_activite_page.dart)
- Boîtes de dialogue de confirmation (déconnexion, suppression de photo, etc.)

## Remarques

- Les anciennes maquettes `.dc.html` restent dans `mobile/maquettes/maquettes/` (suivies par git, non touchées).
- Police : l'app utilise déjà Plus Jakarta Sans via `google_fonts` (core/theme/theme_sopi.dart).
