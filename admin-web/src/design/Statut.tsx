import { L } from '../i18n/libelles'
import { Badge, type TonBadge } from './composants'

/** Ton de pastille de chaque statut métier connu (le sens n'est jamais porté par la couleur seule : le texte est toujours affiché). */
const TON_DU_STATUT: Record<string, TonBadge> = {
  en_attente: 'attention',
  acceptee: 'info',
  confirmee: 'info',
  en_cours: 'info',
  terminee: 'succes',
  refusee: 'danger',
  annulee: 'neutre',
  actif: 'succes',
  refuse: 'danger',
  suspendu: 'attention',
  banni: 'danger',
  reussi: 'succes',
  echoue: 'danger',
  rembourse: 'neutre',
  ouvert: 'attention',
  clos: 'neutre',
  cloture: 'neutre',
  valide: 'succes',
  rejete: 'danger',
  publie: 'succes',
  signale: 'attention',
  supprime: 'neutre',
  paye: 'succes',
}

/** Couleur du point de statut des réservations : celles de Reservations.html, étendues aux statuts qu'elle ne montre pas. */
const POINT_DU_STATUT: Record<string, string> = {
  en_attente: '#f59e0b',
  acceptee: '#f59e0b',
  confirmee: 'var(--color-neutre)',
  en_cours: 'var(--color-neutre)',
  terminee: '#16a34a',
  refusee: '#e5484d',
  annulee: '#9aa0b2',
}

export const couleurPoint = (statut: string) => POINT_DU_STATUT[statut] ?? '#9aa0b2'

/** Pastille d'un statut métier (libellé de L.statuts), ou d'un état propre à l'écran (`ton` + `libelle`). */
export function BadgeDuStatut({ statut, ton, libelle, gras = true }: { statut?: string; ton?: TonBadge; libelle?: string; gras?: boolean }) {
  return (
    <Badge ton={ton ?? (statut ? (TON_DU_STATUT[statut] ?? 'neutre') : 'neutre')} gras={gras}>
      {libelle ?? (statut ? (L.statuts[statut] ?? statut) : '')}
    </Badge>
  )
}
