import { forwardRef, useEffect, useId, useRef, useState } from 'react'
import { L } from '../../i18n/libelles'
import Calendrier from './Calendrier'
import { IconeCalendrier, IconeChevronBas } from './Icones'
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'

interface CommunProps {
  label: string
  erreur?: string
  aide?: string
}

const classeChamp = (erreur?: string) =>
  `w-full min-h-12 rounded-gelule border-[1.5px] bg-fond px-[18px] py-3 text-sm text-texte placeholder:text-texte-discret transition-[border-color,background-color,box-shadow] duration-[var(--duree-rapide)] focus:bg-surface focus:outline-none focus:shadow-[0_0_0_4px_rgb(29_78_216/0.12)] disabled:text-texte-discret ${
    erreur ? 'border-erreur bg-surface' : 'border-transparent focus:border-primaire-600'
  }`

function Enveloppe({
  id,
  label,
  erreur,
  aide,
  children,
}: CommunProps & { id: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="pl-1 text-[13px] font-semibold text-texte">
        {label}
      </label>
      {children}
      {aide && !erreur && (
        <p id={`${id}-aide`} className="text-xs text-texte-doux">
          {aide}
        </p>
      )}
      {erreur && (
        <p id={`${id}-erreur`} role="alert" className="pl-[18px] text-xs font-medium text-erreur-texte">
          {erreur}
        </p>
      )}
    </div>
  )
}

const describedBy = (id: string, erreur?: string, aide?: string) =>
  erreur ? `${id}-erreur` : aide ? `${id}-aide` : undefined

/** Champ de saisie avec libellé, aide et message d'erreur reliés au champ (aria-describedby). */
export const Champ = forwardRef<HTMLInputElement, CommunProps & InputHTMLAttributes<HTMLInputElement>>(function Champ(
  { label, erreur, aide, className = '', ...reste },
  ref,
) {
  const id = useId()
  return (
    <Enveloppe id={id} label={label} erreur={erreur} aide={aide}>
      <input
        ref={ref}
        id={id}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={describedBy(id, erreur, aide)}
        className={`${classeChamp(erreur)} ${className}`}
        {...reste}
      />
    </Enveloppe>
  )
})

export function ZoneTexte({
  label,
  erreur,
  aide,
  className = '',
  ...reste
}: CommunProps & TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const id = useId()
  return (
    <Enveloppe id={id} label={label} erreur={erreur} aide={aide}>
      <textarea
        id={id}
        rows={3}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={describedBy(id, erreur, aide)}
        className={`${classeChamp(erreur)} resize-y rounded-moyen! ${className}`}
        {...reste}
      />
    </Enveloppe>
  )
}

export interface OptionSelecteur {
  valeur: string
  libelle: string
}

export function Selecteur({
  label,
  erreur,
  aide,
  options,
  className = '',
  ...reste
}: CommunProps & { options: OptionSelecteur[] } & SelectHTMLAttributes<HTMLSelectElement>) {
  const id = useId()
  return (
    <Enveloppe id={id} label={label} erreur={erreur} aide={aide}>
      <div className="relative">
        <select
          id={id}
          aria-invalid={erreur ? true : undefined}
          aria-describedby={describedBy(id, erreur, aide)}
          className={`champ-liste appearance-none pr-10 ${classeChamp(erreur)} ${className}`}
          {...reste}
        >
          {options.map((o) => (
            <option key={o.valeur} value={o.valeur}>
              {o.libelle}
            </option>
          ))}
        </select>
        <IconeChevronBas
          width={16}
          height={16}
          className="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-texte-doux"
        />
      </div>
    </Enveloppe>
  )
}

interface PropsDate extends CommunProps {
  /** Jour « AAAA-MM-JJ » ou vide. */
  value: string
  onChange: (jour: string) => void
  min?: string
  max?: string
  disabled?: boolean
}

/**
 * Champ de date : saisie au clavier par segments (champ date natif, indicateur masqué) et calendrier du système de design
 * ouvert par le bouton. La valeur est toujours un jour « AAAA-MM-JJ ».
 */
export function ChampDate({ label, erreur, aide, value, onChange, min, max, disabled }: PropsDate) {
  const id = useId()
  const [ouvert, setOuvert] = useState(false)
  const racine = useRef<HTMLDivElement>(null)
  const declencheur = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    if (!ouvert) return
    const dehors = (e: MouseEvent) => {
      if (!racine.current?.contains(e.target as Node)) setOuvert(false)
    }
    document.addEventListener('mousedown', dehors)
    return () => document.removeEventListener('mousedown', dehors)
  }, [ouvert])

  const fermer = () => {
    setOuvert(false)
    declencheur.current?.focus()
  }

  return (
    <Enveloppe id={id} label={label} erreur={erreur} aide={aide}>
      <div ref={racine} className="relative">
        <input
          id={id}
          type="date"
          value={value}
          min={min}
          max={max}
          disabled={disabled}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={erreur ? true : undefined}
          aria-describedby={describedBy(id, erreur, aide)}
          className={`champ-date appearance-none pr-11 ${classeChamp(erreur)}`}
        />
        <button
          ref={declencheur}
          type="button"
          disabled={disabled}
          aria-label={L.calendrier.ouvrir}
          aria-haspopup="dialog"
          aria-expanded={ouvert}
          onClick={() => setOuvert((o) => !o)}
          className="absolute top-1/2 right-2 grid size-8 -translate-y-1/2 place-items-center rounded-full text-texte-doux transition-colors hover:bg-primaire-50 hover:text-primaire-700"
        >
          <IconeCalendrier width={16} height={16} />
        </button>
        {ouvert && (
          <Calendrier
            valeur={value}
            min={min}
            max={max}
            onChoisir={(jour) => {
              onChange(jour)
              fermer()
            }}
            onFermer={fermer}
          />
        )}
      </div>
    </Enveloppe>
  )
}
