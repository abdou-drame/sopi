import { L } from '../../i18n/libelles'

export type Ton = 'succes' | 'attention' | 'erreur' | 'info' | 'neutre'

const tons: Record<Ton, string> = {
  succes: 'bg-succes-fond text-succes-texte',
  attention: 'bg-attention-fond text-attention-texte',
  erreur: 'bg-erreur-fond text-erreur-texte',
  info: 'bg-info-fond text-info-texte',
  neutre: 'bg-[#eceef2] text-texte-doux',
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
  cloture: 'neutre',
  valide: 'succes',
  rejete: 'erreur',
  publie: 'succes',
  signale: 'attention',
  supprime: 'neutre',
  paye: 'succes',
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
      className={`inline-flex items-center rounded-full px-3 py-[5px] text-xs font-bold ${tons[tonFinal]}`}
    >
      {texte}
    </span>
  )
}
