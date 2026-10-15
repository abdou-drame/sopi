import { render, screen } from '@testing-library/react'
import { readFileSync } from 'node:fs'
import { afterEach, describe, expect, it, vi } from 'vitest'
import FondArrierePlan from './FondArrierePlan'

function simulerMouvement(reduit: boolean) {
  vi.stubGlobal('matchMedia', (requete: string) => ({
    matches: reduit && requete.includes('prefers-reduced-motion'),
    addEventListener: () => {},
    removeEventListener: () => {},
  }))
}

describe('FondArrierePlan', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('est présent, décoratif et n’intercepte pas les clics', () => {
    simulerMouvement(false)
    render(<FondArrierePlan />)
    const fond = screen.getByTestId('fond-arriere-plan')
    expect(fond).toHaveAttribute('aria-hidden', 'true')
    expect(fond).toHaveStyle({ pointerEvents: 'none' })
    expect(fond.querySelectorAll('.fond-tache')).toHaveLength(3)
  })

  it('est animé par défaut et figé quand les animations sont réduites', () => {
    simulerMouvement(false)
    const { unmount } = render(<FondArrierePlan />)
    expect(screen.getByTestId('fond-arriere-plan')).toHaveAttribute('data-fige', 'false')
    unmount()
    simulerMouvement(true)
    render(<FondArrierePlan />)
    expect(screen.getByTestId('fond-arriere-plan')).toHaveAttribute('data-fige', 'true')
  })

  it('la feuille de style fige les taches (animations réduites, mobile) et masque le fond à l’impression', () => {
    const css = readFileSync('src/index.css', 'utf8')
    expect(css).toMatch(/@media print\s*{\s*\.fond-sopi\s*{\s*display: none/)
    expect(css).toMatch(/prefers-reduced-motion: reduce\)[\s\S]*\.fond-tache\s*{\s*animation: none !important/)
    expect(css).toMatch(/max-width: 40rem\)[\s\S]*animation: none/)
  })
})
