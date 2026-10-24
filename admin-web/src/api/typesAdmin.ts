// Types des écrans d'administration de l'étape 2.2 (champs en camelCase, conformes à openapi.yaml).
import type { CompteursStatutReservation, PaiementAdmin } from './types'

export type StatutCompteValeur = 'en_attente' | 'actif' | 'refuse' | 'suspendu' | 'banni'
export type TypeSanction = 'avertissement' | 'suspension_temporaire' | 'bannissement_definitif'
export type DecisionLitige = 'rejet' | TypeSanction
export type CibleSanction = 'prestataire' | 'client'

export interface DemandeListe {
  id: string
  nom: string
  prenom: string
  nomEntreprise: string
  metier: string
  quartier: string
  statutCompte: StatutCompteValeur
  dateSoumission: string
  nombreJustificatifs: number
}

export interface Justificatif {
  id: string
  typeDocument: string
  statut: string
  nomOriginal: string
  typeMime: string
  taille: number
}

export interface DemandeDetail extends Omit<DemandeListe, 'nombreJustificatifs'> {
  telephone: string
  email: string | null
  adresse: string
  motifRefus: string | null
  justificatifs: Justificatif[]
}

export interface PartieLitige {
  id: string
  nom: string
  prenom: string
  telephone: string
  email: string | null
  nomEntreprise?: string
  metier?: string
  quartier?: string
  noteMoyenne?: number | null
}

export interface PaiementLitige {
  id: string
  statut: string
  montantAcompte: number
  commission: number
  payeLe: string | null
  rembourseLe: string | null
}

export interface Litige {
  id: string
  statut: 'ouvert' | 'cloture'
  motif: string
  description: string
  dateCreation: string
  reservation: { id: string; statut: string; dateDebut: string; prestation: string }
  nombrePreuves: number
  reponsePrestataire: string | null
  reponseLe: string | null
  decision: DecisionLitige | null
  motifDecision: string | null
  clotureLe: string | null
  client: PartieLitige
  prestataire: PartieLitige
  prixApplique: number
  paiements: PaiementLitige[]
  decidePar: string | null
  sanctionId: string | null
}

export interface LitigeDetail extends Litige {
  preuves: { id: string; url: string }[]
}

export interface CorpsTrancher {
  decision: DecisionLitige
  motif: string
  cible?: CibleSanction
  dateFin?: string
}

export interface SanctionListe {
  id: string
  type: TypeSanction
  utilisateur: { id: string; prenom: string; nom: string; role: string }
  motif: string
  litigeId: string | null
  avisId: string | null
  dateCreation: string
  dateFin: string | null
  active: boolean
  leveeLe: string | null
}

export interface SanctionUtilisateur {
  id: string
  utilisateurId: string
  type: TypeSanction
  motif: string
  dateFin: string | null
  litigeId: string | null
  avisId: string | null
  posePar: string
  dateCreation: string
  active: boolean
  leveeLe: string | null
  leveePar: string | null
}

export interface CorpsSanction {
  type: TypeSanction
  motif: string
  dateFin?: string
}

export interface UtilisateurListe {
  id: string
  role: 'client' | 'prestataire'
  prenom: string
  nom: string
  email: string | null
  telephone: string
  statut: StatutCompteValeur
  dateCreation: string
  prestataire: { id: string; metier: string; quartier: string; nomEntreprise: string } | null
}

export interface UtilisateurDetail extends Omit<UtilisateurListe, 'prestataire'> {
  prestataire:
    | (NonNullable<UtilisateurListe['prestataire']> & {
        adresse: string
        solde: number
        prestationsActives: number
        reservationsParStatut: CompteursStatutReservation
        noteMoyenne: number | null
        nombreAvis: number
        nombreRetraits: number
        litiges: { total: number; ouverts: number }
      })
    | null
  sanctionActive: { id: string; type: TypeSanction; dateFin: string | null } | null
  nombreSanctions: number
}

/** Métier de la plateforme : le code est la valeur stockée chez les prestataires, il ne change jamais. */
export interface Metier {
  id: string
  code: string
  libelle: string
  actif: boolean
  nombrePrestataires: number
  dateCreation: string
}

export interface AvisAdmin {
  id: string
  note: number
  commentaire: string | null
  dateCreation: string
  reservationId: string
  client: { id: string; nom: string; prenom: string }
  prestataire: { id: string; nomEntreprise: string }
  statut: 'publie' | 'signale' | 'supprime'
  signaleLe: string | null
  motifSignalement: string | null
  modereLe: string | null
  moderePar: string | null
  motifModeration: string | null
  masque: boolean
  masqueLe: string | null
}

export interface ReservationListe {
  id: string
  statut: string
  client: { id: string; prenom: string; initialeNom: string }
  prestataire: { id: string; nomEntreprise: string }
  prestation: { id: string; nom: string }
  creneau: { id: string; debut: string; fin: string }
  prixApplique: number
  statutPaiement: string | null
  dateCreation: string
}

export interface ReservationDetail {
  id: string
  statut: string
  prestataire: { id: string; nomEntreprise: string }
  prestation: { id: string; nom: string; dureeMinutes: number }
  creneau: { id: string; debut: string; fin: string }
  prixApplique: number
  client: { id: string; prenom: string; nom: string }
  motifRefus: string | null
  paiement: Omit<PaiementAdmin, 'reservationId'> | null
  nombrePaiements: number
  avis: { id: string; note: number; statut: string; dateCreation: string } | null
  litige: { id: string; statut: string } | null
  historique: Record<string, string | null>
}

export interface PaiementDetail extends PaiementAdmin {
  reservation: { id: string; statut: string; prixApplique: number; clientId: string; prestataireId: string }
  creeLe: string
  tentativesMaximum: number
  referenceDexpay: string | null
}

export interface NotificationEchec {
  id: string
  type: string
  destinataire: { id: string; role: string | null }
  canauxEnEchec: ('email' | 'push')[]
  tentatives: number
  classeErreur: string | null
  messageErreur?: string | null
  dateCreation: string
}

export interface RetraitListe {
  id: string
  prestataire: { id: string; userId: string; nomEntreprise: string }
  montant: number
  statut: 'en_cours' | 'reussi' | 'echoue'
  bloque: boolean
  dateDemande: string
  traiteLe: string | null
  recredite: boolean
  telephoneVersement: string
}

export interface RetraitDetail extends RetraitListe {
  referenceDexpay: string | null
  recrediteLe: string | null
}

