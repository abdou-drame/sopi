import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Réponses conformes aux exemples de l'API (valeurs fictives). Ces écrans sont en lecture seule : aucune écriture à simuler.
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }

const PAIEMENT = {
  id: 'pa-1',
  reservationId: 'r-1',
  statut: 'reussi',
  montantAcompte: 4500,
  commission: 1500,
  remboursementDu: false,
  remboursementDuLe: null,
  tentativesRemboursement: 0,
  tentativesEpuisees: false,
  payeLe: '2030-01-15T09:00:00+00:00',
  rembourseLe: null,
  crediteLe: null,
}
const RESERVATION_LISTE = {
  id: 'r-1',
  statut: 'confirmee',
  client: { id: 'c-1', prenom: 'Awa', initialeNom: 'D.' },
  prestataire: { id: 'p-1', nomEntreprise: 'Garage Exemple' },
  prestation: { id: 'pr-1', nom: 'Vidange' },
  creneau: { id: 'cr-1', debut: '2030-01-16T09:00:00+00:00', fin: '2030-01-16T10:00:00+00:00' },
  prixApplique: 15000,
  statutPaiement: 'reussi',
  dateCreation: '2030-01-14T09:00:00+00:00',
}
const RESERVATION_DETAIL = {
  id: 'r-1',
  statut: 'confirmee',
  prestataire: { id: 'p-1', nomEntreprise: 'Garage Exemple' },
  prestation: { id: 'pr-1', nom: 'Vidange', dureeMinutes: 60 },
  creneau: RESERVATION_LISTE.creneau,
  prixApplique: 15000,
  client: { id: 'c-1', prenom: 'Awa', nom: 'Diallo' },
  motifRefus: null,
  paiement: { id: 'pa-1', statut: 'reussi', montantAcompte: 4500 },
  nombrePaiements: 2,
  avis: null,
  litige: { id: 'l-1', statut: 'ouvert' },
  historique: { creeeLe: '2030-01-14T09:00:00+00:00', accepteeLe: '2030-01-14T10:00:00+00:00', payeLe: null, rembourseLe: null },
}
const RETRAIT = {
  id: 're-1',
  prestataire: { id: 'p-1', userId: 'u-1', nomEntreprise: 'Garage Exemple' },
  montant: 25000,
  statut: 'en_cours',
  bloque: true,
  dateDemande: '2030-01-10T08:00:00+00:00',
  traiteLe: null,
  recredite: false,
  telephoneVersement: '+22177***01',
}
const NOTIFICATION = {
  id: 'n-1',
  type: 'reservation_confirmee',
  destinataire: { id: 'c-1', role: 'client' },
  canauxEnEchec: ['email', 'push'],
  tentatives: 5,
  classeErreur: 'TransportException',
  dateCreation: '2030-01-20T08:00:00+00:00',
}

describe('écrans de consultation', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('réservations : client en prénom + initiale, jamais de code, filtres dans l’adresse', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/reservations': { corps: PAGE([RESERVATION_LISTE]) } })
    afficherApplication('/reservations')
    const lien = await screen.findByRole('link', { name: 'Vidange' })
    expect(lien).toHaveAttribute('href', '/reservations/r-1')
    expect(screen.getByText('Awa D.')).toBeInTheDocument()
    expect(screen.queryByText(/Diallo/)).not.toBeInTheDocument()
    await userEvent.selectOptions(screen.getByLabelText('Statut'), 'terminee')
    expect(screen.getByTestId('lieu')).toHaveTextContent('/reservations?statut=terminee')
    expect(appels.some((a) => a.chemin === '/admin/reservations' && a.recherche.get('statut') === 'terminee')).toBe(true)
  })

  it('réservation : détail avec nom complet, paiement, litige et historique', async () => {
    simulerApi({ ...base, 'GET /admin/reservations/r-1': { corps: RESERVATION_DETAIL } })
    afficherApplication('/reservations/r-1')
    expect(await screen.findByRole('heading', { level: 1, name: 'Vidange' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Awa Diallo' })).toHaveAttribute('href', '/utilisateurs/c-1')
    expect(screen.getByRole('link', { name: 'Voir le paiement' })).toHaveAttribute('href', '/paiements/pa-1')
    expect(screen.getByRole('link', { name: 'Voir le litige' })).toHaveAttribute('href', '/litiges/l-1')
    expect(screen.getByText('2 tentatives de paiement ; la plus pertinente est affichée.')).toBeInTheDocument()
    expect(screen.getByText('Acceptée le')).toBeInTheDocument()
    expect(screen.queryByText('Payée le')).not.toBeInTheDocument()
    expect(screen.getByText('Aucun avis.')).toBeInTheDocument()
  })

  it('paiements : liste sans référence DexPay, filtre « à rembourser » dans l’adresse', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/paiements': { corps: PAGE([{ ...PAIEMENT, remboursementDu: true, tentativesEpuisees: true }]) } })
    afficherApplication('/paiements?remboursementDu=true')
    expect(await screen.findByText('Tentatives épuisées')).toBeInTheDocument()
    expect(appels.find((a) => a.chemin === '/admin/paiements')?.recherche.get('remboursementDu')).toBe('true')
    expect(screen.queryByText(/DexPay/)).not.toBeInTheDocument()
  })

  it('paiement : la référence DexPay n’apparaît qu’au détail', async () => {
    simulerApi({
      ...base,
      'GET /admin/paiements/pa-1': {
        corps: {
          ...PAIEMENT,
          reservation: { id: 'r-1', statut: 'confirmee', prixApplique: 15000, clientId: 'c-1', prestataireId: 'p-1' },
          creeLe: '2030-01-15T08:50:00+00:00',
          tentativesMaximum: 5,
          referenceDexpay: 'DXP-123456',
        },
      },
    })
    afficherApplication('/paiements/pa-1')
    expect(await screen.findByText('DXP-123456')).toBeInTheDocument()
    expect(screen.getByText('3 000 FCFA')).toBeInTheDocument()
    expect(screen.getByText('0 sur 5')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir la réservation' })).toHaveAttribute('href', '/reservations/r-1')
  })

  it('retraits : numéro masqué tel que reçu, badge « Bloqué » et filtre dans l’adresse', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/retraits': { corps: PAGE([RETRAIT]) } })
    afficherApplication('/retraits?bloques=true')
    expect(await screen.findByText('+22177***01')).toBeInTheDocument()
    const tableau = screen.getByRole('table')
    expect(within(tableau).getByText('Bloqué')).toBeInTheDocument()
    expect(within(tableau).getByRole('link', { name: 'Garage Exemple' })).toHaveAttribute('href', '/retraits/re-1')
    expect(appels.find((a) => a.chemin === '/admin/retraits')?.recherche.get('bloques')).toBe('true')
  })

  it('retrait : détail avec référence DexPay et bandeau de blocage', async () => {
    simulerApi({ ...base, 'GET /admin/retraits/re-1': { corps: { ...RETRAIT, referenceDexpay: 'DXP-RET-9', recrediteLe: null } } })
    afficherApplication('/retraits/re-1')
    expect(await screen.findByText('DXP-RET-9')).toBeInTheDocument()
    expect(screen.getByText('Ce retrait est en cours depuis plus longtemps que le délai normal.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir le prestataire' })).toHaveAttribute('href', '/utilisateurs/u-1')
  })

  it('notifications en échec : libellés lisibles, pagination sans last_page', async () => {
    simulerApi({
      ...base,
      'GET /admin/notifications-en-echec': { corps: { data: [NOTIFICATION], links: {}, meta: { current_page: 1, per_page: 20, total: 1 } } },
    })
    afficherApplication('/notifications-en-echec')
    const lien = await screen.findByRole('link', { name: 'Réservation confirmée' })
    expect(lien).toHaveAttribute('href', '/notifications-en-echec/n-1')
    expect(screen.getByText('E-mail, Push')).toBeInTheDocument()
    expect(screen.getByText('TransportException')).toBeInTheDocument()
  })

  it('notification : détail et 404', async () => {
    simulerApi({ ...base, 'GET /admin/notifications-en-echec/n-1': { corps: NOTIFICATION } })
    afficherApplication('/notifications-en-echec/n-1')
    expect(await screen.findByRole('heading', { level: 1, name: 'Réservation confirmée' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Voir le destinataire/ })).toHaveAttribute('href', '/utilisateurs/c-1')
  })

  it('notification inconnue : « introuvable »', async () => {
    simulerApi({ ...base, 'GET /admin/notifications-en-echec/n-2': { status: 404, corps: { message: 'Notification introuvable.' } } })
    afficherApplication('/notifications-en-echec/n-2')
    expect(await screen.findByText('Élément introuvable')).toBeInTheDocument()
  })
})
