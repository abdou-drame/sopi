import type { ReactNode } from 'react'
import {
  IconeAccueil,
  IconeAvis,
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
      { chemin: '/', libelle: L.menu.tableauDeBord, icone: <IconeAccueil /> },
      { chemin: '/revenus', libelle: L.menu.revenus, icone: <IconeRevenus /> },
    ],
  },
  {
    titre: L.menu.groupeModeration,
    entrees: [
      { chemin: '/dossiers-prestataires', libelle: L.menu.demandes, icone: <IconeDossier /> },
      { chemin: '/litiges', libelle: L.menu.litiges, icone: <IconeLitige /> },
      { chemin: '/sanctions', libelle: L.menu.sanctions, icone: <IconeSanction /> },
      { chemin: '/avis', libelle: L.menu.avis, icone: <IconeAvis /> },
    ],
  },
  {
    titre: L.menu.groupeActivite,
    entrees: [
      { chemin: '/utilisateurs', libelle: L.menu.utilisateurs, icone: <IconeUtilisateurs /> },
      { chemin: '/reservations', libelle: L.menu.reservations, icone: <IconeReservation /> },
      { chemin: '/paiements', libelle: L.menu.paiements, icone: <IconePaiement /> },
      { chemin: '/retraits', libelle: L.menu.retraits, icone: <IconeRetrait /> },
    ],
  },

]
