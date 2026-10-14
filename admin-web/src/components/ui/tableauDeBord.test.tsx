import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import type { PaiementAdmin } from '../../api/types'
import BasculeGelules from './BasculeGelules'
import BoutonRond from './BoutonRond'
import DerniersPaiements from './DerniersPaiements'
import LienGelule from './LienGelule'

const paiement = (o: Partial<PaiementAdmin>): PaiementAdmin => ({
  id: 'p1',
  reservationId: 'abcdef12-0000-0000-0000-000000000000',
  statut: 'reussi',
  montantAcompte: 4500,
  commission: 1500,
  remboursementDu: false,
  remboursementDuLe: null,
  tentativesRemboursement: 0,
  tentativesEpuisees: false,
  payeLe: '2030-01-20T10:30:00+00:00',
  rembourseLe: null,
  crediteLe: null,
  ...o,
})

describe('BasculeGelules', () => {
  function Exemple({ onChange }: { onChange?: (v: string) => void }) {
    const [v, setV] = useState<'7' | '30'>('7')
    return (
      <BasculeGelules
        legende="Période"
        valeur={v}
        onChange={(x) => {
          setV(x)
          onChange?.(x)
        }}
        options={[{ valeur: '7', libelle: '7 jours' }, { valeur: '30', libelle: '30 jours' }]}
      />
    )
  }
  it('marque l’option active et change au clic et au clavier', async () => {
    const onChange = vi.fn()
    render(<Exemple onChange={onChange} />)
    expect(screen.getByRole('group', { name: 'Période' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '7 jours' })).toHaveAttribute('aria-pressed', 'true')
    await userEvent.click(screen.getByRole('button', { name: '30 jours' }))
    expect(onChange).toHaveBeenCalledWith('30')
    expect(screen.getByRole('button', { name: '30 jours' })).toHaveAttribute('aria-pressed', 'true')
    screen.getByRole('button', { name: '7 jours' }).focus()
    await userEvent.keyboard('{Enter}')
    expect(onChange).toHaveBeenLastCalledWith('7')
  })
})

describe('BoutonRond et LienGelule', () => {
  it('BoutonRond : lien nommé vers l’écran, ou bouton d’action', async () => {
    const onClick = vi.fn()
    render(
      <MemoryRouter>
        <BoutonRond libelle="Ouvrir" icone={<span />} vers="/litiges" />
        <BoutonRond libelle="Options" icone={<span />} onClick={onClick} />
      </MemoryRouter>,
    )
    expect(screen.getByRole('link', { name: 'Ouvrir' })).toHaveAttribute('href', '/litiges')
    await userEvent.click(screen.getByRole('button', { name: 'Options' }))
    expect(onClick).toHaveBeenCalled()
  })
  it('LienGelule : lien vers l’écran', () => {
    render(
      <MemoryRouter>
        <LienGelule vers="/revenus">Voir les revenus</LienGelule>
      </MemoryRouter>,
    )
    expect(screen.getByRole('link', { name: 'Voir les revenus' })).toHaveAttribute('href', '/revenus')
  })
})

describe('DerniersPaiements', () => {
  it('affiche titre, référence courte, date, heure de Dakar, statut et montant FCFA', () => {
    render(<DerniersPaiements paiements={[paiement({}), paiement({ id: 'p2', statut: 'en_attente', payeLe: null, montantAcompte: 900 })]} />)
    const tableau = screen.getByRole('table', { name: 'Derniers paiements' })
    const lignes = within(tableau).getAllByRole('row')
    expect(lignes).toHaveLength(3)
    expect(within(lignes[1]).getByText('Acompte de réservation')).toBeInTheDocument()
    expect(within(lignes[1]).getByText('Réservation abcdef12')).toBeInTheDocument()
    expect(within(lignes[1]).getByText('10:30')).toBeInTheDocument()
    expect(within(lignes[1]).getByText('Réussi')).toBeInTheDocument()
    expect(within(lignes[1]).getByText(/4\s500\s*FCFA/)).toBeInTheDocument()
    expect(within(lignes[2]).getByText('En attente')).toBeInTheDocument()
    expect(within(lignes[2]).getAllByText('—')).toHaveLength(2)
  })
  it('n’expose ni le code de réservation, ni la référence DexPay, ni le téléphone', () => {
    const { container } = render(<DerniersPaiements paiements={[paiement({})]} />)
    expect(container.textContent).not.toMatch(/dexpay|\+221|code/i)
  })
  it('chargement : squelettes annoncés', () => {
    render(<DerniersPaiements paiements={[]} chargement />)
    expect(screen.getByRole('status')).toHaveTextContent('Chargement…')
    expect(screen.getAllByRole('row')).toHaveLength(6)
  })
  it('vide', () => {
    render(<DerniersPaiements paiements={[]} />)
    expect(screen.getByText('Aucun paiement pour le moment.')).toBeInTheDocument()
  })
  it('erreur : message et Réessayer', async () => {
    const onReessayer = vi.fn()
    render(<DerniersPaiements paiements={[]} erreur="Panne." onReessayer={onReessayer} />)
    expect(screen.getByRole('alert')).toHaveTextContent('Panne.')
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(onReessayer).toHaveBeenCalled()
  })
})
