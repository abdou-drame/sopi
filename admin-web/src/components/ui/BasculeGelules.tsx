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

/** Bascule en gélules : l'option active est en bleu plein foncé, les autres en gris clair. */
export default function BasculeGelules<V extends string>({ legende, options, valeur, onChange }: Props<V>) {
  return (
    <div role="group" aria-label={legende} className="inline-flex gap-1 rounded-gelule bg-fond p-1">
      {options.map((o) => {
        const actif = o.valeur === valeur
        return (
          <button
            key={o.valeur}
            type="button"
            aria-pressed={actif}
            onClick={() => onChange(o.valeur)}
            className={`rounded-gelule px-3.5 py-1.5 text-xs font-semibold transition-colors duration-[var(--duree-rapide)] ${
              actif ? 'bg-primaire-800 text-white shadow-bouton' : 'text-texte-doux hover:text-texte'
            }`}
          >
            {o.libelle}
          </button>
        )
      })}
    </div>
  )
}
