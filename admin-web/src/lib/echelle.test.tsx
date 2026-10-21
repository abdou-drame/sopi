import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it } from 'vitest'
import EchelleEcran, { calculerEchelle, echelleActuelle } from './echelle'

describe('échelle de l’écran', () => {
  it.each([
    [1920, 1],
    [1440, 1],
    [1366, 1366 / 1440],
    [1280, 1280 / 1440],
    [1100, 1100 / 1440],
    [1024, 0.75],
    [900, 1],
  ])('largeur %i px → échelle %d', (largeur, attendu) => {
    expect(calculerEchelle(largeur)).toBeCloseTo(attendu, 5)
  })

  it('reste dans [0,75 ; 1] dès 1024 px', () => {
    for (let l = 1024; l <= 3000; l += 17) {
      const e = calculerEchelle(l)
      expect(e).toBeGreaterThanOrEqual(0.75)
      expect(e).toBeLessThanOrEqual(1)
    }
  })
})

describe('EchelleEcran', () => {
  afterEach(() => {
    document.body.removeAttribute('style')
  })
  const largeur = (l: number) => Object.defineProperty(window, 'innerWidth', { value: l, configurable: true, writable: true })

  it('applique le zoom dès le premier rendu et le met à jour au redimensionnement', () => {
    largeur(1100)
    render(
      <MemoryRouter initialEntries={['/revenus']}>
        <EchelleEcran />
      </MemoryRouter>,
    )
    expect(Number(document.body.style.zoom)).toBeCloseTo(1100 / 1440, 5)
    expect(echelleActuelle()).toBeCloseTo(1100 / 1440, 5)
    largeur(1920)
    window.dispatchEvent(new Event('resize'))
    expect(document.body.style.zoom).toBe('')
    expect(echelleActuelle()).toBe(1)
  })

  it('ne met pas la page de connexion à l’échelle', () => {
    largeur(1100)
    render(
      <MemoryRouter initialEntries={['/connexion']}>
        <EchelleEcran />
      </MemoryRouter>,
    )
    expect(document.body.style.zoom).toBe('')
    expect(echelleActuelle()).toBe(1)
  })
})
