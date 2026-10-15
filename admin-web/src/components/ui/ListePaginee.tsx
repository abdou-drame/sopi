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
  /** Adresse de la fiche de chaque ligne (ligne cliquable + bouton « Voir »). */
  lien?: (ligne: T) => { vers: string; nom: string }
  messageVide?: string
}

/** Lignes montrées par page. L'API en renvoie 20 : chaque page de l'API fournit deux pages de l'écran. */
export const LIGNES_PAR_PAGE = 10
const LIGNES_API = 20

/**
 * Liste paginée (10 lignes par page à l'écran, ordre fixé par le serveur) : requête, tableau, pagination et états vide / chargement / erreur.
 * Les filtres et la page viennent de l'adresse ; aucun tri côté client (l'API renvoie les plus récents d'abord).
 */
export default function ListePaginee<T>({ legende, cle, charger, etat, colonnes, cleLigne, lien, messageVide }: Props<T>) {
  const pagesParPageApi = LIGNES_API / LIGNES_PAR_PAGE
  const pageApi = Math.ceil(etat.page / pagesParPageApi)
  const params = { ...etat.parametresApi }
  // La page de l'adresse est celle de l'écran (10 lignes) : l'API reçoit sa propre numérotation (20 lignes).
  delete params.page
  if (pageApi > 1) params.page = pageApi
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: [cle, params],
    queryFn: () => charger(params),
    placeholderData: (precedent) => precedent,
  })
  // Seule la tranche de 10 lignes de la page courante est montrée ; si l'API change sa taille de page, on ne découpe pas.
  const decoupe = data?.meta.per_page === LIGNES_API
  const debut = ((etat.page - 1) % pagesParPageApi) * LIGNES_PAR_PAGE
  const lignes = data ? (decoupe ? data.data.slice(debut, debut + LIGNES_PAR_PAGE) : data.data) : []
  const totalPages = data ? (decoupe ? Math.max(1, Math.ceil(data.meta.total / LIGNES_PAR_PAGE)) : (data.meta.last_page ?? 1)) : 1
  return (
    <div className="flex flex-col gap-2">
      {data && <p className="px-2 text-sm text-sous-titre">{L.commun.resultats(data.meta.total)}</p>}
      <Tableau
        legende={legende}
        colonnes={colonnes}
        lignes={lignes}
        cleLigne={cleLigne}
        lien={lien}
        chargement={isPending}
        erreur={isError ? messageDErreur(error) : null}
        onReessayer={() => void refetch()}
        messageVide={messageVide}
        page={decoupe ? etat.page : (data?.meta.current_page ?? etat.page)}
        totalPages={totalPages}
        total={data?.meta.total}
        onPage={etat.allerPage}
      />
    </div>
  )
}
