import type { ReactNode } from 'react'

export interface Definition {
  libelle: string
  valeur: ReactNode
  /** Occupe toute la largeur (textes longs). */
  large?: boolean
}

/** Liste de définitions (libellé / valeur) en grille ; une valeur vide s'affiche « — ». */
export default function Definitions({ elements }: { elements: Definition[] }) {
  return (
    <dl className="grid gap-x-8 gap-y-3.5 sm:grid-cols-2">
      {elements.map((e) => (
        <div key={e.libelle} className={e.large ? 'sm:col-span-2' : undefined}>
          <dt className="text-xs font-medium text-[#9aa0b2]">{e.libelle}</dt>
          <dd className="mt-1 text-[13px] font-semibold break-words whitespace-pre-line text-texte">
            {e.valeur === null || e.valeur === undefined || e.valeur === '' ? '—' : e.valeur}
          </dd>
        </div>
      ))}
    </dl>
  )
}
