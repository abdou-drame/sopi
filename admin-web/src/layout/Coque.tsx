import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { compteursParEcran, useAlertes } from '../api/useAlertes'
import BasculeTheme from '../theme/BasculeTheme'
import Infobulle from '../components/ui/Infobulle'
import FondArrierePlan from '../components/ui/FondArrierePlan'
import { IconeActualiser, IconeChevronBas, IconeCloche, IconeDeconnexion } from '../components/ui/Icones'
import { useAuth } from '../auth/AuthContext'
import { L } from '../i18n/libelles'
import { HoteEnTete } from './EnTeteLarge'
import { MENU } from './menu'
import SopiLogo from '../components/ui/SopiLogo'

const ENTREES = MENU.flatMap((g) => g.entrees)
const entree = (chemin: string) => ENTREES.find((e) => e.chemin === chemin)!

/** Navigation de la barre du haut : les écrans dessinés dans les maquettes. */
const NAV_PRINCIPALE = ['/', '/dossiers-prestataires', '/litiges', '/avis', '/sanctions', '/utilisateurs', '/metiers'].map(entree)
/** Écrans sans maquette, regroupés sous « Plus » pour rester accessibles. */
const NAV_PLUS = ['/revenus', '/reservations', '/paiements', '/retraits', '/notifications-en-echec'].map(entree)
/** Rail de gauche : 3 groupes d'icônes (les deux premiers sont des écrans, le dernier des actions). */
const RAIL = [
  ['/', '/dossiers-prestataires', '/litiges', '/avis'].map(entree),
  ['/sanctions', '/utilisateurs', '/metiers'].map(entree),
]

const CAPSULE = 'flex flex-col gap-1.5 rounded-[34px] bg-surface p-2.5'
const BOUTON_RAIL =
  'relative grid size-12 shrink-0 place-items-center rounded-full text-texte-doux transition-[background-color,color,transform] duration-[var(--duree-rapide)] hover:bg-fond hover:text-texte active:scale-[0.97]'

function Logo() {
  return (
    <Link to="/" className="flex shrink-0 items-center gap-2.5 rounded-gelule" aria-label={L.marque}>
      <SopiLogo taille={34} decoratif />
      <span aria-hidden="true" className="hidden text-xl font-extrabold tracking-tight text-primaire-600 sm:block">
        {L.marque}
      </span>
    </Link>
  )
}

function Pastille({ valeur, erreur = false }: { valeur: number; erreur?: boolean }) {
  return (
    <span
      aria-hidden="true"
      className={`grid h-[18px] min-w-[18px] place-items-center rounded-full px-1.5 text-[10px] font-bold text-white ${erreur ? 'bg-erreur' : 'bg-primaire-600'}`}
    >
      {valeur}
    </span>
  )
}

const classeOnglet = (actif: boolean) =>
  `flex h-9 items-center gap-1.5 rounded-gelule px-4 text-[13px] whitespace-nowrap transition-colors duration-[var(--duree-rapide)] ${
    actif ? 'bg-surface font-bold text-texte shadow-[0_1px_3px_rgb(18_23_43/0.1)]' : 'font-semibold text-texte-doux hover:text-texte'
  }`

function MenuPlus({ compteurs }: { compteurs: Record<string, number> }) {
  const [ouvert, setOuvert] = useState(false)
  const racine = useRef<HTMLDivElement>(null)
  const lieu = useLocation()
  const actif = NAV_PLUS.some((e) => lieu.pathname.startsWith(e.chemin))
  const total = NAV_PLUS.reduce((t, e) => t + (compteurs[e.chemin] ?? 0), 0)

  useEffect(() => setOuvert(false), [lieu.pathname])
  useEffect(() => {
    if (!ouvert) return
    const clic = (e: MouseEvent) => !racine.current?.contains(e.target as Node) && setOuvert(false)
    const touche = (e: KeyboardEvent) => e.key === 'Escape' && setOuvert(false)
    document.addEventListener('mousedown', clic)
    document.addEventListener('keydown', touche)
    return () => {
      document.removeEventListener('mousedown', clic)
      document.removeEventListener('keydown', touche)
    }
  }, [ouvert])

  return (
    <div ref={racine} className="relative">
      <button
        type="button"
        aria-haspopup="true"
        aria-expanded={ouvert}
        onClick={() => setOuvert((o) => !o)}
        className={classeOnglet(actif)}
      >
        {L.menu.plus}
        {total > 0 && <Pastille valeur={total} />}
        <IconeChevronBas width={14} height={14} className={`transition-transform ${ouvert ? 'rotate-180' : ''}`} />
      </button>
      {ouvert && (
        <div className="animate-infobulle absolute top-full left-0 z-40 mt-2 w-64 rounded-grand bg-surface p-2 shadow-eleve sombre:border sombre:border-white/15">
          {NAV_PLUS.map((e) => (
            <NavLink
              key={e.chemin}
              to={e.chemin}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-gelule px-3.5 py-2.5 text-[13px] font-semibold transition-colors ${
                  isActive ? 'bg-primaire-100 text-primaire-600' : 'text-texte hover:bg-fond'
                }`
              }
            >
              <span aria-hidden="true">{e.icone}</span>
              <span className="flex-1">{e.libelle}</span>
              {(compteurs[e.chemin] ?? 0) > 0 && <Pastille valeur={compteurs[e.chemin]} />}
            </NavLink>
          ))}
        </div>
      )}
    </div>
  )
}

function MenuCompte() {
  const { utilisateur, deconnexion } = useAuth()
  const [ouvert, setOuvert] = useState(false)
  const racine = useRef<HTMLDivElement>(null)
  const initiales = `${utilisateur?.prenom?.charAt(0) ?? 'A'}${utilisateur?.nom?.charAt(0) ?? ''}`.toUpperCase()

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
        className="grid size-11 place-items-center rounded-full bg-gradient-to-br from-primaire-500 to-primaire-800 text-sm font-bold text-white transition-transform active:scale-[0.97]"
      >
        <span aria-hidden="true">{initiales}</span>
      </button>
      {ouvert && (
        <div className="animate-infobulle absolute top-full right-0 z-40 mt-2 w-60 rounded-grand bg-surface p-3 shadow-eleve sombre:border sombre:border-white/15">
          <p className="px-2 text-micro text-texte-discret">{L.coque.connecteEn}</p>
          <p className="px-2 text-sm font-semibold text-texte">
            {utilisateur?.prenom} {utilisateur?.nom}
          </p>
          <p className="mb-2 px-2 text-micro text-texte-doux">{L.coque.administrateur}</p>
          <div className="mb-2 flex items-center justify-between px-2">
            <span className="text-xs font-semibold text-texte-doux">{L.coque.modeSombre}</span>
            <BasculeTheme />
          </div>
          <button
            type="button"
            onClick={() => void deconnexion()}
            className="flex w-full items-center gap-2 rounded-gelule bg-fond px-4 py-2.5 text-sm font-semibold text-texte-doux transition-colors hover:text-primaire-600"
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
  const compteurs = compteursParEcran(data?.alertes ?? [])
  return (
    <header className="relative z-30 flex shrink-0 flex-wrap items-center gap-x-5 gap-y-3 rounded-[2rem] bg-surface py-2.5 pr-3.5 pl-[22px] min-[900px]:rounded-gelule">
      <Logo />
      <nav aria-label={L.menu.titre} className="flex min-w-0 flex-1 justify-center">
        <div className="defilement-fin flex max-w-full gap-0.5 overflow-x-auto rounded-gelule bg-fond p-1">
          {NAV_PRINCIPALE.map((e) => {
            const compteur = compteurs[e.chemin] ?? 0
            return (
              <NavLink
                key={e.chemin}
                to={e.chemin}
                end={e.chemin === '/'}
                aria-label={`${e.libelle}${compteur ? `, ${L.coque.alertes(compteur)}` : ''}`}
                className={({ isActive }) => classeOnglet(isActive)}
              >
                {e.libelle}
                {compteur > 0 && <Pastille valeur={compteur} erreur={e.chemin === '/litiges'} />}
              </NavLink>
            )
          })}
          <MenuPlus compteurs={compteurs} />
        </div>
      </nav>
      <div className="flex items-center gap-2">
        <Link
          to="/"
          aria-label={total > 0 ? `${L.coque.notifications} : ${L.coque.alertes(total)}` : L.coque.notifications}
          className="relative grid size-11 place-items-center rounded-full bg-fond text-texte transition-colors hover:bg-[#e4e7ee]"
        >
          <IconeCloche />
          {total > 0 && (
            <span aria-hidden="true" className="absolute top-[11px] right-3 box-content size-2 rounded-full border-2 border-fond bg-erreur" />
          )}
        </Link>
        <MenuCompte />
      </div>
    </header>
  )
}

function Rail() {
  const { data } = useAlertes()
  const { deconnexion } = useAuth()
  const clientRequetes = useQueryClient()
  const compteurs = compteursParEcran(data?.alertes ?? [])
  return (
    <aside
      aria-label={L.coque.raccourcis}
      className="sticky top-0 hidden h-[calc(100dvh-8rem)] max-h-[820px] shrink-0 flex-col justify-center gap-4 self-start min-[900px]:flex"
    >
      {RAIL.map((groupe, g) => (
        <div key={g} className={CAPSULE}>
          {groupe.map((e) => {
            const compteur = compteurs[e.chemin] ?? 0
            return (
              <Infobulle key={e.chemin} texte={e.libelle}>
                <NavLink
                  to={e.chemin}
                  end={e.chemin === '/'}
                  aria-label={`${e.libelle}${compteur ? `, ${L.coque.alertes(compteur)}` : ''}`}
                  className={({ isActive }) =>
                    `${BOUTON_RAIL} ${isActive ? 'bg-primaire-600! text-white! shadow-[0_8px_16px_rgb(29_78_216/0.28)]' : ''}`
                  }
                >
                  {e.icone}
                  {compteur > 0 && (
                    <span aria-hidden="true" className="absolute top-0 right-0 grid h-4 min-w-4 place-items-center rounded-full bg-erreur px-1 text-[10px] font-bold text-white">
                      {compteur}
                    </span>
                  )}
                </NavLink>
              </Infobulle>
            )
          })}
        </div>
      ))}
      <div className={CAPSULE}>
        <Infobulle texte={L.coque.actualiser}>
          <button type="button" aria-label={L.coque.actualiser} onClick={() => void clientRequetes.invalidateQueries()} className={BOUTON_RAIL}>
            <IconeActualiser width={20} height={20} />
          </button>
        </Infobulle>
        <Infobulle texte={L.coque.deconnexion}>
          <button type="button" aria-label={L.coque.deconnexion} onClick={() => void deconnexion()} className={BOUTON_RAIL}>
            <IconeDeconnexion width={20} height={20} />
          </button>
        </Infobulle>
      </div>
    </aside>
  )
}

export default function Coque() {
  const lieu = useLocation()
  const [hote, setHote] = useState<HTMLElement | null>(null)
  const defilement = useRef<HTMLDivElement>(null)

  // Jetons des maquettes (maquette.css) : actifs seulement dans la coque, la connexion reste inchangée.
  useEffect(() => {
    document.documentElement.classList.add('maquette')
    return () => document.documentElement.classList.remove('maquette')
  }, [])

  // Nouvelle page : on revient en haut de la zone défilante.
  useEffect(() => {
    defilement.current?.scrollTo?.({ top: 0 })
  }, [lieu.pathname])

  return (
    <HoteEnTete.Provider value={hote}>
      <FondArrierePlan />
      <div className="flex h-dvh flex-col px-3.5 pt-3">
        <a
          href="#contenu"
          className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-moyen focus:bg-surface focus:px-4 focus:py-2 focus:shadow-eleve"
        >
          {L.coque.allerAuContenu}
        </a>
        <BarreDuHaut />
        {/* Le contenu défile sous la barre du haut, jamais derrière elle. */}
        <div ref={defilement} className="defilement-fin -mx-3.5 mt-4 min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-3.5 pb-6">
          <div ref={setHote} className="empty:hidden mb-4 px-2" />
          <div className="flex gap-3.5">
            <Rail />
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
