import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { usePresence } from './usePresence'

const simuler = (reduit: boolean) =>
  vi.stubGlobal('matchMedia', (q: string) => ({ matches: reduit && q.includes('reduce'), media: q, addEventListener: () => {}, removeEventListener: () => {} }))

describe('usePresence', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.useRealTimers()
  })

  it('garde l’élément le temps de la sortie, puis le retire', () => {
    vi.useFakeTimers()
    simuler(false)
    const { result, rerender } = renderHook(({ ouvert }) => usePresence(ouvert, 180), { initialProps: { ouvert: true } })
    expect(result.current).toEqual({ monte: true, sortie: false })
    rerender({ ouvert: false })
    expect(result.current).toEqual({ monte: true, sortie: true })
    act(() => void vi.advanceTimersByTime(180))
    expect(result.current).toEqual({ monte: false, sortie: false })
  })

  it('retire l’élément tout de suite quand le mouvement est réduit', () => {
    simuler(true)
    const { result, rerender } = renderHook(({ ouvert }) => usePresence(ouvert), { initialProps: { ouvert: true } })
    rerender({ ouvert: false })
    expect(result.current).toEqual({ monte: false, sortie: false })
  })
})
