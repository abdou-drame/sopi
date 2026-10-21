// Types des réponses de l'API /api/v1 utilisés par le back-office (noms de champs en camelCase, conformes à openapi.yaml).

export type Role = 'client' | 'prestataire' | 'administrateur'

export interface Utilisateur {
  id: string
  nom: string
  prenom: string
  telephone: string
  email: string | null
  role: Role
  statut: string
  created_at: string | null
}

export interface ReponseConnexion {
  token: string
  user: Utilisateur
}

export type Gravite = 'info' | 'attention' | 'critique'

export type TypeAlerte =
  | 'dossiers_en_attente'
  | 'litiges_ouverts'
  | 'avis_signales'
  | 'retraits_bloques'
  | 'remboursements_en_echec'
  | 'remboursements_en_retard'
  | 'suspensions_a_echeance'
  | 'notifications_en_echec'

export interface Alerte {
  type: TypeAlerte
  gravite: Gravite
  compteur: number
  message: string
  ids: string[]
  ressource?: string
  ageMinutes: number | null
  echeanceDansMinutes: number | null
}

export interface ReponseAlertes {
  dateGeneration: string
  total: number
  parGravite: Record<Gravite, number>
  alertes: Alerte[]
}

export type CompteursStatutCompte = Record<'en_attente' | 'actif' | 'refuse' | 'suspendu' | 'banni', number>
export type CompteursStatutReservation = Record<
  'en_attente' | 'acceptee' | 'confirmee' | 'en_cours' | 'terminee' | 'refusee' | 'annulee',
  number
>

export interface TableauDeBord {
  dateGeneration: string
  comptes: {
    clients: { total: number; parStatut: CompteursStatutCompte }
    prestataires: { total: number; parStatut: CompteursStatutCompte; dossiersEnAttente: number }
    administrateurs: number
  }
  reservations: {
    total: number
    parStatut: CompteursStatutReservation
    parDomaine: Record<string, { total: number; parStatut: CompteursStatutReservation }>
    activiteRecente: {
      derniers7Jours: { creees: number; terminees: number }
      derniers30Jours: { creees: number; terminees: number }
    }
  }
  paiements: {
    total: { nombre: number; montant: number }
    parStatut: Record<'en_attente' | 'reussi' | 'echoue' | 'rembourse', { nombre: number; montant: number }>
  }
  litiges: { total: number; ouverts: number; clos: number }
  avis: { total: number; signales: number; masques: number; noteMoyenne: number | null }
  sanctions: { suspensionsEnCours: number; bannissements: number }
}

export type Regroupement = 'jour' | 'semaine' | 'mois'

export interface PointRevenus {
  debut: string
  fin: string
  revenus: number
  acomptesEncaisses: number
  remboursements: number
  retraitsVerses: number
}

export interface Revenus {
  dateGeneration: string
  periode: { debut: string; fin: string; regroupement: Regroupement; fuseau: string }
  totaux: { revenus: number; acomptesEncaisses: number; remboursements: number; retraitsVerses: number }
  soldePrestataires: number
  series: PointRevenus[]
  /** Commissions de la période par code de métier, du plus élevé au plus bas. */
  parMetier: { metier: string; revenus: number }[]
}

/** Page de résultats d'une liste (pagination Laravel : 20 éléments par page). */
export interface Page<T> {
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

/** Paiement en liste d'administration (aucune référence DexPay en liste). Montants en FCFA entiers. */
export interface PaiementAdmin {
  id: string
  reservationId: string
  statut: 'en_attente' | 'reussi' | 'echoue' | 'rembourse'
  montantAcompte: number
  commission: number
  remboursementDu: boolean
  remboursementDuLe: string | null
  tentativesRemboursement: number
  tentativesEpuisees: boolean
  payeLe: string | null
  rembourseLe: string | null
  crediteLe: string | null
}
