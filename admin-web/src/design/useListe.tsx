import { useQuery } from '@tanstack/react-query'
import { messageDErreur } from '../api/client'
import type { Page } from '../api/types'
import type { FiltresUrl } from '../lib/useFiltresUrl'
import { EtatChargement, EtatErreur, EtatVide, TableauSquelette } from './etats'

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

/** États vide, chargement et erreur d'une liste (Etats.html), identiques sur toutes les pages. */
export function EtatsListe({
  chargement,
  erreur,
  vide,
  messageVide,
  onReessayer,
  colonnes,
  largeurMin,
  compact,
  filtresActifs = false,
  messageFiltres,
  onReinitialiser,
}: {
  chargement: boolean
  erreur: string | null
  vide: boolean
  messageVide: string
  onReessayer: () => void
  /** Si fourni, le premier chargement affiche un tableau fantôme à ces colonnes (l'appelant masque alors son vrai tableau). */
  colonnes?: { titre: string; droite?: boolean }[]
  largeurMin?: number
  compact?: boolean
  /** Vrai si une recherche ou un filtre est actif : le vide devient « Aucun résultat pour ces filtres ». */
  filtresActifs?: boolean
  messageFiltres?: string
  onReinitialiser?: () => void
}) {
  if (chargement) return colonnes ? <TableauSquelette colonnes={colonnes} largeurMin={largeurMin} compact={compact} /> : <EtatChargement />
  if (erreur) return <EtatErreur texte={erreur} onReessayer={onReessayer} />
  if (vide) return filtresActifs ? <EtatVide variante="filtres" texte={messageFiltres} onReinitialiser={onReinitialiser} /> : <EtatVide texte={messageVide} />
  return null
}
