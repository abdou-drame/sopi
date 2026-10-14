import type { ReactNode } from 'react'
import { L } from '../../i18n/libelles'
import Bouton from './Bouton'
import Squelette from './Squelette'

export interface LigneCarteSopi {
  libelle: string
  valeur: ReactNode
}

interface Props {
  /** Petit libellé au-dessus du montant. */
  libelle: string
  /** Valeur principale, en grand (déjà formatée). */
  montant: ReactNode
  /** Deux lignes secondaires (libellé + valeur). */
  lignes: [LigneCarteSopi, LigneCarteSopi]
  /** Petite mention en bas à droite (ex. la période affichée). */
  note?: string
  chargement?: boolean
  erreur?: string | null
  onReessayer?: () => void
  delai?: number
}

/** Logo rond « S » (décoratif : le mot « Sopi » est écrit à côté). */
function LogoRond() {
  return (
    <span aria-hidden="true" className="grid size-9 place-items-center rounded-full bg-white text-base font-extrabold text-[#1f4f95]">
      S
    </span>
  )
}

/**
 * Carte de synthèse « Sopi » : grande carte aux angles très arrondis, dégradé bleu de la marque, cercle translucide,
 * relief et reflet. Texte blanc en tout thème. Apparition identique aux autres cartes, léger soulèvement au survol
 * (désactivé si « réduire les animations » est actif). Aucune donnée de carte bancaire : décor uniquement.
 */
export default function CarteSopi({ libelle, montant, lignes, note, chargement, erreur, onReessayer, delai = 0 }: Props) {
  return (
    <div
      className="carte-sopi animate-apparition relative isolate flex aspect-[1.586] w-full max-w-md min-w-72 flex-col justify-between overflow-hidden rounded-[2rem] p-6 text-white transition-transform duration-[var(--duree-normale)] ease-[var(--ease-sortie)] motion-safe:hover:-translate-y-1"
      style={{ animationDelay: `${delai}ms` }}
      aria-busy={chargement || undefined}
    >
      <span aria-hidden="true" className="pointer-events-none absolute -right-14 -bottom-16 -z-10 size-56 rounded-full bg-white/10" />
      <span aria-hidden="true" className="pointer-events-none absolute -top-10 right-10 -z-10 size-24 rounded-full bg-white/[0.07]" />

      <div className="flex items-center gap-2.5">
        <LogoRond />
        <span className="text-lg font-bold tracking-tight">{L.marque}</span>
      </div>

      {erreur && !chargement ? (
        <div role="alert" className="flex flex-col items-start gap-2">
          <p className="text-sm font-medium">{erreur}</p>
          {onReessayer && (
            <Bouton variante="secondaire" taille="p" onClick={onReessayer}>
              {L.commun.reessayer}
            </Bouton>
          )}
        </div>
      ) : chargement ? (
        <div role="status" aria-label={L.commun.chargement} className="flex flex-col gap-3">
          <Squelette className="h-3 w-24 bg-white/25" />
          <Squelette className="h-9 w-48 bg-white/25" />
        </div>
      ) : (
        <div>
          <p className="text-xs font-medium text-white/80">{libelle}</p>
          <p className="mt-1 text-3xl leading-tight font-bold tracking-tight break-words">{montant}</p>
        </div>
      )}

      <div className="flex items-end justify-between gap-4 text-xs">
        {lignes.map((l) => (
          <div key={l.libelle} className="min-w-0">
            <p className="text-white/75">{l.libelle}</p>
            {chargement ? <Squelette className="mt-1 h-4 w-20 bg-white/25" /> : <p className="mt-0.5 text-sm font-semibold break-words">{erreur ? '—' : l.valeur}</p>}
          </div>
        ))}
        {note && !chargement && <p className="ml-auto self-end text-right text-white/75">{note}</p>}
      </div>
    </div>
  )
}
