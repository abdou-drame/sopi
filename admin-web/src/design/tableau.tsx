import type { ReactNode } from 'react'
import { L } from '../i18n/libelles'
import type { Selection } from './useSelection'
import { useTri, type EtatTri, type ValeurTri } from './useTri'

export interface ColonneDonnees<T> {
  titre: string
  droite?: boolean
  /** Valeur comparée pour trier cette colonne ; sans elle, l'en-tête n'est pas cliquable (actions, avatars, textes libres). */
  tri?: (ligne: T) => ValeurTri
}

/** Ce que reçoit chaque ligne pour participer à la sélection : sa case à cocher et son attribut de surbrillance. */
export interface ContexteLigne<T> {
  /** Cellule de la case à cocher, à placer en première cellule de la ligne (rien quand le tableau n'a pas de sélection). */
  caseACocher: (ligne: T) => ReactNode
  /** À étaler sur le <tr> : marque la ligne cochée pour la surbrillance. */
  proprietes: (ligne: T) => { 'data-selectionnee'?: 'true' }
}

const CASE = 'size-4 cursor-pointer accent-marque disabled:cursor-not-allowed disabled:opacity-40'

function Fleche({ sens }: { sens: EtatTri['sens'] | null }) {
  return (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className={sens ? 'text-marque' : 'opacity-50'}>
      {sens === 'asc' ? <path d="M12 19V5M6 11l6-6 6 6" /> : sens === 'desc' ? <path d="M12 5v14M6 13l6 6 6-6" /> : <path d="M8 9l4-4 4 4M8 15l4 4 4-4" />}
    </svg>
  )
}

function Cases<T>({ selection }: { selection: Selection<T> }) {
  return (
    <input
      type="checkbox"
      aria-label={L.tableaux.toutSelectionner}
      className={CASE}
      checked={selection.toutCoche}
      ref={(el) => {
        if (el) el.indeterminate = selection.indetermine
      }}
      onChange={selection.basculerTout}
    />
  )
}

/**
 * Tableau commun des listes : en-têtes triables (tri sur la page affichée), case de sélection optionnelle et deux habillages
 * (`mq` pour les listes à lignes cliquables, `compact` pour les écrans à cartes de synthèse). Les lignes sont fournies par
 * `children`, qui reçoit les lignes déjà triées.
 */
export default function TableauDonnees<T>({
  legende,
  colonnes,
  lignes,
  variante = 'mq',
  largeurMin = 860,
  actionsColonne,
  libelleActions,
  masque = false,
  selection,
  children,
}: {
  legende: string
  colonnes: ColonneDonnees<T>[]
  lignes: T[]
  variante?: 'mq' | 'compact'
  largeurMin?: number
  /** Nom accessible de la dernière colonne (actions de ligne). */
  actionsColonne: string
  /** Titre visible de cette colonne (variante `compact` seulement). */
  libelleActions?: string
  masque?: boolean
  selection?: Selection<T>
  children: (lignes: T[], contexte: ContexteLigne<T>) => ReactNode
}) {
  const { triees, etat, basculer } = useTri(lignes, colonnes)
  const compact = variante === 'compact'
  const contexte: ContexteLigne<T> = {
    caseACocher: (ligne) =>
      selection && (
        <td className="w-10">
          <input
            type="checkbox"
            aria-label={selection.libelle(ligne)}
            className={CASE}
            checked={selection.estCoche(ligne)}
            disabled={!selection.peutCocher(ligne)}
            title={selection.peutCocher(ligne) ? undefined : L.tableaux.nonSelectionnable}
            onChange={() => selection.basculer(ligne)}
          />
        </td>
      ),
    proprietes: (ligne) => (selection?.estCoche(ligne) ? { 'data-selectionnee': 'true' } : {}),
  }

  return (
    <div className="overflow-x-auto" hidden={masque}>
      <table
        className={compact ? 'w-full border-collapse text-[13px] [&_tbody_td]:border-b [&_tbody_td]:border-[#eceef2] [&_tbody_tr:last-child_td]:border-b-0 [&_td]:px-4 [&_td]:py-1.5 [&_td]:align-middle [&_td]:font-medium' : 'mq-table'}
        style={{ minWidth: largeurMin }}
        aria-label={legende}
      >
        <thead>
          <tr className={compact ? 'text-left text-[11px] tracking-[0.06em] text-[#9aa0b2] uppercase [&_th]:border-b [&_th]:border-[#eceef2] [&_th]:px-4 [&_th]:py-1.5 [&_th]:font-semibold' : undefined}>
            {selection && (
              <th className="w-10">
                <Cases selection={selection} />
              </th>
            )}
            {colonnes.map((c, i) => {
              const sens = etat?.colonne === i ? etat.sens : null
              return (
                <th
                  key={c.titre}
                  style={c.droite ? { textAlign: 'right' } : undefined}
                  aria-sort={c.tri ? (sens === 'asc' ? 'ascending' : sens === 'desc' ? 'descending' : 'none') : undefined}
                >
                  {c.tri ? (
                    <button
                      type="button"
                      onClick={() => basculer(i)}
                      title={L.tableaux.trierInfobulle}
                      className="inline-flex cursor-pointer items-center gap-1 border-none bg-transparent p-0 text-inherit [font:inherit] [letter-spacing:inherit] [text-transform:inherit] hover:text-[#12172b]"
                    >
                      {c.titre}
                      <Fleche sens={sens} />
                    </button>
                  ) : (
                    c.titre
                  )}
                </th>
              )
            })}
            <th className={compact ? 'relative text-right' : 'relative'}>
              <span className="sr-only">{actionsColonne}</span>
              {compact && libelleActions && <span aria-hidden="true">{libelleActions}</span>}
            </th>
          </tr>
        </thead>
        <tbody>{children(triees, contexte)}</tbody>
      </table>
    </div>
  )
}
