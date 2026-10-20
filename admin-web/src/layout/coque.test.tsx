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

  it('affiche la barre de navigation (7 écrans), le menu « Plus » (5 écrans) et le rail (3 groupes)', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/')
    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })
    expect(within(nav).getAllByRole('link').map((l) => l.getAttribute('aria-label'))).toEqual([
      'Tableau de bord',
      'Dossiers prestataires',
      'Litiges',
      'Modération des avis',
      'Sanctions',
      'Utilisateurs',
      'Métiers',
    ])
    await userEvent.click(within(nav).getByRole('button', { name: /Plus/ }))
    expect(within(nav).getAllByRole('link').slice(7).map((l) => l.textContent)).toEqual([
      'Revenus',
      'Réservations',
      'Paiements',
      'Retraits',
      'Notifications en échec',
    ])
    const rail = screen.getByRole('complementary', { name: 'Raccourcis' })
    expect(within(rail).getAllByRole('link')).toHaveLength(7)
    expect(within(rail).getByRole('button', { name: 'Actualiser' })).toBeInTheDocument()
    expect(within(rail).getByRole('button', { name: 'Se déconnecter' })).toBeInTheDocument()
  })

  it('affiche les pastilles de compteurs issues de /admin/alertes, cumulées par écran', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES } })
    afficherApplication('/')
    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })
    expect(await within(nav).findByRole('link', { name: 'Litiges, 2 alertes' })).toHaveTextContent('2')
    // Le menu « Plus » cumule ses écrans : deux types d'alertes pointent vers « Paiements » (1 + 3).
    await userEvent.click(within(nav).getByRole('button', { name: /Plus/ }))
    expect(within(nav).getByRole('link', { name: /Paiements/ })).toHaveTextContent('4')
    expect(within(nav).getByRole('link', { name: /Retraits/ })).not.toHaveTextContent(/d/)
  })

  it('marque l’écran courant dans le menu', async () => {
    const page = { data: [], links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } }
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES }, 'GET /admin/litiges': { corps: page } })
    afficherApplication('/litiges')
    expect(await screen.findByRole('heading', { name: 'Litiges' })).toBeInTheDocument()
    const nav = screen.getByRole('navigation', { name: 'Navigation principale' })
    expect(within(nav).getByRole('link', { name: 'Litiges' })).toHaveAttribute('aria-current', 'page')
  })

  it('affiche la barre du haut : alertes et menu du compte', async () => {
    const { appels } = simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES }, 'POST /auth/deconnexion': { corps: {} } })
    afficherApplication('/')
    await screen.findByRole('navigation', { name: 'Navigation principale' })
    expect(await screen.findByRole('link', { name: 'Alertes : 3 alertes' })).toBeInTheDocument()

    const compte = screen.getByRole('button', { name: 'Menu du compte' })
    expect(compte).toHaveAttribute('aria-expanded', 'false')
    await userEvent.click(compte)
    expect(compte).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByText('Fatou Diop')).toBeInTheDocument()
    await userEvent.keyboard('{Escape}')
    expect(compte).toHaveAttribute('aria-expanded', 'false')
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)
  })

  it('propose un lien d’évitement vers le contenu principal', async () => {
    simulerApi({ 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } })
    afficherApplication('/')
    expect(await screen.findByRole('link', { name: 'Aller au contenu principal' })).toHaveAttribute('href', '#contenu')
  })
})
