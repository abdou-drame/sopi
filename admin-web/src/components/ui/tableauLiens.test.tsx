import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import Tableau from './Tableau'

interface Ligne {
  id: string
  nom: string
}
const LIGNES: Ligne[] = [
  { id: 'a-1', nom: 'Garage Moussa' },
  { id: 'a-2', nom: 'Salon Awa' },
]
const colonnes = [{ cle: 'nom', titre: 'Nom', rendu: (l: Ligne) => l.nom }]

function Lieu() {
  const l = useLocation()
  return <p data-testid="lieu">{l.pathname}</p>
}

function afficher(avecLien = true) {
  return render(
    <MemoryRouter initialEntries={['/liste?statut=x']}>
      <Routes>
        <Route
          path="/liste"
          element={
            <Tableau
              legende="Liste"
              colonnes={colonnes}
              lignes={LIGNES}
              cleLigne={(l) => l.id}
              lien={avecLien ? (l) => ({ vers: `/fiche/${l.id}`, nom: l.nom }) : undefined}
            />
          }
        />
        <Route path="*" element={<Lieu />} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('tableau : lignes cliquables', () => {
  it('donne à chaque ligne un vrai lien « Voir » vers sa fiche', () => {
    afficher()
    expect(screen.getByRole('link', { name: 'Voir le détail : Garage Moussa' })).toHaveAttribute('href', '/fiche/a-1')
    expect(screen.getByRole('link', { name: 'Voir le détail : Salon Awa' })).toHaveAttribute('href', '/fiche/a-2')
    expect(screen.getAllByRole('link', { name: /Voir/ })[0]).toHaveTextContent('Voir')
  })

  it('étire le lien sur toute la ligne (zone cliquable native, clic molette inclus)', () => {
    afficher()
    const ligne = screen.getByRole('row', { name: /Garage Moussa/ })
    expect(ligne.className).toContain('cursor-pointer')
    const voir = screen.getByRole('link', { name: 'Voir le détail : Garage Moussa' })
    expect(voir.className).toContain('after:absolute')
    expect(voir.className).toContain('after:inset-0')
  })

  it('le lien « Voir » se focalise au clavier et s’active avec Entrée', async () => {
    afficher()
    await userEvent.tab()
    expect(screen.getByRole('link', { name: 'Voir le détail : Garage Moussa' })).toHaveFocus()
    await userEvent.keyboard('{Enter}')
    expect(await screen.findByTestId('lieu')).toHaveTextContent('/fiche/a-1')
  })

  it('laisse le bouton « Voir » naviguer une seule fois', async () => {
    afficher()
    await userEvent.click(screen.getByRole('link', { name: 'Voir le détail : Salon Awa' }))
    expect(await screen.findByTestId('lieu')).toHaveTextContent('/fiche/a-2')
  })

  it('sans lien, les lignes ne sont ni cliquables ni focalisables', () => {
    afficher(false)
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getAllByRole('row')[1].className).not.toContain('cursor-pointer')
  })
})
