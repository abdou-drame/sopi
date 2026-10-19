import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'

export type TypeRetour = 'succes' | 'info' | 'erreur'

/** Durée d'affichage de chaque type de retour, en millisecondes. */
export const DUREES_RETOUR: Record<TypeRetour, number> = { succes: 4000, info: 5000, erreur: 8000 }

interface Retour {
  id: number
  type: TypeRetour
  texte: string
}

interface Notifieur {
  succes: (texte: string) => void
  info: (texte: string) => void
  erreur: (texte: string) => void
}

const ContexteRetours = createContext<Notifieur | null>(null)

const styles: Record<TypeRetour, string> = {
  succes: 'bg-succes-fond text-succes-texte',
  info: 'bg-primaire-50 text-primaire-800',
  erreur: 'bg-erreur-fond text-erreur-texte',
}

function AlerteFlottante({ retour, onFin }: { retour: Retour; onFin: (id: number) => void }) {
  useEffect(() => {
    const minuteur = setTimeout(() => onFin(retour.id), DUREES_RETOUR[retour.type])
    return () => clearTimeout(minuteur)
  }, [retour.id, retour.type, onFin])
  return (
    <div className={`animate-glissement rounded-grand px-5 py-3.5 text-sm font-semibold shadow-bouton ${styles[retour.type]}`}>
      {retour.texte}
    </div>
  )
}

/** Fournit les retours d'action (succès, information, erreur) : alertes flottantes qui disparaissent seules. */
export function AlertesProvider({ children }: { children: ReactNode }) {
  const [retours, setRetours] = useState<Retour[]>([])
  const retirer = useCallback((id: number) => setRetours((liste) => liste.filter((r) => r.id !== id)), [])
  const notifieur = useMemo<Notifieur>(() => {
    let suivant = 0
    const ajouter = (type: TypeRetour) => (texte: string) =>
      setRetours((liste) => [...liste, { id: ++suivant, type, texte }])
    return { succes: ajouter('succes'), info: ajouter('info'), erreur: ajouter('erreur') }
  }, [])
  return (
    <ContexteRetours.Provider value={notifieur}>
      {children}
      <div className="pointer-events-none fixed right-4 top-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2">
        {retours.map((r) => (
          <div key={r.id} className="pointer-events-auto">
            <AlerteFlottante retour={r} onFin={retirer} />
          </div>
        ))}
      </div>
    </ContexteRetours.Provider>
  )
}

/** Accès aux retours d'action : `const retour = useRetours(); retour.succes('Fait.')`. */
export function useRetours(): Notifieur {
  const contexte = useContext(ContexteRetours)
  if (!contexte) throw new Error('useRetours doit être utilisé dans <AlertesProvider>.')
  return contexte
}
