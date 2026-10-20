import { useState } from 'react'
import { act, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import Bouton, { DUREE_COCHE } from './Bouton'

describe('Bouton', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('déclenche onClick', () => {
    const onClick = vi.fn()
    render(<Bouton onClick={onClick}>Valider</Bouton>)
    fireEvent.click(screen.getByRole('button', { name: 'Valider' }))
    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('en chargement : désactivé, occupé, texte masqué mais présent, indicateur affiché, aucun clic', () => {
    const onClick = vi.fn()
    const { container } = render(<Bouton enCours onClick={onClick}>Valider</Bouton>)
    const bouton = screen.getByRole('button', { name: 'Valider' })
    expect(bouton).toBeDisabled()
    expect(bouton).toHaveAttribute('aria-busy', 'true')
    expect(screen.getByText('Valider')).toHaveClass('opacity-0')
    expect(container.querySelector('.animate-spin')).not.toBeNull()
    fireEvent.click(bouton)
    fireEvent.click(bouton)
    expect(onClick).not.toHaveBeenCalled()
  })

  it('désactivé : aucun clic', () => {
    const onClick = vi.fn()
    render(<Bouton disabled onClick={onClick}>Valider</Bouton>)
    fireEvent.click(screen.getByRole('button', { name: 'Valider' }))
    expect(onClick).not.toHaveBeenCalled()
  })

  it('pas de double clic : un bouton qui passe en chargement au premier clic ignore le second', () => {
    const onClick = vi.fn()
    function Exemple() {
      const [enCours, setEnCours] = useState(false)
      return (
        <Bouton
          enCours={enCours}
          onClick={() => {
            onClick()
            setEnCours(true)
          }}
        >
          Valider
        </Bouton>
      )
    }
    render(<Exemple />)
    const bouton = screen.getByRole('button', { name: 'Valider' })
    fireEvent.click(bouton)
    fireEvent.click(bouton)
    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('succès : une coche s\'affiche environ 1 seconde puis le bouton redevient normal', () => {
    const { container, rerender } = render(<Bouton succes={false}>Ajouter</Bouton>)
    expect(container.querySelector('svg')).toBeNull()
    rerender(<Bouton succes>Ajouter</Bouton>)
    expect(container.querySelector('svg')).not.toBeNull()
    expect(screen.getByText('Ajouter')).toHaveClass('opacity-0')
    act(() => void vi.advanceTimersByTime(DUREE_COCHE))
    expect(container.querySelector('svg')).toBeNull()
    expect(screen.getByText('Ajouter')).not.toHaveClass('opacity-0')
  })
})
