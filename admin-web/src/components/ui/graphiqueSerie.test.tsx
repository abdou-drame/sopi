import { act, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import GraphiqueSerie from './GraphiqueSerie'

const donnees = [
  { libelle: 'Mai', valeur: 10 },
  { libelle: 'Juin', valeur: 40 },
  { libelle: 'Juil.', valeur: 25 },
]
const simuler = (reduit: boolean) =>
  vi.stubGlobal('matchMedia', (q: string) => ({ matches: reduit && q.includes('reduce'), media: q, addEventListener: () => {}, removeEventListener: () => {} }))
const formes = (c: HTMLElement) => Array.from(c.querySelectorAll<SVGRectElement>('rect.serie-forme'))
const nombre = (e: Element, attr: string) => Number(e.getAttribute(attr))
const ligne = (c: HTMLElement) => c.querySelector('path.serie-ligne') as SVGPathElement

describe('GraphiqueSerie', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('garde l’étiquette accessible et une forme par période', () => {
    simuler(true)
    const { container } = render(<GraphiqueSerie description="Réservations" donnees={donnees} mode="colonnes" formatValeur={(v) => `${v} rés.`} />)
    expect(screen.getByRole('img', { name: 'Réservations : Mai 10 rés., Juin 40 rés., Juil. 25 rés.' })).toBeInTheDocument()
    expect(formes(container)).toHaveLength(3)
  })

  it('morph colonnes → courbe : la colonne se resserre en point et la ligne se trace', () => {
    simuler(true)
    const { container, rerender } = render(<GraphiqueSerie description="R" donnees={donnees} mode="colonnes" />)
    const avant = formes(container)[1]
    expect(avant.dataset.forme).toBe('colonne')
    const hauteurColonne = nombre(avant, 'height')
    expect(hauteurColonne).toBeGreaterThan(20)
    expect(ligne(container).getAttribute('stroke-dashoffset')).toBe('1')

    rerender(<GraphiqueSerie description="R" donnees={donnees} mode="courbe" />)
    const apres = formes(container)[1]
    expect(apres).toBe(avant) // le même élément se transforme : pas un fondu entre deux graphiques
    expect(apres.dataset.forme).toBe('point')
    expect(nombre(apres, 'width')).toBe(nombre(apres, 'height'))
    expect(nombre(apres, 'height')).toBeLessThan(hauteurColonne)
    expect(nombre(apres, 'rx')).toBe(nombre(apres, 'width') / 2)
    expect(ligne(container).getAttribute('stroke-dashoffset')).toBe('0')
  })

  it('morph courbe → colonnes : le point s’étire en colonne et la ligne s’efface', () => {
    simuler(true)
    const { container, rerender } = render(<GraphiqueSerie description="R" donnees={donnees} mode="courbe" />)
    const avant = formes(container)[1]
    expect(avant.dataset.forme).toBe('point')
    expect(ligne(container).getAttribute('stroke-dashoffset')).toBe('0')

    rerender(<GraphiqueSerie description="R" donnees={donnees} mode="colonnes" />)
    const apres = formes(container)[1]
    expect(apres).toBe(avant)
    expect(apres.dataset.forme).toBe('colonne')
    expect(nombre(apres, 'height')).toBeGreaterThan(20)
    expect(ligne(container).getAttribute('stroke-dashoffset')).toBe('1')
  })

  it('la plus haute valeur est la forme active, le survol déplace la bulle de valeur', () => {
    simuler(true)
    const { container } = render(<GraphiqueSerie description="R" donnees={donnees} mode="colonnes" formatValeur={(v) => `${v} rés.`} />)
    expect(screen.getByText('40 rés.')).toBeInTheDocument()
    const zones = Array.from(container.querySelectorAll('rect[fill="transparent"]'))
    fireEvent.mouseEnter(zones[0])
    expect(screen.getByText('10 rés.')).toBeInTheDocument()
    expect(screen.queryByText('40 rés.')).not.toBeInTheDocument()
  })

  it('mouvement réduit : tout est posé d’emblée, sans croissance ni transition', () => {
    simuler(true)
    const { container } = render(<GraphiqueSerie description="R" donnees={donnees} mode="colonnes" />)
    expect(container.querySelector('[data-anime]')?.getAttribute('data-anime')).toBe('false')
    expect(nombre(formes(container)[1], 'height')).toBeGreaterThan(20) // déjà à sa hauteur finale au premier rendu
  })

  it('animations permises : les formes partent de la base puis poussent jusqu’à leur hauteur', () => {
    simuler(false)
    let rappel: FrameRequestCallback = () => {}
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb) => {
      rappel = cb
      return 1
    })
    vi.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {})
    const { container } = render(<GraphiqueSerie description="R" donnees={donnees} mode="colonnes" />)
    expect(container.querySelector('[data-anime]')?.getAttribute('data-anime')).toBe('true')
    expect(nombre(formes(container)[1], 'height')).toBe(0)
    act(() => rappel(0))
    expect(nombre(formes(container)[1], 'height')).toBeGreaterThan(20)
  })
})
