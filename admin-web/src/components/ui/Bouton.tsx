import { useEffect, useState } from 'react'
import type { ButtonHTMLAttributes } from 'react'

export type VarianteBouton = 'primaire' | 'secondaire' | 'danger' | 'fantome'

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: VarianteBouton
  taille?: 'm' | 'p'
  enCours?: boolean
  /** Passe à vrai quand l'action a réussi : une coche remplace le texte environ 1 seconde. */
  succes?: boolean
}

/** Durée d'affichage de la coche de succès, en millisecondes. */
export const DUREE_COCHE = 1000

// Variantes : primaire (principal), secondaire, danger, fantome (discret).
// Le soulèvement n'agit que sur un bouton actif (enabled:), jamais sur un bouton désactivé.
const variantes: Record<VarianteBouton, string> = {
  primaire:
    'bg-primaire-600 text-white shadow-bouton hover:bg-primaire-700 active:bg-primaire-800 enabled:hover:shadow-eleve',
  secondaire: 'bg-primaire-50 text-primaire-700 hover:bg-primaire-100 enabled:hover:shadow-bouton',
  danger: 'bg-erreur text-white hover:bg-[#b93a3a] active:bg-[#9d3030] enabled:hover:shadow-eleve',
  fantome: 'bg-transparent text-texte-doux hover:bg-primaire-50 hover:text-primaire-700',
}

export default function Bouton({
  variante = 'primaire',
  taille = 'm',
  enCours = false,
  succes = false,
  className = '',
  children,
  disabled,
  type = 'button',
  ...reste
}: Props) {
  const [coche, setCoche] = useState(false)
  useEffect(() => {
    if (!succes) return
    setCoche(true)
    const minuteur = setTimeout(() => setCoche(false), DUREE_COCHE)
    return () => clearTimeout(minuteur)
  }, [succes])
  const masque = enCours || coche
  return (
    <button
      type={type}
      disabled={disabled || enCours}
      aria-busy={enCours || undefined}
      className={`relative inline-flex items-center justify-center gap-2 rounded-gelule font-semibold transition-[background-color,color,transform,box-shadow] duration-[180ms] ease-[var(--ease-standard)] enabled:hover:-translate-y-px enabled:active:translate-y-0 enabled:active:scale-[0.97] motion-reduce:enabled:hover:translate-y-0 motion-reduce:enabled:active:scale-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primaire-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 ${
        taille === 'm' ? 'px-5 py-2.5 text-sm' : 'px-3.5 py-1.5 text-xs'
      } ${variantes[variante]} ${className}`}
      {...reste}
    >
      {enCours && (
        <span
          aria-hidden="true"
          className="absolute left-1/2 top-1/2 -ml-2 -mt-2 size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
        />
      )}
      {coche && !enCours && (
        <svg
          aria-hidden="true"
          viewBox="0 0 24 24"
          className="absolute left-1/2 top-1/2 -ml-2.5 -mt-2.5 size-5 animate-coche fill-none stroke-current stroke-[3]"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M5 12.5l4.5 4.5L19 7.5" />
        </svg>
      )}
      {/* Le texte reste en place (invisible) : le bouton garde exactement sa largeur et sa hauteur. */}
      <span className={`inline-flex items-center justify-center gap-2 ${masque ? 'opacity-0' : ''}`}>{children}</span>
    </button>
  )
}
