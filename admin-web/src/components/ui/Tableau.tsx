import type { ReactNode } from 'react'
import { Link, useLocation } from 'react-router-dom'
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
  /** Fiche de chaque ligne (adresse et nom lisible) : la ligne entière devient un lien (clic, clic molette, clavier) avec un bouton « Voir ». */
  lien?: (ligne: T) => { vers: string; nom: string }
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

/**
 * Ligne dont toute la surface mène à la fiche : le vrai lien « Voir » étire une zone invisible sur toute la ligne (lien étiré).
 * Le clic, le clic molette (nouvel onglet), Ctrl/Cmd+clic et le clavier sont donc ceux d'un lien natif. Les liens et boutons propres
 * à la ligne (nom, actions de modération...) restent au-dessus de cette zone et gardent leur comportement.
 */
function LigneAvecLien({ href, nom, style, children }: { href: string; nom: string; style: React.CSSProperties; children: ReactNode }) {
  const { search } = useLocation()
  return (
    <tr
      style={style}
      className="animate-apparition group relative cursor-pointer transition-colors hover:bg-fond focus-within:bg-fond [&>td]:first:rounded-l-[16px] [&>td]:last:rounded-r-[16px] [&_a:not([data-voir])]:relative [&_a:not([data-voir])]:z-10 [&_button]:relative [&_button]:z-10"
    >
      {children}
      <td className="px-3 py-3.5 text-right">
        <Link
          to={href}
          data-voir
          state={{ recherche: search }}
          aria-label={`${L.commun.voir} : ${nom}`}
          className="inline-flex items-center rounded-gelule bg-fond px-3.5 py-1.5 text-xs font-semibold text-texte after:absolute after:inset-0 after:z-0 after:content-[''] group-hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primaire-600"
        >
          {L.commun.voirCourt}
        </Link>
      </td>
    </tr>
  )
}

/** Tableau paginé avec tri (contrôlé par le parent) et états vide / chargement / erreur. */
export default function Tableau<T>({
  legende,
  colonnes,
  lignes,
  cleLigne,
  lien,
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
    <div className="overflow-hidden rounded-carte bg-surface p-[18px] shadow-carte">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] border-separate border-spacing-y-1 text-[13px]" aria-busy={chargement || undefined}>
          <caption className="sr-only">{legende}</caption>
          <thead>
            <tr>
              {colonnes.map((c) => {
                const actif = tri?.cle === c.cle
                return (
                  <th
                    key={c.cle}
                    scope="col"
                    aria-sort={actif ? (tri?.sens === 'asc' ? 'ascending' : 'descending') : undefined}
                    className={`px-3 py-1.5 text-xs font-medium text-[#9aa0b2] first:px-4 ${aligne(c)}`}
                  >
                    {c.triable && onTri ? (
                      <button
                        type="button"
                        onClick={() => onTri(c.cle)}
                        aria-label={L.commun.trierPar(c.titre)}
                        className="inline-flex items-center gap-1 rounded-petit hover:text-primaire-700"
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
              {lien && (
                <th scope="col" className="px-5 py-3 text-right">
                  <span className="sr-only">{L.commun.actionsLigne}</span>
                </th>
              )}
            </tr>
          </thead>
          <tbody>
            {chargement &&
              Array.from({ length: 5 }, (_, i) => (
                <tr key={`sq-${i}`} className="border-b border-bordure last:border-0">
                  {colonnes.map((c) => (
                    <td key={c.cle} className="px-3 py-3.5 first:px-4">
                      <Squelette className="h-4 w-full max-w-40" />
                    </td>
                  ))}
                  {lien && <td className="px-3 py-3.5 first:px-4" />}
                </tr>
              ))}
            {!chargement &&
              !erreur &&
              lignes.map((ligne, i) => {
                const style = { animationDelay: `${Math.min(i * 25, 250)}ms` }
                const cellules = colonnes.map((c) => (
                  <td key={c.cle} className={`px-3 py-3.5 first:px-4 text-texte ${aligne(c)}`}>
                    {c.rendu(ligne)}
                  </td>
                ))
                if (!lien) {
                  return (
                    <tr key={cleLigne(ligne)} style={style} className="animate-apparition transition-colors hover:bg-fond [&>td:first-child]:rounded-l-[16px] [&>td:last-child]:rounded-r-[16px]">
                      {cellules}
                    </tr>
                  )
                }
                const { vers: href, nom } = lien(ligne)
                return (
                  <LigneAvecLien key={cleLigne(ligne)} href={href} nom={nom} style={style}>
                    {cellules}
                  </LigneAvecLien>
                )
              })}
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
