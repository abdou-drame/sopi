import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'
import { regrouperAcomptes, trierAlertes } from './TableauDeBordPage'

// Réponses conformes aux exemples de openapi.yaml (valeurs fictives).
const statutsReservation = (o: Partial<Record<string, number>> = {}) => ({
  en_attente: 4, acceptee: 2, confirmee: 10, en_cours: 1, terminee: 280, refusee: 15, annulee: 28, ...o,
})
const TABLEAU = {
  dateGeneration: '2030-01-20T10:00:00+00:00',
  comptes: {
    clients: { total: 120, parStatut: { en_attente: 0, actif: 115, refuse: 0, suspendu: 3, banni: 2 } },
    prestataires: { total: 18, parStatut: { en_attente: 2, actif: 14, refuse: 1, suspendu: 1, banni: 0 }, dossiersEnAttente: 2 },
    administrateurs: 1,
  },
  reservations: {
    total: 340,
    parStatut: statutsReservation(),
    parDomaine: {
      mecanique: { total: 150, parStatut: statutsReservation() },
      coiffure: { total: 120, parStatut: statutsReservation() },
      pressing: { total: 70, parStatut: statutsReservation() },
    },
    activiteRecente: { derniers7Jours: { creees: 30, terminees: 25 }, derniers30Jours: { creees: 110, terminees: 95 } },
  },
  paiements: {
    total: { nombre: 300, montant: 1350000 },
    parStatut: {
      en_attente: { nombre: 3, montant: 9000 },
      reussi: { nombre: 280, montant: 1260000 },
      echoue: { nombre: 7, montant: 31500 },
      rembourse: { nombre: 10, montant: 49500 },
    },
  },
  litiges: { total: 9, ouverts: 2, clos: 7 },
  avis: { total: 210, signales: 3, masques: 4, noteMoyenne: 4.35 },
  sanctions: { suspensionsEnCours: 1, bannissements: 2 },
}

const alerte = (type: string, gravite: string, compteur: number, extra = {}) => ({
  type, gravite, compteur, message: `${compteur} ${type}.`, ids: [], ageMinutes: null, echeanceDansMinutes: null, ...extra,
})
const ALERTES = {
  dateGeneration: '2030-01-20T10:00:00+00:00',
  total: 3,
  parGravite: { critique: 1, attention: 1, info: 1 },
  // Volontairement dans le désordre : l'écran doit trier par gravité.
  alertes: [
    alerte('suspensions_a_echeance', 'info', 1, { echeanceDansMinutes: 600 }),
    alerte('retraits_bloques', 'attention', 1, { ageMinutes: 90, ressource: 'retraits' }),
    alerte('litiges_ouverts', 'critique', 2, { ageMinutes: 4500, ressource: 'litiges' }),
  ],
}

const jour = (i: number) => `2030-01-${String(i + 1).padStart(2, '0')}`
const POINTS = Array.from({ length: 30 }, (_, i) => ({
  debut: jour(i), fin: jour(i), revenus: 100, acomptesEncaisses: 1000, remboursements: 0, retraitsVerses: 0,
}))
const REVENUS = {
  periode: { debut: jour(0), fin: jour(29), regroupement: 'jour' },
  totaux: { revenus: 3000, acomptesEncaisses: 30000, remboursements: 0, retraitsVerses: 0 },
  soldePrestataires: 50000,
  series: POINTS,
}
const PAIEMENT = {
  id: '0198a1b2-1111-7000-8000-000000000001',
  reservationId: '0198a1b2-2222-7000-8000-000000000002',
  statut: 'reussi',
  montantAcompte: 4500,
  commission: 1500,
  remboursementDu: false,
  remboursementDuLe: null,
  tentativesRemboursement: 0,
  tentativesEpuisees: false,
  payeLe: '2030-01-20T09:30:00+00:00',
  rembourseLe: null,
  crediteLe: null,
}
const PAIEMENTS = {
  data: Array.from({ length: 6 }, (_, i) => ({ ...PAIEMENT, id: `0198a1b2-1111-7000-8000-00000000000${i}` })),
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 6 },
}

const base = { 'GET /auth/moi': { corps: ADMIN } }
const toutes = {
  ...base,
  'GET /admin/alertes': { corps: ALERTES_VIDES },
  'GET /admin/tableau-de-bord': { corps: TABLEAU },
  'GET /admin/revenus': { corps: REVENUS },
  'GET /admin/paiements': { corps: PAIEMENTS },
}

describe('tableau de bord', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('trie les alertes par gravité puis par nombre', () => {
    const triees = trierAlertes(ALERTES.alertes as never)
    expect(triees.map((a) => a.gravite)).toEqual(['critique', 'attention', 'info'])
  })

  it('regroupe les acomptes en au plus 7 barres', () => {
    expect(regrouperAcomptes(POINTS.slice(-7) as never)).toHaveLength(7)
    const trente = regrouperAcomptes(POINTS as never)
    expect(trente.length).toBeLessThanOrEqual(7)
    expect(trente.reduce((s, b) => s + b.valeur, 0)).toBe(30000)
  })

  it('affiche les alertes triées par gravité avec un lien vers la liste concernée', async () => {
    simulerApi({ ...toutes, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    const bloc = await screen.findByRole('region', { name: 'Alertes' })
    await within(bloc).findByText(/2 litiges_ouverts\./)
    const texte = bloc.textContent ?? ''
    const positions = ['2 litiges_ouverts.', '1 retraits_bloques.', '1 suspensions_a_echeance.'].map((m) => texte.indexOf(m))
    expect(positions.every((p) => p >= 0)).toBe(true)
    expect(positions).toEqual([...positions].sort((a, b) => a - b))
    const liens = within(bloc).getAllByRole('link', { name: 'Voir la liste' })
    expect(liens.map((l) => l.getAttribute('href'))).toEqual(['/litiges', '/retraits', '/sanctions?echeanceDansHeures=24'])
    expect(within(bloc).getAllByRole('alert')).toHaveLength(1)
  })

  it('affiche un message calme quand il n’y a aucune alerte', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    expect(await screen.findByText('Aucune alerte : tout est calme.')).toBeInTheDocument()
  })

  it('compose les cartes à partir des données réelles et relie chaque carte à son écran', async () => {
    simulerApi(toutes)
    afficherApplication('/')

    // Carte Sopi : terminées sur 30 jours, créées sur 7 jours, paiements réussis.
    expect(await screen.findByText('95')).toBeInTheDocument()
    expect(screen.getByText('30')).toBeInTheDocument()
    expect(screen.getByText('280')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir les réservations' })).toHaveAttribute('href', '/reservations')

    // Commission retenue : somme de la série (30 × 100).
    expect(await screen.findByRole('img', { name: /Courbe de la commission/ })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir les revenus' })).toHaveAttribute('href', '/revenus')

    // Derniers paiements : 5 lignes au plus.
    const table = await screen.findByRole('table', { name: 'Derniers paiements' })
    expect(within(table).getAllByRole('row')).toHaveLength(6) // en-tête + 5

    // Compteurs et liens.
    const liens = (nom: RegExp) => screen.getAllByRole('link', { name: nom })[0]
    expect(liens(/Litiges ouverts/)).toHaveAttribute('href', '/litiges')
    expect(liens(/Avis à modérer/)).toHaveAttribute('href', '/avis?aTraiter=1')
    expect(liens(/Dossiers en attente/)).toHaveAttribute('href', '/dossiers-prestataires')
  })

  it('affiche l’en-tête « Bienvenue » pleine largeur et actualise en lecture seule', async () => {
    const { appels } = simulerApi(toutes)
    afficherApplication('/')
    const titre = await screen.findByRole('heading', { level: 1 })
    expect(titre).toHaveTextContent('Bienvenue, Fatou')
    expect(screen.getByLabelText(/Période affichée :/)).toBeInTheDocument()
    await screen.findByText('95')
    const avant = appels.length
    await userEvent.click(screen.getByRole('button', { name: 'Actualiser' }))
    await waitFor(() => expect(appels.length).toBeGreaterThan(avant))
    expect(appels.every((a) => a.methode === 'GET')).toBe(true)
  })

  it('bascule le graphique entre 7 et 30 jours', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    const sept = await screen.findByRole('button', { name: '7 jours' })
    const trente = screen.getByRole('button', { name: '30 jours' })
    await screen.findByRole('img', { name: /Acomptes encaissés par période/ })
    expect(sept).toHaveAttribute('aria-pressed', 'true')
    const compter = () => (screen.getByRole('img', { name: /Acomptes encaissés par période/ }).getAttribute('aria-label') ?? '').split(', ').length

    expect(compter()).toBe(7)
    await userEvent.click(trente)
    expect(trente).toHaveAttribute('aria-pressed', 'true')
    expect(sept).toHaveAttribute('aria-pressed', 'false')
    expect(compter()).toBeLessThanOrEqual(7)
    expect(screen.getByRole('img', { name: /30 000 FCFA|6 000 FCFA|5 000 FCFA/ })).toBeInTheDocument()
  })

  it('affiche les états vides des cartes sans donnée', async () => {
    const vides = POINTS.map((p) => ({ ...p, acomptesEncaisses: 0, revenus: 0 }))
    simulerApi({
      ...toutes,
      'GET /admin/revenus': { corps: { ...REVENUS, series: vides } },
      'GET /admin/paiements': { corps: { ...PAIEMENTS, data: [] } },
    })
    afficherApplication('/')
    expect(await screen.findByText('Aucun acompte encaissé sur cette période.')).toBeInTheDocument()
    expect(await screen.findByText(/Aucun paiement/)).toBeInTheDocument()
  })

  it('affiche les états de chargement', async () => {
    vi.stubGlobal('fetch', vi.fn((entree: RequestInfo | URL) => {
      if (String(entree).endsWith('/auth/moi')) return Promise.resolve(new Response(JSON.stringify(ADMIN)))
      return new Promise(() => {})
    }))
    afficherApplication('/')
    expect(await screen.findByRole('status', { name: 'Chargement… Alertes' })).toBeInTheDocument()
    expect(await screen.findByRole('status', { name: 'Chargement… Acomptes encaissés' })).toBeInTheDocument()
    expect(screen.getByRole('status', { name: 'Chargement… Commission retenue' })).toBeInTheDocument()
  })

  it('affiche une erreur par carte avec « Réessayer », sans casser les autres', async () => {
    let echec = true
    simulerApi({
      ...toutes,
      'GET /admin/revenus': () => (echec ? { status: 500, corps: { message: 'SQLSTATE secret' } } : { corps: REVENUS }),
    })
    afficherApplication('/')
    await waitFor(() => expect(screen.getAllByRole('alert').length).toBeGreaterThan(0))
    expect(screen.queryByText(/SQLSTATE/)).not.toBeInTheDocument()
    // Les autres cartes restent affichées.
    expect(await screen.findByText('95')).toBeInTheDocument()

    echec = false
    await userEvent.click(screen.getAllByRole('button', { name: 'Réessayer' })[0])
    expect(await screen.findByRole('img', { name: /Acomptes encaissés par période/ })).toBeInTheDocument()
  })

  it('affiche l’erreur du tableau de bord sans casser les alertes ni les graphiques', async () => {
    simulerApi({ ...toutes, 'GET /admin/tableau-de-bord': { status: 500, corps: {} } })
    afficherApplication('/')
    await waitFor(() => expect(screen.getAllByRole('alert').length).toBeGreaterThan(0))
    expect(screen.getByText('Aucune alerte : tout est calme.')).toBeInTheDocument()
    expect(await screen.findByRole('img', { name: /Courbe de la commission/ })).toBeInTheDocument()
  })

  it('ne montre aucune donnée sensible (code de réservation, référence DexPay, jeton)', async () => {
    simulerApi({ ...toutes, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    await screen.findByRole('table', { name: 'Derniers paiements' })
    const texte = document.body.textContent ?? ''
    expect(texte).not.toMatch(/jeton-factice|mot de passe|code de réservation|dexpay|\+221/i)
  })
})
