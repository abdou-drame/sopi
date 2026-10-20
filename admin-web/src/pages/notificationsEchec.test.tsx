import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Écran en lecture seule : aucune écriture à simuler. Réponses conformes à l'API (valeurs fictives).
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }

const NOTIFICATION = {
  id: 'n-1',
  type: 'reservation_confirmee',
  destinataire: { id: 'u-1', role: 'client' },
  canauxEnEchec: ['email'],
  tentatives: 5,
  classeErreur: 'TransportException',
  messageErreur: 'Échec pour /suivi/[masqué]',
  dateCreation: '2030-01-20T08:00:00+00:00',
}

describe('notifications en échec', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('liste les notifications sans contenu ni coordonnées, avec le filtre de canal dans l’adresse', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/notifications-en-echec': { corps: PAGE([NOTIFICATION]) } })
    afficherApplication('/notifications-en-echec')
    const lien = await screen.findByRole('link', { name: 'Voir le détail de la notification Réservation confirmée' })
    const tableau = screen.getByRole('table')
    expect(lien).toHaveAttribute('href', '/notifications-en-echec/n-1')
    expect(within(tableau).getByText('E-mail')).toBeInTheDocument()
    expect(within(tableau).getByText('TransportException')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Canal' }))
    await userEvent.click(screen.getByRole('menuitemradio', { name: 'Push' }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/notifications-en-echec?canal=push')
    expect(appels.some((a) => a.chemin === '/admin/notifications-en-echec' && a.recherche.get('canal') === 'push')).toBe(true)
  })

  it('affiche l’état vide', async () => {
    simulerApi({ ...base, 'GET /admin/notifications-en-echec': { corps: PAGE([]) } })
    afficherApplication('/notifications-en-echec')
    expect(await screen.findByText('Aucune notification en échec.')).toBeInTheDocument()
  })

  it('détail : message d’erreur masqué tel que reçu et lien vers le destinataire', async () => {
    simulerApi({ ...base, 'GET /admin/notifications-en-echec/n-1': { corps: NOTIFICATION } })
    afficherApplication('/notifications-en-echec/n-1')
    expect(await screen.findByText('Échec pour /suivi/[masqué]')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Voir le destinataire/ })).toHaveAttribute('href', '/utilisateurs/u-1')
    expect(screen.getByText(/ne sont jamais affichés/)).toBeInTheDocument()
  })

  it('détail : « introuvable » pour une notification qui n’est plus en échec (404)', async () => {
    simulerApi({ ...base, 'GET /admin/notifications-en-echec/n-9': { status: 404, corps: { message: 'Notification introuvable.' } } })
    afficherApplication('/notifications-en-echec/n-9')
    expect((await screen.findAllByText(/introuvable/i)).length).toBeGreaterThan(0)
  })
})
