import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { L } from '../../i18n/libelles'
import { IconeFermer } from './Icones'

interface Props {
  src: string
  alt: string
  className?: string
}

/** Miniature cliquable qui ouvre l'image en grand (Échap ou clic à côté pour fermer, focus rendu à la miniature). */
export default function VisionneuseImage({ src, alt, className = '' }: Props) {
  const [ouverte, setOuverte] = useState(false)
  const fermer = useRef<HTMLButtonElement>(null)
  const declencheur = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    if (!ouverte) return
    fermer.current?.focus()
    const touche = (e: KeyboardEvent) => e.key === 'Escape' && setOuverte(false)
    document.addEventListener('keydown', touche)
    const dernier = declencheur.current
    return () => {
      document.removeEventListener('keydown', touche)
      dernier?.focus()
    }
  }, [ouverte])

  return (
    <>
      <button
        ref={declencheur}
        type="button"
        onClick={() => setOuverte(true)}
        aria-label={`${L.commun.agrandir} : ${alt}`}
        className={`overflow-hidden rounded-moyen border border-bordure transition-transform duration-[var(--duree-normale)] hover:scale-[1.02] ${className}`}
      >
        <img src={src} alt={alt} loading="lazy" className="size-full object-cover" />
      </button>
      {ouverte &&
        createPortal(
          <div
            role="dialog"
            aria-modal="true"
            aria-label={alt}
            className="fixed inset-0 z-50 grid place-items-center bg-primaire-900/80 p-4"
            onClick={() => setOuverte(false)}
          >
            <button
              ref={fermer}
              type="button"
              onClick={() => setOuverte(false)}
              aria-label={L.commun.fermer}
              className="absolute top-4 right-4 grid size-10 place-items-center rounded-full bg-surface text-texte shadow-eleve"
            >
              <IconeFermer />
            </button>
            <img
              src={src}
              alt={alt}
              onClick={(e) => e.stopPropagation()}
              className="animate-apparition max-h-[85vh] max-w-full rounded-moyen bg-surface object-contain shadow-eleve"
            />
          </div>,
          document.body,
        )}
    </>
  )
}
