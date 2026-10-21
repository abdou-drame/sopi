import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { L } from '../i18n/libelles'

/* Bannières des maquettes AlertesTableau.html et BannieresDossier.html. */

const Triangle = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d="M12 3 2 20h20z" />
    <path d="M12 10v4M12 17h.01" />
  </svg>
)

const CercleInfo = () => (
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="9" />
    <path d="M12 8v4M12 16h.01" />
  </svg>
)

/** Bannière de décision d'une fiche (« Ce dossier a déjà été accepté / refusé ») : fond teinté, rayon 24. */
export function BanniereDecision({ ton, children }: { ton: 'succes' | 'danger'; children: ReactNode }) {
  const couleurs = ton === 'succes' ? 'bg-[#d7f7e4] text-[#0e6245]' : 'bg-[#fde9e9] text-[#b0161b]'
  return (
    <div role="status" className={`flex items-center gap-3.5 rounded-3xl px-5 py-4 ${couleurs}`}>
      <CercleInfo />
      <span className="flex-1 text-sm leading-[normal] font-semibold">{children}</span>
    </div>
  )
}

const GRAVITES = {
  danger: { trait: 'border-l-[#b0161b]', pastille: 'bg-[#fde9e9] text-[#b0161b]' },
  attention: { trait: 'border-l-[#8a5a00]', pastille: 'bg-[#fff1cc] text-[#8a5a00]' },
  info: { trait: 'border-l-[#1d4ed8]', pastille: 'bg-[#e7edff] text-[#1d4ed8]' },
} as const

/** Bannière d'alerte : carte blanche, trait de 6 px à gauche, pastille d'icône, texte et lien d'action facultatif. */
export function BanniereAlerteMq({
  gravite,
  children,
  action,
  onFermer,
}: {
  gravite: keyof typeof GRAVITES
  children: ReactNode
  action?: { libelle: string; vers: string }
  /** Si fourni, une croix retire la bannière de l'affichage. */
  onFermer?: () => void
}) {
  const g = GRAVITES[gravite]
  return (
    <div role="alert" className={`flex flex-wrap items-center gap-3.5 rounded-3xl border-l-[6px] bg-white px-[18px] py-3.5 ${g.trait}`}>
      <span className={`grid size-9 shrink-0 place-items-center rounded-full ${g.pastille}`}>
        <Triangle />
      </span>
      <span className="min-w-0 flex-[1_1_240px] text-sm leading-[normal] font-semibold">{children}</span>
      {action && (
        <Link to={action.vers} className="mq-btn-gris inline-flex h-[34px] items-center rounded-[17px] bg-[#f3f4f7] px-3.5 text-xs font-bold text-[#12172b]">
          {action.libelle}
        </Link>
      )}
      {onFermer && (
        <button
          type="button"
          onClick={onFermer}
          aria-label={L.commun.masquerAlerte}
          className="mq-rond grid size-[34px] shrink-0 cursor-pointer place-items-center rounded-full border-none bg-transparent text-[#6b7287]"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18" />
          </svg>
        </button>
      )}
    </div>
  )
}
