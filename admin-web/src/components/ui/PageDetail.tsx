import type { UseQueryResult } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { ApiError, messageDErreur } from '../../api/client'
import { L } from '../../i18n/libelles'
import Bouton from './Bouton'
import Carte from './Carte'
import { IconeRetour } from './Icones'
import Squelette from './Squelette'

interface Props<T> {
  requete: UseQueryResult<T>
  /** Écran de liste d'origine. */
  retour: { vers: string; libelle: string }
  titre: (donnees: T) => string
  sousTitre?: (donnees: T) => string | undefined
  badge?: (donnees: T) => ReactNode
  actions?: (donnees: T) => ReactNode
  children: (donnees: T) => ReactNode
}

/**
 * Page de détail commune à tous les écrans (choix retenu : une page par élément plutôt qu'un tiroir — adresse
 * partageable, retour arrière, place pour les pièces jointes). Gère chargement, erreur, 404 et le retour avec les filtres d'origine.
 */
export default function PageDetail<T>({ requete, retour, titre, sousTitre, badge, actions, children }: Props<T>) {
  const lieu = useLocation()
  const recherche = (lieu.state as { recherche?: string } | null)?.recherche ?? ''
  const lienRetour = (
    <Link
      to={`${retour.vers}${recherche}`}
      className="inline-flex w-fit items-center gap-2 rounded-gelule text-sm font-medium text-sous-titre hover:text-titre"
    >
      <IconeRetour width={16} height={16} />
      {retour.libelle}
    </Link>
  )

  if (requete.isPending) {
    return (
      <div className="flex flex-col gap-6">
        {lienRetour}
        <div role="status" aria-label={L.commun.chargement}>
          <Squelette className="h-10 w-72" />
          <Squelette className="mt-6 h-64 w-full rounded-carte" />
        </div>
      </div>
    )
  }

  if (requete.isError) {
    const introuvable = requete.error instanceof ApiError && requete.error.status === 404
    return (
      <div className="flex flex-col gap-6">
        {lienRetour}
        <Carte>
          <div role="alert" className="flex flex-col items-start gap-3">
            <p className="text-lg font-semibold">{introuvable ? L.commun.elementIntrouvable : L.commun.erreurChargement}</p>
            <p className="text-sm text-texte-doux">{messageDErreur(requete.error)}</p>
            {!introuvable && (
              <Bouton variante="secondaire" taille="p" onClick={() => void requete.refetch()}>
                {L.commun.reessayer}
              </Bouton>
            )}
          </div>
        </Carte>
      </div>
    )
  }

  const d = requete.data
  return (
    <div className="flex flex-col gap-6">
      {lienRetour}
      <header className="animate-apparition flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-titre-page font-bold text-titre">{titre(d)}</h1>
            {badge?.(d)}
          </div>
          {sousTitre?.(d) && <p className="mt-1 text-sm text-sous-titre">{sousTitre(d)}</p>}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2">{actions(d)}</div>}
      </header>
      {children(d)}
    </div>
  )
}
