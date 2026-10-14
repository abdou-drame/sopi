import { useQuery } from '@tanstack/react-query'
import { messageDErreur } from '../../api/client'
import type { Page } from '../../api/types'
import type { FiltresUrl } from '../../lib/useFiltresUrl'
import { L } from '../../i18n/libelles'
import Tableau, { type Colonne } from './Tableau'

interface Props<T> {
  legende: string
  /** Clé de cache TanStack Query (les filtres et la page y sont ajoutés). */
  cle: string
  charger: (params: Record<string, string | number>) => Promise<Page<T>>
  etat: FiltresUrl
  colonnes: Colonne<T>[]
  cleLigne: (ligne: T) => string
  messageVide?: string
}

/**
 * Liste paginée (20 par page, ordre fixé par le serveur) : requête, tableau, pagination et états vide / chargement / erreur.
 * Les filtres et la page viennent de l'adresse ; aucun tri côté client (l'API renvoie les plus récents d'abord).
 */
export default function ListePaginee<T>({ legende, cle, charger, etat, colonnes, cleLigne, messageVide }: Props<T>) {
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: [cle, etat.parametresApi],
    queryFn: () => charger(etat.parametresApi),
    placeholderData: (precedent) => precedent,
  })
  return (
    <div className="flex flex-col gap-2">
      {data && <p className="px-2 text-sm text-sous-titre">{L.commun.resultats(data.meta.total)}</p>}
      <Tableau
        legende={legende}
        colonnes={colonnes}
        lignes={data?.data ?? []}
        cleLigne={cleLigne}
        chargement={isPending}
        erreur={isError ? messageDErreur(error) : null}
        onReessayer={() => void refetch()}
        messageVide={messageVide}
        page={data?.meta.current_page ?? etat.page}
        totalPages={data?.meta.last_page ?? 1}
        total={data?.meta.total}
        onPage={etat.allerPage}
      />
    </div>
  )
}
