import { useEffect, useState } from 'react'

/** Vrai si l'utilisateur n'a pas demandé de réduire les animations (et si le navigateur sait le dire). */
export function animationsActives(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return false
  return !window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

/**
 * Garde un élément affiché le temps de son animation de sortie.
 * `monte` dit s'il faut le rendre ; `sortie` dit s'il est en train de disparaître (pour la classe CSS de sortie).
 * Sans animation possible (mouvement réduit, tests), il disparaît immédiatement.
 */
export function usePresence(ouvert: boolean, dureeMs = 180) {
  const [precedent, setPrecedent] = useState(ouvert)
  const [sortie, setSortie] = useState(false)
  if (precedent !== ouvert) {
    setPrecedent(ouvert)
    setSortie(!ouvert && animationsActives())
  }
  useEffect(() => {
    if (!sortie) return
    const minuteur = setTimeout(() => setSortie(false), dureeMs)
    return () => clearTimeout(minuteur)
  }, [sortie, dureeMs])
  return { monte: ouvert || sortie, sortie: !ouvert && sortie }
}
