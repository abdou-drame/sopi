import { afterEach, describe, expect, it, vi } from 'vitest'
import { actualiserEnCercle } from './cercleActualiser'

const bouton = () => {
  const b = document.createElement('button')
  b.getBoundingClientRect = () => ({ left: 100, top: 200, width: 40, height: 40, right: 140, bottom: 240, x: 100, y: 200, toJSON: () => ({}) })
  return b
}

describe('cercle d’Actualiser', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    delete (document as unknown as Record<string, unknown>).startViewTransition
    delete (document.documentElement as unknown as Record<string, unknown>).animate
  })

  it('sans View Transition : actualise simplement', () => {
    const actualiser = vi.fn(() => Promise.resolve())
    actualiserEnCercle(bouton(), actualiser)
    expect(actualiser).toHaveBeenCalledTimes(1)
  })

  it('avec View Transition : actualise dans la transition et ouvre un cercle depuis le centre du bouton', async () => {
    vi.stubGlobal('matchMedia', () => ({ matches: false }))
    const animer = vi.fn()
    ;(document.documentElement as unknown as Record<string, unknown>).animate = animer
    const actualiser = vi.fn(() => Promise.resolve())
    ;(document as unknown as Record<string, unknown>).startViewTransition = vi.fn((rappel: () => Promise<void>) => {
      void rappel()
      return { ready: Promise.resolve() }
    })
    actualiserEnCercle(bouton(), actualiser)
    expect(actualiser).toHaveBeenCalledTimes(1)
    await vi.waitFor(() => expect(animer).toHaveBeenCalledTimes(1))
    const [cles, options] = animer.mock.calls[0]
    expect(cles.clipPath[0]).toBe('circle(0px at 120px 220px)')
    expect(cles.clipPath[1]).toMatch(/^circle\(\d+(\.\d+)?px at 120px 220px\)$/)
    expect(options).toMatchObject({ duration: 620, pseudoElement: '::view-transition-new(root)' })
  })

  it('mouvement réduit : actualise sans transition', () => {
    vi.stubGlobal('matchMedia', () => ({ matches: true }))
    const demarrer = vi.fn()
    ;(document as unknown as Record<string, unknown>).startViewTransition = demarrer
    const actualiser = vi.fn(() => Promise.resolve())
    actualiserEnCercle(bouton(), actualiser)
    expect(actualiser).toHaveBeenCalledTimes(1)
    expect(demarrer).not.toHaveBeenCalled()
  })
})
