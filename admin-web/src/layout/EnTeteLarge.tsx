import { createContext, useContext, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

/**
 * Hôte d'un en-tête pleine largeur, placé par la coque au-dessus de la colonne d'icônes
 * (comme « Bienvenue » sur le modèle). `undefined` : hors coque, l'en-tête s'affiche à sa place.
 * `null` : la coque est montée mais l'hôte n'est pas encore prêt.
 */
export const HoteEnTete = createContext<HTMLElement | null | undefined>(undefined)

/** Affiche son contenu dans l'en-tête pleine largeur de la coque. */
export default function EnTeteLarge({ children }: { children: ReactNode }) {
  const hote = useContext(HoteEnTete)
  if (hote === undefined) return <>{children}</>
  if (hote === null) return null
  return createPortal(children, hote)
}
