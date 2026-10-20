import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Réponses conformes à l'API (valeurs fictives). Les écritures sont toujours simulées.
const ID = 'u-1'
const PRESTATAIRE = { id: 'p-1', metier: 'Mécanique', quartier: 'Malika', nomEntreprise: 'Garage Exemple' }
const UTILISATEUR = {
  id: ID,
  role: 'prestataire',
  prenom: 'Moussa',
  nom: 'Diop',
  email: 'moussa@example.com',
  telephone: '+221770000002',
  statut: 'actif',
  dateCreation: '2030-01-01T10:00:00+00:00',
  prestataire: PRESTATAIRE,
}
const DETAIL = {
  ...UTILISATEUR,
  prestataire: {
    ...PRESTATAIRE,
    adresse: 'Rue 10',
    solde: 12500,
    prestationsActives: 3,
    reservationsParStatut: { en_attente: 1, acceptee: 0, confirmee: 2, en_cours: 0, terminee: 5, refusee: 0, annulee: 1 },
    noteMoyenne: 4.5,
    nombreAvis: 8,
    nombreRetraits: 2,
    litiges: { total: 3, ouverts: 1 },
  },
  sanctionActive: null,
  nombreSanctions: 0,
}
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }
const routesDetail = {
  ...base,
  [`GET /admin/utilisateurs/${ID}`]: { corps: DETAIL },
  [`GET /admin/utilisateurs/${ID}/sanctions`]: { corps: { data: [] } },
}

describe('utilisateurs', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('liste les utilisateurs et garde les filtres dans l’adresse', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/utilisateurs': { corps: PAGE([UTILISATEUR]) } })
    afficherApplication('/utilisateurs')
    const lien = await screen.findByRole('link', { name: 'Voir le profil de Garage Exemple' })
    expect(lien).toHaveAttribute('href', `/utilisateurs/${ID}`)
    expect(screen.getByText('Garage Exemple')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('tab', { name: /Clients/ }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/utilisateurs?role=client')
    expect(appels.some((a) => a.chemin === '/admin/utilisateurs' && a.recherche.get('role') === 'client')).toBe(true)
  })

  it('affiche le détail d’un prestataire avec liens vers ses réservations et retraits', async () => {
    simulerApi(routesDetail)
    afficherApplication(`/utilisateurs/${ID}`)
    expect(await screen.findByRole('heading', { level: 1, name: 'Moussa Diop' })).toBeInTheDocument()
    expect(screen.getByText('+221770000002')).toBeInTheDocument()
    expect(screen.getByText('4.5 / 5 · 8 avis')).toBeInTheDocument()
    expect(screen.getByText('3 au total, dont 1 ouvert')).toBeInTheDocument()
    expect(await screen.findByText('Aucune sanction enregistrée.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir ses réservations' })).toHaveAttribute('href', '/reservations?prestataire=p-1')
    expect(screen.getByRole('link', { name: /Voir ses retraits/ })).toHaveAttribute('href', '/retraits?prestataire=p-1')
  })

  it('pose un avertissement : motif obligatoire et corps conforme à l’API (appel simulé)', async () => {
    const { appels } = simulerApi({ ...routesDetail, [`POST /admin/utilisateurs/${ID}/sanctions`]: { status: 201, corps: {} } })
    afficherApplication(`/utilisateurs/${ID}`)
    await userEvent.click(await screen.findByRole('button', { name: 'Poser une sanction' }))
    const modale = screen.getByRole('dialog')
    const confirmer = within(modale).getByRole('button', { name: 'Poser une sanction' })
    expect(confirmer).toBeDisabled()
    await userEvent.selectOptions(within(modale).getByLabelText('Type de sanction'), 'avertissement')
    await userEvent.type(within(modale).getByLabelText(/Motif de la sanction/), 'Court')
    await userEvent.click(confirmer)
    expect(within(modale).getByText('Le motif doit contenir au moins 10 caractères.')).toBeInTheDocument()
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)
    await userEvent.type(within(modale).getByLabelText(/Motif de la sanction/), ' : retards constatés')
    await userEvent.click(confirmer)
    expect(await screen.findByText('La sanction a été posée.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({ type: 'avertissement', motif: 'Court : retards constatés' })
  })

  it('suspension temporaire : la date de fin est exigée et envoyée', async () => {
    const { appels } = simulerApi({ ...routesDetail, [`POST /admin/utilisateurs/${ID}/sanctions`]: { status: 201, corps: {} } })
    afficherApplication(`/utilisateurs/${ID}`)
    await userEvent.click(await screen.findByRole('button', { name: 'Poser une sanction' }))
    const modale = screen.getByRole('dialog')
    const confirmer = within(modale).getByRole('button', { name: 'Poser une sanction' })
    await userEvent.selectOptions(within(modale).getByLabelText('Type de sanction'), 'suspension_temporaire')
    expect(confirmer).toBeDisabled()
    fireEvent.change(within(modale).getByLabelText('Fin de la suspension'), { target: { value: '2030-03-01' } })
    expect(confirmer).toBeEnabled()
    await userEvent.type(within(modale).getByLabelText(/Motif de la sanction/), 'Comportement injurieux')
    await userEvent.click(confirmer)
    await screen.findByText('La sanction a été posée.')
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({
      type: 'suspension_temporaire',
      motif: 'Comportement injurieux',
      dateFin: '2030-03-01T23:59:59+00:00',
    })
  })

  it('affiche l’erreur du serveur (sanction déjà active) dans la modale', async () => {
    simulerApi({
      ...routesDetail,
      [`POST /admin/utilisateurs/${ID}/sanctions`]: { status: 409, corps: { message: 'Ce compte a déjà une sanction active.' } },
    })
    afficherApplication(`/utilisateurs/${ID}`)
    await userEvent.click(await screen.findByRole('button', { name: 'Poser une sanction' }))
    const modale = screen.getByRole('dialog')
    await userEvent.selectOptions(within(modale).getByLabelText('Type de sanction'), 'avertissement')
    await userEvent.type(within(modale).getByLabelText(/Motif de la sanction/), 'Motif assez long')
    await userEvent.click(within(modale).getByRole('button', { name: 'Poser une sanction' }))
    expect(await within(modale).findByText('Ce compte a déjà une sanction active.')).toBeInTheDocument()
  })

  it('avec une sanction active : pas de nouvelle sanction, historique avec bouton Lever', async () => {
    simulerApi({
      ...base,
      [`GET /admin/utilisateurs/${ID}`]: {
        corps: { ...DETAIL, sanctionActive: { id: 's-1', type: 'avertissement', dateFin: null }, nombreSanctions: 1 },
      },
      [`GET /admin/utilisateurs/${ID}/sanctions`]: {
        corps: {
          data: [
            {
              id: 's-1',
              utilisateurId: ID,
              type: 'avertissement',
              motif: 'Retards répétés',
              dateFin: null,
              litigeId: null,
              avisId: null,
              posePar: 'a-1',
              dateCreation: '2030-01-10T10:00:00+00:00',
              active: true,
              leveeLe: null,
              leveePar: null,
            },
          ],
        },
      },
    })
    afficherApplication(`/utilisateurs/${ID}`)
    expect(await screen.findByText('Retards répétés')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Poser une sanction' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Lever la sanction/ })).toBeInTheDocument()
  })

  it('affiche « introuvable » pour un utilisateur inconnu', async () => {
    simulerApi({ ...base, [`GET /admin/utilisateurs/${ID}`]: { status: 404, corps: { message: 'Utilisateur introuvable.' } } })
    afficherApplication(`/utilisateurs/${ID}`)
    expect(await screen.findByText('Élément introuvable')).toBeInTheDocument()
  })
})
