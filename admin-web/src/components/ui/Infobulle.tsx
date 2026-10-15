import { useId, useState } from 'react'
import type { ReactNode } from 'react'
import { createPortal } from 'react-dom'

interface Props {
  texte: string
  /** Côté d'affichage : à droite (barre latérale) ou au-dessus. */
  cote?: 'droite' | 'haut'
  children: ReactNode
}

interface Position {
  x: number
  y: number
}

/**
 * Infobulle sombre en gélule, affichée au survol ET au focus clavier de son contenu (reliée par aria-describedby).
 * Elle est rendue dans <body> en position fixe : aucun conteneur à défilement ou à flou ne peut la rogner.
 */
export default function Infobulle({ texte, cote = 'droite', children }: Props) {
  const id = useId()
  const [position, setPosition] = useState<Position | null>(null)

  const afficher = (e: { currentTarget: HTMLElement }) => {
    const r = e.currentTarget.getBoundingClientRect()
    setPosition(cote === 'droite' ? { x: r.right + 12, y: r.top + r.height / 2 } : { x: r.left + r.width / 2, y: r.top - 8 })
  }
  const visible = position !== null
  const decalage = cote === 'droite' ? 'translate(0, -50%)' : 'translate(-50%, -100%)'
  const decalageCache = cote === 'droite' ? 'translate(-6px, -50%)' : 'translate(-50%, calc(-100% + 4px))'

  return (
    <span
      className="relative inline-flex"
      aria-describedby={id}
      onMouseEnter={afficher}
      onMouseLeave={() => setPosition(null)}
      onFocus={afficher}
      onBlur={() => setPosition(null)}
    >
      {children}
      {createPortal(
        <span
          id={id}
          role="tooltip"
          style={{ left: position?.x ?? 0, top: position?.y ?? 0, transform: visible ? decalage : decalageCache }}
          className={`pointer-events-none fixed z-[60] rounded-gelule bg-primaire-900 px-3 py-1.5 text-xs font-medium whitespace-nowrap text-white shadow-infobulle ring-1 ring-white/20 transition-[opacity,transform] duration-[var(--duree-rapide)] ease-[var(--ease-sortie)] ${
            visible ? 'opacity-100' : 'opacity-0'
          }`}
        >
          {texte}
        </span>,
        document.body,
      )}
    </span>
  )
}
