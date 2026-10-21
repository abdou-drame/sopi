import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { L } from '../../i18n/libelles'
import { animationsActives } from '../../design/animation/usePresence'

export type TypeRetour = 'succes' | 'info' | 'erreur'

/** Durée d'affichage de chaque type de retour, en millisecondes. */
export const DUREES_RETOUR: Record<TypeRetour, number> = { succes: 4000, info: 4000, erreur: 8000 }

/** Nombre maximal d'alertes affichées en même temps : les plus anciennes sont retirées. */
export const MAX_RETOURS = 3

interface Retour {
  id: number
  type: TypeRetour
  texte: string
  /** Vrai pendant l'animation de sortie : l'alerte est déjà fermée, elle n'est plus qu'à retirer de l'écran. */
  sortie?: boolean
}

interface Notifieur {
  succes: (texte: string) => void
  info: (texte: string) => void
  erreur: (texte: string) => void
}

const ContexteRetours = createContext<Notifieur | null>(null)

const styles: Record<TypeRetour, string> = {
  succes: 'border-l-[#16a34a]',
  info: 'border-l-[#1d4ed8]',
  erreur: 'border-l-[#e5484d]',
}

function AlerteFlottante({ retour, onFin }: { retour: Retour; onFin: (id: number) => void }) {
  const [enPause, setEnPause] = useState(false)
  const restant = useRef(DUREES_RETOUR[retour.type])
  useEffect(() => {
    if (enPause) return
    const debut = Date.now()
    const minuteur = setTimeout(() => onFin(retour.id), restant.current)
    return () => {
      clearTimeout(minuteur)
      restant.current -= Date.now() - debut
    }
  }, [enPause, retour.id, onFin])
  return (
    <div
      role={retour.type === 'erreur' ? 'alert' : 'status'}
      onMouseEnter={() => setEnPause(true)}
      onMouseLeave={() => setEnPause(false)}
      onFocus={() => setEnPause(true)}
      onBlur={() => setEnPause(false)}
      className={`${retour.sortie ? 'anim-toast-sortie' : 'animate-glissement'} flex items-start gap-3 rounded-[20px] border-l-4 bg-white px-4 py-3.5 text-[13px] font-semibold text-[#12172b] shadow-[0_12px_32px_rgba(18,23,43,0.16)] ${styles[retour.type]}`}
    >
      <span className="min-w-0 flex-1">{retour.texte}</span>
      <button
        type="button"
        aria-label={L.commun.fermer}
        onClick={() => onFin(retour.id)}
        className="-my-1 -mr-2 shrink-0 rounded-xl px-2 text-lg leading-none opacity-70 hover:opacity-100"
      >
        ×
      </button>
    </div>
  )
}

/** Fournit les retours d'action (succès, information, erreur) : alertes flottantes qui disparaissent seules. */
export function AlertesProvider({ children }: { children: ReactNode }) {
  const [retours, setRetours] = useState<Retour[]>([])
  const retirer = useCallback((id: number) => {
    const enlever = () => setRetours((liste) => liste.filter((r) => r.id !== id))
    if (!animationsActives()) return enlever()
    setRetours((liste) => liste.map((r) => (r.id === id ? { ...r, sortie: true } : r)))
    setTimeout(enlever, 180)
  }, [])
  const notifieur = useMemo<Notifieur>(() => {
    let suivant = 0
    const ajouter = (type: TypeRetour) => (texte: string) =>
      setRetours((liste) => {
        // Un message identique déjà affiché est remplacé (le compte à rebours repart), pas dupliqué.
        const sansDoublon = liste.filter((r) => !(r.type === type && r.texte === texte))
        return [...sansDoublon, { id: ++suivant, type, texte }].slice(-MAX_RETOURS)
      })
    return { succes: ajouter('succes'), info: ajouter('info'), erreur: ajouter('erreur') }
  }, [])
  return (
    <ContexteRetours.Provider value={notifieur}>
      {children}
      <div className="pointer-events-none fixed right-4 top-4 z-50 flex w-[min(24rem,calc((100vw-2rem)/var(--echelle)))] flex-col gap-2">
        {retours.map((r) => (
          <div key={r.id} className={r.sortie ? 'pointer-events-none' : 'pointer-events-auto'}>
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
