// Formats d'affichage : montants en FCFA entiers, dates en français, fuseau Africa/Dakar.
export const FUSEAU = 'Africa/Dakar'

const formatEntier = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })

export function formatNombre(n: number): string {
  // Le séparateur de milliers français est une espace insécable fine : on la remplace par une espace normale.
  return formatEntier.format(n).replace(/\s/g, ' ')
}

/** 15000 → « 15 000 FCFA ». */
export function formatMontant(montant: number): string {
  return `${formatNombre(Math.round(montant))} FCFA`
}

const formatJour = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: FUSEAU })
const formatJourCourt = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', timeZone: FUSEAU })
const formatHeure = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: FUSEAU,
})

/** Instant ISO → « 20 janv. 2030 » à Dakar. */
export function formatDate(iso: string): string {
  return formatJour.format(new Date(iso))
}

/** Instant ISO → « 20 janv. 2030, 10:00 » à Dakar. */
export function formatDateHeure(iso: string): string {
  return formatHeure.format(new Date(iso)).replace(' à ', ', ')
}

/** Jour calendaire « AAAA-MM-JJ » (déjà exprimé à Dakar par l'API) → « 20 janv. 2030 ». Aucune conversion de fuseau. */
export function formatJourCalendaire(jour: string, court = false): string {
  const [a, m, j] = jour.split('-').map(Number)
  const date = new Date(Date.UTC(a, m - 1, j, 12))
  return (court ? formatJourCourt : formatJour).format(date)
}

/** Aujourd'hui à Dakar, au format AAAA-MM-JJ. */
export function aujourdhui(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: FUSEAU }).format(new Date())
}

export function ajouterJours(jour: string, n: number): string {
  const [a, m, j] = jour.split('-').map(Number)
  return new Date(Date.UTC(a, m - 1, j + n, 12)).toISOString().slice(0, 10)
}

/** Nombre de jours entre deux jours calendaires, bornes incluses. */
export function nombreDeJours(debut: string, fin: string): number {
  const ms = Date.parse(`${fin}T12:00:00Z`) - Date.parse(`${debut}T12:00:00Z`)
  return Math.round(ms / 86_400_000) + 1
}
