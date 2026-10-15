type Teinte = 'bleu' | 'succes' | 'attention' | 'erreur'

const couleurs: Record<Teinte, string> = {
  bleu: 'bg-primaire-600',
  succes: 'bg-succes',
  attention: 'bg-attention',
  erreur: 'bg-erreur',
}

interface Props {
  libelle: string
  /** Pourcentage de 0 à 100. */
  valeur: number
  teinte?: Teinte
  /** Texte affiché à droite du libellé (par défaut le pourcentage arrondi). */
  texteValeur?: string
  delai?: number
}

/** Barre de progression en gélule dont le remplissage s'anime de gauche à droite à l'apparition. */
export default function BarreProgression({ libelle, valeur, teinte = 'bleu', texteValeur, delai = 0 }: Props) {
  const pct = Math.max(0, Math.min(100, valeur))
  return (
    <div>
      <div className="mb-1.5 flex items-baseline justify-between gap-3 text-xs">
        <span className="font-medium text-texte-doux">{libelle}</span>
        <span className="font-semibold text-texte">{texteValeur ?? `${Math.round(pct)} %`}</span>
      </div>
      <div
        role="progressbar"
        aria-label={libelle}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={Math.round(pct)}
        className="h-2 overflow-hidden rounded-gelule bg-primaire-50"
      >
        <div
          className={`h-full origin-left rounded-gelule ${couleurs[teinte]}`}
          style={{
            width: `${pct}%`,
            animation: `remplissage var(--duree-trace) var(--ease-sortie) ${delai}ms both`,
          }}
        />
      </div>
    </div>
  )
}
