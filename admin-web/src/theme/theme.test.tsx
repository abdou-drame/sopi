import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import BasculeTheme from './BasculeTheme'
import { ThemeProvider } from './ThemeContext'

function simulerSysteme(sombre: boolean) {
  vi.stubGlobal('matchMedia', (q: string) => ({
    matches: sombre && q.includes('dark'),
    media: q,
    addEventListener: () => {},
    removeEventListener: () => {},
  }))
}

const afficher = () =>
  render(
    <ThemeProvider>
      <BasculeTheme />
    </ThemeProvider>,
  )

describe('mode sombre', () => {
  beforeEach(() => {
    window.localStorage.clear()
    delete document.documentElement.dataset.theme
  })
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('suit le réglage du système par défaut (clair)', () => {
    simulerSysteme(false)
    afficher()
    expect(screen.getByRole('switch', { name: 'Mode sombre' })).toHaveAttribute('aria-checked', 'false')
    expect(document.documentElement.dataset.theme).toBe('light')
  })

  it('démarre en sombre si le système le demande', () => {
    simulerSysteme(true)
    afficher()
    expect(screen.getByRole('switch', { name: 'Mode sombre' })).toHaveAttribute('aria-checked', 'true')
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('bascule, mémorise le choix et le préfère au réglage du système', async () => {
    simulerSysteme(true)
    const { unmount } = afficher()
    await userEvent.click(screen.getByRole('switch', { name: 'Mode sombre' }))
    expect(document.documentElement.dataset.theme).toBe('light')
    expect(window.localStorage.getItem('sopi.admin.theme')).toBe('clair')
    unmount()
    afficher()
    expect(screen.getByRole('switch', { name: 'Mode sombre' })).toHaveAttribute('aria-checked', 'false')
  })

  it('fonctionne quand le stockage du navigateur est indisponible', async () => {
    simulerSysteme(false)
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('bloqué')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('bloqué')
    })
    afficher()
    await userEvent.click(screen.getByRole('switch', { name: 'Mode sombre' }))
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('atteignable au clavier et activable avec Entrée', async () => {
    simulerSysteme(false)
    afficher()
    await userEvent.tab()
    expect(screen.getByRole('switch', { name: 'Mode sombre' })).toHaveFocus()
    await userEvent.keyboard('{Enter}')
    expect(document.documentElement.dataset.theme).toBe('dark')
  })
})
