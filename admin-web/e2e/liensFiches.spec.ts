import { expect, test } from '@playwright/test'
import { DEMANDE, ID_DOSSIER, simulerApi } from './apiSimulee'

// Parcours de bout en bout contre l'API SIMULÉE : liste -> fiche pour chaque liste, puis les décisions sur un dossier en attente.
const CAPTURES = process.env.CAPTURES
const ETAPE = process.env.ETAPE ?? 'apres'
const capturer = async (page: import('@playwright/test').Page, nom: string) => {
  if (!CAPTURES) return
  await page.waitForTimeout(700) // laisse finir l'animation d'apparition
  await page.screenshot({ path: `${CAPTURES}/${ETAPE}-${nom}.png` })
}

// Pour chaque liste : adresse, nom du lien de ligne (les listes redessinées ont leur propre libellé), texte d'une cellule
// (autre que le premier lien) et adresse de la fiche attendue.
const VOIR_DETAIL = /^Voir le détail/
const LISTES = [
  { nom: 'dossiers', adresse: '/dossiers-prestataires', lien: /^Examiner le dossier de/, cellule: 'Abdou Drame', fiche: `/dossiers-prestataires/${ID_DOSSIER}` },
  { nom: 'utilisateurs', adresse: '/utilisateurs', lien: /^Voir le profil de/, cellule: 'Awa Diallo', fiche: '/utilisateurs/u-1' },
  { nom: 'reservations', adresse: '/reservations', lien: VOIR_DETAIL, cellule: 'Vidange', fiche: '/reservations/r-1' },
  { nom: 'litiges', adresse: '/litiges', lien: /^Ouvrir le litige/, cellule: 'Vidange', fiche: '/litiges/l-1' },
  { nom: 'paiements', adresse: '/paiements', lien: VOIR_DETAIL, cellule: 'Paiement r-1'.replace('Paiement ', ''), fiche: '/paiements/pa-1' },
  { nom: 'retraits', adresse: '/retraits', lien: VOIR_DETAIL, cellule: 'Garage Exemple', fiche: '/retraits/re-1' },
  { nom: 'notifications-en-echec', adresse: '/notifications-en-echec', lien: VOIR_DETAIL, cellule: 'TransportException', fiche: '/notifications-en-echec/n-1' },
]

for (const l of LISTES) {
  test.describe(`liste ${l.nom}`, () => {
    test.beforeEach(async ({ page }) => {
      await simulerApi(page)
      await page.goto(l.adresse)
      await expect(page.getByRole('link', { name: l.lien }).first()).toBeVisible()
    })

    test('le bouton « Voir » est un vrai lien vers la fiche', async ({ page }) => {
      const voir = page.getByRole('link', { name: l.lien }).first()
      await expect(voir).toHaveAttribute('href', l.fiche)
    })

    test('un clic au milieu de la ligne ouvre la fiche', async ({ page }) => {
      await page.getByRole('row').nth(1).getByText(l.cellule, { exact: false }).first().click({ force: true })
      await expect(page).toHaveURL(new RegExp(`${l.fiche}$`))
    })

    test('le clavier ouvre la fiche (focus visible puis Entrée)', async ({ page }) => {
      const voir = page.getByRole('link', { name: l.lien }).first()
      await voir.focus()
      await expect(voir).toBeFocused()
      await expect(page.getByRole('row').nth(1)).toHaveCSS('cursor', 'pointer')
      await page.keyboard.press('Enter')
      await expect(page).toHaveURL(new RegExp(`${l.fiche}$`))
    })

    test('le clic molette sur la ligne ouvre la fiche dans un nouvel onglet', async ({ page, context }) => {
      const [onglet] = await Promise.all([
        context.waitForEvent('page'),
        page.getByRole('row').nth(1).getByText(l.cellule, { exact: false }).first().click({ button: 'middle', force: true }),
      ])
      await expect(onglet).toHaveURL(new RegExp(`${l.fiche}$`))
      await onglet.close()
    })
  })
}

// Avis et sanctions n'ont plus de ligne cliquable : les maquettes dessinent des cartes (avis) et un tableau sans lien (sanctions).
test('avis : la carte garde un lien vers la réservation concernée', async ({ page }) => {
  await simulerApi(page)
  await page.goto('/avis')
  await expect(page.getByRole('link', { name: 'Voir la réservation' }).first()).toHaveAttribute('href', '/reservations/r-1')
})

test('sanctions : le tableau reste lisible, avec « Lever » sur la sanction active et sans lien de ligne', async ({ page }) => {
  await simulerApi(page)
  await page.goto('/sanctions')
  await expect(page.getByRole('row').nth(1).getByText('Awa Diallo')).toBeVisible()
  await expect(page.getByRole('button', { name: /^Lever/ })).toBeVisible()
  await expect(page.getByRole('link', { name: /^Voir le détail/ })).toHaveCount(0)
})

test('métiers : pas de fiche, les actions restent dans la carte', async ({ page }) => {
  await simulerApi(page)
  await page.goto('/metiers')
  await expect(page.getByText('Mécanique').first()).toBeVisible()
  await expect(page.getByRole('link', { name: /^Voir le détail/ })).toHaveCount(0)
})

test.describe('fiche d’un dossier en attente', () => {
  test('liste puis fiche : boutons visibles sans défiler', async ({ page }) => {
    await simulerApi(page)
    await page.goto('/dossiers-prestataires')
    await capturer(page, 'liste')
    await page.getByRole('row').nth(1).getByText('Abdou Drame').click({ force: true })
    await expect(page).toHaveURL(new RegExp(ID_DOSSIER))
    const accepter = page.getByRole('button', { name: 'Valider le dossier' })
    const refuser = page.getByRole('button', { name: 'Refuser le dossier' })
    await expect(accepter).toBeInViewport()
    await expect(refuser).toBeInViewport()
    await capturer(page, 'fiche')
  })

  test('accepter : confirmation puis appel simulé (aucune écriture réelle)', async ({ page }) => {
    const ecritures = await simulerApi(page)
    await page.goto(`/dossiers-prestataires/${ID_DOSSIER}`)
    await page.getByRole('button', { name: 'Valider le dossier' }).click()
    expect(ecritures).toHaveLength(0) // rien n'est envoyé avant la confirmation
    await page.getByRole('dialog').getByRole('button', { name: 'Accepter le dossier' }).click()
    await expect.poll(() => ecritures.length).toBe(1)
    expect(ecritures[0]).toMatchObject({ methode: 'POST', chemin: `/admin/prestataires/${ID_DOSSIER}/accepter` })
  })

  test('refuser : motif obligatoire (message en français), puis appel simulé avec le motif', async ({ page }) => {
    const ecritures = await simulerApi(page)
    await page.goto(`/dossiers-prestataires/${ID_DOSSIER}`)
    await page.getByRole('button', { name: 'Refuser le dossier' }).click()
    const dialogue = page.getByRole('dialog')
    await dialogue.getByRole('button', { name: 'Refuser le dossier' }).click()
    await expect(dialogue.getByText('Le motif est obligatoire.')).toBeVisible()
    expect(ecritures).toHaveLength(0)
    await dialogue.getByRole('textbox').fill('court')
    await dialogue.getByRole('button', { name: 'Refuser le dossier' }).click()
    await expect(dialogue.getByText(/au moins 10 caractères/)).toBeVisible()
    expect(ecritures).toHaveLength(0)
    await dialogue.getByRole('textbox').fill('La photocopie de la CNI est illisible.')
    await dialogue.getByRole('button', { name: 'Refuser le dossier' }).click()
    await expect.poll(() => ecritures.length).toBe(1)
    expect(ecritures[0]).toMatchObject({ chemin: `/admin/prestataires/${ID_DOSSIER}/refuser`, corps: { motif: 'La photocopie de la CNI est illisible.' } })
  })

  test('dossier refusé : statut et motif à la place des boutons', async ({ page }) => {
    await simulerApi(page, {
      [`GET /admin/prestataires/${ID_DOSSIER}`]: {
        corps: { ...DEMANDE, statutCompte: 'refuse', motifRefus: 'Pièces illisibles.', telephone: '+221771234567', email: 't@example.com', adresse: 'Rue 10', justificatifs: [] },
      },
    })
    await page.goto(`/dossiers-prestataires/${ID_DOSSIER}`)
    await expect(page.getByText('Refusé', { exact: true }).first()).toBeVisible()
    await expect(page.getByText('Pièces illisibles.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Valider le dossier' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Refuser le dossier' })).toHaveCount(0)
  })
})
