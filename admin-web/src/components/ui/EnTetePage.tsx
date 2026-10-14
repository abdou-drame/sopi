import type { ReactNode } from 'react'
import EnTeteLarge from '../../layout/EnTeteLarge'

interface Props {
  titre: string
  sousTitre?: string
  /** Boutons ou liens alignés à droite du titre. */
  actions?: ReactNode
}

/**
 * En-tête d'écran : titre de page, sous-titre et actions. Un seul h1 par écran.
 * Dans la coque, il occupe toute la largeur au-dessus de la colonne d'icônes (comme « Bienvenue » du tableau de bord).
 */
export default function EnTetePage({ titre, sousTitre, actions }: Props) {
  return (
    <EnTeteLarge>
      <header className="animate-apparition flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-titre-page font-bold tracking-tight text-texte">{titre}</h1>
          {sousTitre && <p className="mt-1 text-sm text-texte-doux">{sousTitre}</p>}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-3">{actions}</div>}
      </header>
    </EnTeteLarge>
  )
}
