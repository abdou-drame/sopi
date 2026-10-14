import { L } from '../../i18n/libelles'

export type Ton = 'succes' | 'attention' | 'erreur' | 'info' | 'neutre'

const tons: Record<Ton, string> = {
  succes: 'bg-succes-fond text-succes-texte',
  attention: 'bg-attention-fond text-attention-texte',
  erreur: 'bg-erreur-fond text-erreur-texte',
  info: 'bg-info-fond text-info-texte',
  neutre: 'bg-fond text-texte-doux border border-bordure',
}

const points: Record<Ton, string> = {
  succes: 'bg-succes',
  attention: 'bg-attention',
  erreur: 'bg-erreur',
  info: 'bg-info',
  neutre: 'bg-texte-discret',
}

/** Ton associé à chaque statut métier connu (le sens n'est jamais porté par la couleur seule : le texte est toujours affiché). */
const tonDuStatut: Record<string, Ton> = {
  en_attente: 'attention',
  acceptee: 'info',
  confirmee: 'info',
  en_cours: 'info',
  terminee: 'succes',
  refusee: 'erreur',
  annulee: 'neutre',
  actif: 'succes',
  refuse: 'erreur',
  suspendu: 'attention',
  banni: 'erreur',
  reussi: 'succes',
  echoue: 'erreur',
  rembourse: 'neutre',
  ouvert: 'attention',
  clos: 'neutre',
}

interface Props {
  statut?: string
  ton?: Ton
  libelle?: string
}

export default function BadgeStatut({ statut, ton, libelle }: Props) {
  const tonFinal = ton ?? (statut ? (tonDuStatut[statut] ?? 'neutre') : 'neutre')
  const texte = libelle ?? (statut ? (L.statuts[statut] ?? statut) : '')
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${tons[tonFinal]}`}
    >
      <span aria-hidden="true" className={`size-1.5 rounded-full ${points[tonFinal]}`} />
      {texte}
    </span>
  )
}
