import type { ButtonHTMLAttributes } from 'react'

export type VarianteBouton = 'primaire' | 'secondaire' | 'danger' | 'fantome'

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: VarianteBouton
  taille?: 'm' | 'p'
  enCours?: boolean
}

const variantes: Record<VarianteBouton, string> = {
  primaire: 'bg-primaire-600 text-white shadow-bouton hover:bg-primaire-700 active:bg-primaire-800',
  secondaire: 'bg-primaire-50 text-primaire-700 hover:bg-primaire-100',
  danger: 'bg-erreur text-white hover:bg-[#b93a3a] active:bg-[#9d3030]',
  fantome: 'bg-transparent text-texte-doux hover:bg-primaire-50 hover:text-primaire-700',
}

export default function Bouton({
  variante = 'primaire',
  taille = 'm',
  enCours = false,
  className = '',
  children,
  disabled,
  type = 'button',
  ...reste
}: Props) {
  return (
    <button
      type={type}
      disabled={disabled || enCours}
      aria-busy={enCours || undefined}
      className={`relative inline-flex items-center justify-center gap-2 rounded-gelule font-semibold transition-[background-color,color,transform,box-shadow] duration-[180ms] ease-[var(--ease-standard)] enabled:hover:-translate-y-px enabled:active:translate-y-0 enabled:active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primaire-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 ${
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
      {/* Le texte reste en place (invisible) : le bouton garde exactement sa largeur et sa hauteur. */}
      <span className={`inline-flex items-center justify-center gap-2 ${enCours ? 'opacity-0' : ''}`}>{children}</span>
    </button>
  )
}
