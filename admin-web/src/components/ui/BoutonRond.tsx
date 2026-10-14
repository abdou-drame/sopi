import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

interface Props {
  /** Nom accessible obligatoire : le bouton n'a pas de texte visible. */
  libelle: string
  icone: ReactNode
  /** Si fourni, le bouton est un lien vers cet écran ; sinon un bouton d'action. */
  vers?: string
  onClick?: () => void
}

const classes =
  'grid size-9 shrink-0 place-items-center rounded-full bg-fond text-texte-doux transition-colors duration-[var(--duree-rapide)] hover:bg-primaire-100 hover:text-primaire-700'

/** Petit bouton rond gris placé à droite de l'en-tête d'une carte (ouvrir l'écran détaillé, options...). */
export default function BoutonRond({ libelle, icone, vers, onClick }: Props) {
  if (vers)
    return (
      <Link to={vers} aria-label={libelle} className={classes}>
        {icone}
      </Link>
    )
  return (
    <button type="button" aria-label={libelle} onClick={onClick} className={classes}>
      {icone}
    </button>
  )
}
