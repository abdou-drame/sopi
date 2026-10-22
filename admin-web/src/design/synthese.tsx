import type { ReactNode } from 'react'
import NombreAnime from '../components/ui/NombreAnime'
import { AnimatedCard } from './animation/composants'

export const sansAccent = (t: string) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Pastille de statut : fond coloré, point et libellé. */
export function Pastille({ fond, point, libelle }: { fond: string; point: string; libelle: string }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-[5px] text-xs whitespace-nowrap font-semibold transition-colors duration-200 ${fond}`}>
      <span aria-hidden="true" className="size-1.5 rounded-full" style={{ background: point }} />
      {libelle}
    </span>
  )
}

/** Carte bleue de synthèse (dégradé) : icône ronde, titre, grand chiffre avec unité et note. */
export function CarteBleue({ icone, titre, valeur, unite, aJour, note }: { icone: ReactNode; titre: string; valeur: number | undefined; unite: string; aJour?: boolean; note: string }) {
  return (
    <AnimatedCard>
    <section aria-label={titre} className="box-border flex items-center gap-3 rounded-[24px] px-4 py-2.5 text-white shadow-[0_14px_28px_-14px_rgba(45,106,79,0.55)]" style={{ background: 'var(--color-marque)' }}>
      <span className="grid size-9 shrink-0 place-items-center rounded-full bg-white/20">{icone}</span>
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-2">
          <span className="truncate text-xs font-semibold opacity-90">{titre}</span>
          {aJour && <span className="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-bold">À jour</span>}
        </div>
        <div className="flex items-baseline gap-1.5">
          <span className="text-[24px] leading-[28px] font-bold tracking-[-0.8px] tabular-nums">{valeur === undefined ? '—' : <NombreAnime valeur={valeur} format={String} />}</span>
          <span className="text-xs font-semibold opacity-90">{unite}</span>
        </div>
        <div className="truncate text-xs opacity-85">{note}</div>
      </div>
    </section>
    </AnimatedCard>
  )
}

const ICO = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true } as const
export const ICoche = () => (
  <svg {...ICO}>
    <path d="M5 12.5l4.5 4.5L19 7.5" />
  </svg>
)
export const ICroix = () => (
  <svg {...ICO}>
    <path d="M6 6l12 12M18 6L6 18" />
  </svg>
)
export const IFiltre = () => (
  <svg {...ICO} width={16} height={16}>
    <path d="M4 7h16M7 12h10M10 17h4" />
  </svg>
)

/** Carte de synthèse blanche : rond d'icône coloré, badge, grand chiffre et barre de progression fine (si un pourcentage existe). */
export function CarteStat({
  icone,
  teinte,
  badge,
  badgeTeinte,
  titre,
  valeur,
  pourcentage,
  couleurBarre,
  note,
  formater,
}: {
  icone: ReactNode
  teinte: string
  badge: string
  badgeTeinte: string
  titre: string
  valeur: number | undefined
  pourcentage?: number
  couleurBarre?: string
  note?: string
  formater?: (n: number) => string
}) {
  return (
    <AnimatedCard>
    <section aria-label={titre} className="box-border flex items-center gap-3 rounded-[24px] bg-white px-4 py-2.5">
      <span className={`grid size-9 shrink-0 place-items-center rounded-full ${teinte}`}>{icone}</span>
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-2">
          <span className="truncate text-xs font-medium text-[#8a90a2]">{titre}</span>
          <span className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${badgeTeinte}`}>{badge}</span>
        </div>
        <div className="text-[24px] leading-[28px] font-bold tracking-[-0.8px] text-[#12172b] tabular-nums">{valeur === undefined ? '—' : <NombreAnime valeur={valeur} format={formater ?? String} />}</div>
        {pourcentage !== undefined ? (
          <div className="mt-1 h-1 overflow-hidden rounded-full bg-[#eceef2]" role="presentation">
            <div className="barre-metier h-full rounded-full" style={{ width: `${pourcentage}%`, background: couleurBarre }} />
          </div>
        ) : (
          <div className="truncate text-xs text-[#8a90a2]">{note}</div>
        )}
      </div>
    </section>
    </AnimatedCard>
  )
}

