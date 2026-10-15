import { useEffect, useState } from 'react'
import { animationsReduites } from './NombreAnime'

interface Props {
  /** `plein` : couche fixe de la page ; `clair` / `sombre` : cadre de démonstration aux jetons forcés. */
  variante?: 'plein' | 'clair' | 'sombre'
}

/** Suit la demande « réduire les animations » du système, y compris si elle change pendant la session. */
function useAnimationsReduites(): boolean {
  const [reduit, setReduit] = useState(animationsReduites)
  useEffect(() => {
    if (typeof window.matchMedia !== 'function') return
    const requete = window.matchMedia('(prefers-reduced-motion: reduce)')
    const maj = () => setReduit(requete.matches)
    requete.addEventListener?.('change', maj)
    return () => requete.removeEventListener?.('change', maj)
  }, [])
  return reduit
}

/**
 * Fond d'arrière-plan : trois taches floues (bleu, cyan, violet) qui dérivent lentement derrière tout le contenu.
 * Décoratif : masqué aux lecteurs d'écran et sans effet sur les clics. Couleurs et animation sont dans index.css.
 */
export default function FondArrierePlan({ variante = 'plein' }: Props) {
  const fige = useAnimationsReduites()
  const classes = ['fond-sopi', variante !== 'plein' && `fond-sopi--apercu apercu-fond-${variante}`].filter(Boolean).join(' ')
  return (
    <div aria-hidden="true" data-testid="fond-arriere-plan" data-fige={fige} className={classes} style={{ pointerEvents: 'none' }}>
      <span className="fond-tache fond-tache--bleu" />
      <span className="fond-tache fond-tache--cyan" />
      <span className="fond-tache fond-tache--violet" />
    </div>
  )
}
