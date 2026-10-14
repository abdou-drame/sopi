import { useId } from 'react'
import type { ReactNode } from 'react'

interface Props {
  texte: string
  /** Côté d'affichage : à droite (barre latérale) ou au-dessus. */
  cote?: 'droite' | 'haut'
  children: ReactNode
}

/** Infobulle sombre en gélule, affichée au survol ET au focus clavier de son contenu (reliée par aria-describedby). */
export default function Infobulle({ texte, cote = 'droite', children }: Props) {
  const id = useId()
  const position =
    cote === 'droite'
      ? 'left-full top-1/2 ml-3 -translate-y-1/2 translate-x-[-6px] group-hover/info:translate-x-0 group-focus-within/info:translate-x-0'
      : 'bottom-full left-1/2 mb-2 -translate-x-1/2 translate-y-1 group-hover/info:translate-y-0 group-focus-within/info:translate-y-0'
  return (
    <span className="group/info relative inline-flex" aria-describedby={id}>
      {children}
      <span
        id={id}
        role="tooltip"
        className={`pointer-events-none absolute z-30 rounded-gelule bg-primaire-900 px-3 py-1.5 text-xs font-medium whitespace-nowrap text-white opacity-0 shadow-infobulle ring-1 ring-white/20 transition-[opacity,transform] duration-[var(--duree-rapide)] ease-[var(--ease-sortie)] group-hover/info:opacity-100 group-focus-within/info:opacity-100 ${position}`}
      >
        {texte}
      </span>
    </span>
  )
}
