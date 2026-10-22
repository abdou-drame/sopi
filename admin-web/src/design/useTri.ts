import { useState } from 'react'

export type ValeurTri = string | number | null | undefined
export type SensTri = 'asc' | 'desc'

const vide = (v: ValeurTri) => v === null || v === undefined || v === ''

/** Nombres entre eux, sinon texte français sans tenir compte de la casse ni des accents (« 2 » avant « 10 »). */
function comparer(a: ValeurTri, b: ValeurTri): number {
  if (typeof a === 'number' && typeof b === 'number') return a - b
  return String(a).localeCompare(String(b), 'fr', { sensitivity: 'base', numeric: true })
}

/** Tri stable ; les valeurs absentes restent toujours à la fin, quel que soit le sens. */
export function trierLignes<T>(lignes: T[], valeur: (ligne: T) => ValeurTri, sens: SensTri): T[] {
  const signe = sens === 'asc' ? 1 : -1
  return lignes
    .map((ligne, rang) => ({ ligne, rang, v: valeur(ligne) }))
    .sort((x, y) => {
      if (vide(x.v) || vide(y.v)) return vide(x.v) && vide(y.v) ? x.rang - y.rang : vide(x.v) ? 1 : -1
      return signe * comparer(x.v, y.v) || x.rang - y.rang
    })
    .map((e) => e.ligne)
}

export interface EtatTri {
  colonne: number
  sens: SensTri
}

/**
 * Tri côté client des lignes déjà chargées (l'API n'a pas de paramètre de tri) : neutre, puis croissant, puis décroissant,
 * puis de nouveau neutre. Le tri ne porte que sur la page affichée.
 */
export function useTri<T>(lignes: T[], colonnes: { tri?: (ligne: T) => ValeurTri }[]) {
  const [etat, setEtat] = useState<EtatTri | null>(null)
  const acces = etat ? colonnes[etat.colonne]?.tri : undefined
  const triees = etat && acces ? trierLignes(lignes, acces, etat.sens) : lignes
  const basculer = (colonne: number) =>
    setEtat((e) => (e?.colonne !== colonne ? { colonne, sens: 'asc' } : e.sens === 'asc' ? { colonne, sens: 'desc' } : null))
  return { triees, etat: acces ? etat : null, basculer }
}
