import { useState } from 'react'

export interface Selection<T> {
  /** Lignes cochées de la page affichée. */
  coches: T[]
  nombre: number
  estCoche: (ligne: T) => boolean
  peutCocher: (ligne: T) => boolean
  libelle: (ligne: T) => string
  basculer: (ligne: T) => void
  basculerTout: () => void
  toutCoche: boolean
  /** Vrai si certaines lignes seulement sont cochées (case d'en-tête à moitié cochée). */
  indetermine: boolean
  vider: () => void
  /** Ne garde cochées que les lignes dont l'identifiant est donné (ex. celles dont l'action a échoué). */
  garder: (ids: string[]) => void
}

const AUCUN = new Set<string>()

/**
 * Sélection multiple des lignes d'une page. Elle se vide quand `reinitialiserQuand` change (page, filtres) et ne retient jamais
 * une ligne qui n'est plus affichée. `peutCocher` exclut les lignes sur lesquelles aucune action groupée n'a de sens.
 */
export function useSelection<T>(
  lignes: T[],
  cle: (ligne: T) => string,
  { libelle, peutCocher = () => true, reinitialiserQuand = '' }: { libelle: (ligne: T) => string; peutCocher?: (ligne: T) => boolean; reinitialiserQuand?: string },
): Selection<T> {
  const [etat, setEtat] = useState<{ jeton: string; ids: Set<string> }>({ jeton: reinitialiserQuand, ids: AUCUN })
  const ids = etat.jeton === reinitialiserQuand ? etat.ids : AUCUN
  const modifier = (fn: (ids: Set<string>) => Set<string>) => setEtat((e) => ({ jeton: reinitialiserQuand, ids: fn(e.jeton === reinitialiserQuand ? e.ids : AUCUN) }))

  const cochables = lignes.filter(peutCocher)
  const coches = cochables.filter((l) => ids.has(cle(l)))
  const toutCoche = cochables.length > 0 && coches.length === cochables.length
  return {
    coches,
    nombre: coches.length,
    estCoche: (l) => ids.has(cle(l)) && peutCocher(l),
    peutCocher,
    libelle,
    basculer: (l) =>
      modifier((s) => {
        const suivant = new Set(s)
        if (suivant.has(cle(l))) suivant.delete(cle(l))
        else suivant.add(cle(l))
        return suivant
      }),
    basculerTout: () => modifier(() => (toutCoche ? new Set() : new Set(cochables.map(cle)))),
    toutCoche,
    indetermine: coches.length > 0 && !toutCoche,
    vider: () => modifier(() => new Set()),
    garder: (garder) => modifier((s) => new Set([...s].filter((id) => garder.includes(id)))),
  }
}
