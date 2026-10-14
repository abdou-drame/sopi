import type { ReactNode } from 'react'

interface Props {
  titre?: string
  sousTitre?: string
  /** Contenu à droite de l'en-tête (bouton rond, bascule...). */
  action?: ReactNode
  /** Retard d'apparition en ms (échelonnement des cartes). */
  delai?: number
  className?: string
  children: ReactNode
}

/** Carte blanche très arrondie à ombre diffuse, avec en-tête facultatif (titre, sous-titre, action) et apparition par le bas. */
export default function Carte({ titre, sousTitre, action, delai = 0, className = '', children }: Props) {
  return (
    <div
      className={`animate-apparition rounded-carte bg-surface p-6 shadow-carte ${className}`}
      style={{ animationDelay: `${delai}ms` }}
    >
      {(titre || action) && (
        <header className="mb-4 flex items-start justify-between gap-3">
          <div>
            {titre && <h3 className="text-sm leading-5 font-semibold text-texte">{titre}</h3>}
            {sousTitre && <p className="text-micro text-texte-doux">{sousTitre}</p>}
          </div>
          {action}
        </header>
      )}
      {children}
    </div>
  )
}
