import { Route, Routes } from 'react-router-dom'
import RouteProtegee from './auth/RouteProtegee'
import Coque from './layout/Coque'
import ConnexionPage from './pages/ConnexionPage'
import { DossierDetailPage, DossiersListePage } from './pages/DossiersPages'
import { LitigeDetailPage, LitigesListePage } from './pages/LitigesPages'
import { ReservationsListePage, ReservationDetailPage } from './pages/ReservationsPages'
import { PaiementsListePage, PaiementDetailPage } from './pages/PaiementsPages'
import { RetraitsListePage, RetraitDetailPage } from './pages/RetraitsPages'
import { NotificationEchecDetailPage, NotificationsEchecListePage } from './pages/NotificationsEchecPages'
import AvisListePage from './pages/AvisPages'
import MetiersPage from './pages/MetiersPage'
import SanctionsListePage from './pages/SanctionsPages'
import { UtilisateurDetailPage, UtilisateursListePage } from './pages/UtilisateursPages'
import RevenusPage from './pages/RevenusPage'
import TableauDeBordPage from './pages/TableauDeBordPage'
import { AccesRefusePage, NonTrouvePage } from './pages/PagesSimples'

/** Toutes les routes du back-office ; sans routeur ni fournisseurs, pour être testées avec un MemoryRouter. */
export default function Routage() {
  return (
    <Routes>
      <Route path="/connexion" element={<ConnexionPage />} />
      <Route element={<RouteProtegee />}>
        <Route element={<Coque />}>
          <Route index element={<TableauDeBordPage />} />
          <Route path="/revenus" element={<RevenusPage />} />
          <Route path="/dossiers-prestataires" element={<DossiersListePage />} />
          <Route path="/dossiers-prestataires/:id" element={<DossierDetailPage />} />
          <Route path="/litiges" element={<LitigesListePage />} />
          <Route path="/litiges/:id" element={<LitigeDetailPage />} />
          <Route path="/avis" element={<AvisListePage />} />
          <Route path="/metiers" element={<MetiersPage />} />
          <Route path="/reservations" element={<ReservationsListePage />} />
          <Route path="/reservations/:id" element={<ReservationDetailPage />} />
          <Route path="/paiements" element={<PaiementsListePage />} />
          <Route path="/paiements/:id" element={<PaiementDetailPage />} />
          <Route path="/retraits" element={<RetraitsListePage />} />
          <Route path="/retraits/:id" element={<RetraitDetailPage />} />
          <Route path="/notifications-en-echec" element={<NotificationsEchecListePage />} />
          <Route path="/notifications-en-echec/:id" element={<NotificationEchecDetailPage />} />
          <Route path="/sanctions" element={<SanctionsListePage />} />
          <Route path="/utilisateurs" element={<UtilisateursListePage />} />
          <Route path="/utilisateurs/:id" element={<UtilisateurDetailPage />} />
          <Route path="/acces-refuse" element={<AccesRefusePage />} />
          <Route path="*" element={<NonTrouvePage />} />
        </Route>
      </Route>
    </Routes>
  )
}
