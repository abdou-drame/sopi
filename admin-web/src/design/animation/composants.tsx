import type { ReactNode } from 'react'
import { useLocation } from 'react-router-dom'

/** Enveloppe des écrans : à chaque changement de route, la page se fond puis ses blocs montent en cascade (voir `.page-entree`). */
export function PageTransition({ children }: { children: ReactNode }) {
  const { pathname } = useLocation()
  return (
    <div key={pathname} className="page-entree flex flex-col gap-4">
      {children}
    </div>
  )
}

/**
 * Apparition d'une carte de statistiques : fondu avec un léger décalage selon sa position dans la grille.
 * Le wrapper reste transparent pour la mise en page (la carte remplit toute la cellule).
 */
export function AnimatedCard({ children }: { children: ReactNode }) {
  return <div className="apparition-carte flex [&>*]:min-w-0 [&>*]:flex-1">{children}</div>
}
