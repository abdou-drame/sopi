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
    expect(await within(tableau).findByRole('link', { name: 'Examiner le dossier de Moussa Diop' })).toHaveAttribute('href', ADRESSE_DETAIL)
    expect(within(tableau).getByText('Mécanique')).toBeInTheDocument()
    const demande = appels.find((a) => a.chemin === '/admin/prestataires/demandes')
    expect(demande?.recherche.get('statut')).toBe('en_attente')
  })

  it('calcule les 4 cartes à partir du tableau de bord et filtre la page par recherche et par métier', async () => {
    const tableau = { comptes: { prestataires: { total: 20, parStatut: { en_attente: 3, actif: 15, refuse: 2, suspendu: 0, banni: 0 }, dossiersEnAttente: 3 } } }
    const autre = { ...DEMANDE, id: '0198a1b2-0000-7000-8000-000000000003', nom: 'Fall', prenom: 'Awa', nomEntreprise: 'Salon Awa', metier: 'coiffure', quartier: 'Pikine' }
    simulerApi({
      ...base,
      'GET /admin/tableau-de-bord': { corps: tableau },
      'GET /admin/metiers': { corps: { data: [{ id: 'm1', code: 'mecanique', libelle: 'Mécanique', actif: true }, { id: 'm2', code: 'coiffure', libelle: 'Coiffure', actif: true }] } },
      'GET /admin/prestataires/demandes': { corps: PAGE([DEMANDE, autre]) },
    })
    afficherApplication('/dossiers-prestataires')
    const tableauListe = await screen.findByRole('table', { name: 'Liste des demandes de prestataires' })
    // Carte bleue : en attente. Cartes 2 à 4 : acceptés 15/20 = 75 %, refusés 2/20 = 10 %, total 20.
    const carte = (nom: string) => within(screen.getByRole('region', { name: nom }))
    expect(await carte('En attente de décision').findByText('3')).toBeInTheDocument()
    expect(carte('Acceptés').getByText('15')).toBeInTheDocument()
    expect(carte('Acceptés').getByText('75 %')).toBeInTheDocument()
    expect(carte('Refusés').getByText('2')).toBeInTheDocument()
    expect(carte('Refusés').getByText('10 %')).toBeInTheDocument()
    expect(carte('Dossiers reçus').getByText('20')).toBeInTheDocument()
    expect(within(tableauListe).getAllByRole('link', { name: /Examiner le dossier/ })).toHaveLength(2)
    await userEvent.type(screen.getByRole('searchbox', { name: 'Rechercher un prestataire' }), 'awa')
    expect(within(tableauListe).getAllByRole('link', { name: /Examiner le dossier/ })).toHaveLength(1)
    await userEvent.clear(screen.getByRole('searchbox', { name: 'Rechercher un prestataire' }))
    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Filtrer par métier' }), 'mecanique')
    expect(within(tableauListe).getByRole('link', { name: 'Examiner le dossier de Moussa Diop' })).toBeInTheDocument()
    expect(within(tableauListe).queryByRole('link', { name: 'Examiner le dossier de Awa Fall' })).not.toBeInTheDocument()
  })

  it('met le filtre de statut dans l’adresse', async () => {
    simulerApi({ ...base, 'GET /admin/prestataires/demandes': { corps: PAGE([DEMANDE]) } })
    afficherApplication('/dossiers-prestataires')
    await screen.findByRole('table', { name: 'Liste des demandes de prestataires' })
    await userEvent.click(screen.getByRole('tab', { name: /Tous/ }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/dossiers-prestataires?statut=tous')
  })

  it('affiche l’état vide', async () => {
    simulerApi({ ...base, 'GET /admin/prestataires/demandes': { corps: PAGE([]) } })
    afficherApplication('/dossiers-prestataires')
    expect(await screen.findByText('Aucune demande ne correspond.')).toBeInTheDocument()
  })

  it('affiche le détail, montre l’aperçu de chaque pièce chargé avec le jeton, sans jamais les mettre dans une adresse d’image', async () => {
    const { appels } = simulerApi(routesDetail)
    afficherApplication(ADRESSE_DETAIL)
    expect(await screen.findByRole('heading', { level: 1, name: 'Moussa Diop' })).toBeInTheDocument()
    expect(screen.getByText('+221771234567')).toBeInTheDocument()
    // L'aperçu de chaque pièce est récupéré avec le jeton (adresse locale blob:) ; « Voir » l'ouvre dans un nouvel onglet.
    const ouvrir = vi.fn()
    vi.stubGlobal('open', ouvrir)
    expect(await screen.findByRole('button', { name: /Agrandir l’image : Carte d’identité/ })).toBeInTheDocument()
    expect(await screen.findByRole('link', { name: /Diplôme/ })).toBeInTheDocument()
    await userEvent.click(await screen.findByRole('button', { name: 'Voir Carte d’identité' }))
    await userEvent.click(screen.getByRole('button', { name: 'Voir Diplôme' }))
    await vi.waitFor(() => expect(ouvrir).toHaveBeenCalledTimes(2))
    expect(ouvrir).toHaveBeenCalledWith('blob:fichier-local', '_blank', 'noopener')
    for (const chemin of ['/admin/justificatifs/j-1/telecharger', '/admin/justificatifs/j-2/telecharger']) {
      const appel = appels.find((a) => a.chemin === chemin)
      expect(appel?.entetes.get('Authorization')).toBe('Bearer jeton-factice-pour-test')
    }
    for (const img of screen.queryAllByRole('img')) expect(img.getAttribute('src') ?? '').not.toContain('/api/')
  })

  it('accepte un dossier après confirmation (écriture simulée)', async () => {
    const { appels } = simulerApi({ ...routesDetail, 'POST /admin/prestataires/0198a1b2-0000-7000-8000-000000000002/accepter': { corps: {} } })
    afficherApplication(ADRESSE_DETAIL)
    await userEvent.click(await screen.findByRole('button', { name: 'Valider le dossier' }))
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
    await userEvent.click(await screen.findByRole('button', { name: 'Valider le dossier' }))
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
    expect(screen.queryByRole('button', { name: 'Valider le dossier' })).not.toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Ce dossier a déjà été refusé.')
  })

  it('affiche la bannière « déjà accepté » pour un dossier actif', async () => {
    simulerApi({
      ...base,
      'GET /admin/prestataires/0198a1b2-0000-7000-8000-000000000002': { corps: { ...DETAIL, statutCompte: 'actif', justificatifs: [] } },
    })
    afficherApplication(ADRESSE_DETAIL)
    expect(await screen.findByText('Ce dossier a déjà été accepté.')).toBeInTheDocument()
  })

  it('affiche « introuvable » pour un dossier inconnu', async () => {
    simulerApi({ ...base, 'GET /admin/prestataires/0198a1b2-0000-7000-8000-000000000002': { status: 404, corps: { message: 'Demande introuvable.' } } })
    afficherApplication(ADRESSE_DETAIL)
    expect(await screen.findByText('Élément introuvable')).toBeInTheDocument()
  })
})
