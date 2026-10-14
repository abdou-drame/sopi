import type { ReactNode } from 'react'
import { L } from '../../i18n/libelles'
import Bouton from './Bouton'
import { IconeTri } from './Icones'
import Squelette from './Squelette'

export interface Colonne<T> {
  cle: string
  titre: string
  rendu: (ligne: T) => ReactNode
  triable?: boolean
  alignement?: 'gauche' | 'droite'
}

export interface Tri {
  cle: string
  sens: 'asc' | 'desc'
}

interface Props<T> {
  legende: string
  colonnes: Colonne<T>[]
  lignes: T[]
  cleLigne: (ligne: T) => string
  chargement?: boolean
  erreur?: string | null
  onReessayer?: () => void
  messageVide?: string
  tri?: Tri
  onTri?: (cle: string) => void
  page?: number
  totalPages?: number
  total?: number
  onPage?: (page: number) => void
}

/** Tableau paginé avec tri (contrôlé par le parent) et états vide / chargement / erreur. */
export default function Tableau<T>({
  legende,
  colonnes,
  lignes,
  cleLigne,
  chargement,
  erreur,
  onReessayer,
  messageVide = L.commun.aucuneDonnee,
  tri,
  onTri,
  page = 1,
  totalPages = 1,
  total,
  onPage,
}: Props<T>) {
  const aligne = (c: Colonne<T>) => (c.alignement === 'droite' ? 'text-right' : 'text-left')

  return (
    <div className="overflow-hidden rounded-grand bg-surface shadow-carte">
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm" aria-busy={chargement || undefined}>
          <caption className="sr-only">{legende}</caption>
          <thead>
            <tr className="border-b border-bordure bg-fond/60">
              {colonnes.map((c) => {
                const actif = tri?.cle === c.cle
                return (
                  <th
                    key={c.cle}
                    scope="col"
                    aria-sort={actif ? (tri?.sens === 'asc' ? 'ascending' : 'descending') : undefined}
                    className={`px-4 py-3 text-xs font-semibold tracking-wide text-texte-doux uppercase ${aligne(c)}`}
                  >
                    {c.triable && onTri ? (
                      <button
                        type="button"
                        onClick={() => onTri(c.cle)}
                        aria-label={L.commun.trierPar(c.titre)}
                        className="inline-flex items-center gap-1 rounded-petit uppercase hover:text-primaire-700"
                      >
                        {c.titre}
                        <IconeTri className={actif ? 'text-primaire-600' : 'opacity-40'} />
                      </button>
                    ) : (
                      c.titre
                    )}
                  </th>
                )
              })}
            </tr>
          </thead>
          <tbody>
            {chargement &&
              Array.from({ length: 5 }, (_, i) => (
                <tr key={`sq-${i}`} className="border-b border-bordure last:border-0">
                  {colonnes.map((c) => (
                    <td key={c.cle} className="px-4 py-3.5">
                      <Squelette className="h-4 w-full max-w-40" />
                    </td>
                  ))}
                </tr>
              ))}
            {!chargement &&
              !erreur &&
              lignes.map((ligne) => (
                <tr
                  key={cleLigne(ligne)}
                  className="border-b border-bordure transition-colors last:border-0 hover:bg-primaire-50/60"
                >
                  {colonnes.map((c) => (
                    <td key={c.cle} className={`px-4 py-3.5 text-texte ${aligne(c)}`}>
                      {c.rendu(ligne)}
                    </td>
                  ))}
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      {chargement && <p className="sr-only" role="status">{L.commun.chargement}</p>}

      {erreur && !chargement && (
        <div role="alert" className="flex flex-col items-center gap-3 px-4 py-10 text-center">
          <p className="text-sm font-medium text-erreur-texte">{erreur}</p>
          {onReessayer && (
            <Bouton variante="secondaire" taille="p" onClick={onReessayer}>
              {L.commun.reessayer}
            </Bouton>
          )}
        </div>
      )}

      {!chargement && !erreur && lignes.length === 0 && (
        <p className="px-4 py-10 text-center text-sm text-texte-doux">{messageVide}</p>
      )}

      {onPage && !erreur && totalPages > 1 && (
        <nav
          aria-label="Pagination"
          className="flex items-center justify-between gap-3 border-t border-bordure px-4 py-3"
        >
          <p className="text-xs text-texte-doux">
            {total !== undefined && `${L.commun.resultats(total)} · `}
            {L.commun.page(page, totalPages)}
          </p>
          <div className="flex gap-2">
            <Bouton variante="secondaire" taille="p" disabled={page <= 1} onClick={() => onPage(page - 1)}>
              {L.commun.precedent}
            </Bouton>
            <Bouton variante="secondaire" taille="p" disabled={page >= totalPages} onClick={() => onPage(page + 1)}>
              {L.commun.suivant}
            </Bouton>
          </div>
        </nav>
      )}
    </div>
  )
}
