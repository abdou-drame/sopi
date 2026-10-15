import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { compteursParEcran, useAlertes } from '../api/useAlertes'
import BasculeTheme from '../theme/BasculeTheme'
import Infobulle from '../components/ui/Infobulle'
import FondArrierePlan from '../components/ui/FondArrierePlan'
import { IconeChevronBas, IconeCloche, IconeDeconnexion } from '../components/ui/Icones'
import { useAuth } from '../auth/AuthContext'
import { L } from '../i18n/libelles'
import { HoteEnTete } from './EnTeteLarge'
import { MENU } from './menu'

const ENTREES = MENU.flatMap((g) => g.entrees)
/** Raccourcis de la barre du haut : les écrans les plus consultés. */
const RACCOURCIS = ['/', '/reservations', '/paiements', '/revenus', '/litiges']
  .map((chemin) => ENTREES.find((e) => e.chemin === chemin))
  .filter((e) => e !== undefined)

const VERRE = 'verre sombre:border sombre:border-white/15 sombre:backdrop-blur-xl'

function Logo() {
  return (
    <Link to="/" className="flex shrink-0 items-center gap-3 rounded-gelule pr-2" aria-label={L.marque}>
      <span aria-hidden="true" className="grid size-10 place-items-center rounded-full bg-primaire-600 text-lg font-bold text-white shadow-bouton">
        S
      </span>
      <span aria-hidden="true" className="hidden text-lg font-bold text-primaire-800 sm:block">
        {L.marque}
      </span>
    </Link>
  )
}

function MenuCompte() {
  const { utilisateur, deconnexion } = useAuth()
  const [ouvert, setOuvert] = useState(false)
  const racine = useRef<HTMLDivElement>(null)
  const initiale = (utilisateur?.prenom ?? 'A').charAt(0).toUpperCase()

  useEffect(() => {
    if (!ouvert) return
    const fermerClic = (e: MouseEvent) => {
      if (!racine.current?.contains(e.target as Node)) setOuvert(false)
    }
    const fermerTouche = (e: KeyboardEvent) => e.key === 'Escape' && setOuvert(false)
    document.addEventListener('mousedown', fermerClic)
    document.addEventListener('keydown', fermerTouche)
    return () => {
      document.removeEventListener('mousedown', fermerClic)
      document.removeEventListener('keydown', fermerTouche)
    }
  }, [ouvert])

  return (
    <div ref={racine} className="relative">
      <button
        type="button"
        aria-label={L.coque.menuCompte}
        aria-expanded={ouvert}
        aria-haspopup="true"
        onClick={() => setOuvert((o) => !o)}
        className="flex items-center gap-1.5 rounded-gelule p-1 pr-2 transition-colors hover:bg-fond"
      >
        <span aria-hidden="true" className="grid size-10 place-items-center rounded-full bg-primaire-100 font-semibold text-primaire-700">
          {initiale}
        </span>
        <IconeChevronBas width={14} height={14} className={`text-texte-discret transition-transform duration-[var(--duree-normale)] ${ouvert ? 'rotate-180' : ''}`} />
      </button>
      {ouvert && (
        <div className="animate-infobulle absolute top-full right-0 z-40 mt-2 w-60 rounded-grand bg-surface p-3 shadow-eleve sombre:border sombre:border-white/15">
          <p className="px-2 text-micro text-texte-discret">{L.coque.connecteEn}</p>
          <p className="px-2 text-sm font-semibold text-texte">
            {utilisateur?.prenom} {utilisateur?.nom}
          </p>
          <p className="mb-2 px-2 text-micro text-texte-doux">{L.coque.administrateur}</p>
          <button
            type="button"
            onClick={() => void deconnexion()}
            className="flex w-full items-center gap-2 rounded-gelule bg-fond px-4 py-2.5 text-sm font-medium text-texte-doux transition-colors hover:text-primaire-700"
          >
            <IconeDeconnexion width={18} height={18} />
            {L.coque.deconnexion}
          </button>
        </div>
      )}
    </div>
  )
}

function BarreDuHaut() {
  const { data } = useAlertes()
  const total = data?.total ?? 0
  return (
    <header
      className={`${VERRE} relative z-30 flex shrink-0 items-center justify-between gap-4 rounded-gelule bg-surface/90 py-2 pr-3 pl-3 shadow-carte backdrop-blur`}
    >
      <Logo />
      <nav aria-label={L.coque.raccourcis} className="hidden gap-1 rounded-gelule bg-fond p-1 md:flex">
        {RACCOURCIS.map((e) => (
          <NavLink
            key={e.chemin}
            to={e.chemin}
            end={e.chemin === '/'}
            className={({ isActive }) =>
              `rounded-gelule px-4 py-1.5 text-sm font-medium whitespace-nowrap transition-[background-color,color,box-shadow] duration-[var(--duree-rapide)] ${
                isActive ? 'bg-surface font-semibold text-texte shadow-carte' : 'text-texte-doux hover:text-texte'
              }`
            }
          >
            {e.libelle}
          </NavLink>
        ))}
      </nav>
      <div className="flex items-center gap-2">
        <BasculeTheme />
        <Link
          to="/"
          aria-label={total > 0 ? `${L.coque.notifications} : ${L.coque.alertes(total)}` : L.coque.notifications}
          className="relative grid size-10 place-items-center rounded-full bg-fond text-texte-doux transition-colors hover:text-primaire-700"
        >
          <IconeCloche />
          {total > 0 && (
            <span aria-hidden="true" className="absolute -top-0.5 -right-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-erreur px-1 text-[10px] font-semibold text-white">
              {total}
            </span>
          )}
        </Link>
        <MenuCompte />
      </div>
    </header>
  )
}

function ColonneIcones() {
  const { data } = useAlertes()
  const { deconnexion } = useAuth()
  const compteurs = compteursParEcran(data?.alertes ?? [])
  return (
    <aside className="flex shrink-0 items-start gap-3 overflow-x-auto lg:sticky lg:top-0 lg:flex-col lg:items-center lg:self-start lg:overflow-visible">
      <nav
        aria-label={L.menu.titre}
        className={`${VERRE} defilement-fin flex gap-1 rounded-gelule bg-surface p-2 shadow-carte lg:max-h-[calc(100dvh-15rem)] lg:flex-col lg:overflow-y-auto`}
      >
        {ENTREES.map((e, i) => {
          const compteur = compteurs[e.chemin] ?? 0
          return (
            <Infobulle key={e.chemin} texte={e.libelle}>
              <NavLink
                to={e.chemin}
                end={e.chemin === '/'}
                aria-label={`${e.libelle}${compteur ? `, ${L.coque.alertes(compteur)}` : ''}`}
                className={({ isActive }) =>
                  `group animate-glissement relative grid size-11 shrink-0 place-items-center rounded-full transition-[background-color,color,box-shadow,transform] duration-[var(--duree-normale)] ease-[var(--ease-sortie)] ${
                    isActive ? 'bg-primaire-600 text-white shadow-bouton' : 'text-texte-discret hover:scale-105 hover:bg-primaire-50 hover:text-primaire-700'
                  }`
                }
                style={{ animationDelay: `${i * 40}ms` }}
              >
                {e.icone}
                {compteur > 0 && (
                  <span aria-hidden="true" className="absolute top-0 right-0 grid h-4 min-w-4 place-items-center rounded-full bg-erreur px-1 text-[10px] font-semibold text-white">
                    {compteur}
                  </span>
                )}
              </NavLink>
            </Infobulle>
          )
        })}
      </nav>
      <Infobulle texte={L.coque.deconnexion}>
        <button
          type="button"
          aria-label={L.coque.deconnexion}
          onClick={() => void deconnexion()}
          className={`${VERRE} grid size-11 shrink-0 place-items-center rounded-full bg-surface text-texte-discret shadow-carte transition-colors hover:text-primaire-700`}
        >
          <IconeDeconnexion width={18} height={18} />
        </button>
      </Infobulle>
    </aside>
  )
}

export default function Coque() {
  const lieu = useLocation()
  const [hote, setHote] = useState<HTMLElement | null>(null)
  const defilement = useRef<HTMLDivElement>(null)

  // Nouvelle page : on revient en haut de la zone défilante.
  useEffect(() => {
    defilement.current?.scrollTo?.({ top: 0 })
  }, [lieu.pathname])

  return (
    <HoteEnTete.Provider value={hote}>
      <FondArrierePlan />
      <div className="mx-auto flex h-dvh max-w-[110rem] flex-col px-4 pt-4">
        <a
          href="#contenu"
          className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-moyen focus:bg-surface focus:px-4 focus:py-2 focus:shadow-eleve"
        >
          {L.coque.allerAuContenu}
        </a>
        <BarreDuHaut />
        {/* Le contenu défile sous la barre du haut, jamais derrière elle. */}
        <div ref={defilement} className="defilement-fin -mx-4 mt-4 min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-4 pt-3 pb-10">
        <div ref={setHote} className="empty:hidden mb-6 px-2" />
        <div className="flex flex-col gap-5 lg:flex-row">
          <ColonneIcones />
          <main id="contenu" tabIndex={-1} className="min-w-0 flex-1">
            <div key={lieu.pathname} className="animate-flou">
              <Outlet />
            </div>
          </main>
        </div>
        </div>
      </div>
    </HoteEnTete.Provider>
  )
}
