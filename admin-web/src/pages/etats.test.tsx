import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// États de chargement, vide et erreur des écrans à listes dessinées : squelette, « Aucune donnée »,
// « Aucun résultat pour ces filtres » (avec réinitialisation, champ de recherche local compris) et « Réessayer ».
const PAGE_VIDE = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
}
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }

interface Ecran {
  nom: string
  adresse: string
  route: string
  legende: string
  /** Champ de recherche de l'écran : local (Dossiers, Litiges, Sanctions) ou envoyé dans l'adresse (Avis). */
  recherche: { nom: string; local: boolean }
}
const ECRANS: Ecran[] = [
  { nom: 'Dossiers', adresse: '/dossiers-prestataires', route: 'GET /admin/prestataires/demandes', legende: 'Liste des demandes de prestataires', recherche: { nom: 'Rechercher un prestataire', local: true } },
  { nom: 'Litiges', adresse: '/litiges', route: 'GET /admin/litiges', legende: 'Liste des litiges', recherche: { nom: 'Rechercher un client ou un prestataire', local: true } },
  { nom: 'Sanctions', adresse: '/sanctions', route: 'GET /admin/sanctions', legende: 'Liste des sanctions', recherche: { nom: 'Rechercher un prestataire', local: true } },
  { nom: 'Avis', adresse: '/avis', route: 'GET /admin/avis', legende: 'Liste des avis', recherche: { nom: 'Prestataire (identifiant)', local: false } },
]

/** Fait attendre indéfiniment les requêtes dont le chemin contient `fragment` (premier chargement en cours). */
function ralentir(fragment: string) {
  const reel = globalThis.fetch
  vi.stubGlobal('fetch', (entree: RequestInfo | URL, init?: RequestInit) => (String(entree).includes(fragment) ? new Promise<Response>(() => {}) : reel(entree, init)))
}

describe.each(ECRANS)('états de la liste : $nom', (ecran) => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => {
    sessionStorage.clear()
    vi.unstubAllGlobals()
  })

  it('chargement : tableau et cartes fantômes à la place du vrai contenu', async () => {
    simulerApi({ ...base, [ecran.route]: { corps: PAGE_VIDE } })
    ralentir(ecran.route.split(' ')[1])
    afficherApplication(ecran.adresse)
    // Cartes fantômes + tableau fantôme (les deux annoncés « Chargement… »), jamais le vrai tableau vide.
    expect(await screen.findAllByRole('status', { name: 'Chargement…' })).toHaveLength(2)
    expect(screen.getAllByRole('status', { name: 'Chargement…' }).every((e) => e.getAttribute('aria-busy') === 'true')).toBe(true)
    expect(screen.queryByRole('table', { name: ecran.legende })).not.toBeInTheDocument()
    expect(screen.queryByText('Aucune donnée pour l’instant')).not.toBeInTheDocument()
  })

  it('vide sans filtre : « Aucune donnée pour l’instant »', async () => {
    simulerApi({ ...base, [ecran.route]: { corps: PAGE_VIDE } })
    afficherApplication(ecran.adresse)
    expect(await screen.findByText('Aucune donnée pour l’instant')).toBeInTheDocument()
    expect(screen.queryByText('Aucun résultat pour ces filtres')).not.toBeInTheDocument()
    expect(screen.queryByRole('status', { name: 'Chargement…' })).not.toBeInTheDocument()
    expect(screen.getByRole('table', { name: ecran.legende })).toBeInTheDocument()
  })

  it('vide avec recherche : « Aucun résultat pour ces filtres », et Réinitialiser vide aussi le champ de recherche', async () => {
    simulerApi({ ...base, [ecran.route]: { corps: PAGE_VIDE } })
    afficherApplication(ecran.adresse)
    await screen.findByText('Aucune donnée pour l’instant')
    const champ = screen.getByRole('searchbox', { name: ecran.recherche.nom })
    await userEvent.type(champ, ecran.recherche.local ? 'zzz' : 'zzz{Enter}')
    const titre = await screen.findByText('Aucun résultat pour ces filtres')
    expect(screen.queryByText('Aucune donnée pour l’instant')).not.toBeInTheDocument()
    expect(champ).toHaveValue('zzz')

    const bloc = titre.closest('[role="status"]') as HTMLElement
    await userEvent.click(within(bloc).getByRole('button', { name: 'Réinitialiser les filtres' }))
    expect(await screen.findByText('Aucune donnée pour l’instant')).toBeInTheDocument()
    expect(screen.getByRole('searchbox', { name: ecran.recherche.nom })).toHaveValue('')
    expect(screen.getByTestId('lieu')).toHaveTextContent(new RegExp(`^${ecran.adresse}$|^${ecran.adresse}\\?(?!.*prestataire=zzz)`))
  })

  it('erreur : message, icône d’alerte et « Réessayer » qui relance la requête', async () => {
    let panne = true
    const { appels } = simulerApi({
      ...base,
      [ecran.route]: () => (panne ? { status: 500, corps: { message: 'Panne serveur.' } } : { corps: PAGE_VIDE }),
    })
    afficherApplication(ecran.adresse)
    const alerte = await screen.findByRole('alert')
    expect(alerte).toHaveTextContent('Impossible de charger la liste')
    expect(alerte).toHaveTextContent('problème momentané')
    expect(alerte.querySelector('svg')).not.toBeNull()
    const avant = appels.length
    panne = false
    await userEvent.click(within(alerte).getByRole('button', { name: 'Réessayer' }))
    expect(await screen.findByText('Aucune donnée pour l’instant')).toBeInTheDocument()
    expect(appels.length).toBeGreaterThan(avant)
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})

describe('Litiges : le filtre local de décision est lui aussi réinitialisé', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('Réinitialiser remet la décision à « toutes »', async () => {
    simulerApi({ ...base, 'GET /admin/litiges': { corps: PAGE_VIDE } })
    afficherApplication('/litiges')
    await screen.findByText('Aucune donnée pour l’instant')
    const decision = screen.getByRole('combobox', { name: 'Filtrer par décision' })
    await userEvent.selectOptions(decision, 'ouvert')
    const titre = await screen.findByText('Aucun résultat pour ces filtres')
    await userEvent.click(within(titre.closest('[role="status"]') as HTMLElement).getByRole('button', { name: 'Réinitialiser les filtres' }))
    expect(await screen.findByText('Aucune donnée pour l’instant')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Filtrer par décision' })).toHaveValue('')
  })
})

describe('Dossiers : le filtre local de métier est lui aussi réinitialisé', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('Réinitialiser remet le métier à « tous »', async () => {
    const metiers = { data: [{ id: 'm-1', code: 'mecanique', libelle: 'Mécanique', actif: true, nombrePrestataires: 1, dateCreation: '2030-01-01T10:00:00+00:00' }] }
    simulerApi({ ...base, 'GET /admin/metiers': { corps: metiers }, 'GET /admin/prestataires/demandes': { corps: PAGE_VIDE } })
    afficherApplication('/dossiers-prestataires')
    await screen.findByText('Aucune donnée pour l’instant')
    const metier = screen.getByRole('combobox', { name: 'Filtrer par métier' })
    await userEvent.selectOptions(metier, await within(metier).findByRole('option', { name: 'Mécanique' }))
    const titre = await screen.findByText('Aucun résultat pour ces filtres')
    await userEvent.click(within(titre.closest('[role="status"]') as HTMLElement).getByRole('button', { name: 'Réinitialiser les filtres' }))
    expect(screen.getByRole('combobox', { name: 'Filtrer par métier' })).toHaveValue('')
  })
})
