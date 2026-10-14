import { useEffect, useRef, useState } from 'react'
import type { KeyboardEvent } from 'react'
import { L } from '../../i18n/libelles'
import { ajouterJours, aujourdhui } from '../../lib/format'
import { IconeChevron, IconeChevronDroite } from './Icones'

interface Props {
  /** Jour sélectionné « AAAA-MM-JJ » (ou vide). */
  valeur: string
  min?: string
  max?: string
  onChoisir: (jour: string) => void
  onFermer: () => void
}

const NOMS_MOIS = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' })
const NOM_COMPLET = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'full', timeZone: 'UTC' })
const JOURS_COURTS = ['L', 'M', 'M', 'J', 'V', 'S', 'D']
const JOURS_LONGS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche']

const enDate = (jour: string) => {
  const [a, m, j] = jour.split('-').map(Number)
  return new Date(Date.UTC(a, m - 1, j, 12))
}
const enJour = (a: number, m: number, j: number) => new Date(Date.UTC(a, m, j, 12)).toISOString().slice(0, 10)

/** Calendrier mensuel accessible (grille de boutons, flèches du clavier, PageHaut/PageBas pour changer de mois). */
export default function Calendrier({ valeur, min, max, onChoisir, onFermer }: Props) {
  const reference = valeur || aujourdhui()
  const [annee, setAnnee] = useState(() => enDate(reference).getUTCFullYear())
  const [mois, setMois] = useState(() => enDate(reference).getUTCMonth())
  const [aFocaliser, setAFocaliser] = useState<string | null>(reference)
  const racine = useRef<HTMLDivElement>(null)
  const aujourdHui = aujourdhui()

  // Place le focus sur le jour demandé après le rendu du mois concerné (à l'ouverture : jour sélectionné ou d'aujourd'hui).
  useEffect(() => {
    if (!aFocaliser) return
    const cible =
      racine.current?.querySelector<HTMLElement>(`[data-jour="${aFocaliser}"]:not(:disabled)`) ??
      racine.current?.querySelector<HTMLElement>('[data-jour]:not(:disabled)')
    cible?.focus()
  }, [aFocaliser, annee, mois])

  const premier = new Date(Date.UTC(annee, mois, 1, 12))
  const decalage = (premier.getUTCDay() + 6) % 7
  const nbJours = new Date(Date.UTC(annee, mois + 1, 0, 12)).getUTCDate()
  const cases: (string | null)[] = [
    ...Array<null>(decalage).fill(null),
    ...Array.from({ length: nbJours }, (_, i) => enJour(annee, mois, i + 1)),
  ]

  const visee = aFocaliser ?? reference
  const jourTabulable = cases.includes(visee) ? visee : cases.find((c) => c !== null)

  const allerAuMois = (delta: number) => {
    const d = new Date(Date.UTC(annee, mois + delta, 1, 12))
    setAnnee(d.getUTCFullYear())
    setMois(d.getUTCMonth())
    setAFocaliser(null)
  }
  const hors = (j: string) => (min !== undefined && min !== '' && j < min) || (max !== undefined && max !== '' && j > max)

  const deplacer = (jour: string, e: KeyboardEvent) => {
    let cible: string | null = null
    if (e.key === 'ArrowLeft') cible = ajouterJours(jour, -1)
    else if (e.key === 'ArrowRight') cible = ajouterJours(jour, 1)
    else if (e.key === 'ArrowUp') cible = ajouterJours(jour, -7)
    else if (e.key === 'ArrowDown') cible = ajouterJours(jour, 7)
    else if (e.key === 'Home') cible = ajouterJours(jour, -((enDate(jour).getUTCDay() + 6) % 7))
    else if (e.key === 'End') cible = ajouterJours(jour, 6 - ((enDate(jour).getUTCDay() + 6) % 7))
    else if (e.key === 'PageUp' || e.key === 'PageDown') {
      const d = enDate(jour)
      cible = enJour(d.getUTCFullYear(), d.getUTCMonth() + (e.key === 'PageUp' ? -1 : 1), d.getUTCDate())
    }
    if (!cible) return
    e.preventDefault()
    const d = enDate(cible)
    setAnnee(d.getUTCFullYear())
    setMois(d.getUTCMonth())
    setAFocaliser(cible)
  }

  return (
    <div
      ref={racine}
      role="dialog"
      aria-label={L.calendrier.titre}
      className="animate-infobulle absolute top-full left-0 z-40 mt-2 w-72 rounded-carte bg-surface p-4 text-texte shadow-eleve"
      onKeyDown={(e) => {
        if (e.key === 'Escape') {
          e.stopPropagation()
          onFermer()
        }
      }}
    >
      <div className="mb-3 flex items-center justify-between">
        <button
          type="button"
          onClick={() => allerAuMois(-1)}
          aria-label={L.calendrier.moisPrecedent}
          className="grid size-8 place-items-center rounded-full text-texte-doux hover:bg-primaire-50 hover:text-primaire-700"
        >
          <IconeChevron width={16} height={16} />
        </button>
        <p aria-live="polite" className="text-sm font-semibold capitalize">
          {NOMS_MOIS.format(premier)}
        </p>
        <button
          type="button"
          onClick={() => allerAuMois(1)}
          aria-label={L.calendrier.moisSuivant}
          className="grid size-8 place-items-center rounded-full text-texte-doux hover:bg-primaire-50 hover:text-primaire-700"
        >
          <IconeChevronDroite width={16} height={16} />
        </button>
      </div>
      <div role="grid" aria-label={NOMS_MOIS.format(premier)} className="grid grid-cols-7 gap-y-1 text-center">
        {JOURS_COURTS.map((j, i) => (
          <span key={JOURS_LONGS[i]} role="columnheader" aria-label={JOURS_LONGS[i]} className="pb-1 text-micro font-semibold text-texte-discret uppercase">
            {j}
          </span>
        ))}
        {cases.map((jour, i) =>
          jour === null ? (
            <span key={`vide-${i}`} role="gridcell" />
          ) : (
            <span key={jour} role="gridcell" className="grid place-items-center">
              <button
                type="button"
                data-jour={jour}
                disabled={hors(jour)}
                aria-label={NOM_COMPLET.format(enDate(jour))}
                aria-pressed={jour === valeur}
                aria-current={jour === aujourdHui ? 'date' : undefined}
                tabIndex={jour === jourTabulable ? 0 : -1}
                onKeyDown={(e) => deplacer(jour, e)}
                onClick={() => onChoisir(jour)}
                className={`size-9 rounded-full text-sm transition-colors duration-[var(--duree-rapide)] disabled:cursor-not-allowed disabled:text-texte-discret/50 ${
                  jour === valeur
                    ? 'bg-primaire-600 font-semibold text-white shadow-bouton'
                    : jour === aujourdHui
                      ? 'font-semibold text-primaire-700 ring-1 ring-primaire-300 hover:bg-primaire-50'
                      : 'hover:bg-primaire-50'
                }`}
              >
                {Number(jour.slice(8))}
              </button>
            </span>
          ),
        )}
      </div>
    </div>
  )
}
