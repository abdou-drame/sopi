import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { compteursParEcran, useAlertes } from '../api/useAlertes'
import { useAuth } from '../auth/AuthContext'
import { L } from '../i18n/libelles'
import { HoteEnTete } from './EnTeteLarge'
import MenuPlus from './MenuPlus'
import Logo from '../design/Logo'
import {
  IActualiser,
  IAvis,
  ICloche,
  IDeconnexion,
  IDossiers,
  ILitiges,
  IMetiers,
  IRecherche,
  ISanctions,
  ITableau,
  IUtilisateurs,
} from '../design/icones'

interface Entree {
  chemin: string
  libelle: string
  icone: React.ReactNode
}

// Libellés de la navigation : ceux des maquettes.
const TABLEAU: Entree = { chemin: '/', libelle: 'Tableau de bord', icone: <ITableau /> }
const DOSSIERS: Entree = { chemin: '/dossiers-prestataires', libelle: 'Dossiers', icone: <IDossiers /> }
const LITIGES: Entree = { chemin: '/litiges', libelle: 'Litiges', icone: <ILitiges /> }
const AVIS: Entree = { chemin: '/avis', libelle: 'Avis', icone: <IAvis /> }
const SANCTIONS: Entree = { chemin: '/sanctions', libelle: 'Sanctions', icone: <ISanctions /> }
const UTILISATEURS: Entree = { chemin: '/utilisateurs', libelle: 'Utilisateurs', icone: <IUtilisateurs /> }
const METIERS: Entree = { chemin: '/metiers', libelle: 'Métiers', icone: <IMetiers /> }

/** Barre du haut : les 7 écrans dessinés, dans l'ordre des maquettes. */
const NAV = [TABLEAU, DOSSIERS, LITIGES, AVIS, SANCTIONS, UTILISATEURS, METIERS]
/** Rail de gauche : deux capsules d'écrans, puis la capsule d'actions. */
const RAIL = [
  [TABLEAU, DOSSIERS, LITIGES, AVIS],
  [SANCTIONS, UTILISATEURS, METIERS],
]

const CAPSULE = 'flex flex-col gap-1.5 rounded-[34px] bg-white p-2.5'

function Pastille({ valeur, rouge = false }: { valeur: number; rouge?: boolean }) {
  return (
    <span
      aria-hidden="true"
      className={`box-border flex h-[18px] min-w-[18px] items-center justify-center rounded-[9px] px-[5px] text-[10px] font-bold text-white ${rouge ? 'bg-[#e5484d]' : 'bg-[#1d4ed8]'}`}
    >
      {valeur}
    </span>
  )
}

function BarreDuHaut() {
  const { data } = useAlertes()
  const { utilisateur } = useAuth()
  const total = data?.total ?? 0
  const compteurs = compteursParEcran(data?.alertes ?? [])
  const initiales = `${utilisateur?.prenom?.charAt(0) ?? 'A'}${utilisateur?.nom?.charAt(0) ?? ''}`.toUpperCase()
  return (
    <header className="box-border flex flex-wrap items-center gap-x-5 gap-y-3 rounded-full bg-white py-2.5 pr-3.5 pl-[22px]">
      <Link to="/" aria-label={L.marque} className="flex items-center gap-2.5">
        <Logo />
        <span aria-hidden="true" className="text-xl font-extrabold tracking-[-0.5px] text-[#1d4ed8]">
          {L.marque}
        </span>
      </Link>
      <nav aria-label={L.menu.titre} className="flex min-w-0 flex-[1_1_auto] justify-center">
        <div className="flex max-w-full gap-0.5 overflow-x-auto rounded-full bg-[#f3f4f7] p-1">
          {NAV.map((e) => {
            const compteur = e === DOSSIERS || e === LITIGES ? (compteurs[e.chemin] ?? 0) : 0
            return (
              <NavLink
                key={e.chemin}
                to={e.chemin}
                end={e.chemin === '/'}
                aria-label={`${e.libelle}${compteur ? `, ${L.coque.alertes(compteur)}` : ''}`}
                className="mq-nvi"
              >
                {e.libelle}
                {compteur > 0 && <Pastille valeur={compteur} rouge={e === LITIGES} />}
              </NavLink>
            )
          })}
          <MenuPlus />
        </div>
      </nav>
      <div className="flex items-center gap-2">
        <button
          type="button"
          aria-label="Rechercher"
          className="mq-rond grid size-11 cursor-pointer place-items-center rounded-[22px] border-none bg-[#f3f4f7] text-[#12172b]"
        >
          <IRecherche />
        </button>
        <Link
          to="/"
          aria-label={total > 0 ? `${L.coque.notifications} : ${L.coque.alertes(total)}` : L.coque.notifications}
          className="mq-rond relative grid size-11 place-items-center rounded-[22px] bg-[#f3f4f7] text-[#12172b]"
        >
          <ICloche />
          {total > 0 && <span aria-hidden="true" className="absolute top-[11px] right-3 box-content size-2 rounded-full border-2 border-[#f3f4f7] bg-[#e5484d]" />}
        </Link>
        <span
          aria-label={L.coque.menuCompte}
          role="img"
          className="grid size-11 place-items-center rounded-[22px] text-sm font-bold text-white"
          style={{ background: 'linear-gradient(135deg, #3b66ea, #17338f)' }}
        >
          <span aria-hidden="true">{initiales}</span>
        </span>
      </div>
    </header>
  )
}

function Rail() {
  const { deconnexion } = useAuth()
  const clientRequetes = useQueryClient()
  return (
    <aside
      aria-label={L.coque.raccourcis}
      className="sticky top-4 hidden h-[calc(100vh-32px)] max-h-[820px] flex-[0_0_68px] flex-col justify-center gap-4 self-start min-[900px]:flex"
    >
      {RAIL.map((groupe, g) => (
        <div key={g} className={CAPSULE}>
          {groupe.map((e) => (
            <NavLink key={e.chemin} to={e.chemin} end={e.chemin === '/'} aria-label={e.libelle} title={e.libelle} className="mq-ric">
              {e.icone}
            </NavLink>
          ))}
        </div>
      ))}
      <div className={CAPSULE}>
        <button type="button" aria-label={L.coque.actualiser} title={L.coque.actualiser} onClick={() => void clientRequetes.invalidateQueries()} className="mq-ric">
          <IActualiser />
        </button>
        <button type="button" aria-label={L.coque.deconnexion} title={L.coque.deconnexion} onClick={() => void deconnexion()} className="mq-ric">
          <IDeconnexion />
        </button>
      </div>
    </aside>
  )
}

export default function Coque() {
  const lieu = useLocation()
  const [hote, setHote] = useState<HTMLElement | null>(null)

  // Jetons des maquettes (maquette.css) : actifs seulement dans la coque, la connexion reste inchangée.
  useEffect(() => {
    document.documentElement.classList.add('maquette')
    return () => document.documentElement.classList.remove('maquette')
  }, [])

  // Nouvelle page : on revient en haut.
  useEffect(() => {
    window.scrollTo?.({ top: 0 })
  }, [lieu.pathname])

  return (
    <HoteEnTete.Provider value={hote}>
      <div className="box-border min-h-screen bg-[#eceef2] px-3.5 pt-3 pb-6 text-[#12172b]">
        <a
          href="#contenu"
          className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-[20px] focus:bg-white focus:px-4 focus:py-2"
        >
          {L.coque.allerAuContenu}
        </a>
        <div className="flex flex-col gap-4">
          <BarreDuHaut />
          <div className="flex items-stretch gap-3.5">
            <Rail />
            <main id="contenu" tabIndex={-1} className="flex min-w-0 flex-1 flex-col gap-4 outline-none">
              <div ref={setHote} className="empty:hidden px-2" />
              <Outlet />
            </main>
          </div>
        </div>
      </div>
    </HoteEnTete.Provider>
  )
}
