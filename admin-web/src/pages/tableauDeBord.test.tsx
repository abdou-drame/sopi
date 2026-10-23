import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { aujourdhui } from '../lib/format'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

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

const ALERTES = {
  dateGeneration: '2030-01-20T10:00:00+00:00',
  total: 1,
  parGravite: { critique: 0, attention: 1, info: 0 },
  alertes: [{ type: 'dossiers_en_attente', gravite: 'attention', compteur: 2, message: '2 dossiers en attente.', ids: [], ressource: 'dossiers', ageMinutes: 4500, echeanceDansMinutes: null }],
}

const REVENUS = {
  periode: { debut: '2030-01-01', fin: '2030-01-30', regroupement: 'jour' },
  totaux: { revenus: 3000, acomptesEncaisses: 30000, remboursements: 1500, retraitsVerses: 24000 },
  soldePrestataires: 50000,
  series: [
    { debut: '2030-01-01', fin: '2030-01-01', revenus: 1000, acomptesEncaisses: 0, remboursements: 0, retraitsVerses: 0 },
    { debut: '2030-01-02', fin: '2030-01-02', revenus: 2500, acomptesEncaisses: 0, remboursements: 0, retraitsVerses: 0 },
    { debut: '2030-01-03', fin: '2030-01-03', revenus: 0, acomptesEncaisses: 0, remboursements: 0, retraitsVerses: 0 },
  ],
  parMetier: [
    { metier: 'Coiffure', revenus: 2000 },
    { metier: 'Mécanique', revenus: 1000 },
  ],
}
// Période précédente (les 30 jours d'avant) : mêmes jours, autres montants.
const REVENUS_PRECEDENTS = {
  ...REVENUS,
  series: [400, 800, 300].map((revenus, i) => ({ ...REVENUS.series[i], revenus })),
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
const page = (data: unknown[]) => ({ data, links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length } })
const RESERVATION = {
  id: PAIEMENT.reservationId,
  statut: 'confirmee',
  client: { id: 'c-1', prenom: 'Awa', initialeNom: 'D' },
  prestataire: { id: 'p-1', nomEntreprise: 'Garage Exemple' },
  prestation: { id: 's-1', nom: 'Vidange' },
  creneau: { id: 'k-1', debut: '2030-01-20T10:30:00+00:00', fin: '2030-01-20T11:30:00+00:00' },
  prixApplique: 15000,
  statutPaiement: 'reussi',
  dateCreation: '2030-01-19T10:00:00+00:00',
}
const PRESTATAIRE = {
  id: 'u-1', role: 'prestataire', prenom: 'Moussa', nom: 'Diop', email: null, telephone: '+221770000002', statut: 'actif', dateCreation: '2030-01-01T00:00:00+00:00',
  prestataire: { id: 'p-1', metier: 'mecanique', quartier: 'Malika', nomEntreprise: 'Garage Exemple' },
}
const METIERS = { data: ['Mécanique', 'Coiffure', 'Pressing'].map((libelle, i) => ({ id: `m-${i}`, code: libelle, libelle, actif: true, nombrePrestataires: 3, dateCreation: '2030-01-01' })) }

const base = { 'GET /auth/moi': { corps: ADMIN } }
const toutes = {
  ...base,
  'GET /admin/alertes': { corps: ALERTES_VIDES },
  'GET /admin/tableau-de-bord': { corps: TABLEAU },
  // La période affichée se termine aujourd'hui ; la période précédente se termine avant son début.
  'GET /admin/revenus': ({ url }) => ({ corps: url.searchParams.get('fin') === aujourdhui() ? REVENUS : REVENUS_PRECEDENTS }),
  'GET /admin/paiements': { corps: page([PAIEMENT]) },
  'GET /admin/reservations': { corps: page([RESERVATION]) },
  'GET /admin/utilisateurs': { corps: page([PRESTATAIRE]) },
  'GET /admin/metiers': { corps: METIERS },
}

describe('tableau de bord', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
    localStorage.clear()
  })

  it('trace la courbe des revenus réels et donne, au survol, la valeur du jour et celle de la période précédente', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    const courbe = await screen.findByRole('img', { name: /Commissions Sopi par jour/ })
    await waitFor(() => expect(courbe).toHaveAttribute('aria-label', expect.stringMatching(/2\D500 FCFA/)))
    expect(courbe).toHaveAttribute('aria-label', expect.stringMatching(/1\D000 FCFA/))
    courbe.getBoundingClientRect = () => ({ left: 0, top: 0, right: 360, bottom: 176, width: 360, height: 176, x: 0, y: 0, toJSON: () => ({}) })
    // Milieu du graphique : le deuxième jour sur trois.
    fireEvent.mouseMove(courbe, { clientX: 180 })
    const bulle = courbe.querySelector('.courbe-bulle') as HTMLElement
    expect(bulle).toHaveTextContent(/2 janv\./)
    expect(bulle).toHaveTextContent(/2\D500 FCFA/)
    expect(bulle).toHaveTextContent(/800 FCFA/)
    fireEvent.mouseLeave(courbe)
    expect(courbe).toHaveAttribute('data-survol', 'false')
  })

  it('affiche l’en-tête de bienvenue avec le prénom de l’administrateur', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    expect(await screen.findByRole('heading', { level: 1 })).toHaveTextContent(/Bonjour,\s*Fatou/)
  })

  it('garde la carte commission en FCFA et montre les effectifs d’utilisateurs, d’artisans et de réservations', async () => {
    simulerApi({
      ...toutes,
      'GET /admin/utilisateurs': ({ url }) => ({
        corps: { data: [PRESTATAIRE], links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: url.searchParams.get('role') === 'client' ? 120 : 14 } },
      }),
    })
    afficherApplication('/')
    const commission = (await screen.findByText('Revenus Sopi (commissions)')).closest('section') as HTMLElement
    await waitFor(() => expect(commission).toHaveTextContent(/3\D000\s*FCFA/))
    expect(within(commission).getByText('30 j')).toBeInTheDocument()
    const clients = screen.getAllByText('Utilisateurs').find((e) => e.closest('section'))!.closest('section') as HTMLElement
    await waitFor(() => expect(clients).toHaveTextContent('120'))
    expect(clients).not.toHaveTextContent('FCFA')
    const artisans = screen.getByText('Artisans').closest('section') as HTMLElement
    await waitFor(() => expect(artisans).toHaveTextContent('14'))
    const reservations = screen.getByText('Réservations', { selector: 'span.text-xs' }).closest('section') as HTMLElement
    await waitFor(() => expect(reservations).toHaveTextContent('340'))
    expect(reservations).toHaveTextContent('110 créées')
    expect(screen.queryByText('Acomptes remboursés')).not.toBeInTheDocument()
    expect(screen.queryByText('Reversé aux prestataires')).not.toBeInTheDocument()
  })

  it('montre le nombre de dossiers en attente, le plus ancien depuis les alertes, et relie la carte à son écran', async () => {
    simulerApi({ ...toutes, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    const lien = await screen.findByRole('link', { name: 'Ouvrir les dossiers' })
    expect(lien).toHaveAttribute('href', '/dossiers-prestataires')
    const carte = lien.closest('section, div.box-border') as HTMLElement
    await waitFor(() => expect(screen.getByText(/Le plus ancien : (?!—)/)).toBeInTheDocument())
    expect(carte).toBeTruthy()
    expect(screen.getByText('dossiers')).toBeInTheDocument()
  })

  it('affiche une bannière par alerte réelle, avec le lien de l’écran concerné', async () => {
    simulerApi({ ...toutes, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    const bannieres = await screen.findAllByRole('alert')
    const banniere = bannieres.find((b) => b.textContent?.includes('2 dossiers en attente.'))!
    expect(banniere).toBeDefined()
    expect(within(banniere).getByRole('link', { name: 'Voir' })).toHaveAttribute('href', '/dossiers-prestataires')
  })

  it('n’affiche aucune bannière quand il n’y a aucune alerte', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    await screen.findByRole('link', { name: 'Ouvrir les dossiers' })
    expect(screen.queryByText('2 dossiers en attente.')).not.toBeInTheDocument()
  })

  it('la bannière masquée le reste après rechargement, et revient si le nombre augmente', async () => {
    simulerApi({ ...toutes, 'GET /admin/alertes': { corps: ALERTES } })
    const { unmount } = afficherApplication('/')
    await userEvent.click(await screen.findByRole('button', { name: 'Masquer l’alerte' }))
    unmount()
    afficherApplication('/')
    await screen.findByText('Vidange')
    expect(screen.queryByText('2 dossiers en attente.')).not.toBeInTheDocument()
    localStorage.setItem('sopi.admin.alertes-masquees', JSON.stringify({ dossiers_en_attente: 1 }))
    cleanup()
    afficherApplication('/')
    expect(await screen.findByText('2 dossiers en attente.')).toBeInTheDocument()
  })

  it('montre les litiges ouverts et relie la carte à la liste des litiges', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    expect(await screen.findByRole('link', { name: 'Ouvrir les litiges' })).toHaveAttribute('href', '/litiges')
    expect(await screen.findByText('Litiges ouverts')).toBeInTheDocument()
    await waitFor(() => expect(screen.getByText('Litiges ouverts').closest('div.box-border, section')).toHaveTextContent(/2/))
  })

  it('liste l’activité récente avec l’acompte du paiement correspondant', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    expect(await screen.findByText('Vidange')).toBeInTheDocument()
    expect(screen.getByText(/Garage Exemple · Awa D\./)).toBeInTheDocument()
    expect(screen.getByText('Confirmée')).toBeInTheDocument()
    expect(await screen.findByText(/4.500\s*FCFA/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Tout voir' })).toHaveAttribute('href', '/reservations')
  })

  it('montre les prestataires actifs et le titre de la carte des revenus', async () => {
    simulerApi(toutes)
    afficherApplication('/')
    expect(await screen.findByRole('link', { name: 'Ouvrir les utilisateurs' })).toHaveAttribute('href', '/utilisateurs')
    expect(await screen.findByText('Revenus Sopi')).toBeInTheDocument()
  })

  it('branche la pastille « +N » des prestataires actifs sur le total de la pagination', async () => {
    const { appels } = simulerApi({
      ...toutes,
      'GET /admin/utilisateurs': { corps: { data: [PRESTATAIRE], links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: 14 } } },
    })
    afficherApplication('/')
    expect(await screen.findByText('+13')).toBeInTheDocument()
    const appel = appels.find((x) => x.chemin === '/admin/utilisateurs')
    expect(appel?.recherche.get('role')).toBe('prestataire')
    expect(appel?.recherche.get('statut')).toBe('actif')
  })

  it('trace le graphique des réservations avec les totaux réels par mois, puis par semaine', async () => {
    simulerApi({
      ...toutes,
      'GET /admin/reservations': () => ({ corps: { data: [], links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: 42 } } }),
    })
    afficherApplication('/')
    expect(await screen.findByText('Nombre de réservations par mois')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Annuel' })).toHaveAttribute('aria-selected', 'true')
    await waitFor(() => expect(screen.getAllByRole('img').some((e) => (e.getAttribute('aria-label') ?? '').startsWith('Réservations : ') && (e.getAttribute('aria-label') ?? '').includes(' 42'))).toBe(true))
    await userEvent.click(screen.getByRole('tab', { name: 'Mensuel' }))
    expect(screen.getByRole('tab', { name: 'Mensuel' })).toHaveAttribute('aria-selected', 'true')
    expect(await screen.findByText('Nombre de réservations par semaine')).toBeInTheDocument()
  })

  it('bascule le graphique des réservations entre colonnes et courbe sans changer les données', async () => {
    simulerApi({
      ...toutes,
      'GET /admin/reservations': () => ({ corps: { data: [], links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: 42 } } }),
    })
    afficherApplication('/')
    const graphique = () => screen.getAllByRole('img').find((e) => (e.getAttribute('aria-label') ?? '').startsWith('Réservations : ')) as HTMLElement
    await screen.findByText('Nombre de réservations par mois')
    expect(screen.getByRole('tab', { name: 'Colonnes' })).toHaveAttribute('aria-selected', 'true')
    expect(graphique()).toHaveAttribute('data-mode', 'colonnes')
    await userEvent.click(screen.getByRole('tab', { name: 'Courbe' }))
    expect(screen.getByRole('tab', { name: 'Courbe' })).toHaveAttribute('aria-selected', 'true')
    expect(graphique()).toHaveAttribute('data-mode', 'courbe')
    await userEvent.click(screen.getByRole('tab', { name: 'Colonnes' }))
    expect(graphique()).toHaveAttribute('data-mode', 'colonnes')
  })

  it('actualise en lecture seule depuis le rail', async () => {
    const { appels } = simulerApi(toutes)
    afficherApplication('/')
    await screen.findByText('Vidange')
    const avant = appels.length
    await userEvent.click(screen.getByRole('button', { name: 'Actualiser' }))
    await waitFor(() => expect(appels.length).toBeGreaterThan(avant))
    expect(appels.every((a) => a.methode === 'GET')).toBe(true)
  })

  it('affiche « — » en attendant les données (chargement)', async () => {
    vi.stubGlobal('fetch', vi.fn((entree: RequestInfo | URL) => {
      if (String(entree).endsWith('/auth/moi')) return Promise.resolve(new Response(JSON.stringify(ADMIN)))
      return new Promise(() => {})
    }))
    afficherApplication('/')
    const carte = (await screen.findByText('Revenus Sopi (commissions)')).closest('section') as HTMLElement
    expect(carte).toHaveTextContent('— FCFA')
  })

  it('garde la page entière quand une requête échoue : valeurs en « — », sans message technique', async () => {
    simulerApi({ ...toutes, 'GET /admin/tableau-de-bord': { status: 500, corps: { message: 'SQLSTATE secret' } }, 'GET /admin/revenus': { status: 500, corps: { message: 'SQLSTATE secret' } } })
    afficherApplication('/')
    const carte = (await screen.findByText('Revenus Sopi (commissions)')).closest('section') as HTMLElement
    await waitFor(() => expect(carte).toHaveTextContent('— FCFA'))
    expect(await screen.findByText('Vidange')).toBeInTheDocument()
    expect(screen.queryByText(/SQLSTATE/)).not.toBeInTheDocument()
  })

  it('la croix d’une bannière d’alerte la retire de l’affichage', async () => {
    simulerApi({ ...toutes, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    expect(await screen.findByText('2 dossiers en attente.')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Masquer l’alerte' }))
    expect(screen.queryByText('2 dossiers en attente.')).not.toBeInTheDocument()
  })

  it('ne montre aucune donnée sensible (code de réservation, référence DexPay, jeton)', async () => {
    simulerApi({ ...toutes, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    await screen.findByText('Vidange')
    const texte = document.body.textContent ?? ''
    expect(texte).not.toMatch(/jeton-factice|mot de passe|code de réservation|dexpay|\+221/i)
  })
})
