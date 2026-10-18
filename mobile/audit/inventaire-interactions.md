# Inventaire automatique des éléments interactifs (partie A)

Généré par `node audit/inventaire_interactions.mjs`. Les colonnes « garde », « erreur » et « navigation » sont des **motifs repérés dans le corps de la méthode appelée**, pas une preuve de comportement : voir la matrice relue à la main dans `VERIFICATION-2.md`.

| Écran | Gestes | Gestes qui appellent l’API | Sans garde de double envoi (à vérifier) | Sans traitement d’erreur repéré (à vérifier) |
|---|---:|---:|---:|---:|
| `features/accueil/accueil_page.dart` | 1 | 0 | 0 | 0 |
| `features/auth/compte_restreint_page.dart` | 2 | 1 | 1 | 1 |
| `features/auth/connexion_page.dart` | 9 | 0 | 0 | 0 |
| `features/auth/inscription_page.dart` | 9 | 2 | 0 | 0 |
| `features/auth/mot_de_passe_oublie_page.dart` | 5 | 0 | 0 | 0 |
| `features/compte/aide_page.dart` | 1 | 0 | 0 | 0 |
| `features/compte/changer_mot_de_passe_page.dart` | 3 | 0 | 0 | 0 |
| `features/compte/litige_page.dart` | 11 | 0 | 0 | 0 |
| `features/compte/modifier_profil_page.dart` | 3 | 0 | 0 | 0 |
| `features/compte/notifications_page.dart` | 3 | 2 | 2 | 1 |
| `features/compte/profil_client_page.dart` | 9 | 3 | 3 | 3 |
| `features/inscription_prestataire/etape_activite_page.dart` | 11 | 0 | 0 | 0 |
| `features/inscription_prestataire/etape_informations_page.dart` | 8 | 0 | 0 | 0 |
| `features/inscription_prestataire/etape_justificatifs_page.dart` | 4 | 0 | 0 | 0 |
| `features/inscription_prestataire/suivi_dossier_page.dart` | 10 | 0 | 0 | 0 |
| `features/prestataire/actions_demande.dart` | 0 | 0 | 0 | 0 |
| `features/prestataire/agenda_page.dart` | 6 | 0 | 0 | 0 |
| `features/prestataire/aiguillage_reservation.dart` | 0 | 0 | 0 | 0 |
| `features/prestataire/avis_recus_page.dart` | 1 | 0 | 0 | 0 |
| `features/prestataire/detail_demande_page.dart` | 4 | 0 | 0 | 0 |
| `features/prestataire/disponibilites_page.dart` | 6 | 1 | 1 | 0 |
| `features/prestataire/indisponibilite_page.dart` | 7 | 0 | 0 | 0 |
| `features/prestataire/litiges_recus_page.dart` | 5 | 0 | 0 | 0 |
| `features/prestataire/litige_reponse_page.dart` | 4 | 1 | 0 | 0 |
| `features/prestataire/photos_page.dart` | 7 | 1 | 1 | 0 |
| `features/prestataire/plage_horaire_page.dart` | 7 | 0 | 0 | 0 |
| `features/prestataire/portefeuille_page.dart` | 2 | 0 | 0 | 0 |
| `features/prestataire/prestation_page.dart` | 5 | 0 | 0 | 0 |
| `features/prestataire/profil_prestataire_page.dart` | 14 | 5 | 5 | 5 |
| `features/prestataire/refus_motif_page.dart` | 4 | 0 | 0 | 0 |
| `features/prestataire/reservations_prestataire_page.dart` | 5 | 0 | 0 | 0 |
| `features/prestataire/retrait_page.dart` | 9 | 0 | 0 | 0 |
| `features/prestataire/services_page.dart` | 3 | 0 | 0 | 0 |
| `features/prestataire/service_form_page.dart` | 6 | 1 | 1 | 0 |
| `features/prestataire/tableau_page.dart` | 7 | 0 | 0 | 0 |
| `features/prestataire/terminer_prestation_page.dart` | 4 | 0 | 0 | 0 |
| `features/reservation/accueil_client_page.dart` | 10 | 0 | 0 | 0 |
| `features/reservation/annulation_reservation.dart` | 2 | 1 | 1 | 0 |
| `features/reservation/avis_page.dart` | 5 | 0 | 0 | 0 |
| `features/reservation/avis_prestataire_page.dart` | 1 | 0 | 0 | 0 |
| `features/reservation/choix_creneau_page.dart` | 7 | 2 | 1 | 0 |
| `features/reservation/demande_envoyee_page.dart` | 2 | 0 | 0 | 0 |
| `features/reservation/detail_reservation_page.dart` | 7 | 0 | 0 | 0 |
| `features/reservation/fiche_prestataire_page.dart` | 6 | 0 | 0 | 0 |
| `features/reservation/liste_prestataires_page.dart` | 12 | 0 | 0 | 0 |
| `features/reservation/mes_reservations_page.dart` | 7 | 0 | 0 | 0 |
| `features/reservation/paiement_page.dart` | 7 | 0 | 0 | 0 |
| **Total** | **261** | **20** | **16** | |

## Détail

### `features/accueil/accueil_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 42 | onPressed | Column | — | — | — | — |

### `features/auth/compte_restreint_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 173 | onPressed | _contacter | — | — | — | — |
| 179 | onPressed | (inline) | sessionProvider.quitterRestriction | **non** | **non** | — |

### `features/auth/connexion_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 126 | onChanged | (inline) | — | — | — | — |
| 140 | onChanged | _envoyer | — | — | — | — |
| 145 | onSubmitted | _envoyer | — | — | — | — |
| 153 | onTap | (inline) | — | — | — | oui |
| 159 | onPressed | _envoyer | — | — | — | — |
| 190 | onTap | (inline) | — | — | — | oui |
| 195 | onTap | (inline) | — | — | — | oui |
| 204 | onTap | (inline) | — | — | — | oui |
| 370 | onTap | (inline) | — | — | — | — |

### `features/auth/inscription_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 145 | onChanged | _effacer | — | — | — | — |
| 158 | onChanged | _effacer | — | — | — | — |
| 172 | onChanged | _effacer | — | — | — | — |
| 184 | onChanged | _effacer | — | — | — | — |
| 195 | onChanged | _effacer | — | — | — | — |
| 206 | onChanged | _effacer | — | — | — | — |
| 207 | onSubmitted | _envoyer | sessionProvider.inscrire | oui | oui | — |
| 222 | onPressed | _envoyer | sessionProvider.inscrire | oui | oui | — |
| 254 | onTap | retourOuConnexion | — | — | — | — |

### `features/auth/mot_de_passe_oublie_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 131 | onPressed | _envoyer | — | — | — | — |
| 142 | onPressed | (inline) | — | — | — | — |
| 179 | onChanged | _envoyer | — | — | — | — |
| 184 | onSubmitted | _envoyer | — | — | — | — |
| 225 | onTap | _contacterSupport | — | — | — | — |

### `features/compte/aide_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 138 | onPressed | _contacter | — | — | — | — |

### `features/compte/changer_mot_de_passe_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 124 | onChanged | _effacer | — | — | — | — |
| 137 | onChanged | _effacer | — | — | — | — |
| 157 | onChanged | _effacer | — | — | — | — |

### `features/compte/litige_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 167 | onRetour | (inline) | — | — | — | — |
| 210 | onPressed | _retour | — | — | — | oui |
| 293 | onPressed | _envoyer | — | — | — | — |
| 393 | onTap | onMotif | — | — | — | — |
| 417 | onChanged | onDescription | — | — | — | — |
| 454 | onPressed | (inline) | — | — | — | — |
| 496 | onTap | (inline) | — | — | — | — |
| 526 | onTap | (inline) | — | — | — | — |
| 606 | onTap | (inline) | — | — | — | — |
| 648 | onTap | (inline) | — | — | — | oui |
| 771 | onPressed | (inline) | — | — | — | — |

### `features/compte/modifier_profil_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 127 | onChanged | _effacer | — | — | — | — |
| 139 | onChanged | _effacer | — | — | — | — |
| 152 | onChanged | _effacer | — | — | — | — |

### `features/compte/notifications_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 89 | onPressed | _toutMarquer | toutMarquerLu, depotCompteProvider.toutMarquerLu | **non** | oui | — |
| 96 | onTap | _ouvrir | marquerLue, depotCompteProvider.marquerLue | **non** | **non** | oui |
| 134 | onTap | (inline) | — | — | — | — |

### `features/compte/profil_client_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 33 | onPressed | deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 37 | onPressed | deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 157 | onTap | (inline) | — | — | — | oui |
| 162 | onTap | (inline) | — | — | — | oui |
| 167 | onTap | (inline) | — | — | — | oui |
| 172 | onTap | (inline) | — | — | — | oui |
| 178 | onTap | (inline) | — | — | — | oui |
| 193 | onPressed | _deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 232 | onTap | (inline) | — | — | — | — |

### `features/inscription_prestataire/etape_activite_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 169 | onPressed | Wrap | — | — | — | — |
| 182 | onTap | _apresModification | — | — | — | — |
| 227 | onChanged | (inline) | — | — | — | — |
| 249 | onTap | _choisirQuartier | — | — | — | — |
| 294 | onChanged | (inline) | — | — | — | — |
| 308 | onRetour | _retour | — | — | — | oui |
| 318 | onChanged | _apresModification | — | — | — | — |
| 331 | onChanged | _continuer | — | — | — | oui |
| 335 | onSubmitted | _continuer | — | — | — | oui |
| 362 | onTap | (inline) | — | — | — | — |
| 424 | onTap | (inline) | — | — | — | oui |

### `features/inscription_prestataire/etape_informations_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 117 | onRetour | _retour | — | — | — | oui |
| 127 | onChanged | _modifier | — | — | — | — |
| 135 | onChanged | _modifier | — | — | — | — |
| 144 | onChanged | _modifier | — | — | — | — |
| 153 | onChanged | _modifier | — | — | — | — |
| 162 | onChanged | _modifier | — | — | — | — |
| 171 | onChanged | _modifier | — | — | — | — |
| 172 | onSubmitted | _continuer | — | — | — | oui |

### `features/inscription_prestataire/etape_justificatifs_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 174 | onRetour | _retour | — | — | — | oui |
| 197 | onTap | _ajouter | — | — | — | — |
| 298 | onPressed | (inline) | — | — | — | — |
| 348 | onTap | (inline) | — | — | — | oui |

### `features/inscription_prestataire/suivi_dossier_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 79 | onPressed | _retourConnexion | — | — | — | — |
| 82 | onPressed | _retourConnexion | — | — | — | — |
| 97 | onPressed | _retourConnexion | — | — | — | — |
| 114 | onPressed | _retourConnexion | — | — | — | — |
| 135 | onPressed | _retourConnexion | — | — | — | — |
| 137 | onPressed | _retourConnexion | — | — | — | — |
| 149 | onPressed | _retourConnexion | — | — | — | — |
| 176 | onPressed | (inline) | — | — | — | — |
| 188 | onPressed | _retourConnexion | — | — | — | — |
| 206 | onPressed | (inline) | — | — | — | — |

### `features/prestataire/agenda_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 111 | onRefresh | setState | — | — | — | — |
| 195 | onTap | onChoix | — | — | — | — |
| 239 | onTap | (inline) | — | — | — | — |
| 369 | onPressed | (inline) | — | — | — | — |
| 383 | onPressed | (inline) | — | — | — | — |
| 414 | onTap | (inline) | — | — | — | oui |

### `features/prestataire/avis_recus_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 20 | onRetour | (inline) | — | — | — | oui |

### `features/prestataire/detail_demande_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 73 | onRetour | (inline) | — | — | — | — |
| 93 | onPressed | _accepter | — | — | — | — |
| 102 | onPressed | _enCours | — | — | — | oui |
| 141 | onPressed | (inline) | — | — | — | — |

### `features/prestataire/disponibilites_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 38 | onPressed | (inline) | — | — | — | oui |
| 42 | onPressed | (inline) | supprimerCreneau, depotPrestataireProvider.supprimerCreneau | **non** | oui | oui |
| 77 | onRetour | _retour | — | — | — | oui |
| 150 | onPressed | (inline) | — | — | — | oui |
| 159 | onPressed | (inline) | — | — | — | oui |
| 270 | onTap | (inline) | — | — | — | — |

### `features/prestataire/indisponibilite_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 99 | onPressed | (inline) | — | — | — | oui |
| 103 | onPressed | (inline) | — | — | — | oui |
| 176 | onChanged | _effacer | — | — | — | — |
| 190 | onChanged | _effacer | — | — | — | — |
| 212 | onRetour | _retour | — | — | — | oui |
| 268 | onPressed | _declarer | — | — | — | oui |
| 277 | onPressed | _retour | — | — | — | oui |

### `features/prestataire/litiges_recus_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 88 | onRefresh | (inline) | — | — | — | oui |
| 97 | onRetour | setState | — | — | — | oui |
| 152 | onTap | onChange | — | — | — | — |
| 242 | onTap | (inline) | — | — | — | oui |
| 280 | onPressed | (inline) | — | — | — | oui |

### `features/prestataire/litige_reponse_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 127 | onRefresh | (inline) | — | — | — | — |
| 137 | onRetour | _retour | — | — | — | oui |
| 159 | onPressed | _envoyer | repondreLitige, depotPrestataireProvider.repondreLitige | oui | oui | — |
| 377 | onChanged | onChange | — | — | — | — |

### `features/prestataire/photos_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 97 | onPressed | (inline) | — | — | — | oui |
| 101 | onPressed | (inline) | supprimerPhoto, depotPrestataireProvider.supprimerPhoto | **non** | oui | oui |
| 177 | onTap | (inline) | — | — | — | — |
| 187 | onRefresh | (inline) | — | — | — | — |
| 196 | onRetour | _retour | — | — | — | oui |
| 254 | onTap | (inline) | — | — | — | — |
| 314 | onTap | (inline) | — | — | — | — |

### `features/prestataire/plage_horaire_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 161 | onRetour | _retour | — | — | — | oui |
| 181 | onTap | _choisirJour | — | — | — | — |
| 197 | onChanged | _effacer | — | — | — | — |
| 211 | onChanged | _effacer | — | — | — | — |
| 269 | onChanged | _enCours | — | — | — | — |
| 328 | onPressed | _creer | — | — | — | — |
| 360 | onTap | onChange | — | — | — | — |

### `features/prestataire/portefeuille_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 111 | onTap | (inline) | — | — | — | — |
| 172 | onTap | (inline) | — | — | — | oui |

### `features/prestataire/prestation_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 60 | onPressed | (inline) | — | — | — | oui |
| 64 | onPressed | (inline) | — | — | — | oui |
| 93 | onRetour | _retour | — | — | — | oui |
| 157 | onPressed | (inline) | — | — | — | — |
| 166 | onPressed | _annuler | — | — | — | oui |

### `features/prestataire/profil_prestataire_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 27 | onPressed | deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 31 | onPressed | deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 156 | onTap | (inline) | — | — | — | oui |
| 161 | onTap | (inline) | — | — | — | oui |
| 166 | onTap | (inline) | — | — | — | oui |
| 172 | onTap | (inline) | — | — | — | oui |
| 177 | onTap | (inline) | — | — | — | oui |
| 186 | onTap | (inline) | — | — | — | oui |
| 191 | onTap | (inline) | — | — | — | oui |
| 196 | onTap | _deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 201 | onTap | _deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 207 | onTap | _deconnecter | sessionProvider.deconnecter | **non** | **non** | oui |
| 271 | onTap | (inline) | — | — | — | — |
| 282 | onTap | (inline) | — | — | — | — |

### `features/prestataire/refus_motif_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 87 | onRetour | _retour | — | — | — | oui |
| 171 | onChanged | setState | — | — | — | — |
| 264 | onPressed | (inline) | — | — | — | — |
| 273 | onPressed | _retour | — | — | — | oui |

### `features/prestataire/reservations_prestataire_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 79 | onRefresh | (inline) | — | — | — | oui |
| 88 | onRetour | setState | — | — | — | oui |
| 143 | onTap | onChange | — | — | — | — |
| 244 | onPressed | (inline) | — | — | — | — |
| 275 | onTap | (inline) | — | — | — | oui |

### `features/prestataire/retrait_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 148 | onTap | (inline) | — | — | — | — |
| 189 | onRetour | _retour | — | — | — | oui |
| 217 | onChanged | (inline) | — | — | — | — |
| 251 | onTap | setState | — | — | — | — |
| 277 | onChanged | _effacer | — | — | — | — |
| 323 | onPressed | _envoyer | — | — | — | — |
| 367 | onTap | (inline) | — | — | — | — |
| 430 | onRetour | (inline) | — | — | — | — |
| 511 | onPressed | (inline) | — | — | — | — |

### `features/prestataire/services_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 37 | onRetour | _retour | — | — | — | oui |
| 87 | onPressed | (inline) | — | — | — | oui |
| 158 | onTap | (inline) | — | — | — | oui |

### `features/prestataire/service_form_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 49 | onAction | (inline) | — | — | — | oui |
| 207 | onRetour | _retour | — | — | — | oui |
| 230 | onChanged | _effacer | — | — | — | — |
| 265 | onChanged | _effacer | — | — | — | — |
| 317 | onChanged | _enCours | — | — | — | — |
| 346 | onPressed | _enregistrer | creerPrestation, modifierPrestation | **non** | oui | — |

### `features/prestataire/tableau_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 74 | onRefresh | _actualiser | — | — | — | — |
| 121 | onTap | (inline) | — | — | — | oui |
| 132 | onTap | (inline) | — | — | — | oui |
| 139 | onTap | (inline) | — | — | — | oui |
| 233 | onTap | (inline) | — | — | — | — |
| 281 | onTap | (inline) | — | — | — | oui |
| 449 | onPressed | (inline) | — | — | — | — |

### `features/prestataire/terminer_prestation_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 45 | onPressed | (inline) | — | — | — | oui |
| 49 | onPressed | (inline) | — | — | — | oui |
| 84 | onRetour | _retour | — | — | — | oui |
| 128 | onPressed | _terminer | — | — | — | oui |

### `features/reservation/accueil_client_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 138 | onRefresh | (inline) | — | — | — | — |
| 201 | onTap | (inline) | — | — | — | oui |
| 212 | onTap | (inline) | — | — | — | oui |
| 219 | onTap | (inline) | — | — | — | oui |
| 293 | onTap | (inline) | — | — | — | oui |
| 339 | onTap | (inline) | — | — | — | — |
| 376 | onTap | (inline) | — | — | — | — |
| 481 | onPressed | (inline) | — | — | — | oui |
| 597 | onTap | (inline) | — | — | — | oui |
| 658 | onTap | (inline) | — | — | — | oui |

### `features/reservation/annulation_reservation.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 177 | onPressed | _enCours | — | — | — | oui |
| 184 | onPressed | _annuler | annuler, depotReservationProvider.annuler | **non** | oui | oui |

### `features/reservation/avis_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 170 | onPressed | _enCours | — | — | — | oui |
| 263 | onPressed | _publier | — | — | — | oui |
| 303 | onTap | (inline) | — | — | — | — |
| 387 | onTap | onNote | — | — | — | — |
| 440 | onChanged | onCommentaire | — | — | — | — |

### `features/reservation/avis_prestataire_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 29 | onRetour | (inline) | — | — | — | oui |

### `features/reservation/choix_creneau_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 258 | onRefresh | _charger | agenda | **non** | oui | — |
| 269 | onRetour | (inline) | — | — | — | oui |
| 303 | onPressed | (inline) | — | — | — | — |
| 357 | onTap | _choisirJour | — | — | — | — |
| 399 | onTap | _choisir | liberer, verrouiller | oui | oui | — |
| 487 | onTap | (inline) | — | — | — | — |
| 545 | onTap | (inline) | — | — | — | — |

### `features/reservation/demande_envoyee_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 164 | onPressed | _voirReservation | — | — | — | oui |
| 169 | onPressed | (inline) | — | — | — | oui |

### `features/reservation/detail_reservation_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 182 | onRefresh | _actualiser | — | — | — | — |
| 358 | onPressed | (inline) | — | — | — | — |
| 390 | onPressed | _aller | — | — | — | oui |
| 402 | onPressed | _appeler | — | — | — | — |
| 427 | onPressed | _aller | — | — | — | oui |
| 484 | onPressed | (inline) | — | — | — | oui |
| 711 | onTap | (inline) | — | — | — | — |

### `features/reservation/fiche_prestataire_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 190 | onChoisir | setState | — | — | — | — |
| 199 | onRefresh | _actualiser | — | — | — | — |
| 230 | onPressed | (inline) | — | — | — | oui |
| 273 | onPressed | (inline) | — | — | — | oui |
| 548 | onTap | onChoisir | — | — | — | — |
| 624 | onTap | (inline) | — | — | — | — |

### `features/reservation/liste_prestataires_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 151 | onAction | (inline) | — | — | — | — |
| 172 | onRefresh | (inline) | — | — | — | — |
| 272 | onPressed | (inline) | — | — | — | oui |
| 295 | onTap | (inline) | — | — | — | — |
| 330 | onChanged | (inline) | — | — | — | — |
| 363 | onTap | (inline) | — | — | — | — |
| 369 | onTap | (inline) | — | — | — | — |
| 376 | onTap | (inline) | — | — | — | — |
| 415 | onTap | (inline) | — | — | — | — |
| 498 | onPressed | (inline) | — | — | — | — |
| 523 | onTap | (inline) | — | — | — | — |
| 611 | onPressed | (inline) | — | — | — | — |

### `features/reservation/mes_reservations_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 77 | onRefresh | (inline) | — | — | — | — |
| 137 | onTap | onChange | — | — | — | — |
| 237 | onPressed | (inline) | — | — | — | — |
| 267 | onTap | (inline) | — | — | — | oui |
| 359 | onPressed | ouvrirAnnulation | — | — | — | — |
| 393 | onPressed | (inline) | — | — | — | oui |
| 412 | onPressed | (inline) | — | — | — | oui |

### `features/reservation/paiement_page.dart`

| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |
|---:|---|---|---|:-:|:-:|:-:|
| 317 | onPressed | (inline) | — | — | — | oui |
| 567 | onPressed | toString | — | — | — | — |
| 691 | onPressed | (inline) | — | — | — | — |
| 871 | onPressed | (inline) | — | — | — | oui |
| 878 | onPressed | (inline) | — | — | — | oui |
| 920 | onPressed | (inline) | — | — | — | oui |
| 923 | onPressed | (inline) | — | — | — | oui |