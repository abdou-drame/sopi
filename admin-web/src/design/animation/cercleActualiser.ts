import { animationsActives } from './usePresence'

/** Mêmes durée et courbe que l'animation de la vidéo de référence. */
const DUREE_MS = 620
const COURBE = 'cubic-bezier(0.65, 0, 0.35, 1)'
/** Si les données tardent, le cercle s'ouvre quand même : la page n'est jamais figée plus longtemps. */
const ATTENTE_MAX_MS = 1500

/**
 * « Actualiser » : relance les requêtes, et la page rafraîchie apparaît dans un cercle qui s'ouvre depuis le bouton cliqué
 * (View Transition API, clip-path sur la nouvelle image de la page). Purement visuel : sans View Transition ou en mouvement
 * réduit, on actualise simplement, sans animation.
 */
export function actualiserEnCercle(origine: Element, actualiser: () => Promise<unknown>) {
  const transition = typeof document.startViewTransition === 'function' && animationsActives()
  if (!transition) {
    void actualiser()
    return
  }
  const r = origine.getBoundingClientRect()
  const x = r.left + r.width / 2
  const y = r.top + r.height / 2
  const rayon = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y))
  const vue = document.startViewTransition(async () => {
    await Promise.race([actualiser().catch(() => undefined), new Promise((fin) => setTimeout(fin, ATTENTE_MAX_MS))])
  })
  void vue.ready.then(() => {
    document.documentElement.animate(
      { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${rayon}px at ${x}px ${y}px)`] },
      { duration: DUREE_MS, easing: COURBE, pseudoElement: '::view-transition-new(root)' },
    )
  }).catch(() => undefined)
}
