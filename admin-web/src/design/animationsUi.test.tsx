import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import NombreAnime from '../components/ui/NombreAnime'
import { Onglets } from './composants'
import TableauDonnees from './tableau'

const simuler = (reduit: boolean) =>
  vi.stubGlobal('matchMedia', (q: string) => ({ matches: reduit && q.includes('reduce'), media: q, addEventListener: () => {}, removeEventListener: () => {} }))

afterEach(() => {
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

describe('animations de l’interface', () => {
  it('chiffre : mouvement réduit → valeur finale immédiate ; sinon il monte puis atteint exactement la valeur', async () => {
    simuler(true)
    const { unmount } = render(<NombreAnime valeur={1250} />)
    expect(screen.getByText('1 250')).toBeInTheDocument()
    unmount()

    simuler(false)
    vi.useFakeTimers({ toFake: ['requestAnimationFrame', 'cancelAnimationFrame', 'performance'] })
    render(<NombreAnime valeur={1250} duree={400} />)
    await act(async () => vi.advanceTimersByTime(200))
    const milieu = screen.getAllByText(/\d/).find((e) => e.getAttribute('aria-hidden') === 'true')?.textContent ?? ''
    expect(Number(milieu.replace(/\s/g, ''))).toBeLessThan(1250)
    await act(async () => vi.advanceTimersByTime(600))
    expect(screen.getAllByText('1 250').length).toBeGreaterThan(0)
  })

  it('tableau : les lignes portent la classe de cascade', () => {
    const { container } = render(
      <TableauDonnees legende="Liste" colonnes={[{ titre: 'Nom' }]} lignes={[{ id: 1 }]} actionsColonne="Actions">
        {(lignes) => lignes.map((l) => <tr key={l.id}><td>a</td><td /></tr>)}
      </TableauDonnees>,
    )
    expect(container.querySelector('table')).toHaveClass('lignes-cascade')
  })

  it('onglets : une pastille unique suit l’onglet actif sans changer la sélection', async () => {
    function Exemple() {
      const [actif, setActif] = useState('a')
      return <Onglets libelle="Vue" onglets={[{ cle: 'a', libelle: 'Un', actif: actif === 'a', onSelect: () => setActif('a') }, { cle: 'b', libelle: 'Deux', actif: actif === 'b', onSelect: () => setActif('b') }]} />
    }
    render(<Exemple />)
    expect(screen.getAllByTestId('pastille-onglet')).toHaveLength(1)
    await userEvent.click(screen.getByRole('tab', { name: 'Deux' }))
    expect(screen.getByRole('tab', { name: 'Deux' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Un' })).toHaveAttribute('aria-selected', 'false')
    expect(screen.getAllByTestId('pastille-onglet')).toHaveLength(1)
  })
})
