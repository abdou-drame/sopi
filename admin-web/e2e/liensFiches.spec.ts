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

// Pour chaque liste : adresse, texte d'une cellule (autre que le premier lien) et adresse de la fiche attendue.
const LISTES = [
  { nom: 'dossiers', adresse: '/dossiers-prestataires', cellule: 'TEST E2E Email Depot', fiche: `/dossiers-prestataires/${ID_DOSSIER}` },
  { nom: 'utilisateurs', adresse: '/utilisateurs', cellule: 'Awa Diallo', fiche: '/utilisateurs/u-1' },
  { nom: 'reservations', adresse: '/reservations', cellule: 'Vidange', fiche: '/reservations/r-1' },
  { nom: 'litiges', adresse: '/litiges', cellule: 'Service non réalisé', fiche: '/litiges/l-1' },
  { nom: 'paiements', adresse: '/paiements', cellule: 'Paiement r-1'.replace('Paiement ', ''), fiche: '/paiements/pa-1' },
  { nom: 'retraits', adresse: '/retraits', cellule: 'Garage Exemple', fiche: '/retraits/re-1' },
  { nom: 'sanctions', adresse: '/sanctions', cellule: 'Awa Diallo', fiche: '/utilisateurs/u-1' },
  { nom: 'notifications-en-echec', adresse: '/notifications-en-echec', cellule: 'TransportException', fiche: '/notifications-en-echec/n-1' },
]

for (const l of LISTES) {
  test.describe(`liste ${l.nom}`, () => {
    test.beforeEach(async ({ page }) => {
      await simulerApi(page)
      await page.goto(l.adresse)
      await expect(page.getByRole('link', { name: /^Voir le détail/ }).first()).toBeVisible()
    })

    test('le bouton « Voir » est un vrai lien vers la fiche', async ({ page }) => {
      const voir = page.getByRole('link', { name: /^Voir le détail/ }).first()
      await expect(voir).toHaveAttribute('href', l.fiche)
    })

    test('un clic au milieu de la ligne ouvre la fiche', async ({ page }) => {
      await page.getByRole('row').nth(1).getByText(l.cellule, { exact: false }).first().click({ force: true })
      await expect(page).toHaveURL(new RegExp(`${l.fiche}$`))
    })

    test('le clavier ouvre la fiche (focus visible puis Entrée)', async ({ page }) => {
      const voir = page.getByRole('link', { name: /^Voir le détail/ }).first()
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

test('avis : cartes (pas de ligne ni de bouton « Voir »), lien vers la réservation', async ({ page }) => {
  await simulerApi(page)
  await page.goto('/avis')
  await expect(page.getByText('Retard important')).toBeVisible()
  await expect(page.getByRole('link', { name: /^Voir le détail/ })).toHaveCount(0)
  const lien = page.getByRole('link', { name: 'Voir la réservation' }).first()
  await expect(lien).toHaveAttribute('href', '/reservations/r-1')
  await lien.click()
  await expect(page).toHaveURL(/\/reservations\/r-1$/)
})

test('métiers : pas de fiche, les actions restent dans la ligne', async ({ page }) => {
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
    await page.getByRole('row').nth(1).getByText('TEST E2E Email Depot').click({ force: true })
    await expect(page).toHaveURL(new RegExp(ID_DOSSIER))
    const accepter = page.getByRole('button', { name: 'Accepter le dossier' })
    const refuser = page.getByRole('button', { name: 'Refuser le dossier' })
    await expect(accepter).toBeInViewport()
    await expect(refuser).toBeInViewport()
    await capturer(page, 'fiche')
  })

  test('accepter : confirmation puis appel simulé (aucune écriture réelle)', async ({ page }) => {
    const ecritures = await simulerApi(page)
    await page.goto(`/dossiers-prestataires/${ID_DOSSIER}`)
    await page.getByRole('button', { name: 'Accepter le dossier' }).click()
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
    await expect(page.getByText('Ce dossier a été refusé.')).toBeVisible()
    await expect(page.getByText('Pièces illisibles.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Accepter le dossier' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Refuser le dossier' })).toHaveCount(0)
  })
})
