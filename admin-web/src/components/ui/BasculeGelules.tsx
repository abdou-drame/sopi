interface Option<V extends string> {
  valeur: V
  libelle: string
}

interface Props<V extends string> {
  /** Nom accessible du groupe. */
  legende: string
  options: Option<V>[]
  valeur: V
  onChange: (valeur: V) => void
}

/** Onglets segmentés : l'option active est une pastille blanche ombrée sur fond gris clair. */
export default function BasculeGelules<V extends string>({ legende, options, valeur, onChange }: Props<V>) {
  return (
    <div role="group" aria-label={legende} className="inline-flex gap-0.5 rounded-gelule bg-fond p-1">
      {options.map((o) => {
        const actif = o.valeur === valeur
        return (
          <button
            key={o.valeur}
            type="button"
            aria-pressed={actif}
            onClick={() => onChange(o.valeur)}
            className={`h-9 rounded-gelule px-[18px] text-[13px] transition-colors duration-[var(--duree-rapide)] ${
              actif ? 'bg-surface font-bold text-texte shadow-[0_1px_3px_rgb(18_23_43/0.1)]' : 'font-semibold text-texte-doux hover:text-texte'
            }`}
          >
            {o.libelle}
          </button>
        )
      })}
    </div>
  )
}
