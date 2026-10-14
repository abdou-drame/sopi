import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

interface Props {
  vers: string
  children: ReactNode
  /** « pleine » : bleu plein ; « claire » : fond clair. */
  variante?: 'pleine' | 'claire'
}

const variantes = {
  pleine: 'bg-primaire-700 text-white shadow-bouton hover:bg-primaire-800',
  claire: 'bg-primaire-50 text-primaire-700 hover:bg-primaire-100',
} as const

/** Lien d'action en forme de gélule (même rendu que les boutons gélules du modèle), pour ouvrir un écran. */
export default function LienGelule({ vers, children, variante = 'pleine' }: Props) {
  return (
    <Link
      to={vers}
      className={`inline-flex items-center justify-center rounded-gelule px-5 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors duration-[var(--duree-rapide)] ${variantes[variante]}`}
    >
      {children}
    </Link>
  )
}
