import { act, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

const PAGE = { data: [], links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } }
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES }, 'GET /admin/litiges': { corps: PAGE }, 'GET /admin/avis': { corps: PAGE } }

async function ouvrirRail(adresse: string) {
  simulerApi(base)
  afficherApplication(adresse)
  return within(await screen.findByRole('complementary', { name: 'Raccourcis' }))
}

describe('rail de gauche', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('marque actif l’écran de la route, et lui seul', async () => {
    const rail = await ouvrirRail('/litiges')
    expect(rail.getByRole('link', { name: 'Litiges' })).toHaveAttribute('aria-current', 'page')
    for (const autre of ['Tableau de bord', 'Dossiers', 'Avis', 'Sanctions', 'Utilisateurs', 'Métiers']) {
      expect(rail.getByRole('link', { name: autre })).not.toHaveAttribute('aria-current')
    }
    expect(rail.getByTestId('indicateur-rail')).toHaveStyle({ opacity: '1' })
  })

  it('l’accueil n’est actif que sur « / » exactement', async () => {
    const rail = await ouvrirRail('/')
    expect(rail.getByRole('link', { name: 'Tableau de bord' })).toHaveAttribute('aria-current', 'page')
    await userEvent.click(rail.getByRole('link', { name: 'Avis' }))
    expect(rail.getByRole('link', { name: 'Avis' })).toHaveAttribute('aria-current', 'page')
    expect(rail.getByRole('link', { name: 'Tableau de bord' })).not.toHaveAttribute('aria-current')
  })

  it('l’indicateur disparaît quand l’écran n’est pas dans le rail', async () => {
    const rail = await ouvrirRail('/paiements')
    expect(rail.queryByRole('link', { current: 'page' })).toBeNull()
    expect(rail.getByTestId('indicateur-rail').style.opacity).toBe('')
  })

  it('n’utilise plus le title natif : une bulle décorative s’affiche au survol puis disparaît', async () => {
    const rail = await ouvrirRail('/')
    const lien = rail.getByRole('link', { name: 'Litiges' })
    expect(lien).not.toHaveAttribute('title')
    expect(rail.queryByText('Litiges')).toBeNull()
    await userEvent.hover(lien)
    const bulle = await rail.findByText('Litiges')
    expect(bulle.parentElement).toHaveAttribute('aria-hidden', 'true')
    await userEvent.unhover(lien)
    expect(rail.queryByText('Litiges')).toBeNull()
  })

  it('au clavier : la bulle apparaît au focus et Échap la ferme', async () => {
    const rail = await ouvrirRail('/')
    const bouton = rail.getByRole('button', { name: 'Actualiser' })
    act(() => bouton.focus())
    expect(await rail.findByText('Actualiser')).toBeInTheDocument()
    await userEvent.keyboard('{Escape}')
    expect(rail.queryByText('Actualiser')).toBeNull()
  })

  it('pose un point rouge sur les écrans qui ont des alertes, et seulement eux', async () => {
    const alertes = { alertes: [{ type: 'avis_signales', gravite: 'attention', compteur: 2, message: '2 avis signalés.' }], parGravite: { info: 0, attention: 2, critique: 0 }, total: 2 }
    simulerApi({ ...base, 'GET /admin/alertes': { corps: alertes } })
    afficherApplication('/')
    const rail = within(await screen.findByRole('complementary', { name: 'Raccourcis' }))
    const avis = await rail.findByRole('link', { name: /^Avis, 2 alertes/ })
    expect(within(avis).getByTestId('point-alerte')).toBeInTheDocument()
    expect(rail.getAllByTestId('point-alerte')).toHaveLength(1)
  })

  it('pose aussi le point rouge sur Dossiers et Litiges quand ils ont des alertes', async () => {
    const alertes = {
      alertes: [
        { type: 'dossiers_en_attente', gravite: 'info', compteur: 3, message: '3 dossiers en attente.' },
        { type: 'litiges_ouverts', gravite: 'critique', compteur: 1, message: '1 litige ouvert.' },
      ],
      parGravite: { info: 3, attention: 0, critique: 1 },
      total: 4,
    }
    simulerApi({ ...base, 'GET /admin/alertes': { corps: alertes } })
    afficherApplication('/')
    const rail = within(await screen.findByRole('complementary', { name: 'Raccourcis' }))
    for (const nom of [/^Dossiers, 3 alertes/, /^Litiges, 1 alerte/]) {
      expect(within(await rail.findByRole('link', { name: nom })).getByTestId('point-alerte')).toBeInTheDocument()
    }
    expect(rail.getAllByTestId('point-alerte')).toHaveLength(2)
  })
})
