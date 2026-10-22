import { animationsActives } from './usePresence'

/** Durée totale du cycle (montée, tenue, descente) ; doit rester égale à celle des @keyframes de index.css. */
const DUREE_MS = 800
let minuteur: ReturnType<typeof setTimeout> | undefined

/**
 * Flash ponctuel déclenché par « Actualiser » : toute la page s'assombrit un instant, le graphique s'illumine par-dessus,
 * puis tout revient à l'état normal. Purement visuel : l'effet est porté par l'attribut `data-flash` de <html> (voir index.css),
 * retiré à la fin. Mouvement réduit : simple pulse léger, sans inversion sombre.
 */
export function declencherFlash() {
  const racine = document.documentElement
  clearTimeout(minuteur)
  racine.removeAttribute('data-flash')
  void racine.offsetWidth; // force le recalcul pour rejouer l'animation si on reclique pendant un flash
  racine.setAttribute('data-flash', animationsActives() ? 'plein' : 'reduit')
  minuteur = setTimeout(() => racine.removeAttribute('data-flash'), DUREE_MS)
}
