import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

const ALERTES = {
  dateGeneration: '2030-01-20T10:00:00+00:00',
  total: 3,
  parGravite: { critique: 1, attention: 1, info: 1 },
  alertes: [
    { type: 'litiges_ouverts', gravite: 'critique', compteur: 2, message: '2 litiges ouverts.', ids: [], ressource: 'litiges', ageMinutes: 100, echeanceDansMinutes: null },
    { type: 'remboursements_en_echec', gravite: 'attention', compteur: 1, message: '1 remboursement en échec.', ids: [], ressource: 'paiements', ageMinutes: 10, echeanceDansMinutes: null },
    { type: 'remboursements_en_retard', gravite: 'attention', compteur: 3, message: '3 remboursements en retard.', ids: [], ressource: 'paiements', ageMinutes: 10, echeanceDansMinutes: null },
  ],
}

describe('coque', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('affiche le menu complet des 11 écrans de navigation', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/')
    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })
    const libelles = within(nav).getAllByRole('link').map((l) => l.textContent)
    expect(libelles).toEqual([
      'Tableau de bord',
      'Revenus',
      'Dossiers prestataires',
      'Litiges',
      'Sanctions',
      'Modération des avis',
      'Utilisateurs',
      'Réservations',
      'Paiements',
      'Retraits',
      'Notifications en échec',
    ])
  })

  it('affiche les pastilles de compteurs issues de /admin/alertes, cumulées par écran', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })
    expect(await within(nav).findByRole('link', { name: /Litiges/ })).toHaveTextContent('2')
    // Deux types d'alertes pointent vers « Paiements » : 1 + 3.
    expect(within(nav).getByRole('link', { name: /Paiements/ })).toHaveTextContent('4')
    expect(within(nav).getByRole('link', { name: /Retraits/ })).not.toHaveTextContent(/\d/)
  })

  it('marque l’écran courant et affiche « bientôt disponible » pour les écrans à venir', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/litiges')
    expect(await screen.findByRole('heading', { name: 'Litiges' })).toBeInTheDocument()
    expect(screen.getByText('Bientôt disponible')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Litiges/ })).toHaveAttribute('aria-current', 'page')
  })

  it('replie et déplie la barre latérale', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/')
    const bouton = await screen.findByRole('button', { name: 'Replier le menu' })
    expect(bouton).toHaveAttribute('aria-expanded', 'true')
    await userEvent.click(bouton)
    const deplier = screen.getByRole('button', { name: 'Déplier le menu' })
    expect(deplier).toHaveAttribute('aria-expanded', 'false')
    // Replié : les libellés restent accessibles par aria-label.
    expect(screen.getByRole('link', { name: 'Litiges' })).toBeInTheDocument()
  })

  it('propose un lien d’évitement vers le contenu principal', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/')
    expect(await screen.findByRole('link', { name: 'Aller au contenu principal' })).toHaveAttribute('href', '#contenu')
  })
})
