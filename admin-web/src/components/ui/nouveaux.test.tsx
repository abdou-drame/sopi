import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import BarreProgression from './BarreProgression'
import Carte from './Carte'
import { GraphiqueBarres, GraphiqueCourbe } from './Graphiques'
import Infobulle from './Infobulle'

describe('Carte', () => {
  it('affiche titre, sous-titre, action et contenu', () => {
    render(
      <Carte titre="Évolution" sousTitre="Sur 30 jours" action={<button>Options</button>}>
        <p>Contenu</p>
      </Carte>,
    )
    expect(screen.getByRole('heading', { name: 'Évolution' })).toBeInTheDocument()
    expect(screen.getByText('Sur 30 jours')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Options' })).toBeInTheDocument()
    expect(screen.getByText('Contenu')).toBeInTheDocument()
  })
})

describe('BarreProgression', () => {
  it('expose une barre de progression accessible, bornée entre 0 et 100', () => {
    render(
      <>
        <BarreProgression libelle="Mécanique" valeur={44.4} />
        <BarreProgression libelle="Trop" valeur={180} />
      </>,
    )
    expect(screen.getByRole('progressbar', { name: 'Mécanique' })).toHaveAttribute('aria-valuenow', '44')
    expect(screen.getByRole('progressbar', { name: 'Trop' })).toHaveAttribute('aria-valuenow', '100')
    expect(screen.getByText('44 %')).toBeInTheDocument()
  })
})

describe('Infobulle', () => {
  it('relie le texte à son contenu et reste atteignable au clavier', async () => {
    render(
      <Infobulle texte="Tableau de bord">
        <button>Accueil</button>
      </Infobulle>,
    )
    const bouton = screen.getByRole('button', { name: 'Accueil' })
    expect(screen.getByRole('tooltip')).toHaveTextContent('Tableau de bord')
    expect(bouton.parentElement).toHaveAttribute('aria-describedby', screen.getByRole('tooltip').id)
    await userEvent.tab()
    expect(bouton).toHaveFocus()
  })
})

describe('graphiques', () => {
  const donnees = [
    { libelle: 'Mécanique', valeur: 150 },
    { libelle: 'Coiffure', valeur: 120 },
    { libelle: 'Pressing', valeur: 70 },
  ]

  it('barres en gélule : la plus haute est active par défaut, le survol déplace la pastille', () => {
    render(<GraphiqueBarres description="Par domaine" donnees={donnees} formatValeur={(v) => `${v} rés.`} />)
    expect(screen.getByRole('img', { name: /Par domaine : Mécanique 150 rés\., Coiffure 120 rés\., Pressing 70 rés\./ })).toBeInTheDocument()
    expect(screen.getByText('150 rés.')).toBeInTheDocument()
    expect(screen.queryByText('70 rés.')).not.toBeInTheDocument()
    fireEvent.mouseEnter(screen.getByText('Pressing').parentElement!)
    expect(screen.getByText('70 rés.')).toBeInTheDocument()
    expect(screen.queryByText('150 rés.')).not.toBeInTheDocument()
  })

  it('barres : le focus clavier active aussi la barre', () => {
    render(<GraphiqueBarres description="Par domaine" donnees={donnees} />)
    fireEvent.focus(screen.getByText('Coiffure').parentElement!)
    expect(screen.getByText('120')).toBeInTheDocument()
  })

  it('courbe lissée : pastille sur la valeur la plus haute, puis sur le point survolé', () => {
    const { container } = render(
      <GraphiqueCourbe
        description="Courbe d’exemple"
        libelles={['1 janv.', '2 janv.', '3 janv.']}
        series={[{ nom: 'Acomptes', couleur: 'var(--color-primaire-500)', valeurs: [10, 30, 20] }]}
        formatValeur={(v) => `${v} F`}
      />,
    )
    expect(screen.getByText('2 janv. · 30 F')).toBeInTheDocument()
    // Le tracé utilise des courbes de Bézier (lissage), pas des segments droits.
    expect(container.querySelector('path[d*=" C"]')).not.toBeNull()
    const zones = container.querySelectorAll('rect[fill="transparent"]')
    fireEvent.mouseEnter(zones[0])
    expect(screen.getByText('1 janv. · 10 F')).toBeInTheDocument()
  })

  it('courbe : une série vide ne plante pas', () => {
    render(<GraphiqueCourbe description="Vide" libelles={[]} series={[{ nom: 'A', couleur: 'red', valeurs: [] }]} />)
    expect(screen.getByRole('img', { name: 'Vide' })).toBeInTheDocument()
  })
})
