import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Réponses conformes à l'API (valeurs fictives). Les écritures sont toujours simulées.
const COIFFURE = { id: 'm-1', code: 'coiffure', libelle: 'Coiffure', actif: true, nombrePrestataires: 4, dateCreation: '2030-01-01T09:00:00+00:00' }
const PRESSING = { id: 'm-2', code: 'pressing', libelle: 'Pressing', actif: false, nombrePrestataires: 0, dateCreation: '2030-01-01T09:00:00+00:00' }
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES }, 'GET /admin/metiers': { corps: { data: [COIFFURE, PRESSING] } } }

describe('métiers', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('liste les métiers avec leur état et leur nombre de prestataires', async () => {
    simulerApi(base)
    afficherApplication('/metiers')
    const ligne = (await screen.findByText('Coiffure')).closest('section') as HTMLElement
    expect(within(ligne).getByText('4')).toBeInTheDocument()
    expect(within(ligne).getByText('Actif')).toBeInTheDocument()
    expect(within(screen.getByText('Pressing').closest('section') as HTMLElement).getByText('Désactivé')).toBeInTheDocument()
  })

  it('ajoute un métier : corps conforme, fenêtre fermée, confirmation (appel simulé)', async () => {
    const { appels } = simulerApi({ ...base, 'POST /admin/metiers': { status: 201, corps: { ...COIFFURE, id: 'm-3', code: 'plomberie', libelle: 'Plomberie', nombrePrestataires: 0 } } })
    afficherApplication('/metiers')
    await screen.findByText('Coiffure')
    await userEvent.click(screen.getByRole('button', { name: 'Ajouter un métier' }))
    const ajouter = screen.getByRole('button', { name: 'Ajouter' })
    expect(ajouter).toBeDisabled()
    await userEvent.type(screen.getByLabelText('Nom du métier'), 'Plomberie')
    await userEvent.click(ajouter)
    expect(await screen.findByText('Le métier a été ajouté.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'POST')?.corps).toEqual({ libelle: 'Plomberie' })
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('affiche l’erreur du serveur quand le nom existe déjà (422 simulé)', async () => {
    simulerApi({
      ...base,
      'POST /admin/metiers': { status: 422, corps: { message: 'Un métier portant ce nom existe déjà.', errors: { libelle: ['Un métier portant ce nom existe déjà.'] } } },
    })
    afficherApplication('/metiers')
    await screen.findByText('Coiffure')
    await userEvent.click(screen.getByRole('button', { name: 'Ajouter un métier' }))
    await userEvent.type(screen.getByLabelText('Nom du métier'), 'coiffure')
    await userEvent.click(screen.getByRole('button', { name: 'Ajouter' }))
    expect(await screen.findByText('Un métier portant ce nom existe déjà.')).toBeInTheDocument()
  })

  it('renomme un métier (PATCH simulé avec le seul libellé)', async () => {
    const { appels } = simulerApi({ ...base, 'PATCH /admin/metiers/m-1': { corps: { ...COIFFURE, libelle: 'Coiffure et beauté' } } })
    afficherApplication('/metiers')
    await userEvent.click(await screen.findByRole('button', { name: 'Renommer le métier Coiffure' }))
    const modale = screen.getByRole('dialog')
    const enregistrer = within(modale).getByRole('button', { name: 'Enregistrer' })
    expect(enregistrer).toBeDisabled()
    const champ = within(modale).getByLabelText('Nouveau nom')
    await userEvent.clear(champ)
    await userEvent.type(champ, 'Coiffure et beauté')
    await userEvent.click(enregistrer)
    expect(await screen.findByText('Le métier a été renommé.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'PATCH')?.corps).toEqual({ libelle: 'Coiffure et beauté' })
  })

  it('désactive un métier après confirmation (PATCH simulé)', async () => {
    const { appels } = simulerApi({ ...base, 'PATCH /admin/metiers/m-1': { corps: { ...COIFFURE, actif: false } } })
    afficherApplication('/metiers')
    await userEvent.click(await screen.findByRole('button', { name: 'Désactiver le métier Coiffure' }))
    const modale = screen.getByRole('dialog')
    expect(appels.some((a) => a.methode === 'PATCH')).toBe(false)
    await userEvent.click(within(modale).getByRole('button', { name: 'Désactiver' }))
    expect(await screen.findByText('Le métier a été désactivé.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'PATCH')?.corps).toEqual({ actif: false })
  })

  it('réactive un métier désactivé, et signale le refus du dernier métier actif (409 simulé)', async () => {
    const { appels } = simulerApi({ ...base, 'PATCH /admin/metiers/m-2': { corps: { ...PRESSING, actif: true } } })
    afficherApplication('/metiers')
    await userEvent.click(await screen.findByRole('button', { name: 'Réactiver le métier Pressing' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Réactiver' }))
    expect(await screen.findByText('Le métier a été réactivé.')).toBeInTheDocument()
    expect(appels.find((a) => a.methode === 'PATCH')?.corps).toEqual({ actif: true })
  })

  it('montre le message du serveur quand le dernier métier actif ne peut pas être désactivé', async () => {
    simulerApi({ ...base, 'PATCH /admin/metiers/m-1': { status: 409, corps: { message: 'Au moins un métier doit rester actif.' } } })
    afficherApplication('/metiers')
    await userEvent.click(await screen.findByRole('button', { name: 'Désactiver le métier Coiffure' }))
    const modale = screen.getByRole('dialog')
    await userEvent.click(within(modale).getByRole('button', { name: 'Désactiver' }))
    expect(await within(modale).findByText('Au moins un métier doit rester actif.')).toBeInTheDocument()
  })
})
