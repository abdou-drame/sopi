import { act, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AlertesProvider, DUREES_RETOUR, useRetours } from './Alertes'

function Declencheurs() {
  const retour = useRetours()
  return (
    <>
      <button onClick={() => retour.succes('Fait')}>succes</button>
      <button onClick={() => retour.info('Info')}>info</button>
      <button onClick={() => retour.erreur('Échec')}>erreur</button>
      <button onClick={() => retour.succes('Message ' + Math.random())}>unique</button>
    </>
  )
}

function afficher() {
  render(
    <AlertesProvider>
      <Declencheurs />
    </AlertesProvider>,
  )
  return {
    click: (el: HTMLElement) => act(() => void fireEvent.click(el)),
    hover: (el: HTMLElement) => act(() => void fireEvent.mouseEnter(el)),
    unhover: (el: HTMLElement) => act(() => void fireEvent.mouseLeave(el)),
  }
}

const avancer = (ms: number) => act(() => void vi.advanceTimersByTime(ms))

describe('Alertes', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it.each([
    ['succes', 'Fait', DUREES_RETOUR.succes],
    ['info', 'Info', DUREES_RETOUR.info],
    ['erreur', 'Échec', DUREES_RETOUR.erreur],
  ])('%s disparaît après sa durée', (bouton, texte, duree) => {
    const u = afficher()
    u.click(screen.getByText(bouton, { selector: 'button' }))
    expect(screen.getByText(texte)).toBeInTheDocument()
    avancer(duree - 1)
    expect(screen.getByText(texte)).toBeInTheDocument()
    avancer(1)
    expect(screen.queryByText(texte)).not.toBeInTheDocument()
  })

  it('les durées sont 4 s, 5 s et 8 s', () => {
    expect(DUREES_RETOUR).toEqual({ succes: 4000, info: 5000, erreur: 8000 })
  })

  it('se ferme avec le bouton ×', () => {
    const u = afficher()
    u.click(screen.getByText('succes', { selector: 'button' }))
    u.click(screen.getByRole('button', { name: 'Fermer' }))
    expect(screen.queryByText('Fait')).not.toBeInTheDocument()
  })

  it('met le compte à rebours en pause au survol', () => {
    const u = afficher()
    u.click(screen.getByText('succes', { selector: 'button' }))
    avancer(3000)
    u.hover(screen.getByText('Fait'))
    avancer(10000)
    expect(screen.getByText('Fait')).toBeInTheDocument()
    u.unhover(screen.getByText('Fait'))
    avancer(999)
    expect(screen.getByText('Fait')).toBeInTheDocument()
    avancer(1)
    expect(screen.queryByText('Fait')).not.toBeInTheDocument()
  })

  it('affiche 3 alertes au maximum, les plus anciennes sont retirées', () => {
    const u = afficher()
    for (let i = 0; i < 4; i++) u.click(screen.getByText('unique', { selector: 'button' }))
    expect(screen.getAllByRole('status')).toHaveLength(3)
  })

  it('ne duplique pas un message répété', () => {
    const u = afficher()
    u.click(screen.getByText('succes', { selector: 'button' }))
    u.click(screen.getByText('succes', { selector: 'button' }))
    expect(screen.getAllByText('Fait')).toHaveLength(1)
  })

  it('utilise role="status" pour succès et information, role="alert" pour les erreurs', () => {
    const u = afficher()
    u.click(screen.getByText('succes', { selector: 'button' }))
    u.click(screen.getByText('info', { selector: 'button' }))
    u.click(screen.getByText('erreur', { selector: 'button' }))
    expect(screen.getAllByRole('status')).toHaveLength(2)
    expect(screen.getByRole('alert')).toHaveTextContent('Échec')
  })
})
