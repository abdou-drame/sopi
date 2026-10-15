import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { formatMontant } from '../../lib/format'
import CarteSopi from './CarteSopi'

const lignes: [{ libelle: string; valeur: string }, { libelle: string; valeur: string }] = [
  { libelle: 'Acomptes encaissés', valeur: formatMontant(450000) },
  { libelle: 'Soldes des prestataires', valeur: formatMontant(82000) },
]

describe('CarteSopi', () => {
  it('affiche la marque, le libellé, le montant en FCFA, les deux lignes et la note', () => {
    render(<CarteSopi libelle="Commission retenue" montant={formatMontant(135000)} lignes={lignes} note="1 sept. – 30 sept." />)
    expect(screen.getByText('Sopi')).toBeInTheDocument()
    expect(screen.getByText('Commission retenue')).toBeInTheDocument()
    expect(screen.getByText(/135\s000\s*FCFA/)).toBeInTheDocument()
    expect(screen.getByText('Acomptes encaissés')).toBeInTheDocument()
    expect(screen.getByText(/450\s000\s*FCFA/)).toBeInTheDocument()
    expect(screen.getByText('Soldes des prestataires')).toBeInTheDocument()
    expect(screen.getByText('1 sept. – 30 sept.')).toBeInTheDocument()
  })

  it('n’affiche aucun numéro de carte ni date d’expiration', () => {
    const { container } = render(<CarteSopi libelle="X" montant="1" lignes={lignes} />)
    expect(container.textContent).not.toMatch(/\d{4}\s\d{4}/)
    expect(container.textContent).not.toMatch(/\d{2}\/\d{2}/)
  })

  it('état de chargement : squelettes et aucune valeur', () => {
    render(<CarteSopi libelle="Commission retenue" montant="" lignes={lignes} chargement />)
    expect(screen.getByRole('status', { name: 'Chargement…' })).toBeInTheDocument()
    expect(screen.queryByText('Commission retenue')).not.toBeInTheDocument()
    expect(screen.queryByText(/FCFA/)).not.toBeInTheDocument()
  })

  it('état d’erreur : message et bouton Réessayer', async () => {
    const onReessayer = vi.fn()
    render(<CarteSopi libelle="X" montant="1" lignes={lignes} erreur="Impossible de joindre le serveur." onReessayer={onReessayer} />)
    expect(screen.getByRole('alert')).toHaveTextContent('Impossible de joindre le serveur.')
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(onReessayer).toHaveBeenCalled()
  })
})
