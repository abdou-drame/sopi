import type { ButtonHTMLAttributes } from 'react'

export type VarianteBouton = 'primaire' | 'secondaire' | 'danger' | 'succes' | 'fantome'

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: VarianteBouton
  taille?: 'm' | 'p'
  enCours?: boolean
  /** Texte affiché à la place du libellé pendant l'envoi (ex. « Validation… »). */
  texteEnCours?: string
}

const variantes: Record<VarianteBouton, string> = {
  primaire: 'bg-primaire-600 text-white shadow-bouton hover:bg-primaire-700',
  secondaire: 'bg-fond text-texte hover:bg-[#e4e7ee]',
  danger: 'bg-erreur text-white shadow-[0_8px_18px_rgb(229_72_77/0.28)] hover:bg-[#cf3a3f]',
  succes: 'bg-succes text-white shadow-[0_8px_18px_rgb(22_163_106/0.28)] hover:bg-[#12905d]',
  fantome: 'bg-transparent text-texte-doux hover:bg-fond hover:text-texte',
}

/** Bouton arrondi du Guide : survol plus foncé, appui à 97 %, chargement (pastille tournante), désactivé estompé. */
export default function Bouton({
  variante = 'primaire',
  taille = 'm',
  enCours = false,
  texteEnCours,
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
      className={`inline-flex items-center justify-center gap-2 rounded-gelule font-bold transition-[background-color,transform,box-shadow] duration-[var(--duree-rapide)] ease-[var(--ease-standard)] active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-45 disabled:shadow-none disabled:active:scale-100 ${
        taille === 'm' ? 'h-12 px-6 text-[13px]' : 'h-9 px-4 text-xs'
      } ${variantes[variante]} ${className}`}
      {...reste}
    >
      {enCours && (
        <span
          aria-hidden="true"
          className="size-4 animate-spin rounded-full border-[2.5px] border-current/40 border-t-current"
        />
      )}
      {enCours && texteEnCours ? texteEnCours : children}
    </button>
  )
}
