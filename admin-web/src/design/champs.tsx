import { useId } from 'react'
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'
import { IChevronBas, ICalendrier } from './icones'

/** Champs des fenêtres (maquettes MetierFormulaires, NouvelleSanction, Trancher) : fond #f3f4f7, rayon 22, hauteur 46. */
const BASE = 'box-border w-full border-none bg-[#f3f4f7] text-sm leading-[normal] text-[#12172b] outline-none'
const ENTREE = `${BASE} h-[46px] rounded-[22px] px-[18px]`

interface Commun {
  label: string
  aide?: ReactNode
  erreur?: string | null
}

function Etiquette({ label, id, aide, erreur, children }: Commun & { id: string; children: ReactNode }) {
  return (
    <div className="block">
      <label htmlFor={id} className="mb-1.5 block text-xs leading-[normal] font-semibold text-[#6b7287]">
        {label}
      </label>
      {children}
      {erreur ? (
        <span id={`${id}-erreur`} role="alert" className="mt-1.5 block pl-1 text-xs leading-[normal] font-medium text-[#b0161b]">
          {erreur}
        </span>
      ) : (
        aide && (
          <span id={`${id}-aide`} className="mt-1.5 block pl-1 text-xs leading-[normal] text-[#8a90a2]">
            {aide}
          </span>
        )
      )}
    </div>
  )
}

const decrit = (id: string, c: Commun) => (c.erreur ? `${id}-erreur` : c.aide ? `${id}-aide` : undefined)

export function ChampTexte({ label, aide, erreur, ...rest }: Commun & Omit<InputHTMLAttributes<HTMLInputElement>, 'className'>) {
  const id = useId()
  return (
    <Etiquette id={id} label={label} aide={aide} erreur={erreur}>
      <input id={id} aria-invalid={erreur ? true : undefined} aria-describedby={decrit(id, { label, aide, erreur })} className={`mq-champ ${ENTREE}`} {...rest} />
    </Etiquette>
  )
}

export function ChampZone({ label, aide, erreur, ...rest }: Commun & Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'className'>) {
  const id = useId()
  return (
    <Etiquette id={id} label={label} aide={aide} erreur={erreur}>
      <textarea id={id} aria-invalid={erreur ? true : undefined} aria-describedby={decrit(id, { label, aide, erreur })} className={`mq-champ ${BASE} block min-h-24 resize-y rounded-[22px] px-[18px] py-3.5 leading-5`} {...rest} />
    </Etiquette>
  )
}

export interface OptionChamp {
  valeur: string
  libelle: string
}

export function ChampSelect({ label, aide, erreur, options, ...rest }: Commun & { options: OptionChamp[] } & Omit<SelectHTMLAttributes<HTMLSelectElement>, 'className' | 'children'>) {
  const id = useId()
  return (
    <Etiquette id={id} label={label} aide={aide} erreur={erreur}>
      <span className="relative block">
        <select id={id} aria-invalid={erreur ? true : undefined} aria-describedby={decrit(id, { label, aide, erreur })} className={`mq-champ ${ENTREE} cursor-pointer appearance-none pr-11`} {...rest}>
          {options.map((o) => (
            <option key={o.valeur} value={o.valeur}>
              {o.libelle}
            </option>
          ))}
        </select>
        <span className="pointer-events-none absolute top-1/2 right-[18px] -translate-y-1/2 text-[#12172b]">
          <IChevronBas width={14} height={14} />
        </span>
      </span>
    </Etiquette>
  )
}

/** Champ de date (jj/mm/aaaa selon la langue du navigateur) : saisie native, icône calendrier de la maquette. */
export function ChampJour({ label, aide, erreur, ...rest }: Commun & Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'type'>) {
  const id = useId()
  return (
    <Etiquette id={id} label={label} aide={aide} erreur={erreur}>
      <span className="relative block">
        <input id={id} type="date" aria-invalid={erreur ? true : undefined} aria-describedby={decrit(id, { label, aide, erreur })} className={`mq-champ mq-jour ${ENTREE} pr-11`} {...rest} />
        <span className="pointer-events-none absolute top-1/2 right-[18px] -translate-y-1/2 text-[#6b7287]">
          <ICalendrier width={16} height={16} />
        </span>
      </span>
    </Etiquette>
  )
}
