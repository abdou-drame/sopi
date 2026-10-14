import { useQuery } from '@tanstack/react-query'
import { api } from './endpoints'
import type { Alerte, TypeAlerte } from './types'

/** Alertes de l'administrateur, rafraîchies chaque minute ; partagées par la barre latérale et le tableau de bord. */
export function useAlertes() {
  return useQuery({
    queryKey: ['alertes'],
    queryFn: api.alertes,
    refetchInterval: 60_000,
    retry: false,
  })
}

/** Écran de liste où ouvrir les éléments d'une alerte (chemins du back-office). */
export const LIEN_ALERTE: Record<TypeAlerte, string> = {
  dossiers_en_attente: '/dossiers-prestataires',
  litiges_ouverts: '/litiges',
  avis_signales: '/avis?aTraiter=1',
  retraits_bloques: '/retraits',
  remboursements_en_echec: '/paiements',
  remboursements_en_retard: '/paiements',
  notifications_en_echec: '/notifications-en-echec',
  suspensions_a_echeance: '/sanctions?echeanceDansHeures=24',
}

export const lienDeLAlerte = (a: Alerte): string => LIEN_ALERTE[a.type] ?? '/'

/** Total des éléments en alerte par chemin d'écran (pastilles de la barre latérale). */
export function compteursParEcran(alertes: Alerte[]): Record<string, number> {
  const compteurs: Record<string, number> = {}
  for (const a of alertes) {
    const chemin = lienDeLAlerte(a).split('?')[0]
    compteurs[chemin] = (compteurs[chemin] ?? 0) + a.compteur
  }
  return compteurs
}
