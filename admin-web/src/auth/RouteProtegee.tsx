import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { L } from '../i18n/libelles'
import { useAuth } from './AuthContext'

/** Les routes enfants ne sont accessibles qu'à un administrateur connecté ; sinon retour à la connexion. */
export default function RouteProtegee() {
  const { utilisateur, initialisation } = useAuth()
  const lieu = useLocation()

  if (initialisation) {
    return (
      <div role="status" className="grid min-h-[calc(100vh/var(--echelle))] place-items-center text-sous-titre">
        {L.commun.chargement}
      </div>
    )
  }
  if (!utilisateur) {
    return <Navigate to="/connexion" replace state={{ depuis: lieu.pathname + lieu.search }} />
  }
  return <Outlet />
}
