import { forwardRef, useId } from 'react'
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'

interface CommunProps {
  label: string
  erreur?: string
  aide?: string
}

const classeChamp = (erreur?: string) =>
  `w-full rounded-gelule border bg-fond px-4 py-2.5 text-sm text-texte placeholder:text-texte-discret transition-colors duration-[var(--duree-rapide)] focus:bg-surface disabled:text-texte-discret ${
    erreur ? 'border-erreur' : 'border-transparent hover:border-primaire-200'
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
      <label htmlFor={id} className="text-sm font-medium text-texte">
        {label}
      </label>
      {children}
      {aide && !erreur && (
        <p id={`${id}-aide`} className="text-xs text-texte-doux">
          {aide}
        </p>
      )}
      {erreur && (
        <p id={`${id}-erreur`} role="alert" className="text-xs font-medium text-erreur-texte">
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
      <select
        id={id}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={describedBy(id, erreur, aide)}
        className={`${classeChamp(erreur)} ${className}`}
        {...reste}
      >
        {options.map((o) => (
          <option key={o.valeur} value={o.valeur}>
            {o.libelle}
          </option>
        ))}
      </select>
    </Enveloppe>
  )
}
