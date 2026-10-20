import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Réponses conformes aux exemples de openapi.yaml (valeurs fictives). Les écritures sont toujours simulées.
const DEMANDE = {
  id: '0198a1b2-0000-7000-8000-000000000002',
  nom: 'Diop',
  prenom: 'Moussa',
  nomEntreprise: 'Garage Moussa',
  metier: 'mecanique',
  quartier: 'Malika',
  statutCompte: 'en_attente',
  dateSoumission: '2030-01-12T09:30:00+00:00',
  nombreJustificatifs: 2,
}
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
const DETAIL = {
  ...DEMANDE,
  nombreJustificatifs: undefined,
  telephone: '+221771234567',
  email: 'moussa@example.com',
  adresse: 'Rue 10, près du marché',
  motifRefus: null,
  justificatifs: [
    { id: 'j-1', typeDocument: 'cni', statut: 'en_attente', nomOriginal: 'cni.png', typeMime: 'image/png', taille: 184320 },
    { id: 'j-2', typeDocument: 'diplome', statut: 'en_attente', nomOriginal: 'diplome.pdf', typeMime: 'application/pdf', taille: 90000 },
  ],
}
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }
const routesDetail = {
  ...base,
  'GET /admin/prestataires/0198a1b2-0000-7000-8000-000000000002': { corps: DETAIL },
  'GET /admin/justificatifs/j-1/telecharger': { binaire: { type: 'image/png' } },
  'GET /admin/justificatifs/j-2/telecharger': { binaire: { type: 'application/pdf' } },
}
const ADRESSE_DETAIL = '/dossiers-prestataires/0198a1b2-0000-7000-8000-000000000002'

describe('dossiers prestataires', () => {
  beforeEach(() => {
    sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test')
    URL.createObjectURL = vi.fn(() => 'blob:fichier-local')
    URL.revokeObjectURL = vi.fn()
  })
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('liste par défaut les dossiers en attente et envoie le filtre à l’API', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/prestataires/demandes': { corps: PAGE([DEMANDE]) } })
    afficherApplication('/dossiers-prestataires')
    const tableau = await screen.findByRole('table', { name: 'Liste des demandes de prestataires' })
    expect(await within(tableau).findByRole('link', { name: 'Moussa Diop' })).toHaveAttribute('href', ADRESSE_DETAIL)
    expect(within(tableau).getByText('Garage Moussa')).toBeInTheDocument()
    expect(within(tableau).getByText('Mécanique')).toBeInTheDocument()
    const demande = appels.find((a) => a.chemin === '/admin/prestataires/demandes')
    expect(demande?.recherche.get('statut')).toBe('en_attente')
  })

  it('met le filtre de statut dans l’adresse', async () => {
    simulerApi({ ...base, 'GET /admin/prestataires/demandes': { corps: PAGE([DEMANDE]) } })
    afficherApplication('/dossiers-prestataires')
    await screen.findByRole('table', { name: 'Liste des demandes de prestataires' })
    await userEvent.click(screen.getByRole('tab', { name: 'Tous' }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/dossiers-prestataires?statut=tous')
  })

  it('affiche l’état vide', async () => {
    simulerApi({ ...base, 'GET /admin/prestataires/demandes': { corps: PAGE([]) } })
    afficherApplication('/dossiers-prestataires')
    expect(await screen.findByText('Aucune demande ne correspond.')).toBeInTheDocument()
  })

  it('affiche le détail et charge les justificatifs avec le jeton, sans jamais les mettre dans une adresse d’image', async () => {
    const { appels } = simulerApi(routesDetail)
    afficherApplication(ADRESSE_DETAIL)
    expect(await screen.findByRole('heading', { level: 1, name: 'Garage Moussa' })).toBeInTheDocument()
    expect(screen.getByText('+221771234567')).toBeInTheDocument()
    // L'image vient d'une adresse locale (blob:), pas de l'API : aucune balise <img src> ne vise l'API.
    const image = await screen.findByRole('img', { name: /Carte d’identité/ })
    expect(image).toHaveAttribute('src', 'blob:fichier-local')
    const lienPdf = await screen.findByRole('link', { name: /Ouvrir/ })
    expect(lienPdf).toHaveAttribute('href', 'blob:fichier-local')
    for (const chemin of ['/admin/justificatifs/j-1/telecharger', '/admin/justificatifs/j-2/telecharger']) {
      const appel = appels.find((a) => a.chemin === chemin)
      expect(appel?.entetes.get('Authorization')).toBe('Bearer jeton-factice-pour-test')
    }
    for (const img of screen.getAllByRole('img')) expect(img.getAttribute('src')).not.toContain('/api/')
  })

  it('accepte un dossier après confirmation (écriture simulée)', async () => {
    const { appels } = simulerApi({ ...routesDetail, 'POST /admin/prestataires/0198a1b2-0000-7000-8000-000000000002/accepter': { corps: {} } })
    afficherApplication(ADRESSE_DETAIL)
    await userEvent.click(await screen.findByRole('button', { name: 'Accepter le dossier' }))
    const modale = screen.getByRole('dialog')
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)
    await userEvent.click(within(modale).getByRole('button', { name: 'Accepter le dossier' }))
    expect(await screen.findByText('Le dossier a été accepté.')).toBeInTheDocument()
    expect(appels.filter((a) => a.methode === 'POST')).toHaveLength(1)
  })

  it('refuse un dossier : motif de 10 à 500 caractères exigé avant tout envoi', async () => {
    const { appels } = simulerApi({ ...routesDetail, 'POST /admin/prestataires/0198a1b2-0000-7000-8000-000000000002/refuser': { corps: {} } })
    afficherApplication(ADRESSE_DETAIL)
    await userEvent.click(await screen.findByRole('button', { name: 'Refuser le dossier' }))
    const modale = screen.getByRole('dialog')
    const bouton = within(modale).getByRole('button', { name: 'Refuser le dossier' })
    await userEvent.click(bouton)
    expect(within(modale).getByText('Le motif est obligatoire.')).toBeInTheDocument()
    await userEvent.type(within(modale).getByLabelText(/Motif du refus/), 'Trop cour')
    await userEvent.click(bouton)
    expect(within(modale).getByText('Le motif doit contenir au moins 10 caractères.')).toBeInTheDocument()
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)

    await userEvent.type(within(modale).getByLabelText(/Motif du refus/), ' : pièces illisibles')
    await userEvent.click(bouton)
    expect(await screen.findByText('Le dossier a été refusé.')).toBeInTheDocument()
    const envoi = appels.find((a) => a.methode === 'POST')
    expect(envoi?.corps).toEqual({ motif: 'Trop cour : pièces illisibles' })
  })

  it('affiche l’erreur du serveur dans la modale (409)', async () => {
    simulerApi({
      ...routesDetail,
      'POST /admin/prestataires/0198a1b2-0000-7000-8000-000000000002/accepter': { status: 409, corps: { message: 'Ce dossier a déjà été traité.' } },
    })
    afficherApplication(ADRESSE_DETAIL)
    await userEvent.click(await screen.findByRole('button', { name: 'Accepter le dossier' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Accepter le dossier' }))
    expect(await within(screen.getByRole('dialog')).findByText('Ce dossier a déjà été traité.')).toBeInTheDocument()
  })

  it('masque les décisions d’un dossier déjà traité et montre le motif du refus', async () => {
    simulerApi({
      ...base,
      'GET /admin/prestataires/0198a1b2-0000-7000-8000-000000000002': {
        corps: { ...DETAIL, statutCompte: 'refuse', motifRefus: 'Pièces illisibles.', justificatifs: [] },
      },
    })
    afficherApplication(ADRESSE_DETAIL)
    expect(await screen.findByText('Pièces illisibles.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Accepter le dossier' })).not.toBeInTheDocument()
    expect(screen.getByText('Aucun justificatif déposé.')).toBeInTheDocument()
  })

  it('affiche « introuvable » pour un dossier inconnu', async () => {
    simulerApi({ ...base, 'GET /admin/prestataires/0198a1b2-0000-7000-8000-000000000002': { status: 404, corps: { message: 'Demande introuvable.' } } })
    afficherApplication(ADRESSE_DETAIL)
    expect(await screen.findByText('Élément introuvable')).toBeInTheDocument()
  })
})
