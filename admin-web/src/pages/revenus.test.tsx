import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { fireEvent } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ajouterJours, aujourdhui } from '../lib/format'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'
import { erreurPeriode } from './RevenusPage'

// Réponse conforme à l'exemple de openapi.yaml (valeurs fictives).
const REVENUS = {
  dateGeneration: '2030-01-20T10:00:00+00:00',
  periode: { debut: '2030-01-01', fin: '2030-01-10', regroupement: 'semaine', fuseau: 'Africa/Dakar' },
  totaux: { revenus: 42000, acomptesEncaisses: 150000, remboursements: 9000, retraitsVerses: 60000 },
  soldePrestataires: 84000,
  series: [
    { debut: '2030-01-01', fin: '2030-01-06', revenus: 30000, acomptesEncaisses: 90000, remboursements: 0, retraitsVerses: 60000 },
    { debut: '2030-01-07', fin: '2030-01-10', revenus: 12000, acomptesEncaisses: 60000, remboursements: 9000, retraitsVerses: 0 },
  ],
}

const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }

describe('revenus', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('pagine le tableau par 10 lignes', async () => {
    const jour = (i: number) => `2030-01-${String(i + 1).padStart(2, '0')}`
    const series = Array.from({ length: 25 }, (_, i) => ({ debut: jour(i), fin: jour(i), revenus: 100, acomptesEncaisses: 1000, remboursements: 0, retraitsVerses: 0 }))
    simulerApi({ ...base, 'GET /admin/revenus': { corps: { ...REVENUS, series } } })
    afficherApplication('/revenus')
    const tableau = await screen.findByRole('table', { name: 'Détail par période' })
    expect(within(tableau).getAllByRole('row')).toHaveLength(11) // en-tête + 10
    await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))
    expect(within(screen.getByRole('table', { name: 'Détail par période' })).getAllByRole('row')).toHaveLength(11)
    await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))
    expect(within(screen.getByRole('table', { name: 'Détail par période' })).getAllByRole('row')).toHaveLength(6) // en-tête + 5
    expect(screen.getByRole('button', { name: 'Suivant' })).toBeDisabled()
  })

  it('demande par défaut les 30 derniers jours, par jour (fuseau de Dakar)', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/revenus': { corps: REVENUS } })
    afficherApplication('/revenus')
    await screen.findAllByText('Commission retenue')
    const fin = aujourdhui()
    const revenus = appels.find((a) => a.chemin === '/admin/revenus')!
    expect(revenus.entetes.get('Authorization')).toMatch(/^Bearer /)
    expect((screen.getByLabelText('Début') as HTMLInputElement).value).toBe(ajouterJours(fin, -29))
    expect((screen.getByLabelText('Fin') as HTMLInputElement).value).toBe(fin)
    expect(screen.getByLabelText('Regroupement')).toHaveValue('jour')
  })

  it('affiche les totaux en FCFA, la courbe et le tableau', async () => {
    simulerApi({ ...base, 'GET /admin/revenus': { corps: REVENUS } })
    afficherApplication('/revenus')

    const totaux = await screen.findByRole('region', { name: 'Totaux de la période' })
    expect(await within(totaux).findByRole('region', { name: 'Commission retenue' })).toHaveTextContent('42 000 FCFA')
    expect(within(totaux).getByRole('region', { name: 'Acomptes encaissés' })).toHaveTextContent('150 000 FCFA')
    expect(within(totaux).getByRole('region', { name: 'Remboursements' })).toHaveTextContent('9 000 FCFA')
    expect(within(totaux).getByRole('region', { name: 'Retraits versés' })).toHaveTextContent('60 000 FCFA')
    expect(within(totaux).getByRole('region', { name: 'Soldes des prestataires' })).toHaveTextContent('84 000 FCFA')

    expect(screen.getByRole('img', { name: /Courbe de la commission et des acomptes/ })).toBeInTheDocument()

    const tableau = screen.getByRole('table', { name: 'Détail par période' })
    const lignes = within(tableau).getAllByRole('row')
    expect(lignes).toHaveLength(3)
    // Regroupement « semaine » : période affichée de bout en bout, dates en français.
    expect(within(lignes[1]).getByText(/1 janv\. – 6 janv\. 2030/)).toBeInTheDocument()
    expect(within(lignes[1]).getByText('30 000 FCFA')).toBeInTheDocument()
    expect(within(lignes[2]).getByText('9 000 FCFA')).toBeInTheDocument()
  })

  it('renvoie les filtres choisis à l’API (période et regroupement)', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/revenus': { corps: REVENUS } })
    afficherApplication('/revenus')
    await screen.findAllByText('Commission retenue')

    fireEvent.change(screen.getByLabelText('Début'), { target: { value: '2030-01-01' } })
    fireEvent.change(screen.getByLabelText('Fin'), { target: { value: '2030-01-31' } })
    await userEvent.selectOptions(screen.getByLabelText('Regroupement'), 'semaine')
    await userEvent.click(screen.getByRole('button', { name: 'Appliquer' }))

    await screen.findAllByText('Commission retenue')
    const dernier = appels.filter((a) => a.chemin === '/admin/revenus').at(-1)!
    expect(appels.filter((a) => a.chemin === '/admin/revenus')).toHaveLength(2)
    expect(dernier).toBeDefined()
  })

  it('envoie debut, fin et regroupement dans l’adresse', async () => {
    const adresses: string[] = []
    simulerApi({
      ...base,
      'GET /admin/revenus': ({ url }) => {
        adresses.push(url.search)
        return { corps: REVENUS }
      },
    })
    afficherApplication('/revenus')
    await screen.findAllByText('Commission retenue')
    fireEvent.change(screen.getByLabelText('Début'), { target: { value: '2030-01-01' } })
    fireEvent.change(screen.getByLabelText('Fin'), { target: { value: '2030-01-31' } })
    await userEvent.selectOptions(screen.getByLabelText('Regroupement'), 'mois')
    await userEvent.click(screen.getByRole('button', { name: 'Appliquer' }))
    await vi.waitFor(() => expect(adresses).toHaveLength(2))
    expect(new URLSearchParams(adresses[1]).get('debut')).toBe('2030-01-01')
    expect(new URLSearchParams(adresses[1]).get('fin')).toBe('2030-01-31')
    expect(new URLSearchParams(adresses[1]).get('regroupement')).toBe('mois')
  })

  it('refuse une période de plus de 366 jours sans appeler l’API', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/revenus': { corps: REVENUS } })
    afficherApplication('/revenus')
    await screen.findAllByText('Commission retenue')
    fireEvent.change(screen.getByLabelText('Début'), { target: { value: '2028-01-01' } })
    fireEvent.change(screen.getByLabelText('Fin'), { target: { value: '2030-01-31' } })
    expect(await screen.findByText('La période ne peut pas dépasser 366 jours.')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Appliquer' }))
    expect(appels.filter((a) => a.chemin === '/admin/revenus')).toHaveLength(1)
  })

  it('valide la période : 366 jours inclus passent, 367 non, fin avant début non', () => {
    expect(erreurPeriode({ debut: '2030-01-01', fin: '2030-12-31', regroupement: 'jour' })).toBeNull() // 365 jours
    expect(erreurPeriode({ debut: '2029-12-31', fin: '2030-12-31', regroupement: 'jour' })).toBeNull() // 366 jours
    expect(erreurPeriode({ debut: '2029-12-30', fin: '2030-12-31', regroupement: 'jour' })).toMatch(/366 jours/)
    expect(erreurPeriode({ debut: '2030-02-01', fin: '2030-01-01', regroupement: 'jour' })).toMatch(/date de fin/)
    expect(erreurPeriode({ debut: '', fin: '2030-01-01', regroupement: 'jour' })).toMatch(/date de fin/)
  })

  it('affiche les erreurs 422 de l’API sous les champs', async () => {
    simulerApi({
      ...base,
      'GET /admin/revenus': { status: 422, corps: { message: 'La date de début est invalide.', errors: { debut: ['La date de début est invalide.'] } } },
    })
    afficherApplication('/revenus')
    expect(await screen.findAllByText('La date de début est invalide.')).not.toHaveLength(0)
  })

  it('affiche l’état vide quand la période ne contient aucune série', async () => {
    simulerApi({ ...base, 'GET /admin/revenus': { corps: { ...REVENUS, series: [] } } })
    afficherApplication('/revenus')
    expect((await screen.findAllByText('Aucune donnée à afficher.')).length).toBeGreaterThanOrEqual(2)
  })

  it('affiche l’état de chargement', async () => {
    vi.stubGlobal('fetch', vi.fn((entree: RequestInfo | URL) => {
      const u = String(entree)
      if (u.endsWith('/auth/moi')) return Promise.resolve(new Response(JSON.stringify(ADMIN)))
      if (u.endsWith('/admin/alertes')) return Promise.resolve(new Response(JSON.stringify(ALERTES_VIDES)))
      return new Promise(() => {})
    }))
    afficherApplication('/revenus')
    const totaux = await screen.findByRole('region', { name: 'Totaux de la période' })
    expect(await within(totaux).findByRole('status', { name: 'Chargement…' })).toBeInTheDocument()
    expect(screen.getAllByRole('status', { name: 'Chargement…' })).toHaveLength(3)
  })

  it('affiche une erreur avec « Réessayer » puis les données', async () => {
    let echec = true
    simulerApi({ ...base, 'GET /admin/revenus': () => (echec ? { status: 500, corps: {} } : { corps: REVENUS }) })
    afficherApplication('/revenus')
    const totaux = await screen.findByRole('region', { name: 'Totaux de la période' })
    expect(await within(totaux).findByRole('alert')).toHaveTextContent('problème momentané')
    echec = false
    await userEvent.click(within(totaux).getByRole('button', { name: 'Réessayer' }))
    expect(await within(totaux).findByRole('region', { name: 'Commission retenue' })).toHaveTextContent('42 000 FCFA')
  })
})
