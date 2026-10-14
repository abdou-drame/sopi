import type { ReactNode } from 'react'

interface Props {
  titre: string
  sousTitre?: string
  /** Boutons ou liens alignés à droite du titre. */
  actions?: ReactNode
}

/** En-tête d'écran : titre de page, sous-titre et actions. Un seul h1 par écran. */
export default function EnTetePage({ titre, sousTitre, actions }: Props) {
  return (
    <header className="animate-apparition flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="text-titre-page font-bold text-titre">{titre}</h1>
        {sousTitre && <p className="mt-1 text-sm text-sous-titre">{sousTitre}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </header>
  )
}
