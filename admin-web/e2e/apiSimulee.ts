import type { Page } from '@playwright/test'

// API simulée du back-office (valeurs fictives). Toute route non prévue répond 404 ; les écritures sont consignées, jamais envoyées.
export const ID_DOSSIER = '0198a1b2-0000-7000-8000-000000000002'

const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})

export const DEMANDE = {
  id: ID_DOSSIER,
  nom: 'Drame',
  prenom: 'Abdou',
  nomEntreprise: 'TEST E2E Email Depot',
  metier: 'mecanique',
  quartier: 'Malika',
  statutCompte: 'en_attente',
  dateSoumission: '2030-01-12T09:30:00+00:00',
  nombreJustificatifs: 2,
}
const DETAIL = {
  ...DEMANDE,
  nombreJustificatifs: undefined,
  telephone: '+221771234567',
  email: 'test@example.com',
  adresse: 'Rue 10, près du marché',
  motifRefus: null,
  justificatifs: [
    { id: 'j-1', typeDocument: 'cni', statut: 'en_attente', nomOriginal: 'cni.png', typeMime: 'image/png', taille: 184320 },
    { id: 'j-2', typeDocument: 'diplome', statut: 'en_attente', nomOriginal: 'diplome.pdf', typeMime: 'application/pdf', taille: 90000 },
  ],
}
const ADMIN = {
  id: '0198a1b2-0000-7000-8000-000000000001',
  nom: 'Diop',
  prenom: 'Fatou',
  telephone: '+221700000001',
  email: null,
  role: 'administrateur',
  statut: 'actif',
  created_at: '2030-01-01T00:00:00+00:00',
}
const ALERTES = { dateGeneration: '2030-01-20T10:00:00+00:00', total: 0, parGravite: { critique: 0, attention: 0, info: 0 }, alertes: [] }
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64')

/** Un jeu de lignes par liste : { liste, detail } où `liste` est la réponse paginée et `detail` la fiche attendue. */
export const LISTES: Record<string, { api: string; ligne: Record<string, unknown>; detail?: { api: string; corps: unknown } }> = {
  dossiers: { api: '/admin/prestataires/demandes', ligne: DEMANDE, detail: { api: `/admin/prestataires/${ID_DOSSIER}`, corps: DETAIL } },
  utilisateurs: {
    api: '/admin/utilisateurs',
    ligne: { id: 'u-1', nom: 'Diallo', prenom: 'Awa', telephone: '+22177***67', email: null, role: 'client', statut: 'actif', dateCreation: '2030-01-05T10:00:00+00:00' },
  },
  reservations: {
    api: '/admin/reservations',
    ligne: {
      id: 'r-1', statut: 'confirmee', client: { id: 'u-1', prenom: 'Awa', initialeNom: 'D.' }, prestataire: { id: 'p-1', nomEntreprise: 'Garage Exemple' },
      prestation: { id: 'pr-1', nom: 'Vidange' }, creneau: { id: 'cr-1', debut: '2030-01-16T09:00:00+00:00', fin: '2030-01-16T10:00:00+00:00' },
      prixApplique: 15000, statutPaiement: 'reussi', dateCreation: '2030-01-14T09:00:00+00:00',
    },
  },
  litiges: {
    api: '/admin/litiges',
    ligne: {
      id: 'l-1', statut: 'ouvert', motif: 'Service non réalisé', description: 'Le garage n’est pas venu.', dateCreation: '2030-01-18T10:00:00+00:00',
      reservation: { id: 'r-1', statut: 'confirmee', dateDebut: '2030-01-16T09:00:00+00:00', prestation: 'Vidange' }, nombrePreuves: 0,
      reponsePrestataire: null, reponseLe: null, decision: null, motifDecision: null, clotureLe: null,
      client: { id: 'u-1', nom: 'Diallo', prenom: 'Awa', telephone: '+22177***67', email: null },
      prestataire: { id: 'u-2', nom: 'Sy', prenom: 'Ibou', telephone: '+22177***01', email: null, nomEntreprise: 'Garage Exemple' },
    },
  },
  avis: {
    api: '/admin/avis',
    ligne: {
      id: 'a-1', note: 2, commentaire: 'Retard important', dateCreation: '2030-01-18T10:00:00+00:00', reservationId: 'r-1', client: { id: 'u-1', nom: 'Diallo', prenom: 'Awa' },
      prestataire: { id: 'p-1', nomEntreprise: 'Garage Exemple' }, statut: 'signale', signaleLe: '2030-01-19T10:00:00+00:00', motifSignalement: 'Propos injustes',
      modereLe: null, moderePar: null, motifModeration: null, masque: false, masqueLe: null,
    },
  },
  paiements: {
    api: '/admin/paiements',
    ligne: {
      id: 'pa-1', reservationId: 'r-1', statut: 'reussi', montantAcompte: 4500, commission: 1500, remboursementDu: false, remboursementDuLe: null,
      tentativesRemboursement: 0, tentativesEpuisees: false, payeLe: '2030-01-15T09:00:00+00:00', rembourseLe: null, crediteLe: null,
    },
  },
  retraits: {
    api: '/admin/retraits',
    ligne: {
      id: 're-1', prestataire: { id: 'p-1', userId: 'u-2', nomEntreprise: 'Garage Exemple' }, montant: 25000, statut: 'en_cours', bloque: false,
      dateDemande: '2030-01-10T08:00:00+00:00', traiteLe: null, recredite: false, telephoneVersement: '+22177***01',
    },
  },
  sanctions: {
    api: '/admin/sanctions',
    ligne: {
      id: 's-1', type: 'avertissement', utilisateur: { id: 'u-1', prenom: 'Awa', nom: 'Diallo', role: 'client' }, motif: 'Comportement',
      litigeId: null, avisId: null, dateCreation: '2030-01-10T08:00:00+00:00', dateFin: null, active: true, leveeLe: null,
    },
  },
  'notifications-en-echec': {
    api: '/admin/notifications-en-echec',
    ligne: {
      id: 'n-1', type: 'reservation_confirmee', destinataire: { id: 'u-1', role: 'client' }, canauxEnEchec: ['email'], tentatives: 5,
      classeErreur: 'TransportException', messageErreur: null, dateCreation: '2030-01-20T08:00:00+00:00',
    },
  },
  metiers: {
    api: '/admin/metiers',
    ligne: { id: 'm-1', code: 'mecanique', libelle: 'Mécanique', actif: true, nombrePrestataires: 3, dateCreation: '2030-01-01T00:00:00+00:00' },
  },
}

export interface Ecriture {
  methode: string
  chemin: string
  corps: unknown
}

/** Branche l'API simulée sur la page. Renvoie la liste des écritures tentées (simulées, jamais envoyées). */
export async function simulerApi(page: Page, surcharges: Record<string, { status?: number; corps: unknown }> = {}) {
  const ecritures: Ecriture[] = []
  await page.addInitScript(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  await page.route('**/api/v1/**', async (route) => {
    const requete = route.request()
    const url = new URL(requete.url())
    const chemin = url.pathname.replace(/^\/api\/v1/, '')
    const methode = requete.method()
    const repondre = (corps: unknown, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(corps) })
    if (methode !== 'GET') {
      ecritures.push({ methode, chemin, corps: requete.postDataJSON() })
      return repondre({ message: 'Opération simulée.' })
    }
    const cle = `${methode} ${chemin}`
    if (surcharges[cle]) return repondre(surcharges[cle].corps, surcharges[cle].status)
    if (chemin === '/auth/moi') return repondre(ADMIN)
    if (chemin === '/admin/alertes') return repondre(ALERTES)
    if (chemin.startsWith('/admin/justificatifs/')) return route.fulfill({ status: 200, contentType: 'image/png', body: PNG })
    for (const { api, ligne, detail } of Object.values(LISTES)) {
      if (chemin === api) return repondre(PAGE([ligne]))
      if (detail && chemin === detail.api) return repondre(detail.corps)
    }
    return repondre({ message: 'Route non simulée.' }, 404)
  })
  return ecritures
}
