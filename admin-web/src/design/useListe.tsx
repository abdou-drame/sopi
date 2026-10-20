import { useQuery } from '@tanstack/react-query'
import { messageDErreur } from '../api/client'
import type { Page } from '../api/types'
import type { FiltresUrl } from '../lib/useFiltresUrl'
import Bouton from '../components/ui/Bouton'
import Squelette from '../components/ui/Squelette'
import { L } from '../i18n/libelles'

/** Lignes montrées par page à l'écran. L'API en renvoie 20 : chaque page de l'API fournit deux pages de l'écran. */
export const LIGNES_PAR_PAGE = 10
const LIGNES_API = 20

/**
 * Données d'une liste dessinée : mêmes règles que l'ancienne liste paginée (10 lignes à l'écran, page et filtres dans
 * l'adresse, ordre fixé par le serveur), sans le rendu : les maquettes dessinent le tableau et le pied de liste.
 */
export function useListe<T>(cle: string, charger: (params: Record<string, string | number>) => Promise<Page<T>>, etat: FiltresUrl) {
  const pagesParPageApi = LIGNES_API / LIGNES_PAR_PAGE
  const pageApi = Math.ceil(etat.page / pagesParPageApi)
  const params = { ...etat.parametresApi }
  delete params.page
  if (pageApi > 1) params.page = pageApi
  const requete = useQuery({ queryKey: [cle, params], queryFn: () => charger(params), placeholderData: (precedent) => precedent })
  const { data } = requete
  const decoupe = data?.meta.per_page === LIGNES_API
  const debut = ((etat.page - 1) % pagesParPageApi) * LIGNES_PAR_PAGE
  const lignes = data ? (decoupe ? data.data.slice(debut, debut + LIGNES_PAR_PAGE) : data.data) : []
  const total = data?.meta.total ?? 0
  const page = decoupe ? etat.page : (data?.meta.current_page ?? etat.page)
  const totalPages = data ? (decoupe ? Math.max(1, Math.ceil(total / LIGNES_PAR_PAGE)) : (data.meta.last_page ?? 1)) : 1
  const premier = lignes.length === 0 ? 0 : (page - 1) * (decoupe ? LIGNES_PAR_PAGE : (data?.meta.per_page ?? lignes.length)) + 1
  return {
    lignes,
    total,
    page,
    totalPages,
    premier,
    dernier: premier === 0 ? 0 : premier + lignes.length - 1,
    chargement: requete.isPending,
    erreur: requete.isError ? messageDErreur(requete.error) : null,
    recharger: () => void requete.refetch(),
    donneesPresentes: !!data,
  }
}

/** États vide, chargement et erreur d'une liste : composants existants, non redessinés (absents des maquettes). */
export function EtatsListe({ chargement, erreur, vide, messageVide, onReessayer }: { chargement: boolean; erreur: string | null; vide: boolean; messageVide: string; onReessayer: () => void }) {
  if (chargement)
    return (
      <div role="status" aria-label={L.commun.chargement} className="flex flex-col gap-2">
        <Squelette className="h-14 w-full rounded-grand" />
        <Squelette className="h-14 w-full rounded-grand" />
        <Squelette className="h-14 w-full rounded-grand" />
      </div>
    )
  if (erreur)
    return (
      <div role="alert" className="flex flex-col items-start gap-3 rounded-grand bg-erreur-fond p-5 text-erreur-texte">
        <p className="text-sm font-medium">{erreur}</p>
        <Bouton variante="secondaire" taille="p" onClick={onReessayer}>
          {L.commun.reessayer}
        </Bouton>
      </div>
    )
  if (vide) return <p className="py-10 text-center text-sm text-[#8a90a2]">{messageVide}</p>
  return null
}
