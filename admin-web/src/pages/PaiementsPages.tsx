import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { PaiementAdmin } from '../api/types'
import CarteListe from '../design/CarteListe'
import PageDetailMq from '../design/PageDetailMq'
import { BadgeDuStatut } from '../design/Statut'
import { Badge, Carte, GrilleTuiles, LienTexte, LigneLien, TitreCarte, TitreDetail, TitrePage, type TonBadge } from '../design/composants'
import { useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatDateHeure, formatMontant } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.paiements
const ABSENT = <span className="text-[#9aa0b2]">{T.aucun}</span>

const OPTIONS_STATUT = [
  { valeur: '', libelle: L.commun.tous },
  ...(['en_attente', 'reussi', 'echoue', 'rembourse'] as const).map((valeur) => ({ valeur, libelle: L.statuts[valeur] })),
]
const OPTIONS_REMBOURSEMENT = [
  { valeur: '', libelle: T.toutes },
  { valeur: 'true', libelle: T.remboursementsDus },
]

/** État du remboursement d'un paiement : à faire, tentatives épuisées, fait, ou rien. */
function etatRemboursement(p: PaiementAdmin): { ton: TonBadge; libelle: string } | null {
  if (p.remboursementDu && p.tentativesEpuisees) return { ton: 'danger', libelle: T.remboursementEpuise }
  if (p.remboursementDu) return { ton: 'attention', libelle: T.remboursementAttendu }
  if (p.rembourseLe) return { ton: 'succes', libelle: T.remboursementFait }
  return null
}

export function PaiementsListePage() {
  const etat = useFiltresUrl(['statut', 'remboursementDu', 'retardRemboursementMinutes', 'reservation'])
  const liste = useListe('paiements', api.paiements, etat)
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Paiements" suite="des acomptes" sousTitre={T.sousTitre} />
      <CarteListe
        legende={T.legende}
        etat={etat}
        liste={liste}
        messageVide={T.vide}
        actionsColonne={L.commun.voir}
        largeurMin={900}
        filtres={[
          { cle: 'reservation', type: 'recherche', label: T.reservation },
          { cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT },
          { cle: 'remboursementDu', type: 'select', label: T.remboursementDu, options: OPTIONS_REMBOURSEMENT },
          { cle: 'retardRemboursementMinutes', type: 'nombre', label: T.retard },
        ]}
        colonnes={[{ titre: T.colReservation }, { titre: T.colPaye }, { titre: T.colAcompte, droite: true }, { titre: T.colCommission, droite: true }, { titre: T.colRemboursement }, { titre: L.commun.statut }]}
      >
        {liste.lignes.map((p: PaiementAdmin) => {
          const e = etatRemboursement(p)
          return (
            <LigneLien key={p.id} vers={`/paiements/${p.id}`} libelle={`Voir le détail du paiement ${p.reservationId.slice(0, 8)}`}>
              <td className="font-semibold">{`${p.reservationId.slice(0, 8)}…`}</td>
              <td>{p.payeLe ? formatDateHeure(p.payeLe) : ABSENT}</td>
              <td style={{ textAlign: 'right' }}>{formatMontant(p.montantAcompte)}</td>
              <td style={{ textAlign: 'right' }}>{formatMontant(p.commission)}</td>
              <td>{e ? <Badge ton={e.ton}>{e.libelle}</Badge> : ABSENT}</td>
              <td>
                <BadgeDuStatut statut={p.statut} />
              </td>
            </LigneLien>
          )
        })}
      </CarteListe>
    </div>
  )
}

export function PaiementDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['paiement', id], queryFn: () => api.paiement(id) })
  return (
    <PageDetailMq requete={requete} retour="/paiements" libelleRetour={T.retour}>
      {(p) => {
        const e = etatRemboursement(p)
        return (
          <div className="flex flex-col gap-4">
            <TitreDetail retour="/paiements" libelleRetour={T.retour} titre={T.detailTitre} badge={<BadgeDuStatut statut={p.statut} />} infos={formatMontant(p.montantAcompte)} />
            <div className="grid gap-4 lg:grid-cols-2">
              <Carte>
                <TitreCarte titre={T.detailTitre} />
                <GrilleTuiles
                  elements={[
                    { libelle: T.acompte, valeur: formatMontant(p.montantAcompte) },
                    { libelle: T.commission, valeur: formatMontant(p.commission) },
                    { libelle: T.credit, valeur: formatMontant(p.montantAcompte - p.commission) },
                    { libelle: T.creeLe, valeur: formatDateHeure(p.creeLe) },
                    { libelle: T.payeLe, valeur: p.payeLe ? formatDateHeure(p.payeLe) : null },
                    { libelle: T.crediteLe, valeur: p.crediteLe ? formatDateHeure(p.crediteLe) : null },
                    // La référence DexPay n'est montrée qu'ici, jamais en liste.
                    { libelle: T.referenceDexpay, valeur: p.referenceDexpay, large: true },
                  ]}
                />
              </Carte>
              <Carte>
                <TitreCarte titre={T.colRemboursement} />
                <GrilleTuiles
                  elements={[
                    { libelle: T.colRemboursement, valeur: e ? <Badge ton={e.ton}>{e.libelle}</Badge> : T.aucun },
                    { libelle: T.remboursementDuLe, valeur: p.remboursementDuLe ? formatDateHeure(p.remboursementDuLe) : null },
                    { libelle: T.tentatives, valeur: T.tentativesTexte(p.tentativesRemboursement, p.tentativesMaximum) },
                    { libelle: T.rembourseLe, valeur: p.rembourseLe ? formatDateHeure(p.rembourseLe) : null },
                  ]}
                />
              </Carte>
            </div>
            <Carte>
              <TitreCarte titre={T.reservationTitre} />
              <GrilleTuiles
                elements={[
                  { libelle: T.statutReservation, valeur: <BadgeDuStatut statut={p.reservation.statut} /> },
                  { libelle: T.prixReservation, valeur: formatMontant(p.reservation.prixApplique) },
                  { libelle: T.voirReservation, valeur: <LienTexte vers={`/reservations/${p.reservation.id}`}>{T.voirReservation}</LienTexte> },
                  { libelle: T.voirClient, valeur: <LienTexte vers={`/utilisateurs/${p.reservation.clientId}`}>{T.voirClient}</LienTexte> },
                ]}
              />
            </Carte>
          </div>
        )
      }}
    </PageDetailMq>
  )
}
