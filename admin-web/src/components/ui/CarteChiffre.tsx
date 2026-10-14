import type { ReactNode } from 'react'
import Squelette from './Squelette'

type Teinte = 'bleu' | 'violet' | 'cyan' | 'rose'

const teintes: Record<Teinte, string> = {
  bleu: 'bg-primaire-100 text-primaire-700',
  violet: 'bg-violet-fond text-violet-texte',
  cyan: 'bg-cyan-fond text-cyan-texte',
  rose: 'bg-rose-fond text-rose-texte',
}

interface Props {
  titre: string
  valeur: ReactNode
  aide?: string
  icone?: ReactNode
  teinte?: Teinte
  chargement?: boolean
  /** Retard d'apparition en ms (effet d'échelonnement). */
  delai?: number
}

/** Carte de chiffre clé : grand nombre, titre et ligne d'aide, avec une pastille d'icône colorée. */
export default function CarteChiffre({ titre, valeur, aide, icone, teinte = 'bleu', chargement, delai = 0 }: Props) {
  return (
    <div
      className="animate-apparition rounded-carte bg-surface p-6 shadow-carte"
      style={{ animationDelay: `${delai}ms` }}
      aria-busy={chargement || undefined}
    >
      <div className="flex items-start justify-between gap-3">
        <p className="text-sm font-semibold text-texte">{titre}</p>
        {icone && (
          <span className={`grid size-10 shrink-0 place-items-center rounded-full ${teintes[teinte]}`}>{icone}</span>
        )}
      </div>
      {chargement ? (
        <>
          <Squelette className="mt-3 h-8 w-28" />
          <Squelette className="mt-3 h-3 w-36" />
        </>
      ) : (
        <>
          <p className="mt-2 text-chiffre font-bold tracking-tight text-texte">{valeur}</p>
          {aide && <p className="mt-1 text-micro text-texte-doux">{aide}</p>}
        </>
      )}
    </div>
  )
}
