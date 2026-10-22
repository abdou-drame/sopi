import type { ReactNode } from 'react'
import { L } from '../i18n/libelles'
import { BoutonAction } from './boutons'
import { IAlerte, IBoiteVide, IRecherche } from './icones'

/** Bloc centré commun au vide et à l'erreur (Etats.html) : rond de 64, titre 15 gras, texte 13. */
function BlocEtat({ ton, icone, titre, texte, role, children }: { ton: 'neutre' | 'erreur'; icone: ReactNode; titre: string; texte?: string | null; role?: 'alert' | 'status'; children?: ReactNode }) {
  return (
    <div role={role} className="px-3 py-10 text-center">
      <span className={`inline-flex size-16 items-center justify-center rounded-full ${ton === 'erreur' ? 'bg-[#fde9e9] text-[#b0161b]' : 'bg-[#f3f4f7] text-[#9aa0b2]'}`}>{icone}</span>
      <div className="mt-3.5 text-[15px] leading-[normal] font-bold">{titre}</div>
      {texte && <div className="mt-1 text-[13px] leading-[normal] text-[#8a90a2]">{texte}</div>}
      {children && <div className="mt-4">{children}</div>}
    </div>
  )
}

/**
 * Liste sans ligne. `donnees` : rien n'existe encore. `filtres` : la recherche ou les filtres ne trouvent rien,
 * avec un bouton pour tout réinitialiser quand `onReinitialiser` est fourni.
 */
export function EtatVide({ texte, variante = 'donnees', onReinitialiser }: { texte?: string | null; variante?: 'donnees' | 'filtres'; onReinitialiser?: () => void }) {
  if (variante === 'filtres') {
    return (
      <BlocEtat ton="neutre" icone={<IRecherche width={26} height={26} />} titre={L.commun.aucunResultatFiltres} texte={texte ?? L.commun.aucunResultatFiltresAide} role="status">
        {onReinitialiser && <BoutonAction onClick={onReinitialiser}>{L.commun.reinitialiser}</BoutonAction>}
      </BlocEtat>
    )
  }
  return <BlocEtat ton="neutre" icone={<IBoiteVide width={26} height={26} />} titre={L.commun.aucuneDonneePourLinstant} texte={texte} role="status" />
}

export function EtatErreur({ titre = L.commun.erreurListe, texte, onReessayer }: { titre?: string; texte?: string | null; onReessayer?: () => void }) {
  return (
    <BlocEtat ton="erreur" icone={<IAlerte width={26} height={26} />} titre={titre} texte={texte} role="alert">
      {onReessayer && <BoutonAction onClick={onReessayer}>{L.commun.reessayer}</BoutonAction>}
    </BlocEtat>
  )
}

/** Lignes animées : avatar rond, deux traits, un trait court à droite. Animation coupée avec prefers-reduced-motion. */
export function EtatChargement({ lignes = 4 }: { lignes?: number }) {
  return (
    <div role="status" aria-label={L.commun.chargement} aria-busy="true">
      {Array.from({ length: lignes }, (_, i) => (
        <div key={i} className="flex items-center gap-3 py-3" aria-hidden="true">
          <div className="mq-sk size-9 rounded-full" />
          <div className="flex flex-1 flex-col gap-2">
            <div className="mq-sk h-3.5 w-[55%] rounded-[7px]" />
            <div className="mq-sk h-2.5 w-[35%] rounded-[5px]" />
          </div>
          <div className="mq-sk h-3.5 w-[70px] rounded-[7px]" />
        </div>
      ))}
    </div>
  )
}

/** Largeurs des cellules fantômes des colonnes intermédiaires, en alternance (long, moyen, pastille, court). */
const CELLULES = [
  <div key="long" className="mq-sk h-3 w-[85%] rounded-[6px]" />,
  <div key="moyen" className="mq-sk h-3 w-[60%] rounded-[6px]" />,
  <div key="pastille" className="mq-sk h-6 w-[88px] rounded-full" />,
  <div key="court" className="mq-sk h-3 w-[45%] rounded-[6px]" />,
]

/**
 * Tableau fantôme : mêmes titres et même nombre de colonnes que le vrai tableau (qu'il remplace pendant le premier chargement),
 * première colonne avec avatar, dernière colonne d'actions avec un bouton rond.
 */
export function TableauSquelette({ colonnes, lignes = 5, largeurMin = 860, compact = false }: { colonnes: { titre: string; droite?: boolean }[]; lignes?: number; largeurMin?: number; compact?: boolean }) {
  return (
    <div role="status" aria-label={L.commun.chargement} aria-busy="true" className="overflow-x-auto">
      <table className={`mq-table ${compact ? '[&_td]:!py-1.5 [&_th]:!py-1.5' : ''}`} style={{ minWidth: largeurMin }} aria-hidden="true">
        <thead>
          <tr>
            {colonnes.map((c) => (
              <th key={c.titre} style={c.droite ? { textAlign: 'right' } : undefined}>
                {c.titre}
              </th>
            ))}
            <th />
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: lignes }, (_, i) => (
            <tr key={i}>
              {colonnes.map((c, j) => (
                <td key={c.titre} style={c.droite ? { textAlign: 'right' } : undefined}>
                  {j === 0 ? (
                    <div className="flex items-center gap-2.5">
                      <div className="mq-sk size-[30px] shrink-0 rounded-full" />
                      <div className="flex flex-1 flex-col gap-1.5">
                        <div className="mq-sk h-3 w-[70%] rounded-[6px]" />
                        <div className="mq-sk h-2.5 w-[40%] rounded-[5px]" />
                      </div>
                    </div>
                  ) : (
                    <div className={c.droite ? 'flex justify-end' : undefined}>{CELLULES[(j - 1 + i) % CELLULES.length]}</div>
                  )}
                </td>
              ))}
              <td>
                <div className="mq-sk ml-auto size-8 rounded-full" />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

/** Cartes de synthèse fantômes (même grille et même gabarit que CarteStat / CarteBleue). */
export function CartesSquelette({ n = 4 }: { n?: number }) {
  return (
    <div role="status" aria-label={L.commun.chargement} aria-busy="true" className="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(190px,1fr))]">
      {Array.from({ length: n }, (_, i) => (
        <div key={i} aria-hidden="true" className="flex flex-col gap-3 rounded-[24px] bg-white p-4">
          <div className="flex items-center justify-between">
            <div className="mq-sk size-9 rounded-full" />
            <div className="mq-sk h-5 w-16 rounded-full" />
          </div>
          <div className="mq-sk h-3 w-[55%] rounded-[6px]" />
          <div className="mq-sk h-6 w-[35%] rounded-[8px]" />
          <div className="mq-sk h-2.5 w-[70%] rounded-[5px]" />
        </div>
      ))}
    </div>
  )
}
