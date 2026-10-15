import { lazy, Suspense } from 'react'
import { Route, Routes } from 'react-router-dom'
import RouteProtegee from './auth/RouteProtegee'
import Coque from './layout/Coque'
import ConnexionPage from './pages/ConnexionPage'
import { DossierDetailPage, DossiersListePage } from './pages/DossiersPages'
import { LitigeDetailPage, LitigesListePage } from './pages/LitigesPages'
import { ReservationsListePage, ReservationDetailPage } from './pages/ReservationsPages'
import { PaiementsListePage, PaiementDetailPage } from './pages/PaiementsPages'
import { RetraitsListePage, RetraitDetailPage } from './pages/RetraitsPages'
import AvisListePage from './pages/AvisPages'
import SanctionsListePage from './pages/SanctionsPages'
import { UtilisateurDetailPage, UtilisateursListePage } from './pages/UtilisateursPages'
import RevenusPage from './pages/RevenusPage'
import TableauDeBordPage from './pages/TableauDeBordPage'
import { AccesRefusePage, NonTrouvePage } from './pages/PagesSimples'

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
            <Route index element={<TableauDeBordPage />} />
            <Route path="/revenus" element={<RevenusPage />} />
            <Route path="/dossiers-prestataires" element={<DossiersListePage />} />
            <Route path="/dossiers-prestataires/:id" element={<DossierDetailPage />} />
            <Route path="/litiges" element={<LitigesListePage />} />
            <Route path="/litiges/:id" element={<LitigeDetailPage />} />
            <Route path="/avis" element={<AvisListePage />} />
            <Route path="/reservations" element={<ReservationsListePage />} />
            <Route path="/reservations/:id" element={<ReservationDetailPage />} />
            <Route path="/paiements" element={<PaiementsListePage />} />
            <Route path="/paiements/:id" element={<PaiementDetailPage />} />
            <Route path="/retraits" element={<RetraitsListePage />} />
            <Route path="/retraits/:id" element={<RetraitDetailPage />} />
            <Route path="/sanctions" element={<SanctionsListePage />} />
            <Route path="/utilisateurs" element={<UtilisateursListePage />} />
            <Route path="/utilisateurs/:id" element={<UtilisateurDetailPage />} />
            <Route path="/acces-refuse" element={<AccesRefusePage />} />
            <Route path="*" element={<NonTrouvePage />} />
          </Route>
        </Route>
      </Routes>
    </Suspense>
  )
}
