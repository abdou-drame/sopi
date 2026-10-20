import type { UseQueryResult } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ApiError, messageDErreur } from '../api/client'
import { L } from '../i18n/libelles'
import { Carte } from './composants'
import { EtatChargement, EtatErreur } from './etats'
import { IRetour } from './icones'

/**
 * Chargement, erreur et 404 d'une page de détail, au style des états des maquettes (Etats.html), sous le rond « retour ».
 * Une fois les données là, le contenu est celui de la maquette de l'écran.
 */
export default function PageDetailMq<T>({
  requete,
  retour,
  libelleRetour,
  children,
}: {
  requete: UseQueryResult<T>
  retour: string
  libelleRetour: string
  children: (donnees: T) => ReactNode
}) {
  if (requete.isPending || requete.isError) {
    const introuvable = requete.isError && requete.error instanceof ApiError && requete.error.status === 404
    return (
      <div className="flex flex-col gap-3.5">
        <Link to={retour} aria-label={libelleRetour} className="mq-btn-blanc grid size-12 shrink-0 place-items-center rounded-3xl text-[#12172b]">
          <IRetour />
        </Link>
        <Carte>
          {requete.isPending ? (
            <EtatChargement lignes={5} />
          ) : introuvable ? (
            <EtatErreur titre={L.commun.elementIntrouvable} texte={messageDErreur(requete.error)} />
          ) : (
            <EtatErreur titre={L.commun.erreurChargement} texte={messageDErreur(requete.error)} onReessayer={() => void requete.refetch()} />
          )}
        </Carte>
      </div>
    )
  }
  return <>{children(requete.data as T)}</>
}
