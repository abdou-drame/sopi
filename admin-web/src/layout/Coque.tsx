import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { compteursParEcran, useAlertes } from '../api/useAlertes'
import { IconeChevron, IconeDeconnexion } from '../components/ui/Icones'
import { useAuth } from '../auth/AuthContext'
import { L } from '../i18n/libelles'
import { MENU } from './menu'

function Logo({ replie }: { replie: boolean }) {
  return (
    <div className="flex items-center gap-3 px-2">
      <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-moyen bg-white/15 text-lg font-bold text-white">
        S
      </span>
      {!replie && (
        <div className="leading-tight">
          <p className="text-lg font-semibold text-white">{L.marque}</p>
          <p className="text-xs text-primaire-200">{L.sousMarque}</p>
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
      className={`sticky top-0 flex h-screen shrink-0 flex-col gap-6 bg-gradient-to-b from-primaire-800 to-primaire-900 p-4 transition-[width] duration-[var(--duree-normale)] ease-[var(--ease-sortie)] ${
        replie ? 'w-[4.75rem]' : 'w-64'
      }`}
    >
      <Logo replie={replie} />
      <nav aria-label={L.menu.titre} className="flex-1 overflow-y-auto">
        {MENU.map((groupe) => (
          <div key={groupe.titre} className="mb-4">
            {!replie && (
              <p className="mb-1 px-3 text-[11px] font-semibold tracking-wider text-primaire-300 uppercase">
                {groupe.titre}
              </p>
            )}
            <ul className="flex flex-col gap-1">
              {groupe.entrees.map((e, i) => {
                const compteur = compteurs[e.chemin] ?? 0
                return (
                  <li key={e.chemin} className="animate-glissement" style={{ animationDelay: `${i * 40}ms` }}>
                    <NavLink
                      to={e.chemin}
                      end={e.chemin === '/'}
                      title={replie ? e.libelle : undefined}
                      aria-label={
                        replie ? `${e.libelle}${compteur ? `, ${L.coque.alertes(compteur)}` : ''}` : undefined
                      }
                      className={({ isActive }) =>
                        `relative flex items-center gap-3 rounded-moyen px-3 py-2.5 text-sm font-medium transition-colors duration-[var(--duree-rapide)] ${
                          isActive ? 'bg-white text-primaire-800 shadow-carte' : 'text-primaire-100 hover:bg-white/10'
                        } ${replie ? 'justify-center' : ''}`
                      }
                    >
                      <span className="shrink-0">{e.icone}</span>
                      {!replie && <span className="flex-1 truncate">{e.libelle}</span>}
                      {compteur > 0 && (
                        <span
                          className={`grid min-w-5 place-items-center rounded-full bg-erreur px-1.5 text-[11px] font-semibold text-white ${
                            replie ? 'absolute top-0.5 right-1 h-4 min-w-4 px-1 text-[10px]' : 'h-5'
                          }`}
                        >
                          {!replie && <span className="sr-only">{L.coque.alertes(compteur)} : </span>}
                          {compteur}
                        </span>
                      )}
                    </NavLink>
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
        className="flex items-center justify-center gap-2 rounded-moyen px-3 py-2 text-sm text-primaire-200 hover:bg-white/10"
      >
        <IconeChevron className={`transition-transform duration-[var(--duree-normale)] ${replie ? 'rotate-180' : ''}`} />
        {!replie && L.menu.replier}
      </button>
    </aside>
  )
}

export default function Coque() {
  const { utilisateur, deconnexion } = useAuth()
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
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-end gap-4 px-6 py-4 md:px-8">
          <div className="flex items-center gap-3">
            <span aria-hidden="true" className="grid size-10 place-items-center rounded-full bg-primaire-100 font-semibold text-primaire-700">
              {initiale}
            </span>
            <div className="hidden leading-tight sm:block">
              <p className="text-sm font-semibold">
                {utilisateur?.prenom} {utilisateur?.nom}
              </p>
              <p className="text-xs text-texte-doux">{L.coque.administrateur}</p>
            </div>
          </div>
          <button
            type="button"
            onClick={() => void deconnexion()}
            className="flex items-center gap-2 rounded-moyen border border-bordure bg-surface px-3 py-2 text-sm font-medium text-texte-doux transition-colors hover:border-primaire-300 hover:text-primaire-700"
          >
            <IconeDeconnexion width={18} height={18} />
            {L.coque.deconnexion}
          </button>
        </header>
        <main id="contenu" tabIndex={-1} className="flex-1 px-6 pb-10 md:px-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
