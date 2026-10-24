import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Réponses conformes aux exemples de openapi.yaml (valeurs fictives). Les écritures sont toujours simulées.
const ID = '0198a1b2-0000-7000-8000-000000000090'
const LITIGE = {
  id: ID,
  statut: 'ouvert',
  motif: 'Prestataire absent',
  description: 'Le prestataire ne s’est pas présenté.',
  dateCreation: '2030-01-16T12:00:00+00:00',
  reservation: { id: '0198a1b2-0000-7000-8000-000000000060', statut: 'confirmee', dateDebut: '2030-01-16T09:00:00+00:00', prestation: 'Vidange' },
  nombrePreuves: 2,
  reponsePrestataire: null,
  reponseLe: null,
  decision: null,
  motifDecision: null,
  clotureLe: null,
  client: { id: 'c-1', nom: 'Diallo', prenom: 'Awa', telephone: '+221770000001', email: null },
  prestataire: { id: 'p-1', nom: 'Diop', prenom: 'Moussa', telephone: '+221770000002', email: 'moussa@example.com', nomEntreprise: 'Garage Exemple' },
  prixApplique: 15000,
  paiements: [{ id: 'pa-1', statut: 'paye', montantAcompte: 4500, commission: 1500, payeLe: '2030-01-15T09:00:00+00:00', rembourseLe: null }],
  decidePar: null,
  sanctionId: null,
}
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
const DETAIL = {
  ...LITIGE,
  preuves: [
    { id: 'pr-1', url: `/api/v1/admin/litiges/${ID}/preuves/pr-1` },
    { id: 'pr-2', url: `/api/v1/admin/litiges/${ID}/preuves/pr-2` },
  ],
}
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }
const routesDetail = {
  ...base,
  [`GET /admin/litiges/${ID}`]: { corps: DETAIL },
  [`GET /admin/litiges/${ID}/preuves/pr-1`]: { binaire: { type: 'image/jpeg' } },
  [`GET /admin/litiges/${ID}/preuves/pr-2`]: { binaire: { type: 'image/png' } },
}

async function ouvrirModaleTrancher() {
  await userEvent.click(await screen.findByRole('button', { name: 'Trancher le litige' }))
  return screen.getByRole('dialog')
}

describe('litiges', () => {
  beforeEach(() => {
    sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test')
    URL.createObjectURL = vi.fn(() => 'blob:preuve-locale')
    URL.revokeObjectURL = vi.fn()
  })
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('liste par défaut les litiges ouverts et garde le filtre dans l’adresse', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/litiges': { corps: PAGE([LITIGE]) } })
    afficherApplication('/litiges')
    const lien = await screen.findByRole('link', { name: 'Ouvrir le litige Prestataire absent' })
    expect(lien).toHaveAttribute('href', `/litiges/${ID}`)
    expect(appels.find((a) => a.chemin === '/admin/litiges')?.recherche.get('statut')).toBe('ouvert')
    await userEvent.click(screen.getByRole('tab', { name: /Clôturés/ }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/litiges?statut=cloture')
  })

  it('calcule les 4 cartes (ouverts, clôturés, acomptes bloqués, taux) et filtre la page par recherche et décision', async () => {
    const tableau = { litiges: { total: 6, ouverts: 2, clos: 4 }, reservations: { total: 35 } }
    const rembourse = { ...LITIGE, id: 'l-2', motif: 'Retard', client: { ...LITIGE.client, prenom: 'Fatou', nom: 'Sow' }, decision: 'avertissement', paiements: [{ ...LITIGE.paiements[0], id: 'pa-2', statut: 'rembourse', montantAcompte: 2000 }] }
    simulerApi({ ...base, 'GET /admin/tableau-de-bord': { corps: tableau }, 'GET /admin/litiges': { corps: PAGE([LITIGE, rembourse]) } })
    afficherApplication('/litiges')
    const liste = await screen.findByRole('table', { name: 'Liste des litiges' })
    const carte = (nom: string) => within(screen.getByRole('region', { name: nom }))
    expect(await carte('Litiges ouverts').findByText('2')).toBeInTheDocument()
    expect(carte('Clôturés').getByText('4')).toBeInTheDocument()
    expect(carte('Clôturés').getByText('67 %')).toBeInTheDocument()
    // Acomptes bloqués : 4 500 (payé), le paiement remboursé de 2 000 n'est pas compté.
    expect(await carte('Acomptes bloqués').findByText((t) => t.replace(/[^0-9]/g, '') === '4500')).toBeInTheDocument()
    expect(carte('Taux de litige').getByText('17')).toBeInTheDocument()
    expect(carte('Taux de litige').getByText(/6 litiges pour 35 réservations/)).toBeInTheDocument()
    expect(within(liste).getAllByRole('link', { name: /Ouvrir le litige/ })).toHaveLength(2)
    await userEvent.type(screen.getByRole('searchbox', { name: 'Rechercher un client ou un prestataire' }), 'fatou')
    expect(within(liste).getAllByRole('link', { name: /Ouvrir le litige/ })).toHaveLength(1)
    await userEvent.clear(screen.getByRole('searchbox', { name: 'Rechercher un client ou un prestataire' }))
    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Filtrer par décision' }), 'ouvert')
    expect(within(liste).getByRole('link', { name: 'Ouvrir le litige Prestataire absent' })).toBeInTheDocument()
    expect(within(liste).queryByRole('link', { name: 'Ouvrir le litige Retard' })).not.toBeInTheDocument()
  })

  it('affiche le détail : motif, chronologie, acompte, réservation et personnes concernées', async () => {
    simulerApi(routesDetail)
    afficherApplication(`/litiges/${ID}`)
    expect(await screen.findByRole('heading', { level: 1, name: /Litige/ })).toBeInTheDocument()
    expect(screen.getByText(/Le prestataire ne s’est pas présenté/)).toBeInTheDocument()
    expect(screen.getByText('Aucune réponse écrite du prestataire pour le moment')).toBeInTheDocument()
    expect(screen.getByText('Garage Exemple')).toBeInTheDocument()
    expect(screen.getByText('Vidange')).toBeInTheDocument()
    expect(screen.getByText(/4.500/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir le profil du client' })).toHaveAttribute('href', '/utilisateurs/c-1')
    expect(screen.getByRole('link', { name: 'Voir le profil du prestataire' })).toHaveAttribute('href', '/utilisateurs/p-1')
    expect(screen.getByRole('button', { name: 'Trancher le litige' })).toBeEnabled()
    expect(screen.queryByRole('button', { name: /Rembourser/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Rejeter le litige' })).not.toBeInTheDocument()
  })

  it('affiche métier, quartier et note du prestataire quand l’API les fournit', async () => {
    const detail = { ...DETAIL, prestataire: { ...DETAIL.prestataire, metier: 'mecanique', quartier: 'Malika', noteMoyenne: 4.5 } }
    simulerApi({ ...routesDetail, [`GET /admin/litiges/${ID}`]: { corps: detail } })
    afficherApplication(`/litiges/${ID}`)
    expect(await screen.findByText('Malika')).toBeInTheDocument()
    expect(screen.getByText('Mécanique')).toBeInTheDocument()
    expect(screen.getByText('Prestataire · note 4,5 / 5')).toBeInTheDocument()
  })

  it('charge les preuves du client avec le jeton dans les emplacements dessinés', async () => {
    const { appels } = simulerApi(routesDetail)
    afficherApplication(`/litiges/${ID}`)
    expect(await screen.findByRole('button', { name: /Preuve 1 du client/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Preuve 2 du client/ })).toBeInTheDocument()
    const preuves = appels.filter((a) => a.chemin.includes('/preuves/'))
    expect(preuves.map((a) => a.chemin).sort()).toEqual([`/admin/litiges/${ID}/preuves/pr-1`, `/admin/litiges/${ID}/preuves/pr-2`])
    expect(preuves.every((a) => a.methode === 'GET')).toBe(true)
  })

  it('affiche le texte de la réponse du prestataire dans la chronologie', async () => {
    simulerApi({
      ...routesDetail,
      [`GET /admin/litiges/${ID}`]: { corps: { ...DETAIL, reponsePrestataire: 'J’étais sur place à l’heure.', reponseLe: '2030-01-16T08:00:00+00:00' } },
    })
    afficherApplication(`/litiges/${ID}`)
    expect(await screen.findByText(/J’étais sur place à l’heure./)).toBeInTheDocument()
    expect(screen.queryByText('Aucune réponse écrite du prestataire pour le moment')).not.toBeInTheDocument()
  })

  it('rejette la plainte : décision et motif obligatoires, corps conforme à l’API', async () => {
    const { appels } = simulerApi({ ...routesDetail, [`POST /admin/litiges/${ID}/trancher`]: { corps: {} } })
    afficherApplication(`/litiges/${ID}`)
    const modale = await ouvrirModaleTrancher()
    const confirmer = within(modale).getByRole('button', { name: 'Trancher le litige' })
    expect(confirmer).toBeDisabled() // le rejet est une décision à choisir dans la modale, pas une action séparée

    await userEvent.selectOptions(within(modale).getByLabelText('Décision'), 'rejet')
    expect(confirmer).toBeEnabled()
    expect(within(modale).queryByLabelText('Compte sanctionné')).not.toBeInTheDocument()
    await userEvent.type(within(modale).getByLabelText(/Motif de la décision/), 'Court')
    await userEvent.click(confirmer)
    expect(within(modale).getByText('Le motif doit contenir au moins 10 caractères.')).toBeInTheDocument()
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)

    await userEvent.type(within(modale).getByLabelText(/Motif de la décision/), ' : signalement non fondé')
    await userEvent.click(confirmer)
    expect(await screen.findByText('Le litige a été tranché.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({ decision: 'rejet', motif: 'Court : signalement non fondé' })
  })

  it('suspension temporaire : date de fin exigée, cible et date envoyées', async () => {
    const { appels } = simulerApi({ ...routesDetail, [`POST /admin/litiges/${ID}/trancher`]: { corps: {} } })
    afficherApplication(`/litiges/${ID}`)
    const modale = await ouvrirModaleTrancher()
    const confirmer = within(modale).getByRole('button', { name: 'Trancher le litige' })

    await userEvent.selectOptions(within(modale).getByLabelText('Décision'), 'suspension_temporaire')
    expect(confirmer).toBeDisabled()
    await userEvent.selectOptions(within(modale).getByLabelText('Compte sanctionné'), 'client')
    fireEvent.change(within(modale).getByLabelText('Fin de la suspension'), { target: { value: '2030-03-01' } })
    expect(confirmer).toBeEnabled()
    await userEvent.type(within(modale).getByLabelText(/Motif de la décision/), 'Comportement injurieux répété')
    await userEvent.click(confirmer)

    expect(await screen.findByText('Le litige a été tranché.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({
      decision: 'suspension_temporaire',
      motif: 'Comportement injurieux répété',
      cible: 'client',
      dateFin: '2030-03-01T23:59:59+00:00',
    })
  })

  it('sans choix de cible, aucune cible n’est envoyée (le serveur prend le prestataire)', async () => {
    const { appels } = simulerApi({ ...routesDetail, [`POST /admin/litiges/${ID}/trancher`]: { corps: {} } })
    afficherApplication(`/litiges/${ID}`)
    const modale = await ouvrirModaleTrancher()
    await userEvent.selectOptions(within(modale).getByLabelText('Décision'), 'avertissement')
    await userEvent.type(within(modale).getByLabelText(/Motif de la décision/), 'Retards répétés constatés')
    await userEvent.click(within(modale).getByRole('button', { name: 'Trancher le litige' }))
    await screen.findByText('Le litige a été tranché.')
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({ decision: 'avertissement', motif: 'Retards répétés constatés' })
  })

  it('affiche l’erreur 422 du serveur dans la modale', async () => {
    simulerApi({
      ...routesDetail,
      [`POST /admin/litiges/${ID}/trancher`]: { status: 422, corps: { message: 'Le motif doit contenir au moins 10 caractères.', errors: { motif: ['Le motif doit contenir au moins 10 caractères.'] } } },
    })
    afficherApplication(`/litiges/${ID}`)
    const modale = await ouvrirModaleTrancher()
    await userEvent.selectOptions(within(modale).getByLabelText('Décision'), 'rejet')
    await userEvent.type(within(modale).getByLabelText(/Motif de la décision/), 'Motif assez long')
    await userEvent.click(within(modale).getByRole('button', { name: 'Trancher le litige' }))
    expect(await within(modale).findByText('Le motif doit contenir au moins 10 caractères.')).toBeInTheDocument()
  })

  it('un litige clôturé montre sa clôture et ne propose plus de trancher', async () => {
    simulerApi({
      ...base,
      [`GET /admin/litiges/${ID}`]: {
        corps: { ...DETAIL, preuves: [], statut: 'cloture', decision: 'rejet', motifDecision: 'Signalement non fondé.', clotureLe: '2030-01-17T09:00:00+00:00' },
      },
    })
    afficherApplication(`/litiges/${ID}`)
    expect(await screen.findByText('Litige clôturé')).toBeInTheDocument()
    expect(screen.getAllByText('Clôturé · rejet').length).toBeGreaterThan(0)
    expect(screen.getByText(/Plainte rejetée/)).toHaveTextContent('« Signalement non fondé. »')
    expect(screen.queryByRole('button', { name: 'Trancher le litige' })).not.toBeInTheDocument()
  })

  it('affiche « introuvable » pour un litige inconnu', async () => {
    simulerApi({ ...base, [`GET /admin/litiges/${ID}`]: { status: 404, corps: { message: 'Litige introuvable.' } } })
    afficherApplication(`/litiges/${ID}`)
    expect(await screen.findByText('Élément introuvable')).toBeInTheDocument()
  })
})
