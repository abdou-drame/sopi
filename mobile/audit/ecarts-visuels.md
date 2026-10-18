# Écarts visuels mesurables — maquettes `.dc.html` contre code Dart

Généré par `node audit/comparer_maquettes.mjs` (ne pas éditer à la main). Chaque ligne « absent » est un **signal à vérifier**, pas une preuve : voir les limites dans `VERIFICATION-2.md`.

| Maquette | Écran Dart | Valeurs | Trouvées dans l’écran | Trouvées ailleurs (core/ ou dossier) | Absentes | VISIBLE | MINEUR |
|---|---|---:|---:|---:|---:|---:|---:|
| Agenda | `features/prestataire/agenda_page.dart` | 42 | 26 | 16 | 0 | 0 | 0 |
| Aide | `features/compte/aide_page.dart` | 27 | 7 | 19 | 1 | 0 | 1 |
| Annulation | `features/reservation/annulation_reservation.dart` | 28 | 14 | 13 | 1 | 0 | 1 |
| Avis | `features/reservation/avis_page.dart` | 36 | 14 | 22 | 0 | 0 | 0 |
| AvisPrestataire | `features/reservation/avis_prestataire_page.dart` | 33 | 0 | 33 | 0 | 0 | 0 |
| ChangerMotDePasse | `features/compte/changer_mot_de_passe_page.dart` | 34 | 2 | 31 | 1 | 0 | 1 |
| CodeFaux | `features/prestataire/prestation_page.dart` | 38 | 6 | 31 | 1 | 0 | 1 |
| CodeValide | `features/prestataire/prestation_page.dart` | 38 | 6 | 29 | 3 | 0 | 3 |
| CodeVerification | `features/prestataire/prestation_page.dart` | 35 | 6 | 27 | 2 | 0 | 2 |
| Conge | `features/prestataire/indisponibilite_page.dart` | 39 | 8 | 29 | 2 | 0 | 2 |
| Connexion | `features/auth/connexion_page.dart` | 43 | 20 | 23 | 0 | 0 | 0 |
| ConnexionBloquee | `features/auth/connexion_page.dart + features/auth/compte_restreint_page.dart` | 47 | 21 | 26 | 0 | 0 | 0 |
| Creneau | `features/reservation/choix_creneau_page.dart` | 45 | 22 | 22 | 1 | 0 | 1 |
| DemandeEnvoyee | `features/reservation/demande_envoyee_page.dart` | 36 | 14 | 19 | 3 | 0 | 3 |
| DetailAPayer | `features/reservation/detail_reservation_page.dart` | 41 | 18 | 21 | 2 | 0 | 2 |
| DetailEnAttente | `features/reservation/detail_reservation_page.dart` | 39 | 16 | 20 | 3 | 0 | 3 |
| DetailReservation | `features/reservation/detail_reservation_page.dart` | 43 | 20 | 21 | 2 | 0 | 2 |
| Disponibilites | `features/prestataire/disponibilites_page.dart` | 41 | 15 | 25 | 1 | 0 | 1 |
| DossierRefuse | `features/inscription_prestataire/suivi_dossier_page.dart` | 38 | 16 | 20 | 2 | 0 | 2 |
| Fiche | `features/reservation/fiche_prestataire_page.dart` | 45 | 30 | 14 | 1 | 0 | 1 |
| Inscription | `features/auth/inscription_page.dart` | 38 | 9 | 28 | 1 | 0 | 1 |
| Litige | `features/compte/litige_page.dart` | 40 | 18 | 22 | 0 | 0 | 0 |
| Main | `features/reservation/accueil_client_page.dart` | 51 | 23 | 26 | 2 | 0 | 2 |
| ModifierProfil | `features/compte/modifier_profil_page.dart` | 35 | 2 | 32 | 1 | 0 | 1 |
| MotDePasse | `features/auth/mot_de_passe_oublie_page.dart` | 42 | 4 | 38 | 0 | 0 | 0 |
| Notifications | `features/compte/notifications_page.dart` | 31 | 11 | 19 | 1 | 0 | 1 |
| Paiement | `features/reservation/paiement_page.dart` | 38 | 19 | 16 | 3 | 0 | 3 |
| PaiementEchec | `features/reservation/paiement_page.dart` | 32 | 16 | 15 | 1 | 0 | 1 |
| PaiementOk | `features/reservation/paiement_page.dart` | 31 | 14 | 16 | 1 | 0 | 1 |
| PlageHoraire | `features/prestataire/plage_horaire_page.dart` | 44 | 11 | 31 | 2 | 0 | 2 |
| Portefeuille | `features/prestataire/portefeuille_page.dart` | 42 | 20 | 22 | 0 | 0 | 0 |
| PrestaAvis | `features/prestataire/avis_recus_page.dart` | 33 | 0 | 32 | 1 | 0 | 1 |
| PrestaCompte | `features/inscription_prestataire/etape_informations_page.dart` | 36 | 1 | 34 | 1 | 0 | 1 |
| PrestaDetailDemande | `features/prestataire/detail_demande_page.dart` | 41 | 20 | 20 | 1 | 0 | 1 |
| PrestaInscription1 | `features/inscription_prestataire/etape_activite_page.dart` | 39 | 14 | 24 | 1 | 0 | 1 |
| PrestaInscription2 | `features/inscription_prestataire/etape_justificatifs_page.dart` | 42 | 16 | 25 | 1 | 0 | 1 |
| PrestaLitigeReponse | `features/prestataire/litige_reponse_page.dart` | 45 | 22 | 23 | 0 | 0 | 0 |
| PrestaLitiges | `features/prestataire/litiges_recus_page.dart` | 39 | 19 | 20 | 0 | 0 | 0 |
| PrestaPhotos | `features/prestataire/photos_page.dart` | 21 | 8 | 13 | 0 | 0 | 0 |
| PrestaProfil | `features/prestataire/profil_prestataire_page.dart` | 43 | 20 | 22 | 1 | 0 | 1 |
| PrestaReservations | `features/prestataire/reservations_prestataire_page.dart` | 43 | 15 | 28 | 0 | 0 | 0 |
| PrestaTerminer | `features/prestataire/terminer_prestation_page.dart` | 36 | 2 | 33 | 1 | 0 | 1 |
| Prestataires | `features/reservation/liste_prestataires_page.dart` | 39 | 19 | 19 | 1 | 0 | 1 |
| Prestation | `features/prestataire/prestation_page.dart` | 37 | 6 | 29 | 2 | 0 | 2 |
| Profil | `features/compte/profil_client_page.dart` | 40 | 13 | 26 | 1 | 0 | 1 |
| RefusMotif | `features/prestataire/refus_motif_page.dart` | 39 | 15 | 23 | 1 | 0 | 1 |
| Reservations | `features/reservation/mes_reservations_page.dart` | 45 | 16 | 29 | 0 | 0 | 0 |
| Retrait | `features/prestataire/retrait_page.dart` | 48 | 23 | 24 | 1 | 0 | 1 |
| RetraitEchec | `features/prestataire/retrait_page.dart` | 48 | 23 | 24 | 1 | 0 | 1 |
| ServiceForm | `features/prestataire/service_form_page.dart` | 46 | 13 | 31 | 2 | 0 | 2 |
| Services | `features/prestataire/services_page.dart` | 34 | 10 | 23 | 1 | 0 | 1 |
| SuiviDossier | `features/inscription_prestataire/suivi_dossier_page.dart` | 39 | 16 | 21 | 2 | 0 | 2 |
| Suspendu | `features/auth/compte_restreint_page.dart` | 33 | 12 | 19 | 2 | 0 | 2 |
| Tableau | `features/prestataire/tableau_page.dart` | 47 | 23 | 22 | 2 | 0 | 2 |
| **Total** | | **2105** | **754** | **1290** | **61** | **0** | **61** |

## Détail des valeurs absentes

### Aide

- MINEUR · width = 100 px

### Annulation

- MINEUR · border-radius = 25 px

### ChangerMotDePasse

- MINEUR · width = 100 px

### CodeFaux

- MINEUR · border-radius = 25 px

### CodeValide

- MINEUR · height = 96 px
- MINEUR · width = 96 px
- MINEUR · width = 100 px

### CodeVerification

- MINEUR · height = 96 px
- MINEUR · width = 96 px

### Conge

- MINEUR · border-radius = 25 px
- MINEUR · width = 100 px

### Creneau

- MINEUR · border-radius = 23 px

### DemandeEnvoyee

- MINEUR · border-radius = 52 px
- MINEUR · border-radius = 25 px
- MINEUR · padding = 36 px

### DetailAPayer

- MINEUR · border-radius = 7 px
- MINEUR · border-radius = 25 px

### DetailEnAttente

- MINEUR · border-radius = 7 px
- MINEUR · border-radius = 25 px
- MINEUR · width = 100 px

### DetailReservation

- MINEUR · border-radius = 7 px
- MINEUR · border-radius = 25 px

### Disponibilites

- MINEUR · border-radius = 25 px

### DossierRefuse

- MINEUR · border-radius = 52 px
- MINEUR · border-radius = 25 px

### Fiche

- MINEUR · border-radius = 23 px

### Inscription

- MINEUR · width = 100 px

### Main

- MINEUR · border-radius = 5 px
- MINEUR · width = 52 px

### ModifierProfil

- MINEUR · width = 100 px

### Notifications

- MINEUR · border-radius = 5 px

### Paiement

- MINEUR · border-radius = 17 px
- MINEUR · height = 56 px
- MINEUR · width = 100 px

### PaiementEchec

- MINEUR · border-radius = 56 px

### PaiementOk

- MINEUR · border-radius = 56 px

### PlageHoraire

- MINEUR · border-radius = 11 px
- MINEUR · width = 100 px

### PrestaAvis

- MINEUR · height = 92 px

### PrestaCompte

- MINEUR · width = 100 px

### PrestaDetailDemande

- MINEUR · border-radius = 25 px

### PrestaInscription1

- MINEUR · width = 100 px

### PrestaInscription2

- MINEUR · width = 100 px

### PrestaProfil

- MINEUR · width = 100 px

### PrestaTerminer

- MINEUR · border-radius = 25 px

### Prestataires

- MINEUR · height = 56 px

### Prestation

- MINEUR · border-radius = 1 px
- MINEUR · border-radius = 25 px

### Profil

- MINEUR · width = 100 px

### RefusMotif

- MINEUR · border-radius = 25 px

### Retrait

- MINEUR · width = 100 px

### RetraitEchec

- MINEUR · width = 100 px

### ServiceForm

- MINEUR · border-radius = 11 px
- MINEUR · width = 100 px

### Services

- MINEUR · width = 100 px

### SuiviDossier

- MINEUR · border-radius = 52 px
- MINEUR · border-radius = 25 px

### Suspendu

- MINEUR · border-radius = 52 px
- MINEUR · border-radius = 25 px

### Tableau

- MINEUR · border-radius = 5 px
- MINEUR · border-radius = 21 px
