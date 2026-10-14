import { requete } from './client'
import type { ReponseAlertes, ReponseConnexion, Regroupement, Revenus, TableauDeBord, Utilisateur } from './types'

// Un appel par route de l'API utilisée par le back-office. Aucune règle métier ici.
export const api = {
  connexion: (telephone: string, password: string) =>
    requete<ReponseConnexion>('POST', '/auth/connexion', { corps: { telephone, password }, publique: true }),
  deconnexion: () => requete<{ message: string }>('POST', '/auth/deconnexion'),
  moi: () => requete<Utilisateur>('GET', '/auth/moi'),

  tableauDeBord: () => requete<TableauDeBord>('GET', '/admin/tableau-de-bord'),
  alertes: () => requete<ReponseAlertes>('GET', '/admin/alertes'),
  revenus: (params: { debut: string; fin: string; regroupement: Regroupement }) =>
    requete<Revenus>('GET', '/admin/revenus', { params }),
}
