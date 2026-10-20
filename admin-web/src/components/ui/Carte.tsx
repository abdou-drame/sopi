import type { ReactNode } from 'react'

interface Props {
  titre?: string
  /** Petite icône à gauche du titre. */
  icone?: ReactNode
  sousTitre?: string
  /** Contenu à droite de l'en-tête (bouton rond, bascule...). */
  action?: ReactNode
  /** Retard d'apparition en ms (échelonnement des cartes). */
  delai?: number
  className?: string
  children: ReactNode
}

/** Carte blanche très arrondie à ombre diffuse, avec en-tête facultatif (titre, sous-titre, action) et apparition par le bas. */
export default function Carte({ titre, icone, sousTitre, action, delai = 0, className = '', children }: Props) {
  return (
    <div
      className={`animate-apparition rounded-carte bg-surface p-[18px] shadow-carte ${className}`}
      style={{ animationDelay: `${delai}ms` }}
    >
      {(titre || action) && (
        <header className="mb-4 flex items-start justify-between gap-3">
          <div className="flex items-start gap-3">
            {icone && <span className="grid size-11 shrink-0 place-items-center rounded-full bg-fond text-texte-doux">{icone}</span>}
            <div>
            {titre && <h3 className="text-[15px] leading-5 font-bold text-texte">{titre}</h3>}
            {sousTitre && <p className="text-xs text-texte-discret">{sousTitre}</p>}
            </div>
          </div>
          {action}
        </header>
      )}
      {children}
    </div>
  )
}
