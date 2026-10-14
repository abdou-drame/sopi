import type { ReactNode } from 'react'
import { Link, useLocation } from 'react-router-dom'

/** Lien vers un détail qui mémorise les filtres de la liste d'origine, pour que « Retour » les retrouve. */
export default function LienLigne({ vers, children }: { vers: string; children: ReactNode }) {
  const lieu = useLocation()
  return (
    <Link to={vers} state={{ recherche: lieu.search }} className="font-semibold text-primaire-700 hover:underline">
      {children}
    </Link>
  )
}
