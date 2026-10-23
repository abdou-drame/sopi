import { animationsActives } from './usePresence'

/** Mêmes durée et courbe que l'animation de la vidéo de référence. */
const DUREE_MS = 620
const COURBE = 'cubic-bezier(0.65, 0, 0.35, 1)'
const ATTENTE_RENDU_MS = 60

/**
 * « Actualiser » : un cercle s'ouvre depuis le bouton cliqué (View Transition API, clip-path sur la nouvelle image de la page).
 * Comme dans la vidéo, le cercle doit révéler un état VISIBLEMENT différent : `repartirDeZero` vide les données (écran « fantôme »),
 * puis elles reviennent et les chiffres remontent. Le cercle s'ouvre tout de suite, sans attendre le réseau.
 * Sans View Transition ou en mouvement réduit : on actualise simplement (`actualiser`), sans animation.
 */
export function actualiserEnCercle(origine: Element, actualiser: () => Promise<unknown>, repartirDeZero: () => Promise<unknown> = actualiser) {
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
    void repartirDeZero().catch(() => undefined)
    // Le temps que React affiche l'état « fantôme » avant la capture de la nouvelle page. Pas de requestAnimationFrame ici :
    // le rendu est suspendu pendant ce rappel, il ne se déclencherait jamais et la transition serait abandonnée.
    await new Promise<void>((fin) => setTimeout(fin, ATTENTE_RENDU_MS))
  })
  void vue.ready.then(() => {
    document.documentElement.animate(
      { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${rayon}px at ${x}px ${y}px)`] },
      { duration: DUREE_MS, easing: COURBE, pseudoElement: '::view-transition-new(root)' },
    )
  }).catch(() => undefined)
}
