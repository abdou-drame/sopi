import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'
import { trierAlertes } from './TableauDeBordPage'

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
      mecanique: { total: 150, parStatut: statutsReservation({ terminee: 130, annulee: 6 }) },
      coiffure: { total: 120, parStatut: statutsReservation({ terminee: 100, annulee: 7 }) },
      pressing: { total: 70, parStatut: statutsReservation({ terminee: 50, annulee: 15 }) },
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

const base = { 'GET /auth/moi': { corps: ADMIN } }

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

  it('affiche les alertes triées par gravité avec un lien vers la liste concernée', async () => {
    simulerApi({ ...base, 'GET /admin/alertes': { corps: ALERTES }, 'GET /admin/tableau-de-bord': { corps: TABLEAU } })
    afficherApplication('/')
    const bloc = await screen.findByRole('region', { name: 'Alertes' })
    await within(bloc).findByText(/2 litiges_ouverts\./)
    const texte = bloc.textContent ?? ''
    const positions = ['2 litiges_ouverts.', '1 retraits_bloques.', '1 suspensions_a_echeance.'].map((m) => texte.indexOf(m))
    expect(positions.every((p) => p >= 0)).toBe(true)
    expect(positions).toEqual([...positions].sort((a, b) => a - b))
    const liens = within(bloc).getAllByRole('link', { name: 'Voir la liste' })
    expect(liens.map((l) => l.getAttribute('href'))).toEqual(['/litiges', '/retraits', '/sanctions?echeanceDansHeures=24'])
    expect(within(bloc).getByText('Plus ancien élément : 3 j 3 h')).toBeInTheDocument()
    expect(within(bloc).getByText('Prochaine échéance dans 10 h')).toBeInTheDocument()
    // Seule l'alerte critique est annoncée comme une alerte (role="alert").
    expect(within(bloc).getAllByRole('alert')).toHaveLength(1)
  })

  it('affiche un message calme quand il n’y a aucune alerte', async () => {
    simulerApi({ ...base, 'GET /admin/alertes': { corps: ALERTES_VIDES }, 'GET /admin/tableau-de-bord': { corps: TABLEAU } })
    afficherApplication('/')
    expect(await screen.findByText('Aucune alerte : tout est calme.')).toBeInTheDocument()
  })

  it('affiche les effectifs, réservations, paiements, litiges, avis et sanctions', async () => {
    simulerApi({ ...base, 'GET /admin/alertes': { corps: ALERTES_VIDES }, 'GET /admin/tableau-de-bord': { corps: TABLEAU } })
    afficherApplication('/')

    const effectifs = await screen.findByRole('region', { name: 'Effectifs' })
    expect(await within(effectifs).findByText('120')).toBeInTheDocument()
    expect(within(effectifs).getByText('115 actifs · 3 suspendus · 2 bannis')).toBeInTheDocument()
    expect(within(effectifs).getByText('Dossiers en attente')).toBeInTheDocument()

    const reservations = screen.getByRole('region', { name: 'Réservations' })
    expect(within(reservations).getByText('340')).toBeInTheDocument()
    expect(within(reservations).getByText('Mécanique')).toBeInTheDocument()
    expect(within(reservations).getByText('Coiffure')).toBeInTheDocument()
    expect(within(reservations).getByText('Pressing')).toBeInTheDocument()
    expect(within(reservations).getByText('130 terminées · 6 annulées')).toBeInTheDocument()
    expect(within(reservations).getByText('Créées · 7 derniers jours')).toBeInTheDocument()
    expect(within(reservations).getByText('25 terminées')).toBeInTheDocument()
    expect(within(reservations).getByRole('img', { name: /Réservations par statut/ })).toBeInTheDocument()

    const paiements = screen.getByRole('region', { name: 'Paiements' })
    expect(within(paiements).getByText('1 350 000 FCFA')).toBeInTheDocument()
    expect(within(paiements).getByText('1 260 000 FCFA')).toBeInTheDocument()
    expect(within(paiements).getByText('Remboursé')).toBeInTheDocument()

    const autres = screen.getByRole('region', { name: /Litiges, avis et sanctions/ })
    expect(within(autres).getByText('4,35 / 5')).toBeInTheDocument()
    expect(within(autres).getByText('7 clos sur 9')).toBeInTheDocument()
    expect(within(autres).getByText('2 bannissements')).toBeInTheDocument()
  })

  it('affiche un tiret quand il n’y a pas encore de note moyenne', async () => {
    const vide = { ...TABLEAU, avis: { ...TABLEAU.avis, noteMoyenne: null } }
    simulerApi({ ...base, 'GET /admin/alertes': { corps: ALERTES_VIDES }, 'GET /admin/tableau-de-bord': { corps: vide } })
    afficherApplication('/')
    expect(await screen.findByText('Note moyenne')).toBeInTheDocument()
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('affiche les états de chargement de chaque bloc', async () => {
    simulerApi({ ...base })
    vi.stubGlobal('fetch', vi.fn((entree: RequestInfo | URL) => {
      if (String(entree).endsWith('/auth/moi')) return Promise.resolve(new Response(JSON.stringify(ADMIN)))
      return new Promise(() => {})
    }))
    afficherApplication('/')
    expect(await screen.findAllByRole('status')).not.toHaveLength(0)
    expect(await screen.findByRole('status', { name: 'Chargement… Effectifs' })).toBeInTheDocument()
    expect(screen.getByRole('status', { name: 'Chargement… Alertes' })).toBeInTheDocument()
  })

  it('affiche une erreur par bloc avec « Réessayer », sans casser les alertes', async () => {
    let echec = true
    simulerApi({
      ...base,
      'GET /admin/alertes': { corps: ALERTES_VIDES },
      'GET /admin/tableau-de-bord': () => (echec ? { status: 500, corps: { message: 'SQLSTATE secret' } } : { corps: TABLEAU }),
    })
    afficherApplication('/')
    const effectifs = await screen.findByRole('region', { name: 'Effectifs' })
    expect(await within(effectifs).findByRole('alert')).toHaveTextContent('Le service rencontre un problème momentané.')
    expect(screen.queryByText(/SQLSTATE/)).not.toBeInTheDocument()
    // Les alertes viennent d'une autre requête : elles restent affichées.
    expect(screen.getByText('Aucune alerte : tout est calme.')).toBeInTheDocument()

    echec = false
    await userEvent.click(within(effectifs).getByRole('button', { name: 'Réessayer' }))
    expect(await within(effectifs).findByText('120')).toBeInTheDocument()
  })

  it('affiche l’erreur du bloc d’alertes quand /admin/alertes échoue', async () => {
    simulerApi({ ...base, 'GET /admin/alertes': { status: 500, corps: {} }, 'GET /admin/tableau-de-bord': { corps: TABLEAU } })
    afficherApplication('/')
    const bloc = await screen.findByRole('region', { name: 'Alertes' })
    await waitFor(() => expect(within(bloc).getByRole('alert')).toHaveTextContent('problème momentané'))
    expect(await screen.findByText('340')).toBeInTheDocument()
  })

  it('ne montre aucune donnée sensible (code de réservation, jeton, mot de passe)', async () => {
    simulerApi({ ...base, 'GET /admin/alertes': { corps: ALERTES }, 'GET /admin/tableau-de-bord': { corps: TABLEAU } })
    afficherApplication('/')
    await screen.findByText('340')
    const texte = document.body.textContent ?? ''
    expect(texte).not.toMatch(/jeton-factice|mot de passe|code de réservation/i)
  })
})
