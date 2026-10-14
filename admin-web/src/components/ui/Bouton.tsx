import type { ButtonHTMLAttributes } from 'react'

export type VarianteBouton = 'primaire' | 'secondaire' | 'danger' | 'fantome'

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: VarianteBouton
  taille?: 'm' | 'p'
  enCours?: boolean
}

const variantes: Record<VarianteBouton, string> = {
  primaire: 'bg-primaire-600 text-white hover:bg-primaire-700 active:bg-primaire-800',
  secondaire: 'bg-primaire-50 text-primaire-700 hover:bg-primaire-100 border border-primaire-100',
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
      className={`inline-flex items-center justify-center gap-2 rounded-moyen font-medium transition-[background-color,transform,box-shadow] duration-[var(--duree-rapide)] ease-[var(--ease-standard)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 ${
        taille === 'm' ? 'px-4 py-2.5 text-sm' : 'px-3 py-1.5 text-xs'
      } ${variantes[variante]} ${className}`}
      {...reste}
    >
      {enCours && (
        <span
          aria-hidden="true"
          className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
        />
      )}
      {children}
    </button>
  )
}
