import { useLayoutEffect } from 'react'
import { useLocation } from 'react-router-dom'

/** Largeur pour laquelle les maquettes sont dessinées. */
export const LARGEUR_MAQUETTE = 1440
/** En dessous, la mise en page responsive reste telle quelle (échelle 1). */
export const LARGEUR_MIN_ECHELLE = 1024
export const ECHELLE_MIN = 0.75

/** Échelle de l'écran : largeur / 1440 bornée à [0,75 ; 1] dès 1024 px, 1 en dessous de 1024 px. */
export function calculerEchelle(largeur: number): number {
  if (largeur < LARGEUR_MIN_ECHELLE) return 1
  return Math.min(1, Math.max(ECHELLE_MIN, largeur / LARGEUR_MAQUETTE))
}

/** Échelle actuellement appliquée (variable CSS --echelle), 1 si aucune. */
export function echelleActuelle(): number {
  const v = Number.parseFloat(document.body.style.getPropertyValue('--echelle'))
  return Number.isFinite(v) && v > 0 ? v : 1
}

/** Applique l'échelle au corps de la page (propriété zoom : défilement, clics et position des éléments restent justes). */
export function appliquerEchelle(echelle: number) {
  const corps = document.body
  if (echelle === 1) corps.style.removeProperty('zoom')
  else corps.style.setProperty('zoom', String(echelle))
  corps.style.setProperty('--echelle', String(echelle))
}

/** Page de connexion : mise en page inchangée, jamais mise à l'échelle. */
const SANS_ECHELLE = ['/connexion']

/**
 * Met le zoom à jour à chaque redimensionnement. L'effet est synchrone (avant la première peinture) : pas de flash à 100 %.
 * À placer en premier dans l'arbre pour passer avant les mesures des autres composants.
 */
export function useEchelleEcran() {
  const { pathname } = useLocation()
  useLayoutEffect(() => {
    const maj = () => appliquerEchelle(SANS_ECHELLE.includes(pathname) ? 1 : calculerEchelle(window.innerWidth))
    maj()
    window.addEventListener('resize', maj)
    return () => window.removeEventListener('resize', maj)
  }, [pathname])
}

export default function EchelleEcran() {
  useEchelleEcran()
  return null
}
