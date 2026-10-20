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
    <dl className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-2">
      {elements.map((e) => (
        <div key={e.libelle} className={`rounded-[20px] bg-fond px-[18px] py-3.5 ${e.large ? 'col-span-full' : ''}`}>
          <dt className="text-xs font-medium text-[#9aa0b2]">{e.libelle}</dt>
          <dd className="mt-[3px] text-sm font-semibold break-words whitespace-pre-line text-texte">
            {e.valeur === null || e.valeur === undefined || e.valeur === '' ? '—' : e.valeur}
          </dd>
        </div>
      ))}
    </dl>
  )
}
