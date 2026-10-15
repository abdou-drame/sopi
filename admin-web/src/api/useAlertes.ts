import { useQuery } from '@tanstack/react-query'
import { api } from './endpoints'
import type { Alerte, TypeAlerte } from './types'

/** Alertes de l'administrateur, rafraîchies chaque minute ; partagées par la barre latérale et le tableau de bord. */
export function useAlertes() {
  return useQuery({
    queryKey: ['alertes'],
    queryFn: async () => {
      const reponse = await api.alertes()
      // Les types d'alerte sans écran sont ignorés, totaux recalculés.
      const alertes = reponse.alertes.filter((a) => a.type in LIEN_ALERTE)
      if (alertes.length === reponse.alertes.length) return reponse
      const parGravite = { info: 0, attention: 0, critique: 0 }
      for (const a of alertes) parGravite[a.gravite] += a.compteur
      return { ...reponse, alertes, parGravite, total: alertes.reduce((t, a) => t + a.compteur, 0) }
    },
    refetchInterval: 60_000,
    retry: false,
  })
}

/** Écran de liste où ouvrir les éléments d'une alerte (chemins du back-office). */
export const LIEN_ALERTE: Record<TypeAlerte, string> = {
  dossiers_en_attente: '/dossiers-prestataires',
  litiges_ouverts: '/litiges',
  avis_signales: '/avis',
  retraits_bloques: '/retraits?bloques=true',
  remboursements_en_echec: '/paiements?remboursementDu=true',
  remboursements_en_retard: '/paiements?remboursementDu=true',
  suspensions_a_echeance: '/sanctions?echeanceDansHeures=24',
  notifications_en_echec: '/notifications-en-echec',
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
