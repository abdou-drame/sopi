import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { dureeMinutes } from '../../i18n/libelles'
import { formatMontant } from '../../lib/format'
import BadgeStatut from './BadgeStatut'
import ModaleConfirmation from './ModaleConfirmation'
import Tableau from './Tableau'

describe('ModaleConfirmation', () => {
  function afficher(surConfirmer = vi.fn(), surAnnuler = vi.fn(), motifObligatoire = true) {
    render(
      <ModaleConfirmation
        ouverte
        titre="Refuser ce dossier ?"
        motifObligatoire={motifObligatoire}
        libelleConfirmer="Refuser"
        onConfirmer={surConfirmer}
        onAnnuler={surAnnuler}
      />,
    )
    return { surConfirmer, surAnnuler }
  }

  it('refuse de confirmer sans motif et affiche une erreur', async () => {
    const { surConfirmer } = afficher()
    await userEvent.click(screen.getByRole('button', { name: 'Refuser' }))
    expect(surConfirmer).not.toHaveBeenCalled()
    expect(screen.getByRole('alert')).toHaveTextContent('Le motif est obligatoire.')
  })

  it('refuse un motif composé uniquement d’espaces', async () => {
    const { surConfirmer } = afficher()
    await userEvent.type(screen.getByLabelText('Motif'), '   ')
    await userEvent.click(screen.getByRole('button', { name: 'Refuser' }))
    expect(surConfirmer).not.toHaveBeenCalled()
  })

  it('confirme avec le motif saisi (sans espaces autour)', async () => {
    const { surConfirmer } = afficher()
    await userEvent.type(screen.getByLabelText('Motif'), '  Pièce illisible ')
    await userEvent.click(screen.getByRole('button', { name: 'Refuser' }))
    expect(surConfirmer).toHaveBeenCalledWith('Pièce illisible')
  })

  it('se ferme avec Échap et via Annuler', async () => {
    const { surAnnuler } = afficher()
    await userEvent.keyboard('{Escape}')
    expect(surAnnuler).toHaveBeenCalledTimes(1)
    await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))
    expect(surAnnuler).toHaveBeenCalledTimes(2)
  })

  it('met le focus dans la modale et le garde en boucle avec Tab', async () => {
    afficher()
    const dialogue = screen.getByRole('dialog', { name: 'Refuser ce dossier ?' })
    expect(dialogue).toContainElement(document.activeElement as HTMLElement)
    await userEvent.tab()
    await userEvent.tab()
    await userEvent.tab()
    await userEvent.tab()
    expect(dialogue).toContainElement(document.activeElement as HTMLElement)
  })

  it('sans motif obligatoire, confirme directement', async () => {
    const { surConfirmer } = afficher(vi.fn(), vi.fn(), false)
    expect(screen.queryByLabelText('Motif')).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Refuser' }))
    expect(surConfirmer).toHaveBeenCalledWith('')
  })

  it('ne rend rien quand elle est fermée', () => {
    render(<ModaleConfirmation ouverte={false} titre="X" onConfirmer={vi.fn()} onAnnuler={vi.fn()} />)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})

describe('Tableau', () => {
  interface Ligne {
    id: string
    nom: string
  }
  const colonnes = [
    { cle: 'nom', titre: 'Nom', triable: true, rendu: (l: Ligne) => l.nom },
    { cle: 'autre', titre: 'Autre', rendu: () => '—' },
  ]
  const lignes: Ligne[] = [
    { id: 'a', nom: 'Awa' },
    { id: 'b', nom: 'Moussa' },
  ]

  it('affiche les lignes et la légende accessible', () => {
    render(<Tableau legende="Utilisateurs" colonnes={colonnes} lignes={lignes} cleLigne={(l) => l.id} />)
    const tableau = screen.getByRole('table', { name: 'Utilisateurs' })
    expect(within(tableau).getAllByRole('row')).toHaveLength(3)
    expect(screen.getByText('Moussa')).toBeInTheDocument()
  })

  it('appelle le tri et indique le sens de tri', async () => {
    const surTri = vi.fn()
    render(
      <Tableau
        legende="U"
        colonnes={colonnes}
        lignes={lignes}
        cleLigne={(l) => l.id}
        tri={{ cle: 'nom', sens: 'desc' }}
        onTri={surTri}
      />,
    )
    expect(screen.getByRole('columnheader', { name: /Nom/ })).toHaveAttribute('aria-sort', 'descending')
    await userEvent.click(screen.getByRole('button', { name: 'Trier par Nom' }))
    expect(surTri).toHaveBeenCalledWith('nom')
    expect(screen.queryByRole('button', { name: 'Trier par Autre' })).not.toBeInTheDocument()
  })

  it('affiche l’état vide', () => {
    render(<Tableau legende="U" colonnes={colonnes} lignes={[]} cleLigne={(l) => l.id} messageVide="Aucun utilisateur." />)
    expect(screen.getByText('Aucun utilisateur.')).toBeInTheDocument()
  })

  it('affiche l’état de chargement sans lignes', () => {
    render(<Tableau legende="U" colonnes={colonnes} lignes={lignes} cleLigne={(l) => l.id} chargement />)
    expect(screen.getByRole('status')).toHaveTextContent('Chargement…')
    expect(screen.queryByText('Awa')).not.toBeInTheDocument()
  })

  it('affiche l’erreur avec un bouton Réessayer', async () => {
    const reessayer = vi.fn()
    render(
      <Tableau legende="U" colonnes={colonnes} lignes={[]} cleLigne={(l) => l.id} erreur="Échec." onReessayer={reessayer} />,
    )
    expect(screen.getByRole('alert')).toHaveTextContent('Échec.')
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(reessayer).toHaveBeenCalled()
  })

  it('pagine : désactive Précédent en page 1 et appelle onPage', async () => {
    const surPage = vi.fn()
    render(
      <Tableau legende="U" colonnes={colonnes} lignes={lignes} cleLigne={(l) => l.id} page={1} totalPages={3} total={58} onPage={surPage} />,
    )
    expect(screen.getByRole('button', { name: 'Précédent' })).toBeDisabled()
    expect(screen.getByText(/58 résultats/)).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))
    expect(surPage).toHaveBeenCalledWith(2)
  })
})

describe('BadgeStatut', () => {
  it('affiche toujours le texte du statut, pas seulement une couleur', () => {
    render(<BadgeStatut statut="en_attente" />)
    expect(screen.getByText('En attente')).toBeInTheDocument()
  })

  it('utilise des tons différents pour succès et erreur', () => {
    const { rerender } = render(<BadgeStatut statut="reussi" />)
    expect(screen.getByText('Réussi').className).toContain('text-succes-texte')
    rerender(<BadgeStatut statut="echoue" />)
    expect(screen.getByText('Échoué').className).toContain('text-erreur-texte')
  })

  it('retombe sur le texte brut pour un statut inconnu', () => {
    render(<BadgeStatut statut="inconnu_x" />)
    expect(screen.getByText('inconnu_x')).toBeInTheDocument()
  })
})

describe('formats', () => {
  it('formate les montants en FCFA entiers avec espace', () => {
    expect(formatMontant(15000)).toBe('15 000 FCFA')
    expect(formatMontant(1350000)).toBe('1 350 000 FCFA')
    expect(formatMontant(0)).toBe('0 FCFA')
  })

  it('formate les durées', () => {
    expect(dureeMinutes(45)).toBe('45 min')
    expect(dureeMinutes(90)).toBe('1 h 30')
    expect(dureeMinutes(4500)).toBe('3 j 3 h')
  })
})
