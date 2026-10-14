import type { FormEvent, ReactNode } from 'react'
import { L } from '../../i18n/libelles'
import Bouton from './Bouton'

interface Props {
  children: ReactNode
  onReinitialiser?: () => void
  onAppliquer?: () => void
  libelleAppliquer?: string
  legende?: string
}

/** Rangée de champs de filtre (formulaire accessible) avec actions « appliquer » et « réinitialiser ». */
export default function BarreFiltres({ children, onReinitialiser, onAppliquer, libelleAppliquer, legende }: Props) {
  const soumettre = (e: FormEvent) => {
    e.preventDefault()
    onAppliquer?.()
  }
  return (
    <form
      onSubmit={soumettre}
      role="search"
      aria-label={legende ?? 'Filtres'}
      className="flex flex-wrap items-end gap-3 rounded-carte bg-surface p-5 shadow-carte"
    >
      {children}
      {(onAppliquer || onReinitialiser) && (
        <div className="flex gap-2">
          {onAppliquer && <Bouton type="submit">{libelleAppliquer ?? 'Appliquer'}</Bouton>}
          {onReinitialiser && (
            <Bouton variante="fantome" onClick={onReinitialiser}>
              {L.commun.reinitialiser}
            </Bouton>
          )}
        </div>
      )}
    </form>
  )
}
