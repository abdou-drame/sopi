import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { PaiementAdmin } from '../api/types'
import BadgeStatut from '../components/ui/BadgeStatut'
import Carte from '../components/ui/Carte'
import Definitions from '../components/ui/Definitions'
import EnTetePage from '../components/ui/EnTetePage'
import FiltresListe from '../components/ui/FiltresListe'
import LienLigne from '../components/ui/LienLigne'
import ListePaginee from '../components/ui/ListePaginee'
import PageDetail from '../components/ui/PageDetail'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { formatDateHeure, formatMontant } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.paiements
const lien = 'font-semibold text-primaire-700 hover:underline'

const OPTIONS_STATUT = [
  { valeur: '', libelle: L.commun.tous },
  ...(['en_attente', 'reussi', 'echoue', 'rembourse'] as const).map((valeur) => ({ valeur, libelle: L.statuts[valeur] })),
]
const OPTIONS_REMBOURSEMENT = [
  { valeur: '', libelle: T.toutes },
  { valeur: 'true', libelle: T.remboursementsDus },
]

/** État du remboursement d'un paiement : à faire, tentatives épuisées, fait, ou rien. */
function etatRemboursement(p: PaiementAdmin): { ton: 'attention' | 'erreur' | 'succes'; libelle: string } | null {
  if (p.remboursementDu && p.tentativesEpuisees) return { ton: 'erreur', libelle: T.remboursementEpuise }
  if (p.remboursementDu) return { ton: 'attention', libelle: T.remboursementAttendu }
  if (p.rembourseLe) return { ton: 'succes', libelle: T.remboursementFait }
  return null
}

export function PaiementsListePage() {
  const etat = useFiltresUrl(['statut', 'remboursementDu', 'retardRemboursementMinutes', 'reservation'])
  const colonnes: Colonne<PaiementAdmin>[] = [
    {
      cle: 'reservation',
      titre: T.colReservation,
      rendu: (p) => <LienLigne vers={`/paiements/${p.id}`}>{`${p.reservationId.slice(0, 8)}…`}</LienLigne>,
    },
    { cle: 'acompte', titre: T.colAcompte, alignement: 'droite', rendu: (p) => formatMontant(p.montantAcompte) },
    { cle: 'commission', titre: T.colCommission, alignement: 'droite', rendu: (p) => formatMontant(p.commission) },
    { cle: 'paye', titre: T.colPaye, rendu: (p) => (p.payeLe ? formatDateHeure(p.payeLe) : T.aucun) },
    {
      cle: 'remboursement',
      titre: T.colRemboursement,
      rendu: (p) => {
        const e = etatRemboursement(p)
        return e ? <BadgeStatut ton={e.ton} libelle={e.libelle} /> : T.aucun
      },
    },
    { cle: 'statut', titre: L.commun.statut, rendu: (p) => <BadgeStatut statut={p.statut} /> },
  ]
  return (
    <div className="flex flex-col gap-3.5">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[
          { cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT },
          { cle: 'remboursementDu', type: 'select', label: T.remboursementDu, options: OPTIONS_REMBOURSEMENT },
          { cle: 'retardRemboursementMinutes', type: 'nombre', label: T.retard },
          { cle: 'reservation', type: 'texte', label: T.reservation },
        ]}
      />
      <ListePaginee legende={T.legende} cle="paiements" charger={api.paiements} etat={etat} colonnes={colonnes} cleLigne={(p) => p.id} lien={(p) => ({ vers: `/paiements/${p.id}`, nom: `Paiement ${p.reservationId.slice(0, 8)}` })} messageVide={T.vide} />
    </div>
  )
}

export function PaiementDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['paiement', id], queryFn: () => api.paiement(id) })
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/paiements', libelle: T.retour }}
      titre={() => T.detailTitre}
      sousTitre={(p) => formatMontant(p.montantAcompte)}
      badge={(p) => <BadgeStatut statut={p.statut} />}
    >
      {(p) => {
        const e = etatRemboursement(p)
        return (
          <>
            <div className="grid gap-5 lg:grid-cols-2">
              <Carte titre={T.detailTitre}>
                <Definitions
                  elements={[
                    { libelle: T.acompte, valeur: formatMontant(p.montantAcompte) },
                    { libelle: T.commission, valeur: formatMontant(p.commission) },
                    { libelle: T.credit, valeur: formatMontant(p.montantAcompte - p.commission) },
                    { libelle: T.creeLe, valeur: formatDateHeure(p.creeLe) },
                    { libelle: T.payeLe, valeur: p.payeLe ? formatDateHeure(p.payeLe) : null },
                    { libelle: T.crediteLe, valeur: p.crediteLe ? formatDateHeure(p.crediteLe) : null },
                    // La référence DexPay n'est montrée qu'ici, jamais en liste.
                    { libelle: T.referenceDexpay, valeur: p.referenceDexpay },
                  ]}
                />
              </Carte>
              <Carte titre={T.colRemboursement}>
                <Definitions
                  elements={[
                    { libelle: T.colRemboursement, valeur: e ? <BadgeStatut ton={e.ton} libelle={e.libelle} /> : T.aucun },
                    { libelle: T.remboursementDuLe, valeur: p.remboursementDuLe ? formatDateHeure(p.remboursementDuLe) : null },
                    { libelle: T.tentatives, valeur: T.tentativesTexte(p.tentativesRemboursement, p.tentativesMaximum) },
                    { libelle: T.rembourseLe, valeur: p.rembourseLe ? formatDateHeure(p.rembourseLe) : null },
                  ]}
                />
              </Carte>
            </div>
            <Carte titre={T.reservationTitre}>
              <Definitions
                elements={[
                  { libelle: T.statutReservation, valeur: <BadgeStatut statut={p.reservation.statut} /> },
                  { libelle: T.prixReservation, valeur: formatMontant(p.reservation.prixApplique) },
                  {
                    libelle: T.voirReservation,
                    valeur: (
                      <Link to={`/reservations/${p.reservation.id}`} className={lien}>
                        {T.voirReservation}
                      </Link>
                    ),
                  },
                  {
                    libelle: T.voirClient,
                    valeur: (
                      <Link to={`/utilisateurs/${p.reservation.clientId}`} className={lien}>
                        {T.voirClient}
                      </Link>
                    ),
                  },
                ]}
              />
            </Carte>
          </>
        )
      }}
    </PageDetail>
  )
}
