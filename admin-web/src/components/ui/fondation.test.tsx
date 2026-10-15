import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../api/client'
import type { Page } from '../../api/types'
import { useFiltresUrl } from '../../lib/useFiltresUrl'
import Calendrier from './Calendrier'
import { ChampDate, Selecteur } from './Champs'
import FiltresListe from './FiltresListe'
import ListePaginee from './ListePaginee'
import PageDetail from './PageDetail'
import { useQuery } from '@tanstack/react-query'

afterEach(() => vi.restoreAllMocks())

function Lieu() {
  const l = useLocation()
  return <p data-testid="lieu">{l.pathname + l.search}</p>
}

function enveloppe(ui: React.ReactNode, adresse = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[adresse]}>
        {ui}
        <Lieu />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

interface Element {
  id: string
  nom: string
}
const page = (n: number, derniere: number, data: Element[]): Page<Element> => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: n, last_page: derniere, per_page: 20, total: 41 },
})

function EcranListe({ charger }: { charger: (p: Record<string, string | number>) => Promise<Page<Element>> }) {
  const etat = useFiltresUrl(['statut', 'q'], { statut: 'ouvert' })
  return (
    <>
      <FiltresListe
        legende="Filtres"
        etat={etat}
        filtres={[
          { cle: 'statut', type: 'select', label: 'Statut', options: [{ valeur: 'ouvert', libelle: 'Ouverts' }, { valeur: 'tous', libelle: 'Tous' }] },
          { cle: 'q', type: 'texte', label: 'Recherche' },
        ]}
      />
      <ListePaginee
        legende="Éléments"
        cle="elements"
        charger={charger}
        etat={etat}
        cleLigne={(e) => e.id}
        colonnes={[{ cle: 'nom', titre: 'Nom', rendu: (e) => e.nom }]}
        messageVide="Aucun élément."
      />
    </>
  )
}

describe('liste paginée et filtres dans l’adresse', () => {
  it('charge avec les filtres par défaut et affiche le total', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 3, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />)
    expect(await screen.findByText('Alpha')).toBeInTheDocument()
    expect(charger).toHaveBeenCalledWith({ statut: 'ouvert' })
    expect(screen.getByText('41 résultats')).toBeInTheDocument()
  })

  it('un filtre écrit dans l’adresse, revient à la page 1 et la valeur par défaut n’est pas écrite', async () => {
    const charger = vi.fn().mockResolvedValue(page(2, 3, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />, '/?page=3')
    await screen.findByText('Alpha')
    expect(charger).toHaveBeenLastCalledWith({ statut: 'ouvert', page: 2 })
    await userEvent.selectOptions(screen.getByLabelText('Statut'), 'tous')
    expect(screen.getByTestId('lieu')).toHaveTextContent('/?statut=tous')
    await userEvent.selectOptions(screen.getByLabelText('Statut'), 'ouvert')
    expect(screen.getByTestId('lieu')).toHaveTextContent(/^\/$/)
  })

  it('le champ texte s’applique à Entrée, pas à chaque frappe', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 1, []))
    enveloppe(<EcranListe charger={charger} />)
    await screen.findByText('Aucun élément.')
    await userEvent.type(screen.getByLabelText('Recherche'), 'abc')
    expect(screen.getByTestId('lieu')).toHaveTextContent(/^\/$/)
    await userEvent.type(screen.getByLabelText('Recherche'), '{Enter}')
    expect(screen.getByTestId('lieu')).toHaveTextContent('/?q=abc')
    await waitFor(() => expect(charger).toHaveBeenLastCalledWith({ statut: 'ouvert', q: 'abc' }))
  })

  it('lit les filtres d’une adresse partagée et les réinitialise', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 1, []))
    enveloppe(<EcranListe charger={charger} />, '/?statut=tous&q=zed')
    await screen.findByText('Aucun élément.')
    expect(screen.getByLabelText('Statut')).toHaveValue('tous')
    expect(screen.getByLabelText('Recherche')).toHaveValue('zed')
    await userEvent.click(screen.getByRole('button', { name: /Réinitialiser/ }))
    expect(screen.getByTestId('lieu')).toHaveTextContent(/^\/$/)
    expect(screen.getByLabelText('Recherche')).toHaveValue('')
  })

  it('pagination : page suivante dans l’adresse', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 3, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />)
    await screen.findByText('Alpha')
    await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/?page=2')
  })

  it('affiche l’état d’erreur avec « Réessayer »', async () => {
    const charger = vi.fn().mockRejectedValueOnce(new ApiError('Erreur serveur.', 500)).mockResolvedValue(page(1, 1, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />)
    expect(await screen.findByRole('alert')).toHaveTextContent('Erreur serveur.')
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(await screen.findByText('Alpha')).toBeInTheDocument()
  })
})

function EcranDetail({ erreur }: { erreur?: ApiError }) {
  const requete = useQuery({
    queryKey: ['detail'],
    queryFn: async () => {
      if (erreur) throw erreur
      return { id: '1', nom: 'Alpha' }
    },
  })
  return (
    <Routes>
      <Route
        path="*"
        element={
          <PageDetail requete={requete} retour={{ vers: '/liste', libelle: 'Retour à la liste' }} titre={(d) => d.nom}>
            {(d) => <p>Contenu {d.id}</p>}
          </PageDetail>
        }
      />
    </Routes>
  )
}

describe('page de détail', () => {
  it('affiche le titre et le contenu', async () => {
    enveloppe(<EcranDetail />)
    expect(await screen.findByRole('heading', { name: 'Alpha' })).toBeInTheDocument()
    expect(screen.getByText('Contenu 1')).toBeInTheDocument()
  })
  it('404 : élément introuvable, sans bouton Réessayer', async () => {
    enveloppe(<EcranDetail erreur={new ApiError('Litige introuvable.', 404)} />)
    expect(await screen.findByText('Élément introuvable')).toBeInTheDocument()
    expect(screen.getByText('Litige introuvable.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Réessayer' })).not.toBeInTheDocument()
  })
  it('erreur : message et bouton Réessayer', async () => {
    enveloppe(<EcranDetail erreur={new ApiError('Panne.', 500)} />)
    expect(await screen.findByRole('button', { name: 'Réessayer' })).toBeInTheDocument()
  })
  it('le lien de retour retrouve les filtres de la liste', async () => {
    const client = new QueryClient()
    render(
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[{ pathname: '/detail', state: { recherche: '?statut=tous&page=2' } }]}>
          <EcranDetail />
        </MemoryRouter>
      </QueryClientProvider>,
    )
    expect(await screen.findByRole('link', { name: /Retour à la liste/ })).toHaveAttribute('href', '/liste?statut=tous&page=2')
  })
})

describe('Selecteur', () => {
  it('reste un vrai select étiqueté, modifiable', async () => {
    const onChange = vi.fn()
    render(<Selecteur label="Rôle" options={[{ valeur: 'a', libelle: 'A' }, { valeur: 'b', libelle: 'B' }]} onChange={onChange} />)
    await userEvent.selectOptions(screen.getByLabelText('Rôle'), 'b')
    expect(onChange).toHaveBeenCalled()
  })
})

describe('Calendrier et ChampDate', () => {
  it('Calendrier : choisit un jour, navigue au clavier, ferme avec Échap', async () => {
    const onChoisir = vi.fn()
    const onFermer = vi.fn()
    render(<Calendrier valeur="2030-03-15" onChoisir={onChoisir} onFermer={onFermer} />)
    const jour = screen.getByRole('button', { name: /15 mars 2030/ })
    expect(jour).toHaveAttribute('aria-pressed', 'true')
    jour.focus()
    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('button', { name: /16 mars 2030/ })).toHaveFocus()
    await userEvent.keyboard('{Enter}')
    expect(onChoisir).toHaveBeenCalledWith('2030-03-16')
    await userEvent.keyboard('{Escape}')
    expect(onFermer).toHaveBeenCalled()
  })

  it('Calendrier : respecte min et max', () => {
    render(<Calendrier valeur="2030-03-15" min="2030-03-10" max="2030-03-20" onChoisir={() => {}} onFermer={() => {}} />)
    expect(screen.getByRole('button', { name: / 9 mars 2030/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: / 21 mars 2030/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: / 12 mars 2030/ })).toBeEnabled()
  })

  function Exemple() {
    const [v, setV] = useState('2030-03-15')
    return <ChampDate label="Début" value={v} onChange={setV} />
  }

  it('ChampDate : ouvre le calendrier, applique le jour choisi, rend le focus au déclencheur', async () => {
    render(<Exemple />)
    const declencheur = screen.getByRole('button', { name: 'Choisir une date' })
    await userEvent.click(declencheur)
    await userEvent.click(screen.getByRole('button', { name: /20 mars 2030/ }))
    expect(screen.getByLabelText('Début')).toHaveValue('2030-03-20')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(declencheur).toHaveFocus()
  })

  it('ChampDate : la saisie directe reste possible', () => {
    render(<Exemple />)
    fireEvent.change(screen.getByLabelText('Début'), { target: { value: '2030-04-01' } })
    expect(screen.getByLabelText('Début')).toHaveValue('2030-04-01')
  })
})
