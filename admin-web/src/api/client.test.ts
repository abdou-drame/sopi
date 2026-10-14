import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, brancherGestionnaires, erreursParChamp, requete } from './client'
import { ecrireJeton, lireJeton } from '../auth/jeton'
import { simulerApi } from '../test/outils'

describe('client d’API', () => {
  const surSessionExpiree = vi.fn()
  const surAccesRefuse = vi.fn()

  beforeEach(() => {
    sessionStorage.clear()
    surSessionExpiree.mockClear()
    surAccesRefuse.mockClear()
    brancherGestionnaires({ surSessionExpiree, surAccesRefuse })
  })
  afterEach(() => vi.unstubAllGlobals())

  it('envoie le jeton en Bearer et construit la requête avec ses paramètres (vides ignorés)', async () => {
    ecrireJeton('jeton-factice-pour-test')
    const { appels, fetchSimule } = simulerApi({ 'GET /admin/revenus': { corps: { ok: true } } })
    await requete('GET', '/admin/revenus', { params: { debut: '2030-01-01', fin: '', regroupement: undefined } })
    expect(String(fetchSimule.mock.calls[0][0])).toBe('/api/v1/admin/revenus?debut=2030-01-01')
    expect(appels[0].entetes.get('Authorization')).toBe('Bearer jeton-factice-pour-test')
  })

  it('sur 401 : efface le jeton et déclenche le retour à la connexion', async () => {
    ecrireJeton('jeton-factice-pour-test')
    simulerApi({ 'GET /x': { status: 401, corps: { message: 'Authentification requise.' } } })
    await expect(requete('GET', '/x')).rejects.toMatchObject({ status: 401 })
    expect(lireJeton()).toBeNull()
    expect(surSessionExpiree).toHaveBeenCalledTimes(1)
  })

  it('sur 403 : déclenche la page « accès refusé »', async () => {
    simulerApi({ 'GET /x': { status: 403, corps: { message: 'Interdit.' } } })
    await expect(requete('GET', '/x')).rejects.toMatchObject({ status: 403 })
    expect(surAccesRefuse).toHaveBeenCalledTimes(1)
  })

  it('sur 422 : expose les erreurs par champ', async () => {
    simulerApi({ 'POST /x': { status: 422, corps: { message: 'Invalide.', errors: { debut: ['La date de début est invalide.'] } } } })
    const erreur = await requete('POST', '/x', { corps: {} }).catch((e) => e)
    expect(erreur).toBeInstanceOf(ApiError)
    expect(erreursParChamp(erreur)).toEqual({ debut: 'La date de début est invalide.' })
  })

  it('sur 429 sans message : indique l’attente d’après Retry-After', async () => {
    simulerApi({ 'GET /x': { status: 429, corps: {}, entetes: { 'Retry-After': '120' } } })
    await expect(requete('GET', '/x')).rejects.toMatchObject({ status: 429, message: expect.stringContaining('2 minutes') })
  })

  it('sur 5xx : message neutre, sans détail technique du serveur', async () => {
    simulerApi({ 'GET /x': { status: 500, corps: { message: 'SQLSTATE[42P01] relation introuvable' } } })
    const erreur = (await requete('GET', '/x').catch((e) => e)) as ApiError
    expect(erreur.message).toBe('Le service rencontre un problème momentané. Réessayez dans quelques instants.')
    expect(erreur.message).not.toContain('SQLSTATE')
  })

  it('sur panne réseau : message dédié', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('network')))
    await expect(requete('GET', '/x')).rejects.toMatchObject({ status: 0, message: expect.stringContaining('Impossible de joindre') })
  })

  it('une requête publique (connexion) ne déclenche pas les réactions globales sur 401', async () => {
    simulerApi({ 'POST /auth/connexion': { status: 401, corps: { message: 'Téléphone ou mot de passe incorrect.' } } })
    await expect(requete('POST', '/auth/connexion', { corps: {}, publique: true })).rejects.toMatchObject({
      status: 401,
      message: 'Téléphone ou mot de passe incorrect.',
    })
    expect(surSessionExpiree).not.toHaveBeenCalled()
  })
})
