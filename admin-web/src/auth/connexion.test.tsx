import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'
import { lireJeton } from './jeton'

// Jetons et mots de passe de test : valeurs factices uniquement.
const JETON_TEST = 'jeton-factice-pour-test'

async function saisir(telephone = '+221700000001', motDePasse = 'mot-de-passe-factice') {
  await userEvent.type(screen.getByLabelText('Téléphone'), telephone)
  await userEvent.type(screen.getByLabelText('Mot de passe'), motDePasse)
  await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))
}

describe('connexion', () => {
  beforeEach(() => sessionStorage.clear())
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('redirige un visiteur non connecté vers la page de connexion', async () => {
    simulerApi({})
    afficherApplication('/revenus')
    expect(await screen.findByRole('heading', { name: 'Connexion administrateur' })).toBeInTheDocument()
    expect(screen.getByTestId('lieu')).toHaveTextContent('/connexion')
  })

  it('connecte un administrateur, stocke le jeton et envoie le jeton aux requêtes suivantes', async () => {
    const { appels } = simulerApi({
      'POST /auth/connexion': { corps: { token: JETON_TEST, user: ADMIN } },
      'GET /admin/alertes': { corps: ALERTES_VIDES },
    })
    afficherApplication('/connexion')
    await saisir()

    expect(await screen.findByText('Fatou Diop')).toBeInTheDocument()
    expect(screen.getByTestId('lieu')).toHaveTextContent('/')
    expect(lireJeton()).toBe(JETON_TEST)
    // La connexion utilise le téléphone (jamais l'e-mail) et ne porte pas de jeton.
    const connexion = appels.find((a) => a.chemin === '/auth/connexion')!
    expect(connexion.corps).toEqual({ telephone: '+221700000001', password: 'mot-de-passe-factice' })
    expect(connexion.entetes.get('Authorization')).toBeNull()
    await waitFor(() => expect(appels.some((a) => a.chemin === '/admin/alertes')).toBe(true))
    expect(appels.find((a) => a.chemin === '/admin/alertes')!.entetes.get('Authorization')).toBe(`Bearer ${JETON_TEST}`)
  })

  it('affiche une erreur claire quand les identifiants sont faux (401)', async () => {
    simulerApi({ 'POST /auth/connexion': { status: 401, corps: { message: 'Téléphone ou mot de passe incorrect.' } } })
    afficherApplication('/connexion')
    await saisir()
    expect(await screen.findByRole('alert')).toHaveTextContent('Téléphone ou mot de passe incorrect.')
    expect(screen.getByTestId('lieu')).toHaveTextContent('/connexion')
    expect(lireJeton()).toBeNull()
    // Une 401 de connexion n'est pas une « session expirée ».
    expect(screen.queryByText(/session a expiré/i)).not.toBeInTheDocument()
  })

  it('affiche le message de blocage après 5 échecs (429)', async () => {
    simulerApi({
      'POST /auth/connexion': {
        status: 429,
        corps: { message: 'Trop de tentatives échouées. Réessayez dans 15 minutes.' },
        entetes: { 'Retry-After': '900' },
      },
    })
    afficherApplication('/connexion')
    await saisir()
    expect(await screen.findByRole('alert')).toHaveTextContent('Réessayez dans 15 minutes')
    expect(lireJeton()).toBeNull()
  })

  it('déduit la durée de Retry-After quand le serveur ne donne pas de message', async () => {
    simulerApi({ 'POST /auth/connexion': { status: 429, corps: {}, entetes: { 'Retry-After': '900' } } })
    afficherApplication('/connexion')
    await saisir()
    expect(await screen.findByRole('alert')).toHaveTextContent('Réessayez dans 15 minutes')
  })

  it('affiche les erreurs par champ sur une 422', async () => {
    simulerApi({
      'POST /auth/connexion': {
        status: 422,
        corps: { message: 'Le téléphone est obligatoire.', errors: { telephone: ['Le téléphone est obligatoire.'], password: ['Le mot de passe est obligatoire.'] } },
      },
    })
    afficherApplication('/connexion')
    await userEvent.type(screen.getByLabelText('Téléphone'), 'x')
    await userEvent.type(screen.getByLabelText('Mot de passe'), 'y')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))
    expect(await screen.findByText('Le téléphone est obligatoire.')).toBeInTheDocument()
    expect(screen.getByText('Le mot de passe est obligatoire.')).toBeInTheDocument()
    expect(screen.getByLabelText('Téléphone')).toHaveAttribute('aria-invalid', 'true')
  })

  it('refuse un compte non administrateur et révoque son jeton', async () => {
    const { appels } = simulerApi({
      'POST /auth/connexion': { corps: { token: JETON_TEST, user: { ...ADMIN, role: 'client' } } },
      'POST /auth/deconnexion': { corps: { message: 'Vous êtes déconnecté.' } },
    })
    afficherApplication('/connexion')
    await saisir()
    expect(await screen.findByRole('alert')).toHaveTextContent('réservé aux administrateurs')
    expect(lireJeton()).toBeNull()
    expect(screen.getByTestId('lieu')).toHaveTextContent('/connexion')
    expect(appels.some((a) => a.chemin === '/auth/deconnexion')).toBe(true)
  })

  it('affiche le message du serveur pour un compte non actif (403)', async () => {
    simulerApi({ 'POST /auth/connexion': { status: 403, corps: { message: 'Votre compte est suspendu.' } } })
    afficherApplication('/connexion')
    await saisir()
    expect(await screen.findByRole('alert')).toHaveTextContent('Votre compte est suspendu.')
    expect(screen.queryByRole('heading', { name: 'Accès refusé' })).not.toBeInTheDocument()
  })

  it('permet d’afficher et de masquer le mot de passe', async () => {
    simulerApi({})
    afficherApplication('/connexion')
    const champ = screen.getByLabelText('Mot de passe')
    expect(champ).toHaveAttribute('type', 'password')
    await userEvent.click(screen.getByRole('button', { name: 'Afficher le mot de passe' }))
    expect(champ).toHaveAttribute('type', 'text')
  })
})

describe('session et accès', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', JETON_TEST))
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('rétablit la session d’un administrateur avec un jeton valide', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/revenus')
    expect(await screen.findByText('Fatou Diop')).toBeInTheDocument()
    expect(screen.getByTestId('lieu')).toHaveTextContent('/revenus')
  })

  it('renvoie à la connexion avec « session expirée » sur une 401', async () => {
    simulerApi({ 'GET /auth/moi': { status: 401, corps: { message: 'Authentification requise.' } } })
    afficherApplication('/revenus')
    expect(await screen.findByText('Votre session a expiré. Veuillez vous reconnecter.')).toBeInTheDocument()
    expect(screen.getByTestId('lieu')).toHaveTextContent('/connexion')
    expect(lireJeton()).toBeNull()
  })

  it('renvoie à la connexion quand une requête de l’écran répond 401', async () => {
    simulerApi({
      'GET /auth/moi': { corps: ADMIN },
      'GET /admin/alertes': { status: 401, corps: { message: 'Authentification requise.' } },
    })
    afficherApplication('/revenus')
    expect(await screen.findByText('Votre session a expiré. Veuillez vous reconnecter.')).toBeInTheDocument()
    expect(lireJeton()).toBeNull()
  })

  it('affiche la page « accès refusé » sur une 403', async () => {
    simulerApi({
      'GET /auth/moi': { corps: ADMIN },
      'GET /admin/alertes': { status: 403, corps: { message: "Vous n'avez pas le droit d'effectuer cette action." } },
    })
    afficherApplication('/revenus')
    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
    expect(screen.getByTestId('lieu')).toHaveTextContent('/acces-refuse')
  })

  it('refuse un jeton qui n’appartient pas à un administrateur', async () => {
    simulerApi({ 'GET /auth/moi': { corps: { ...ADMIN, role: 'prestataire' } } })
    afficherApplication('/')
    expect(await screen.findByRole('heading', { name: 'Connexion administrateur' })).toBeInTheDocument()
    expect(lireJeton()).toBeNull()
  })

  it('déconnecte et efface le jeton', async () => {
    const { appels } = simulerApi({
      'GET /auth/moi': { corps: ADMIN },
      'GET /admin/alertes': { corps: ALERTES_VIDES },
      'POST /auth/deconnexion': { corps: { message: 'Vous êtes déconnecté.' } },
    })
    afficherApplication('/')
    await userEvent.click(await screen.findByRole('button', { name: 'Se déconnecter' }))
    expect(await screen.findByRole('heading', { name: 'Connexion administrateur' })).toBeInTheDocument()
    expect(lireJeton()).toBeNull()
    expect(appels.some((a) => a.chemin === '/auth/deconnexion')).toBe(true)
  })

  it('affiche la page 404 pour une adresse inconnue', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/nimporte-quoi')
    expect(await screen.findByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument()
  })
})
