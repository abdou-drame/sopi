import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../api/client'
import type { Page } from '../api/types'
import { dureeMinutes } from '../i18n/libelles'
import { formatMontant } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'
import { ChampJour, ChampSelect } from './champs'
import CarteListe from './CarteListe'
import { CartesSquelette, EtatChargement, EtatErreur, EtatVide, TableauSquelette } from './etats'
import Fenetre from './Fenetre'
import PageDetailMq from './PageDetailMq'
import { BadgeDuStatut } from './Statut'
import { LigneLien } from './composants'
import { useListe } from './useListe'

afterEach(() => vi.restoreAllMocks())

function Lieu() {
  const l = useLocation()
  return <p data-testid="lieu">{l.pathname + l.search}</p>
}

function enveloppe(ui: React.ReactNode, adresse = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[adresse]}>
        {ui}
        <Lieu />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('Fenetre (confirmation avec motif)', () => {
  function afficher(surConfirmer = vi.fn(), surAnnuler = vi.fn(), motifObligatoire = true) {
    render(<Fenetre ouverte titre="Refuser ce dossier ?" motifObligatoire={motifObligatoire} libelleConfirmer="Refuser" onConfirmer={surConfirmer} onAnnuler={surAnnuler} />)
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
    await userEvent.type(screen.getByLabelText(/Motif/), '   ')
    await userEvent.click(screen.getByRole('button', { name: 'Refuser' }))
    expect(surConfirmer).not.toHaveBeenCalled()
  })

  it('confirme avec le motif saisi (sans espaces autour)', async () => {
    const { surConfirmer } = afficher()
    await userEvent.type(screen.getByLabelText(/Motif/), '  Pièce illisible ')
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

  it('met le focus dans la fenêtre et le garde en boucle avec Tab', async () => {
    afficher()
    const dialogue = screen.getByRole('dialog', { name: 'Refuser ce dossier ?' })
    expect(dialogue).toContainElement(document.activeElement as HTMLElement)
    for (let i = 0; i < 4; i++) await userEvent.tab()
    expect(dialogue).toContainElement(document.activeElement as HTMLElement)
  })

  it('sans motif obligatoire, confirme directement', async () => {
    const { surConfirmer } = afficher(vi.fn(), vi.fn(), false)
    expect(screen.queryByLabelText(/Motif/)).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Refuser' }))
    expect(surConfirmer).toHaveBeenCalledWith('')
  })

  it('ne rend rien quand elle est fermée', () => {
    render(<Fenetre ouverte={false} titre="X" onConfirmer={vi.fn()} onAnnuler={vi.fn()} />)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})

describe('BadgeDuStatut', () => {
  it('affiche toujours le texte du statut, pas seulement une couleur', () => {
    render(<BadgeDuStatut statut="en_attente" />)
    expect(screen.getByText('En attente')).toBeInTheDocument()
  })

  it('utilise des tons différents pour succès et erreur', () => {
    const { rerender } = render(<BadgeDuStatut statut="reussi" />)
    const reussi = screen.getByText('Réussi').className
    rerender(<BadgeDuStatut statut="echoue" />)
    expect(screen.getByText('Échoué').className).not.toBe(reussi)
  })

  it('retombe sur le texte brut pour un statut inconnu', () => {
    render(<BadgeDuStatut statut="inconnu_x" />)
    expect(screen.getByText('inconnu_x')).toBeInTheDocument()
  })
})

describe('formats', () => {
  it('formate les montants en FCFA entiers avec espace', () => {
    expect(formatMontant(15000)).toMatch(/^15\s000\sFCFA$/)
    expect(formatMontant(1350000)).toMatch(/^1\s350\s000\sFCFA$/)
    expect(formatMontant(0)).toBe('0 FCFA')
  })

  it('formate les durées', () => {
    expect(dureeMinutes(45)).toBe('45 min')
    expect(dureeMinutes(90)).toBe('1 h 30')
    expect(dureeMinutes(4500)).toBe('3 j 3 h')
  })
})

describe('états vide, chargement, erreur', () => {
  it('chargement : lignes annoncées', () => {
    render(<EtatChargement />)
    expect(screen.getByRole('status', { name: 'Chargement…' })).toHaveAttribute('aria-busy', 'true')
  })

  it('vide : message', () => {
    render(<EtatVide texte="Aucun utilisateur." />)
    expect(screen.getByText('Aucun utilisateur.')).toBeInTheDocument()
  })

  it('vide « données » : icône, titre et message, sans bouton', () => {
    render(<EtatVide texte="Aucun utilisateur." />)
    expect(screen.getByText('Aucune donnée pour l’instant')).toBeInTheDocument()
    expect(screen.getByRole('status').querySelector('svg')).not.toBeNull()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('vide « filtres » : titre dédié et bouton qui réinitialise (absent sans action)', async () => {
    const reinitialiser = vi.fn()
    const { rerender } = render(<EtatVide variante="filtres" onReinitialiser={reinitialiser} />)
    expect(screen.getByText('Aucun résultat pour ces filtres')).toBeInTheDocument()
    expect(screen.queryByText('Aucune donnée pour l’instant')).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Réinitialiser les filtres' }))
    expect(reinitialiser).toHaveBeenCalledTimes(1)
    rerender(<EtatVide variante="filtres" />)
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('squelette de tableau : mêmes titres de colonnes, lignes fantômes, annoncé occupé', () => {
    render(<TableauSquelette colonnes={[{ titre: 'Nom' }, { titre: 'Montant', droite: true }]} lignes={3} />)
    const zone = screen.getByRole('status', { name: 'Chargement…' })
    expect(zone).toHaveAttribute('aria-busy', 'true')
    expect(Array.from(zone.querySelectorAll('th')).map((t) => t.textContent)).toEqual(['Nom', 'Montant', ''])
    expect(zone.querySelectorAll('tbody tr')).toHaveLength(3)
    expect(zone.querySelectorAll('tbody td')).toHaveLength(9) // 2 colonnes + la colonne d'actions, trois fois
    expect(zone.querySelectorAll('.mq-sk').length).toBeGreaterThan(9)
  })

  it('cartes fantômes : une par carte attendue', () => {
    render(<CartesSquelette n={3} />)
    const zone = screen.getByRole('status', { name: 'Chargement…' })
    expect(zone).toHaveAttribute('aria-busy', 'true')
    expect(zone.children).toHaveLength(3)
  })

  it('erreur : message et bouton Réessayer', async () => {
    const reessayer = vi.fn()
    render(<EtatErreur texte="Échec." onReessayer={reessayer} />)
    expect(screen.getByRole('alert')).toHaveTextContent('Échec.')
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(reessayer).toHaveBeenCalled()
  })
})

interface Element {
  id: string
  nom: string
}
const page = (n: number, derniere: number, data: Element[]): Page<Element> => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: n, last_page: derniere, per_page: 20, total: 41 },
})

function EcranListe({ charger }: { charger: (p: Record<string, string | number>) => Promise<Page<Element>> }) {
  const etat = useFiltresUrl(['statut', 'q'], { statut: 'ouvert' })
  const liste = useListe('elements', charger, etat)
  return (
    <CarteListe
      legende="Éléments"
      etat={etat}
      liste={liste}
      colonnes={[{ titre: 'Nom' }]}
      actionsColonne="Voir"
      messageVide="Aucun élément."
      filtres={[
        { cle: 'statut', type: 'select', label: 'Statut', options: [{ valeur: 'ouvert', libelle: 'Ouverts' }, { valeur: 'tous', libelle: 'Tous' }] },
        { cle: 'q', type: 'texte', label: 'Recherche' },
      ]}
    >
      {(lignes) => lignes.map((e) => (
        <LigneLien key={e.id} vers={`/fiche/${e.id}`} libelle={`Voir le détail : ${e.nom}`}>
          <td>{e.nom}</td>
        </LigneLien>
      ))}
    </CarteListe>
  )
}

describe('liste dessinée et filtres dans l’adresse', () => {
  it('charge avec les filtres par défaut et affiche le total', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 3, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />)
    expect(await screen.findByText('Alpha')).toBeInTheDocument()
    expect(charger).toHaveBeenCalledWith({ statut: 'ouvert' })
    expect(screen.getByText(/41 résultats/)).toBeInTheDocument()
  })

  it('un filtre s’écrit dans l’adresse, revient à la page 1, et la valeur par défaut n’est pas écrite', async () => {
    const charger = vi.fn().mockResolvedValue(page(2, 3, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />, '/?page=3')
    await screen.findByText('Alpha')
    expect(charger).toHaveBeenLastCalledWith({ statut: 'ouvert', page: 2 })
    await userEvent.click(screen.getByRole('button', { name: /Filtrer par statut/ }))
    await userEvent.click(screen.getByRole('menuitemradio', { name: 'Tous' }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/?statut=tous')
    await userEvent.click(screen.getByRole('button', { name: /Filtrer par statut/ }))
    await userEvent.click(screen.getByRole('menuitemradio', { name: 'Ouverts' }))
    expect(screen.getByTestId('lieu')).toHaveTextContent(/^\/$/)
  })

  it('le champ texte s’applique à Entrée, pas à chaque frappe', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 1, []))
    enveloppe(<EcranListe charger={charger} />)
    await screen.findByText('Aucun élément.')
    await userEvent.type(screen.getByLabelText('Recherche'), 'abc')
    expect(screen.getByTestId('lieu')).toHaveTextContent(/^\/$/)
    await userEvent.type(screen.getByLabelText('Recherche'), '{Enter}')
    expect(screen.getByTestId('lieu')).toHaveTextContent('/?q=abc')
  })

  it('lit les filtres d’une adresse partagée et les réinitialise', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 1, []))
    enveloppe(<EcranListe charger={charger} />, '/?statut=tous&q=zed')
    const titre = await screen.findByText('Aucun résultat pour ces filtres')
    expect(screen.getByLabelText('Recherche')).toHaveValue('zed')
    await userEvent.click(within(titre.closest('[role="status"]') as HTMLElement).getByRole('button', { name: 'Réinitialiser les filtres' }))
    expect(screen.getByTestId('lieu')).toHaveTextContent(/^\/$/)
    expect(screen.getByLabelText('Recherche')).toHaveValue('')
  })

  it('pagination : page suivante dans l’adresse', async () => {
    const charger = vi.fn().mockResolvedValue(page(1, 3, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />)
    await screen.findByText('Alpha')
    expect(screen.getByRole('button', { name: 'Précédent' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))
    expect(screen.getByTestId('lieu')).toHaveTextContent('/?page=2')
  })

  it('affiche l’état d’erreur avec « Réessayer »', async () => {
    const charger = vi.fn().mockRejectedValueOnce(new ApiError('Erreur serveur.', 500)).mockResolvedValue(page(1, 1, [{ id: '1', nom: 'Alpha' }]))
    enveloppe(<EcranListe charger={charger} />)
    expect(await screen.findByRole('alert')).toHaveTextContent('Erreur serveur.')
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(await screen.findByText('Alpha')).toBeInTheDocument()
  })

  it('chargement : tableau fantôme à la place du vrai tableau, puis les données', async () => {
    let finir: (p: Page<Element>) => void = () => {}
    const charger = vi.fn().mockReturnValue(new Promise<Page<Element>>((r) => (finir = r)))
    enveloppe(<EcranListe charger={charger} />)
    const squelette = await screen.findByRole('status', { name: 'Chargement…' })
    expect(within(squelette).getByText('Nom')).toBeInTheDocument()
    expect(screen.queryByRole('table', { name: 'Éléments' })).not.toBeInTheDocument()
    finir(page(1, 1, [{ id: '1', nom: 'Alpha' }]))
    expect(await screen.findByText('Alpha')).toBeInTheDocument()
    expect(screen.queryByRole('status', { name: 'Chargement…' })).not.toBeInTheDocument()
  })

  it('erreur : « Réessayer » relance la requête', async () => {
    const charger = vi.fn().mockRejectedValueOnce(new ApiError('Erreur serveur.', 500)).mockResolvedValue(page(1, 1, []))
    enveloppe(<EcranListe charger={charger} />)
    await screen.findByRole('alert')
    expect(charger).toHaveBeenCalledTimes(1)
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    await screen.findByText('Aucune donnée pour l’instant')
    expect(charger).toHaveBeenCalledTimes(2)
  })

  it('affiche l’état vide', async () => {
    enveloppe(<EcranListe charger={vi.fn().mockResolvedValue({ ...page(1, 1, []), meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })} />)
    expect(await screen.findByText('Aucun élément.')).toBeInTheDocument()
  })
})

describe('LigneLien : lignes cliquables', () => {
  function afficher() {
    return render(
      <MemoryRouter initialEntries={['/liste?statut=x']}>
        <Routes>
          <Route
            path="/liste"
            element={
              <table aria-label="Liste">
                <tbody>
                  <LigneLien vers="/fiche/a-1" libelle="Voir le détail : Garage Moussa">
                    <td>Garage Moussa</td>
                  </LigneLien>
                  <LigneLien vers="/fiche/a-2" libelle="Voir le détail : Salon Awa">
                    <td>Salon Awa</td>
                  </LigneLien>
                </tbody>
              </table>
            }
          />
          <Route path="*" element={<Lieu />} />
        </Routes>
      </MemoryRouter>,
    )
  }

  it('donne à chaque ligne un vrai lien vers sa fiche', () => {
    afficher()
    expect(screen.getByRole('link', { name: 'Voir le détail : Garage Moussa' })).toHaveAttribute('href', '/fiche/a-1')
    expect(screen.getByRole('link', { name: 'Voir le détail : Salon Awa' })).toHaveAttribute('href', '/fiche/a-2')
    expect(screen.getAllByRole('link')[0]).toHaveAttribute('data-voir')
  })

  it('étire le lien sur toute la ligne (zone cliquable native, clic molette inclus)', () => {
    afficher()
    expect(screen.getByRole('row', { name: /Garage Moussa/ }).className).toContain('cursor-pointer')
    const voir = screen.getByRole('link', { name: 'Voir le détail : Garage Moussa' })
    expect(voir.className).toContain('after:absolute')
    expect(voir.className).toContain('after:inset-0')
  })

  it('le lien se focalise au clavier et s’active avec Entrée', async () => {
    afficher()
    await userEvent.tab()
    expect(screen.getByRole('link', { name: 'Voir le détail : Garage Moussa' })).toHaveFocus()
    await userEvent.keyboard('{Enter}')
    expect(await screen.findByTestId('lieu')).toHaveTextContent('/fiche/a-1')
  })

  it('navigue une seule fois au clic', async () => {
    afficher()
    await userEvent.click(screen.getByRole('link', { name: 'Voir le détail : Salon Awa' }))
    expect(await screen.findByTestId('lieu')).toHaveTextContent('/fiche/a-2')
  })
})

describe('page de détail', () => {
  function EcranDetail({ erreur }: { erreur?: ApiError }) {
    const requete = useQuery({
      queryKey: ['detail'],
      queryFn: async () => {
        if (erreur) throw erreur
        return { id: '1', nom: 'Alpha' }
      },
    })
    return (
      <PageDetailMq requete={requete} retour="/liste" libelleRetour="Retour à la liste">
        {(d) => (
          <>
            <h1>{d.nom}</h1>
            <p>Contenu {d.id}</p>
          </>
        )}
      </PageDetailMq>
    )
  }

  it('affiche le titre et le contenu', async () => {
    enveloppe(<EcranDetail />)
    expect(await screen.findByRole('heading', { name: 'Alpha' })).toBeInTheDocument()
    expect(screen.getByText('Contenu 1')).toBeInTheDocument()
  })

  it('404 : élément introuvable, sans bouton Réessayer, avec le lien de retour', async () => {
    enveloppe(<EcranDetail erreur={new ApiError('Litige introuvable.', 404)} />)
    expect(await screen.findByText('Élément introuvable')).toBeInTheDocument()
    expect(screen.getByText('Litige introuvable.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Réessayer' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Retour à la liste' })).toHaveAttribute('href', '/liste')
  })

  it('erreur : message et bouton Réessayer', async () => {
    enveloppe(<EcranDetail erreur={new ApiError('Panne.', 500)} />)
    expect(await screen.findByRole('button', { name: 'Réessayer' })).toBeInTheDocument()
  })

  it('chargement : état annoncé', () => {
    enveloppe(<EcranDetail />)
    expect(screen.getByRole('status', { name: 'Chargement…' })).toBeInTheDocument()
  })
})

describe('champs', () => {
  it('ChampSelect reste un vrai select étiqueté, modifiable', async () => {
    const onChange = vi.fn()
    render(<ChampSelect label="Rôle" options={[{ valeur: 'a', libelle: 'A' }, { valeur: 'b', libelle: 'B' }]} onChange={onChange} />)
    await userEvent.selectOptions(screen.getByLabelText('Rôle'), 'b')
    expect(onChange).toHaveBeenCalled()
  })

  it('ChampJour : champ date natif étiqueté, saisie directe possible', async () => {
    const onChange = vi.fn()
    render(<ChampJour label="Début" defaultValue="2030-03-15" onChange={onChange} />)
    const champ = screen.getByLabelText('Début')
    expect(champ).toHaveAttribute('type', 'date')
    expect(champ).toHaveValue('2030-03-15')
    await userEvent.clear(champ)
    expect(onChange).toHaveBeenCalled()
  })

  it('un champ en erreur est signalé et décrit', () => {
    render(<ChampSelect label="Rôle" erreur="Choisissez un rôle." options={[{ valeur: 'a', libelle: 'A' }]} />)
    expect(screen.getByLabelText('Rôle')).toHaveAttribute('aria-invalid', 'true')
    expect(within(screen.getByRole('alert')).getByText('Choisissez un rôle.')).toBeInTheDocument()
  })
})
