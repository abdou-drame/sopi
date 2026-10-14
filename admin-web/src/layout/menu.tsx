import type { ReactNode } from 'react'
import {
  IconeAccueil,
  IconeAvis,
  IconeCloche,
  IconeDossier,
  IconeLitige,
  IconePaiement,
  IconeRetrait,
  IconeReservation,
  IconeRevenus,
  IconeSanction,
  IconeUtilisateurs,
} from '../components/ui/Icones'
import { L } from '../i18n/libelles'

export interface EntreeMenu {
  chemin: string
  libelle: string
  icone: ReactNode
  /** Écran livré dans cette étape (les autres affichent « bientôt disponible »). */
  disponible: boolean
}

export interface GroupeMenu {
  titre: string
  entrees: EntreeMenu[]
}

// Menu complet : les 12 écrans du back-office (l'écran de connexion est hors menu).
export const MENU: GroupeMenu[] = [
  {
    titre: L.menu.groupePilotage,
    entrees: [
      { chemin: '/', libelle: L.menu.tableauDeBord, icone: <IconeAccueil />, disponible: true },
      { chemin: '/revenus', libelle: L.menu.revenus, icone: <IconeRevenus />, disponible: true },
    ],
  },
  {
    titre: L.menu.groupeModeration,
    entrees: [
      { chemin: '/dossiers-prestataires', libelle: L.menu.demandes, icone: <IconeDossier />, disponible: false },
      { chemin: '/litiges', libelle: L.menu.litiges, icone: <IconeLitige />, disponible: false },
      { chemin: '/sanctions', libelle: L.menu.sanctions, icone: <IconeSanction />, disponible: false },
      { chemin: '/avis', libelle: L.menu.avis, icone: <IconeAvis />, disponible: false },
    ],
  },
  {
    titre: L.menu.groupeActivite,
    entrees: [
      { chemin: '/utilisateurs', libelle: L.menu.utilisateurs, icone: <IconeUtilisateurs />, disponible: false },
      { chemin: '/reservations', libelle: L.menu.reservations, icone: <IconeReservation />, disponible: false },
      { chemin: '/paiements', libelle: L.menu.paiements, icone: <IconePaiement />, disponible: false },
      { chemin: '/retraits', libelle: L.menu.retraits, icone: <IconeRetrait />, disponible: false },
    ],
  },
  {
    titre: L.menu.groupeSysteme,
    entrees: [
      { chemin: '/notifications-en-echec', libelle: L.menu.notifications, icone: <IconeCloche />, disponible: false },
    ],
  },
]

export const ENTREES_A_VENIR = MENU.flatMap((g) => g.entrees).filter((e) => !e.disponible)
