import type { UseQueryResult } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ApiError, messageDErreur } from '../api/client'
import Bouton from '../components/ui/Bouton'
import Squelette from '../components/ui/Squelette'
import { L } from '../i18n/libelles'
import { IRetour } from './icones'
import { Carte } from './composants'

/**
 * Chargement, erreur et 404 d'une page de détail dessinée : états absents des maquettes, on garde les composants existants
 * (Squelette, Bouton) sous le seul rond « retour » dessiné. Une fois les données là, le contenu est celui de la maquette.
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
        {requete.isPending ? (
          <div role="status" aria-label={L.commun.chargement}>
            <Squelette className="h-10 w-72" />
            <Squelette className="mt-6 h-64 w-full rounded-grand" />
          </div>
        ) : (
          <Carte>
            <div role="alert" className="flex flex-col items-start gap-3">
              <p className="text-lg font-semibold">{introuvable ? L.commun.elementIntrouvable : L.commun.erreurChargement}</p>
              <p className="text-sm text-[#8a90a2]">{messageDErreur(requete.error)}</p>
              {!introuvable && (
                <Bouton variante="secondaire" taille="p" onClick={() => void requete.refetch()}>
                  {L.commun.reessayer}
                </Bouton>
              )}
            </div>
          </Carte>
        )}
      </div>
    )
  }
  return <>{children(requete.data as T)}</>
}
