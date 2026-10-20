import type { ButtonHTMLAttributes, ReactNode } from 'react'

type Variante = 'bleu' | 'gris' | 'rouge'

const STYLE: Record<Variante, string> = {
  bleu: 'mq-btn-bleu',
  gris: 'mq-btn-gris',
  rouge: 'mq-btn-rouge',
}

/** Bouton des fenêtres et des états (maquettes) : 48 px de haut, rayon 24, 13 px gras. */
export function BoutonAction({ variante = 'bleu', enCours = false, children, ...rest }: { variante?: Variante; enCours?: boolean; children: ReactNode } & Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'className'>) {
  return (
    <button
      type="button"
      {...rest}
      disabled={rest.disabled || enCours}
      aria-busy={enCours || undefined}
      className={`${STYLE[variante]} inline-flex h-12 cursor-pointer items-center gap-2 rounded-3xl border-none text-[13px] leading-[normal] font-bold disabled:cursor-not-allowed ${variante === 'bleu' ? 'px-6' : 'px-[22px]'}`}
    >
      {children}
    </button>
  )
}
