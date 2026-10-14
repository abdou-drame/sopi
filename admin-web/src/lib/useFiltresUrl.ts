import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

export interface FiltresUrl {
  /** Valeur courante de chaque filtre (valeur par défaut si absent de l'adresse). */
  valeurs: Record<string, string>
  page: number
  /** Paramètres prêts pour l'API : filtres (défauts compris) et page. */
  parametresApi: Record<string, string | number>
  /** Vrai si au moins un filtre diffère de sa valeur par défaut. */
  modifies: boolean
  definir: (changement: Record<string, string>) => void
  allerPage: (page: number) => void
  reinitialiser: () => void
}

/**
 * Filtres et page courante stockés dans l'adresse (noms identiques à ceux de l'API) : un lien est partageable
 * et le retour arrière restaure l'écran. Une valeur égale à son défaut n'est pas écrite. Changer un filtre revient à la page 1.
 */
export function useFiltresUrl(cles: string[], defauts: Record<string, string> = {}): FiltresUrl {
  const [params, setParams] = useSearchParams()
  const sCles = cles.join('|')
  const sDefauts = JSON.stringify(defauts)

  const valeurs = useMemo(() => {
    const d = JSON.parse(sDefauts) as Record<string, string>
    return Object.fromEntries(sCles.split('|').filter(Boolean).map((c) => [c, params.get(c) ?? d[c] ?? '']))
  }, [params, sCles, sDefauts])

  const brut = Number(params.get('page'))
  const page = Number.isInteger(brut) && brut >= 1 ? brut : 1

  const definir = useCallback(
    (changement: Record<string, string>) => {
      const d = JSON.parse(sDefauts) as Record<string, string>
      const suivant = new URLSearchParams(params)
      for (const [c, v] of Object.entries(changement)) {
        // Un défaut non vide doit rester explicite dans l'adresse quand l'utilisateur choisit « vide » (ex. tous).
        if (v === (d[c] ?? '')) suivant.delete(c)
        else suivant.set(c, v)
      }
      suivant.delete('page')
      setParams(suivant)
    },
    [params, setParams, sDefauts],
  )

  const allerPage = useCallback(
    (n: number) => {
      const suivant = new URLSearchParams(params)
      if (n <= 1) suivant.delete('page')
      else suivant.set('page', String(n))
      setParams(suivant)
    },
    [params, setParams],
  )

  const reinitialiser = useCallback(() => {
    const suivant = new URLSearchParams(params)
    for (const c of sCles.split('|').filter(Boolean)) suivant.delete(c)
    suivant.delete('page')
    setParams(suivant)
  }, [params, setParams, sCles])

  const parametresApi = useMemo(() => {
    const p: Record<string, string | number> = {}
    for (const [c, v] of Object.entries(valeurs)) if (v !== '') p[c] = v
    if (page > 1) p.page = page
    return p
  }, [valeurs, page])

  const d = JSON.parse(sDefauts) as Record<string, string>
  const modifies = Object.entries(valeurs).some(([c, v]) => v !== (d[c] ?? ''))

  return { valeurs, page, parametresApi, modifies, definir, allerPage, reinitialiser }
}
