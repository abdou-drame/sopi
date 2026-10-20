import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Réponses conformes à l'API (valeurs fictives). Les écritures sont toujours simulées.
const SANCTION = {
  id: 's-1',
  type: 'suspension_temporaire',
  utilisateur: { id: 'u-1', prenom: 'Moussa', nom: 'Diop', role: 'prestataire' },
  motif: 'Retards répétés',
  litigeId: null,
  avisId: null,
  dateCreation: '2030-01-10T10:00:00+00:00',
  dateFin: '2030-02-10T23:59:59+00:00',
  active: true,
  leveeLe: null,
}
const LEVEE = { ...SANCTION, id: 's-2', type: 'avertissement', active: false, leveeLe: '2030-01-12T10:00:00+00:00', dateFin: null }
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }

describe('sanctions', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('liste par défaut les sanctions actives, avec le filtre dans l’adresse', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/sanctions': { corps: PAGE([SANCTION]) } })
    afficherApplication('/sanctions')
    expect(await screen.findByText('Moussa Diop')).toBeInTheDocument()
    expect(within(screen.getByRole('table')).getByText('Suspension temporaire')).toBeInTheDocument()
    expect(appels.find((a) => a.chemin === '/admin/sanctions')?.recherche.get('actives')).toBe('true')
    await userEvent.click(screen.getByRole('tab', { name: /Toutes/ }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/sanctions?actives=')
  })

  it('une sanction levée est signalée et n’a plus de bouton Lever', async () => {
    simulerApi({ ...base, 'GET /admin/sanctions': { corps: PAGE([LEVEE]) } })
    afficherApplication('/sanctions?actives=false')
    expect(await screen.findByText('Levée')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Lever la sanction/ })).not.toBeInTheDocument()
  })

  it('lève une sanction après confirmation (appel simulé)', async () => {
    const { appels } = simulerApi({
      ...base,
      'GET /admin/sanctions': { corps: PAGE([SANCTION]) },
      'POST /admin/sanctions/s-1/lever': { corps: { ...SANCTION, active: false } },
    })
    afficherApplication('/sanctions')
    await userEvent.click(await screen.findByRole('button', { name: 'Lever la sanction de Moussa Diop' }))
    const modale = screen.getByRole('dialog')
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)
    await userEvent.click(within(modale).getByRole('button', { name: 'Lever' }))
    expect(await screen.findByText('La sanction a été levée.')).toBeInTheDocument()
    expect(appels.filter((a) => a.methode === 'POST')).toHaveLength(1)
  })

  it('affiche l’erreur 409 du serveur dans la modale', async () => {
    simulerApi({
      ...base,
      'GET /admin/sanctions': { corps: PAGE([SANCTION]) },
      'POST /admin/sanctions/s-1/lever': { status: 409, corps: { message: 'Cette sanction est déjà levée.' } },
    })
    afficherApplication('/sanctions')
    await userEvent.click(await screen.findByRole('button', { name: 'Lever la sanction de Moussa Diop' }))
    const modale = screen.getByRole('dialog')
    await userEvent.click(within(modale).getByRole('button', { name: 'Lever' }))
    expect(await within(modale).findByText('Cette sanction est déjà levée.')).toBeInTheDocument()
  })
})
