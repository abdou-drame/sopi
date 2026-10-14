import { lazy, Suspense } from 'react'
import { Route, Routes } from 'react-router-dom'
import RouteProtegee from './auth/RouteProtegee'
import Coque from './layout/Coque'
import { ENTREES_A_VENIR } from './layout/menu'
import ConnexionPage from './pages/ConnexionPage'
import { AccesRefusePage, BientotPage, NonTrouvePage } from './pages/PagesSimples'

// Page de démonstration du système de design : chargée uniquement en développement (le code est retiré du build de production).
const DemoPage = import.meta.env.DEV ? lazy(() => import('./pages/DemoPage')) : null

/** Toutes les routes du back-office ; sans routeur ni fournisseurs, pour être testées avec un MemoryRouter. */
export default function Routage() {
  return (
    <Suspense fallback={null}>
      <Routes>
        <Route path="/connexion" element={<ConnexionPage />} />
        {DemoPage && <Route path="/dev/design-system" element={<DemoPage />} />}
        <Route element={<RouteProtegee />}>
          <Route element={<Coque />}>
            <Route index element={<BientotPage />} />
            <Route path="/revenus" element={<BientotPage />} />
            {ENTREES_A_VENIR.map((e) => (
              <Route key={e.chemin} path={e.chemin} element={<BientotPage />} />
            ))}
            <Route path="/acces-refuse" element={<AccesRefusePage />} />
            <Route path="*" element={<NonTrouvePage />} />
          </Route>
        </Route>
      </Routes>
    </Suspense>
  )
}
