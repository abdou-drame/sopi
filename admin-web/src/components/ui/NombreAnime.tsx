import { useEffect, useRef, useState } from 'react'
import { formatNombre } from '../../lib/format'

/** Vrai si l'utilisateur demande de réduire les animations (ou si le navigateur ne sait pas le dire : on n'anime alors pas). */
export function animationsReduites(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return true
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

interface Props {
  valeur: number
  format?: (n: number) => string
  duree?: number
}

/** Nombre qui monte de 0 à sa valeur à l'apparition ; affiche directement la valeur finale si les animations sont réduites. */
export default function NombreAnime({ valeur, format = formatNombre, duree = 900 }: Props) {
  const reduit = animationsReduites()
  const [affiche, setAffiche] = useState(0)
  const depart = useRef(0)

  useEffect(() => {
    if (reduit) return
    let image = 0
    const debut = performance.now()
    const de = depart.current
    const pas = (maintenant: number) => {
      const t = Math.min(1, (maintenant - debut) / duree)
      const courant = Math.round(de + (valeur - de) * (1 - Math.pow(1 - t, 3)))
      depart.current = courant
      setAffiche(courant)
      if (t < 1) image = requestAnimationFrame(pas)
    }
    image = requestAnimationFrame(pas)
    return () => cancelAnimationFrame(image)
  }, [valeur, duree, reduit])

  if (reduit) return <>{format(valeur)}</>

  return (
    <>
      <span aria-hidden="true">{format(affiche)}</span>
      <span className="sr-only">{format(valeur)}</span>
    </>
  )
}
