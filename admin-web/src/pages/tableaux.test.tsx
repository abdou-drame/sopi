import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { executerEnLot, resumeEchecs } from '../design/ActionsGroupees'
import { trierLignes } from '../design/useTri'
import { ADMIN, afficherApplication, ALERTES_VIDES, simulerApi } from '../test/outils'

// Tri sur la page affichée, sélection multiple et actions groupées (Avis, Sanctions). Les écritures sont toujours simulées.
const PAGE = (data: unknown[]) => ({
  data,
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
})
const base = { 'GET /auth/moi': { corps: ADMIN }, 'GET /admin/alertes': { corps: ALERTES_VIDES } }

const sanction = (id: string, prenom: string, nom: string, extra: object = {}) => ({
  id,
  type: 'avertissement',
  utilisateur: { id: `u-${id}`, prenom, nom, role: 'prestataire' },
  motif: 'Retards répétés',
  litigeId: null,
  avisId: null,
  dateCreation: '2030-01-10T10:00:00+00:00',
  dateFin: null,
  active: true,
  leveeLe: null,
  ...extra,
})
const S1 = sanction('s-1', 'Moussa', 'Diop')
const S2 = sanction('s-2', 'Awa', 'Ba')
const S3 = sanction('s-3', 'Zeynab', 'Sow')
const S_LEVEE = sanction('s-4', 'Omar', 'Fall', { active: false, leveeLe: '2030-01-12T10:00:00+00:00' })

const avis = (id: string, prenom: string, nom: string, extra: object = {}) => ({
  id,
  note: 2,
  commentaire: `Commentaire ${id}.`,
  dateCreation: '2030-01-17T10:00:00+00:00',
  reservationId: `r-${id}`,
  client: { id: `c-${id}`, nom, prenom },
  prestataire: { id: 'p-1', nomEntreprise: 'Garage Exemple' },
  statut: 'signale',
  signaleLe: '2030-01-18T08:00:00+00:00',
  motifSignalement: 'Avis injuste.',
  modereLe: null,
  moderePar: null,
  motifModeration: null,
  masque: false,
  masqueLe: null,
  ...extra,
})

/** Texte de la première cellule de chaque ligne de données du tableau. */
function colonne(table: HTMLElement, rang: number) {
  return within(table)
    .getAllByRole('row')
    .slice(1)
    .map((l) => within(l).getAllByRole('cell')[rang].textContent)
}

describe('tri : fonction pure', () => {
  it('trie dans les deux sens, nombres et textes, valeurs absentes toujours à la fin', () => {
    const lignes = [{ v: 10 }, { v: 2 }, { v: null }, { v: 33 }]
    expect(trierLignes(lignes, (l) => l.v, 'asc').map((l) => l.v)).toEqual([2, 10, 33, null])
    expect(trierLignes(lignes, (l) => l.v, 'desc').map((l) => l.v)).toEqual([33, 10, 2, null])
    const noms = [{ n: 'zoé' }, { n: 'Éric' }, { n: 'alice' }]
    expect(trierLignes(noms, (l) => l.n, 'asc').map((l) => l.n)).toEqual(['alice', 'Éric', 'zoé'])
  })

  it('est stable : les égalités gardent leur ordre d’origine', () => {
    const lignes = [{ k: 1, id: 'a' }, { k: 1, id: 'b' }, { k: 0, id: 'c' }]
    expect(trierLignes(lignes, (l) => l.k, 'asc').map((l) => l.id)).toEqual(['c', 'a', 'b'])
    expect(trierLignes(lignes, (l) => l.k, 'desc').map((l) => l.id)).toEqual(['a', 'b', 'c'])
  })
})

describe('actions groupées : traitement par lot', () => {
  it('continue après une erreur et résume « 2 sur 3 traités »', async () => {
    const elements = [{ id: '1', libelle: 'Awa' }, { id: '2', libelle: 'Moussa' }, { id: '3', libelle: 'Zeynab' }]
    const resultat = await executerEnLot(elements, async (id) => {
      if (id === '2') throw new Error('boom')
    })
    expect(resultat.reussis).toEqual(['1', '3'])
    expect(resultat.echecs.map((e) => e.id)).toEqual(['2'])
    expect(resumeEchecs(3, resultat)).toMatch(/^2 sur 3 traités\. 1 en échec : Moussa : /)
  })
})

describe('Sanctions : tri, sélection et levée groupée', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('trie par prestataire : croissant, décroissant, puis retour à l’ordre d’origine', async () => {
    simulerApi({ ...base, 'GET /admin/sanctions': { corps: PAGE([S1, S2, S3]) } })
    afficherApplication('/sanctions')
    const table = await screen.findByRole('table', { name: 'Liste des sanctions' })
    // La colonne 0 est la case à cocher, la colonne 1 le prestataire.
    const noms = () => colonne(table, 1).map((t) => t?.trim())
    expect(noms()).toEqual(['MDMoussa Diop', 'ABAwa Ba', 'ZSZeynab Sow'].map((t) => expect.stringContaining(t.slice(2))))
    const entete = within(table).getByRole('columnheader', { name: /Prestataire/ })
    const bouton = within(entete).getByRole('button')
    expect(bouton).toHaveAttribute('title', 'Trie la page affichée')
    expect(entete).toHaveAttribute('aria-sort', 'none')

    await userEvent.click(bouton)
    expect(entete).toHaveAttribute('aria-sort', 'ascending')
    expect(noms().map((t) => /Awa|Moussa|Zeynab/.exec(t ?? '')?.[0])).toEqual(['Awa', 'Moussa', 'Zeynab'])
    await userEvent.click(bouton)
    expect(entete).toHaveAttribute('aria-sort', 'descending')
    expect(noms().map((t) => /Awa|Moussa|Zeynab/.exec(t ?? '')?.[0])).toEqual(['Zeynab', 'Moussa', 'Awa'])
    await userEvent.click(bouton)
    expect(entete).toHaveAttribute('aria-sort', 'none')
    expect(noms().map((t) => /Awa|Moussa|Zeynab/.exec(t ?? '')?.[0])).toEqual(['Moussa', 'Awa', 'Zeynab'])
  })

  it('les colonnes sans sens (motif, actions) ne sont pas cliquables', async () => {
    simulerApi({ ...base, 'GET /admin/sanctions': { corps: PAGE([S1]) } })
    afficherApplication('/sanctions')
    const table = await screen.findByRole('table', { name: 'Liste des sanctions' })
    expect(within(within(table).getByRole('columnheader', { name: /Motif/ })).queryByRole('button')).toBeNull()
  })

  it('sélection : tout cocher, compteur, case indéterminée, tout décocher ; une sanction levée n’est pas cochable', async () => {
    simulerApi({ ...base, 'GET /admin/sanctions': { corps: PAGE([S1, S2, S_LEVEE]) } })
    afficherApplication('/sanctions?actives=')
    await screen.findByRole('table', { name: 'Liste des sanctions' })
    expect(screen.queryByRole('toolbar', { name: 'Actions groupées' })).not.toBeInTheDocument()
    const tout = screen.getByRole('checkbox', { name: 'Tout sélectionner' }) as HTMLInputElement
    expect(screen.getByRole('checkbox', { name: 'la sanction de Omar Fall' })).toBeDisabled()

    await userEvent.click(screen.getByRole('checkbox', { name: 'la sanction de Moussa Diop' }))
    const barre = await screen.findByRole('toolbar', { name: 'Actions groupées' })
    expect(within(barre).getByText('1 sélectionné')).toBeInTheDocument()
    expect(tout.indeterminate).toBe(true)

    await userEvent.click(tout)
    expect(within(barre).getByText('2 sélectionnés')).toBeInTheDocument()
    expect(tout.checked).toBe(true)
    expect(tout.indeterminate).toBe(false)
    expect(screen.getByRole('checkbox', { name: 'la sanction de Omar Fall' })).not.toBeChecked()

    await userEvent.click(tout)
    expect(screen.queryByRole('toolbar', { name: 'Actions groupées' })).not.toBeInTheDocument()
    expect(tout.checked).toBe(false)
  })

  it('« Tout désélectionner » vide la sélection', async () => {
    simulerApi({ ...base, 'GET /admin/sanctions': { corps: PAGE([S1, S2]) } })
    afficherApplication('/sanctions')
    await screen.findByRole('table', { name: 'Liste des sanctions' })
    await userEvent.click(screen.getByRole('checkbox', { name: 'Tout sélectionner' }))
    const barre = await screen.findByRole('toolbar', { name: 'Actions groupées' })
    await userEvent.click(within(barre).getByRole('button', { name: 'Tout désélectionner' }))
    expect(screen.queryByRole('toolbar', { name: 'Actions groupées' })).not.toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'la sanction de Awa Ba' })).not.toBeChecked()
  })

  it('lève les sanctions cochées après confirmation indiquant leur nombre', async () => {
    const { appels } = simulerApi({
      ...base,
      'GET /admin/sanctions': { corps: PAGE([S1, S2, S3]) },
      'POST /admin/sanctions/s-1/lever': { corps: { ...S1, active: false } },
      'POST /admin/sanctions/s-2/lever': { corps: { ...S2, active: false } },
    })
    afficherApplication('/sanctions')
    await screen.findByRole('table', { name: 'Liste des sanctions' })
    await userEvent.click(screen.getByRole('checkbox', { name: 'la sanction de Moussa Diop' }))
    await userEvent.click(screen.getByRole('checkbox', { name: 'la sanction de Awa Ba' }))
    await userEvent.click(within(await screen.findByRole('toolbar', { name: 'Actions groupées' })).getByRole('button', { name: 'Lever (2)' }))

    const modale = screen.getByRole('dialog')
    expect(within(modale).getByText('Lever les sanctions sélectionnées ?')).toBeInTheDocument()
    expect(within(modale).getByText('2 éléments concernés')).toBeInTheDocument()
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)

    await userEvent.click(within(modale).getByRole('button', { name: 'Lever' }))
    expect(await screen.findByText('2 sanctions ont été levées.')).toBeInTheDocument()
    expect(appels.filter((a) => a.methode === 'POST').map((a) => a.chemin).sort()).toEqual(['/admin/sanctions/s-1/lever', '/admin/sanctions/s-2/lever'])
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.queryByRole('toolbar', { name: 'Actions groupées' })).not.toBeInTheDocument()
  })

  it('erreur partielle : « 2 sur 3 traités », la fenêtre reste ouverte, seule la ligne en échec reste cochée et peut être relancée', async () => {
    let panne = true
    const { appels } = simulerApi({
      ...base,
      'GET /admin/sanctions': { corps: PAGE([S1, S2, S3]) },
      'POST /admin/sanctions/s-1/lever': { corps: { ...S1, active: false } },
      'POST /admin/sanctions/s-2/lever': { corps: { ...S2, active: false } },
      'POST /admin/sanctions/s-3/lever': () => (panne ? { status: 409, corps: { message: 'Cette sanction est déjà levée.' } } : { corps: { ...S3, active: false } }),
    })
    afficherApplication('/sanctions')
    await screen.findByRole('table', { name: 'Liste des sanctions' })
    await userEvent.click(screen.getByRole('checkbox', { name: 'Tout sélectionner' }))
    await userEvent.click(within(await screen.findByRole('toolbar', { name: 'Actions groupées' })).getByRole('button', { name: 'Lever (3)' }))
    const modale = screen.getByRole('dialog')
    await userEvent.click(within(modale).getByRole('button', { name: 'Lever' }))

    const alerte = await within(modale).findByRole('alert')
    expect(alerte).toHaveTextContent('2 sur 3 traités.')
    expect(alerte).toHaveTextContent('1 en échec : Zeynab Sow : Cette sanction est déjà levée.')
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(within(modale).getByText('1 élément concerné')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'la sanction de Zeynab Sow' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'la sanction de Moussa Diop' })).not.toBeChecked()
    expect(within(screen.getByRole('toolbar', { name: 'Actions groupées' })).getByText('1 sélectionné')).toBeInTheDocument()

    panne = false
    const avant = appels.filter((a) => a.methode === 'POST').length
    await userEvent.click(within(modale).getByRole('button', { name: 'Réessayer (1)' }))
    expect(await screen.findByText('1 sanction a été levée.')).toBeInTheDocument()
    // Seule la ligne en échec est relancée, pas les deux déjà levées.
    expect(appels.filter((a) => a.methode === 'POST')).toHaveLength(avant + 1)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('échec total : 0 sur 2 traité, rien n’est décoché', async () => {
    simulerApi({
      ...base,
      'GET /admin/sanctions': { corps: PAGE([S1, S2]) },
      'POST /admin/sanctions/s-1/lever': { status: 409, corps: { message: 'Déjà levée.' } },
      'POST /admin/sanctions/s-2/lever': { status: 409, corps: { message: 'Déjà levée.' } },
    })
    afficherApplication('/sanctions')
    await screen.findByRole('table', { name: 'Liste des sanctions' })
    await userEvent.click(screen.getByRole('checkbox', { name: 'Tout sélectionner' }))
    await userEvent.click(within(await screen.findByRole('toolbar', { name: 'Actions groupées' })).getByRole('button', { name: 'Lever (2)' }))
    const modale = screen.getByRole('dialog')
    await userEvent.click(within(modale).getByRole('button', { name: 'Lever' }))
    expect(await within(modale).findByRole('alert')).toHaveTextContent('0 sur 2 traité.')
    expect(within(screen.getByRole('toolbar', { name: 'Actions groupées' })).getByText('2 sélectionnés')).toBeInTheDocument()
  })

  it('annuler ne lance aucun appel et garde la sélection', async () => {
    const { appels } = simulerApi({ ...base, 'GET /admin/sanctions': { corps: PAGE([S1, S2]) } })
    afficherApplication('/sanctions')
    await screen.findByRole('table', { name: 'Liste des sanctions' })
    await userEvent.click(screen.getByRole('checkbox', { name: 'Tout sélectionner' }))
    await userEvent.click(within(await screen.findByRole('toolbar', { name: 'Actions groupées' })).getByRole('button', { name: 'Lever (2)' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Annuler' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)
    expect(screen.getByRole('toolbar', { name: 'Actions groupées' })).toBeInTheDocument()
  })
})

describe('Avis : masquer et republier en groupe', () => {
  beforeEach(() => sessionStorage.setItem('sopi.admin.jeton', 'jeton-factice-pour-test'))
  afterEach(() => sessionStorage.clear())

  it('masque les avis cochés : motif obligatoire, un appel par avis avec le même motif', async () => {
    const A1 = avis('a-1', 'Awa', 'Diallo')
    const A2 = avis('a-2', 'Omar', 'Ndiaye')
    const { appels } = simulerApi({
      ...base,
      'GET /admin/avis': { corps: PAGE([A1, A2]) },
      'POST /admin/avis/a-1/masquer': { corps: { ...A1, masque: true } },
      'POST /admin/avis/a-2/masquer': { corps: { ...A2, masque: true } },
    })
    afficherApplication('/avis')
    await screen.findByRole('table', { name: 'Liste des avis' })
    await userEvent.click(screen.getByRole('checkbox', { name: 'Tout sélectionner' }))
    const barre = await screen.findByRole('toolbar', { name: 'Actions groupées' })
    expect(within(barre).getByRole('button', { name: 'Republier (0)' })).toBeDisabled()
    await userEvent.click(within(barre).getByRole('button', { name: 'Masquer (2)' }))

    const modale = screen.getByRole('dialog')
    expect(within(modale).getByText('2 éléments concernés')).toBeInTheDocument()
    await userEvent.click(within(modale).getByRole('button', { name: 'Masquer' }))
    expect(within(modale).getByText('Le motif est obligatoire.')).toBeInTheDocument()
    expect(appels.some((a) => a.methode === 'POST')).toBe(false)

    await userEvent.type(within(modale).getByLabelText(/Motif de la modération/), 'Propos injurieux répétés')
    await userEvent.click(within(modale).getByRole('button', { name: 'Masquer' }))
    expect(await screen.findByText('2 avis ont été masqués.')).toBeInTheDocument()
    const posts = appels.filter((a) => a.methode === 'POST')
    expect(posts).toHaveLength(2)
    expect(posts.every((p) => JSON.stringify(p.corps) === JSON.stringify({ motif: 'Propos injurieux répétés' }))).toBe(true)
  })

  it('une sélection mixte propose Masquer pour les publiés et Republier pour les masqués', async () => {
    const PUBLIE = avis('a-1', 'Awa', 'Diallo')
    const MASQUE = avis('a-2', 'Omar', 'Ndiaye', { statut: 'supprime', masque: true, masqueLe: '2030-01-19T09:00:00+00:00' })
    const { appels } = simulerApi({
      ...base,
      'GET /admin/avis': { corps: PAGE([PUBLIE, MASQUE]) },
      'POST /admin/avis/a-2/retablir': { corps: { ...MASQUE, masque: false } },
    })
    afficherApplication('/avis')
    await screen.findByRole('table', { name: 'Liste des avis' })
    await userEvent.click(screen.getByRole('checkbox', { name: 'Tout sélectionner' }))
    const barre = await screen.findByRole('toolbar', { name: 'Actions groupées' })
    expect(within(barre).getByRole('button', { name: 'Masquer (1)' })).toBeEnabled()
    await userEvent.click(within(barre).getByRole('button', { name: 'Republier (1)' }))
    const modale = screen.getByRole('dialog')
    expect(within(modale).getByText('1 élément concerné')).toBeInTheDocument()
    await userEvent.click(within(modale).getByRole('button', { name: 'Republier' }))
    expect(await screen.findByText('1 avis a été republié.')).toBeInTheDocument()
    expect(appels.filter((a) => a.methode === 'POST').map((a) => a.chemin)).toEqual(['/admin/avis/a-2/retablir'])
  })

  it('trie par note dans les deux sens', async () => {
    simulerApi({ ...base, 'GET /admin/avis': { corps: PAGE([avis('a-1', 'Awa', 'Diallo', { note: 4 }), avis('a-2', 'Omar', 'Ndiaye', { note: 1 }), avis('a-3', 'Bintou', 'Ka', { note: 3 })]) } })
    afficherApplication('/avis')
    const table = await screen.findByRole('table', { name: 'Liste des avis' })
    const prenoms = () => within(table).getAllByRole('row').slice(1).map((l) => /Awa|Omar|Bintou/.exec(l.textContent ?? '')?.[0])
    const bouton = within(within(table).getByRole('columnheader', { name: /Note/ })).getByRole('button')
    await userEvent.click(bouton)
    expect(prenoms()).toEqual(['Omar', 'Bintou', 'Awa'])
    await userEvent.click(bouton)
    expect(prenoms()).toEqual(['Awa', 'Bintou', 'Omar'])
  })
})
