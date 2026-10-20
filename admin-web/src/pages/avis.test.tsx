import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'
import { ActionModeration } from './AvisPages'

// Réponses conformes à l'API (valeurs fictives). Les écritures sont toujours simulées.
const AVIS = {
  id: 'a-1',
  note: 1,
  commentaire: 'Service décevant.',
  dateCreation: '2030-01-17T10:00:00+00:00',
  reservationId: 'r-1',
  client: { id: 'c-1', nom: 'Diallo', prenom: 'Awa' },
  prestataire: { id: 'p-1', nomEntreprise: 'Garage Exemple' },
  statut: 'signale',
  signaleLe: '2030-01-18T08:00:00+00:00',
  motifSignalement: 'Avis injuste.',
  modereLe: null,
  moderePar: null,
  motifModeration: null,
  masque: false,
  masqueLe: null,
}
const MASQUE = { ...AVIS, id: 'a-2', statut: 'supprime', masque: true, masqueLe: '2030-01-19T09:00:00+00:00', modereLe: '2030-01-19T09:00:00+00:00', motifModeration: 'Propos injurieux.' }
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
/** « Conserver » et « Rétablir » ne sont pas dessinés : on les teste isolément, sans passer par la page. */
function afficherAction(avis: typeof AVIS, mode: 'conserver' | 'retablir') {
  const messages: string[] = []
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <ActionModeration avis={avis} mode={mode} onFait={(m) => messages.push(m)} />
    </QueryClientProvider>,
  )
  return messages
}
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }

describe('modération des avis', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('montre par défaut les avis à modérer et traduit la vue en paramètres de l’API', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/avis': { corps: PAGE([AVIS]) } })
    afficherApplication('/avis')
    expect(await screen.findByText(/Service décevant/)).toBeInTheDocument()
    const recherche = appels.find((a) => a.chemin === '/admin/avis')?.recherche
    expect(recherche?.get('aTraiter')).toBe('true')
    expect(recherche?.has('vue')).toBe(false)
    await userEvent.click(screen.getByRole('tab', { name: /Masqués/ }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/avis?vue=masques')
  })

  it('masque un avis : motif obligatoire, corps conforme (appel simulé)', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/avis': { corps: PAGE([AVIS]) }, 'POST /admin/avis/a-1/masquer': { corps: MASQUE } })
    afficherApplication('/avis')
    await userEvent.click(await screen.findByRole('button', { name: 'Masquer l’avis de Awa Diallo' }))
    const modale = screen.getByRole('dialog')
    const confirmer = within(modale).getByRole('button', { name: 'Masquer' })
    await userEvent.type(within(modale).getByLabelText(/Motif de la modération/), 'Court')
    await userEvent.click(confirmer)
    expect(within(modale).getByText('Le motif doit contenir au moins 10 caractères.')).toBeInTheDocument()
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)
    await userEvent.type(within(modale).getByLabelText(/Motif de la modération/), ' : propos injurieux')
    await userEvent.click(confirmer)
    expect(await screen.findByText('L’avis a été masqué.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({ motif: 'Court : propos injurieux' })
  })

  it('conserve un avis signalé sans motif : corps vide', async () => {
    const { appels } = simulerApi({ 'POST /admin/avis/a-1/conserver': { corps: AVIS } })
    const messages = afficherAction(AVIS, 'conserver')
    await userEvent.click(screen.getByRole('button', { name: 'Conserver l’avis de Awa Diallo' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Conserver' }))
    await vi.waitFor(() => expect(messages).toEqual(['L’avis a été conservé.']))
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({})
  })

  it('rétablit un avis masqué (action fonctionnelle, non affichée sur la page)', async () => {
    const { appels } = simulerApi({ 'POST /admin/avis/a-2/retablir': { corps: AVIS } })
    const messages = afficherAction(MASQUE, 'retablir')
    await userEvent.click(screen.getByRole('button', { name: 'Rétablir l’avis de Awa Diallo' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Rétablir' }))
    await vi.waitFor(() => expect(messages).toEqual(['L’avis a été rétabli.']))
    expect(appels.filter((a) => a.methode === 'POST')).toHaveLength(1)
  })

  it('n’affiche sur la carte que « Masquer l’avis » et « Voir la réservation » (Conserver et Rétablir ne sont pas dessinés)', async () => {
    simulerApi({ ...base, 'GET /admin/avis': { corps: PAGE([AVIS, MASQUE]) } })
    afficherApplication('/avis')
    expect(await screen.findByRole('button', { name: 'Masquer l’avis de Awa Diallo' })).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Voir la réservation' })).toHaveLength(2)
    expect(screen.queryByRole('button', { name: /Conserver l’avis/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Rétablir l’avis/ })).not.toBeInTheDocument()
  })

  it('affiche l’erreur 409 du serveur dans la modale', async () => {
    simulerApi({
      ...base,
      'GET /admin/avis': { corps: PAGE([AVIS]) },
      'POST /admin/avis/a-1/masquer': { status: 409, corps: { message: 'Cet avis est déjà masqué.' } },
    })
    afficherApplication('/avis')
    await userEvent.click(await screen.findByRole('button', { name: 'Masquer l’avis de Awa Diallo' }))
    const modale = screen.getByRole('dialog')
    await userEvent.type(within(modale).getByLabelText(/Motif de la modération/), 'Motif suffisamment long')
    await userEvent.click(within(modale).getByRole('button', { name: 'Masquer' }))
    expect(await within(modale).findByText('Cet avis est déjà masqué.')).toBeInTheDocument()
  })
})

describe('pagination de 10', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('montre 10 lignes par page : la page 2 de l’écran vient de la même page de l’API', async () => {
    const lignes = Array.from({ length: 20 }, (_, i) => ({ ...AVIS, id: `a-${i}`, commentaire: `Commentaire ${i + 1}`, client: { id: 'c', nom: 'Diallo', prenom: 'Awa' } }))
    const reponse = { ...PAGE(lignes), meta: { current_page: 1, last_page: 2, per_page: 20, total: 25 } }
    const { appels } = simulerApi({ ...base, 'GET /admin/avis': { corps: reponse } })
    afficherApplication('/avis?page=2')
    expect(await screen.findByText(/Commentaire 11/)).toBeInTheDocument()
    expect(screen.getByText(/Commentaire 20/)).toBeInTheDocument()
    expect(screen.queryByText(/Commentaire 10/)).not.toBeInTheDocument()
    expect(appels.find((a) => a.chemin === '/admin/avis')?.recherche.has('page')).toBe(false)
  })
})
