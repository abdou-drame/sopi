import { useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { compteursParEcran, useAlertes } from '../api/useAlertes'
import BasculeTheme from '../theme/BasculeTheme'
import Infobulle from '../components/ui/Infobulle'
import { IconeChevron, IconeDeconnexion } from '../components/ui/Icones'
import { useAuth } from '../auth/AuthContext'
import { L } from '../i18n/libelles'
import { MENU } from './menu'

function Logo({ replie }: { replie: boolean }) {
  return (
    <div className={`flex items-center gap-3 ${replie ? 'justify-center' : 'px-1'}`}>
      <span aria-hidden="true" className="grid size-11 shrink-0 place-items-center rounded-full bg-primaire-600 text-lg font-bold text-white">
        S
      </span>
      {!replie && (
        <div className="leading-tight">
          <p className="text-lg font-bold text-primaire-800">{L.marque}</p>
          <p className="text-micro text-texte-doux">{L.sousMarque}</p>
        </div>
      )}
    </div>
  )
}

function BarreLaterale({ replie, surBascule }: { replie: boolean; surBascule: () => void }) {
  const { data } = useAlertes()
  const compteurs = compteursParEcran(data?.alertes ?? [])

  return (
    <aside
      className={`verre sombre:border sombre:border-white/15 sombre:backdrop-blur-xl sticky top-4 m-4 flex h-[calc(100vh-2rem)] shrink-0 flex-col gap-5 rounded-carte bg-surface p-3 shadow-carte transition-[width] duration-[var(--duree-normale)] ease-[var(--ease-sortie)] ${
        replie ? 'w-[4.75rem]' : 'w-64'
      }`}
    >
      <Logo replie={replie} />
      <nav aria-label={L.menu.titre} className="defilement-fin -mx-1 flex-1 overflow-x-hidden overflow-y-auto px-1 py-1 pb-6">
        {MENU.map((groupe) => (
          <div key={groupe.titre} className="mb-3">
            {!replie && (
              <p className="mb-1 px-2 text-micro font-semibold tracking-wider text-texte-discret uppercase">
                {groupe.titre}
              </p>
            )}
            <ul className="flex flex-col gap-1">
              {groupe.entrees.map((e, i) => {
                const compteur = compteurs[e.chemin] ?? 0
                const lien = (
                  <NavLink
                    to={e.chemin}
                    end={e.chemin === '/'}
                    aria-label={replie ? `${e.libelle}${compteur ? `, ${L.coque.alertes(compteur)}` : ''}` : undefined}
                    className={({ isActive }) =>
                      `group relative flex items-center gap-3 rounded-gelule text-sm font-medium transition-colors duration-[var(--duree-rapide)] ${
                        replie ? 'justify-center' : 'pr-3'
                      } ${isActive ? 'text-primaire-800' : 'text-texte-doux hover:text-primaire-700'}`
                    }
                  >
                    {({ isActive }) => (
                      <>
                        <span
                          className={`grid size-11 shrink-0 place-items-center rounded-full transition-[background-color,color,box-shadow,transform] duration-[var(--duree-normale)] ease-[var(--ease-sortie)] ${
                            isActive
                              ? 'bg-primaire-600 text-white shadow-bouton'
                              : 'group-hover:scale-105 group-hover:bg-primaire-50'
                          }`}
                        >
                          {e.icone}
                        </span>
                        {!replie && <span className="flex-1 truncate">{e.libelle}</span>}
                        {compteur > 0 && (
                          <span
                            className={`grid place-items-center rounded-full bg-erreur font-semibold text-white ${
                              replie ? 'absolute top-0 right-0.5 h-4 min-w-4 px-1 text-[10px]' : 'h-5 min-w-5 px-1.5 text-micro'
                            }`}
                          >
                            {!replie && <span className="sr-only">{L.coque.alertes(compteur)} : </span>}
                            {compteur}
                          </span>
                        )}
                      </>
                    )}
                  </NavLink>
                )
                return (
                  <li key={e.chemin} className="animate-glissement" style={{ animationDelay: `${i * 40}ms` }}>
                    {replie ? <Infobulle texte={e.libelle}>{lien}</Infobulle> : lien}
                  </li>
                )
              })}
            </ul>
          </div>
        ))}
      </nav>
      <button
        type="button"
        onClick={surBascule}
        aria-label={replie ? L.menu.deplier : L.menu.replier}
        aria-expanded={!replie}
        className="flex items-center justify-center gap-2 rounded-gelule bg-fond px-3 py-2.5 text-sm font-medium text-texte-doux transition-colors hover:text-primaire-700"
      >
        <IconeChevron className={`transition-transform duration-[var(--duree-normale)] ${replie ? 'rotate-180' : ''}`} />
        {!replie && L.menu.replier}
      </button>
    </aside>
  )
}

export default function Coque() {
  const { utilisateur, deconnexion } = useAuth()
  const lieu = useLocation()
  const [replie, setReplie] = useState(false)
  const initiale = (utilisateur?.prenom ?? 'A').charAt(0).toUpperCase()

  return (
    <div className="flex min-h-screen">
      <a
        href="#contenu"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-moyen focus:bg-surface focus:px-4 focus:py-2 focus:shadow-eleve"
      >
        {L.coque.allerAuContenu}
      </a>
      <BarreLaterale replie={replie} surBascule={() => setReplie((r) => !r)} />
      <div className="flex min-w-0 flex-1 flex-col pr-4">
        <header className="verre sombre:border sombre:border-white/15 sombre:backdrop-blur-xl sticky top-4 z-20 mt-4 flex items-center justify-between gap-4 rounded-gelule bg-surface/90 py-2 pr-2 pl-6 shadow-carte backdrop-blur">
          <p className="truncate text-lg text-texte-doux">
            Bienvenue, <span className="font-bold text-texte">{utilisateur?.prenom}</span>
          </p>
          <div className="flex items-center gap-3">
            <div className="hidden text-right leading-tight sm:block">
              <p className="text-sm font-semibold">
                {utilisateur?.prenom} {utilisateur?.nom}
              </p>
              <p className="text-micro text-texte-doux">{L.coque.administrateur}</p>
            </div>
            <BasculeTheme />
            <span aria-hidden="true" className="grid size-10 place-items-center rounded-full bg-primaire-100 font-semibold text-primaire-700">
              {initiale}
            </span>
            <button
              type="button"
              onClick={() => void deconnexion()}
              className="flex items-center gap-2 rounded-gelule bg-fond px-4 py-2.5 text-sm font-medium text-texte-doux transition-colors hover:text-primaire-700"
            >
              <IconeDeconnexion width={18} height={18} />
              {L.coque.deconnexion}
            </button>
          </div>
        </header>
        <main id="contenu" tabIndex={-1} className="flex-1 px-2 pt-8 pb-10">
          <div key={lieu.pathname} className="animate-flou">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
