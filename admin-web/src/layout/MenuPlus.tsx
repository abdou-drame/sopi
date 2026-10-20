import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { Link, useLocation } from 'react-router-dom'
import { L } from '../i18n/libelles'
import { ICalendrier, ICloche, IChevronBas, IGraphique, IPaiement, IRetrait } from '../design/icones'

interface Page {
  chemin: string
  libelle: string
  icone: ReactNode
}

/** Les cinq pages regroupées sous « Plus » (Plus.html), dans l'ordre de la maquette. */
export const PAGES_PLUS: Page[] = [
  { chemin: '/revenus', libelle: L.menu.revenus, icone: <IGraphique /> },
  { chemin: '/reservations', libelle: L.menu.reservations, icone: <ICalendrier width={18} height={18} /> },
  { chemin: '/paiements', libelle: L.menu.paiements, icone: <IPaiement /> },
  { chemin: '/retraits', libelle: L.menu.retraits, icone: <IRetrait /> },
  { chemin: '/notifications-en-echec', libelle: L.menu.notificationsEnEchec, icone: <ICloche width={18} height={18} /> },
]

const dansPage = (chemin: string, page: string) => chemin === page || chemin.startsWith(`${page}/`)

/** Entrée « Plus » de la barre du haut : liste déroulante vers les cinq pages, page active surlignée. */
export default function MenuPlus() {
  const { pathname } = useLocation()
  const [ouvert, setOuvert] = useState(false)
  const [position, setPosition] = useState({ haut: 0, gauche: 0 })
  const declencheur = useRef<HTMLButtonElement>(null)
  const liste = useRef<HTMLDivElement>(null)
  const actif = PAGES_PLUS.some((p) => dansPage(pathname, p.chemin))

  useEffect(() => setOuvert(false), [pathname])

  // La liste est hors de la barre (qui défile en largeur) : position fixe, centrée sous le bouton.
  useLayoutEffect(() => {
    if (!ouvert) return
    const placer = () => {
      const r = declencheur.current?.getBoundingClientRect()
      if (r) setPosition({ haut: r.bottom + 10, gauche: Math.max(8, Math.min(r.left + r.width / 2 - 130, window.innerWidth - 268)) })
    }
    placer()
    window.addEventListener('resize', placer)
    window.addEventListener('scroll', placer, true)
    return () => {
      window.removeEventListener('resize', placer)
      window.removeEventListener('scroll', placer, true)
    }
  }, [ouvert])

  useEffect(() => {
    if (!ouvert) return
    liste.current?.querySelector<HTMLElement>('[aria-current="page"], a')?.focus()
    const dehors = (e: MouseEvent) => {
      const cible = e.target as Node
      if (!liste.current?.contains(cible) && !declencheur.current?.contains(cible)) setOuvert(false)
    }
    document.addEventListener('mousedown', dehors)
    return () => document.removeEventListener('mousedown', dehors)
  }, [ouvert])

  const surClavier = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setOuvert(false)
      declencheur.current?.focus()
      return
    }
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return
    const liens = Array.from(liste.current?.querySelectorAll<HTMLElement>('a') ?? [])
    const i = liens.indexOf(document.activeElement as HTMLElement)
    e.preventDefault()
    liens[(i + (e.key === 'ArrowDown' ? 1 : -1) + liens.length) % liens.length]?.focus()
  }

  return (
    <>
      <button
        ref={declencheur}
        type="button"
        aria-haspopup="menu"
        aria-expanded={ouvert}
        aria-current={actif ? 'page' : undefined}
        onClick={() => setOuvert((o) => !o)}
        onKeyDown={(e) => e.key === 'ArrowDown' && !ouvert && (e.preventDefault(), setOuvert(true))}
        className="mq-nvi cursor-pointer border-none bg-transparent font-[inherit]"
      >
        {L.menu.plus}
        <IChevronBas width={14} height={14} />
      </button>
      {ouvert &&
        createPortal(
          <div
            ref={liste}
            role="menu"
            aria-label={L.menu.plus}
            onKeyDown={surClavier}
            style={{ top: position.haut, left: position.gauche }}
            className="fixed z-30 box-border w-[260px] rounded-[26px] bg-white p-2 shadow-[0_18px_44px_rgba(18,23,43,0.18)]"
          >
            {PAGES_PLUS.map((p) => {
              const courante = dansPage(pathname, p.chemin)
              return (
                <Link
                  key={p.chemin}
                  to={p.chemin}
                  role="menuitem"
                  aria-current={courante ? 'page' : undefined}
                  className={`mq-rond flex h-11 items-center gap-3 rounded-[22px] px-3.5 text-[13px] leading-[normal] font-semibold text-[#12172b] ${courante ? 'bg-[#f3f4f7] font-bold' : ''}`}
                >
                  <span className={courante ? 'text-[#1d4ed8]' : 'text-[#6b7287]'}>{p.icone}</span>
                  {p.libelle}
                </Link>
              )
            })}
          </div>,
          document.body,
        )}
    </>
  )
}
