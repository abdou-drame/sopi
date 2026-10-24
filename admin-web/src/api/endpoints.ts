import { requete } from './client'
import type { PaiementAdmin, Page, ReponseAlertes, ReponseConnexion, Regroupement, Revenus, TableauDeBord, Utilisateur } from './types'
import type {
  AvisAdmin,
  CorpsSanction,
  CorpsRefusDemande,
  CorpsTrancher,
  DemandeDetail,
  DemandeListe,
  Litige,
  LitigeDetail,
  Metier,
  NotificationEchec,
  PaiementDetail,
  ReservationDetail,
  ReservationListe,
  RetraitDetail,
  RetraitListe,
  SanctionListe,
  SanctionUtilisateur,
  UtilisateurDetail,
  UtilisateurListe,
} from './typesAdmin'

type Params = Record<string, string | number>

// Un appel par route de l'API utilisée par le back-office. Aucune règle métier ici.
export const api = {
  connexion: (telephone: string, password: string) =>
    requete<ReponseConnexion>('POST', '/auth/connexion', { corps: { telephone, password }, publique: true }),
  deconnexion: () => requete<{ message: string }>('POST', '/auth/deconnexion'),
  moi: () => requete<Utilisateur>('GET', '/auth/moi'),

  tableauDeBord: () => requete<TableauDeBord>('GET', '/admin/tableau-de-bord'),
  alertes: () => requete<ReponseAlertes>('GET', '/admin/alertes'),
  derniersPaiements: () => requete<Page<PaiementAdmin>>('GET', '/admin/paiements'),
  revenus: (params: { debut: string; fin: string; regroupement: Regroupement }) =>
    requete<Revenus>('GET', '/admin/revenus', { params }),

  demandes: (params: Params) => requete<Page<DemandeListe>>('GET', '/admin/prestataires/demandes', { params }),
  demande: (id: string) => requete<DemandeDetail>('GET', `/admin/prestataires/${id}`),
  accepterDemande: (id: string) => requete<unknown>('POST', `/admin/prestataires/${id}/accepter`),
  refuserDemande: (id: string, corps: CorpsRefusDemande) => requete<unknown>('POST', `/admin/prestataires/${id}/refuser`, { corps }),

  litiges: (params: Params) => requete<Page<Litige>>('GET', '/admin/litiges', { params }),
  litige: (id: string) => requete<LitigeDetail>('GET', `/admin/litiges/${id}`),
  trancherLitige: (id: string, corps: CorpsTrancher) => requete<unknown>('POST', `/admin/litiges/${id}/trancher`, { corps }),

  sanctions: (params: Params) => requete<Page<SanctionListe>>('GET', '/admin/sanctions', { params }),
  leverSanction: (id: string) => requete<SanctionUtilisateur>('POST', `/admin/sanctions/${id}/lever`),
  sanctionsUtilisateur: (id: string) => requete<{ data: SanctionUtilisateur[] }>('GET', `/admin/utilisateurs/${id}/sanctions`),
  poserSanction: (id: string, corps: CorpsSanction) =>
    requete<SanctionUtilisateur>('POST', `/admin/utilisateurs/${id}/sanctions`, { corps }),

  utilisateurs: (params: Params) => requete<Page<UtilisateurListe>>('GET', '/admin/utilisateurs', { params }),
  utilisateur: (id: string) => requete<UtilisateurDetail>('GET', `/admin/utilisateurs/${id}`),

  avis: (params: Params) => requete<Page<AvisAdmin>>('GET', '/admin/avis', { params }),
  masquerAvis: (id: string, motif: string) => requete<AvisAdmin>('POST', `/admin/avis/${id}/masquer`, { corps: { motif } }),
  conserverAvis: (id: string, motif?: string) =>
    requete<AvisAdmin>('POST', `/admin/avis/${id}/conserver`, { corps: motif ? { motif } : {} }),
  retablirAvis: (id: string) => requete<AvisAdmin>('POST', `/admin/avis/${id}/retablir`),
  metiers: () => requete<{ data: Metier[] }>('GET', '/admin/metiers'),
  creerMetier: (libelle: string) => requete<Metier>('POST', '/admin/metiers', { corps: { libelle } }),
  modifierMetier: (id: string, corps: { libelle?: string; actif?: boolean }) => requete<Metier>('PATCH', `/admin/metiers/${id}`, { corps }),

  reservations: (params: Params) => requete<Page<ReservationListe>>('GET', '/admin/reservations', { params }),
  reservation: (id: string) => requete<ReservationDetail>('GET', `/admin/reservations/${id}`),
  paiements: (params: Params) => requete<Page<PaiementAdmin>>('GET', '/admin/paiements', { params }),
  paiement: (id: string) => requete<PaiementDetail>('GET', `/admin/paiements/${id}`),
  notificationsEnEchec: (params: Params) => requete<Page<NotificationEchec>>('GET', '/admin/notifications-en-echec', { params }),
  notificationEnEchec: (id: string) => requete<NotificationEchec>('GET', `/admin/notifications-en-echec/${id}`),
  retraits: (params: Params) => requete<Page<RetraitListe>>('GET', '/admin/retraits', { params }),
  retrait: (id: string) => requete<RetraitDetail>('GET', `/admin/retraits/${id}`),
}
