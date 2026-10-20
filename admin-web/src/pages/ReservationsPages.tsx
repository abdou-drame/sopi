import { useQuery } from '@tanstack/react-query'
import { api } from '../api/endpoints'
import type { ReservationListe } from '../api/typesAdmin'
import CarteListe from '../design/CarteListe'
import PageDetailMq from '../design/PageDetailMq'
import { BadgeDuStatut, couleurPoint } from '../design/Statut'
import { Avatar, Carte, GrilleTuiles, LienTexte, LigneLien, PointStatut, TitreCarte, TitreDetail, TitrePage } from '../design/composants'
import { useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatDate, formatDateHeure, formatHeureDuJour, formatMontant } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'
import { useParams } from 'react-router-dom'

const T = L.reservations

const OPTIONS_STATUT = [
  { valeur: '', libelle: L.commun.tous },
  ...(['en_attente', 'acceptee', 'confirmee', 'en_cours', 'terminee', 'refusee', 'annulee'] as const).map((valeur) => ({ valeur, libelle: L.statuts[valeur] })),
]

const texteCarte = 'm-0 mt-3.5 text-sm leading-[normal] text-[#8a90a2]'

export function ReservationsListePage() {
  const etat = useFiltresUrl(['statut', 'prestataire', 'client', 'q'])
  const liste = useListe('reservations', api.reservations, etat)
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Réservations" suite="de la plateforme" sousTitre={T.sousTitre} />
      <CarteListe
        legende={T.legende}
        etat={etat}
        liste={liste}
        messageVide={T.vide}
        actionsColonne={L.commun.voir}
        largeurMin={900}
        filtres={[
          { cle: 'q', type: 'recherche', label: T.recherche, placeholder: T.recherchePlaceholder },
          { cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT },
          { cle: 'prestataire', type: 'texte', label: T.prestataire },
          { cle: 'client', type: 'texte', label: T.client },
        ]}
        colonnes={[{ titre: 'Réservation' }, { titre: 'Date' }, { titre: 'Heure' }, { titre: T.colPrix, droite: true }, { titre: T.colPaiement }, { titre: L.commun.statut }]}
      >
        {liste.lignes.map((r: ReservationListe) => (
          <LigneLien key={r.id} vers={`/reservations/${r.id}`} libelle={`Voir le détail de la réservation ${r.prestation.nom}`}>
            <td>
              <div className="flex items-center gap-3">
                <Avatar nom={r.prestataire.nomEntreprise} taille={36} />
                <span>
                  <span className="block font-semibold">{r.prestation.nom}</span>
                  {/* Liste : prénom et initiale seulement ; le nom complet est réservé au détail. */}
                  <span className="block text-[11px] text-[#9aa0b2]">
                    {r.prestataire.nomEntreprise} · {r.client.prenom} {r.client.initialeNom}
                  </span>
                </span>
              </div>
            </td>
            <td>{formatDate(r.creneau.debut)}</td>
            <td>{formatHeureDuJour(r.creneau.debut)}</td>
            <td style={{ textAlign: 'right' }}>{formatMontant(r.prixApplique)}</td>
            <td>{r.statutPaiement ? <BadgeDuStatut statut={r.statutPaiement} /> : <span className="text-[#9aa0b2]">{T.sansPaiement}</span>}</td>
            <td>
              <PointStatut couleur={couleurPoint(r.statut)}>{L.statuts[r.statut] ?? r.statut}</PointStatut>
            </td>
          </LigneLien>
        ))}
      </CarteListe>
    </div>
  )
}

export function ReservationDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['reservation', id], queryFn: () => api.reservation(id) })
  return (
    <PageDetailMq requete={requete} retour="/reservations" libelleRetour={T.retour}>
      {(r) => (
        <div className="flex flex-col gap-4">
          <TitreDetail
            retour="/reservations"
            libelleRetour={T.retour}
            titre={r.prestation.nom}
            badge={<BadgeDuStatut statut={r.statut} />}
            infos={`${r.prestataire.nomEntreprise} · ${formatDateHeure(r.creneau.debut)}`}
          />
          <div className="grid gap-4 lg:grid-cols-2">
            <Carte>
              <TitreCarte titre={T.infos} />
              <GrilleTuiles
                elements={[
                  { libelle: T.prestation, valeur: r.prestation.nom },
                  { libelle: T.duree, valeur: T.dureeMinutes(r.prestation.dureeMinutes) },
                  { libelle: T.creneau, valeur: `${formatDateHeure(r.creneau.debut)} → ${formatDateHeure(r.creneau.fin)}`, large: true },
                  { libelle: T.prix, valeur: formatMontant(r.prixApplique) },
                  ...(r.motifRefus ? [{ libelle: T.motifRefus, valeur: r.motifRefus, large: true }] : []),
                ]}
              />
            </Carte>
            <Carte>
              <TitreCarte titre={T.partiesTitre} />
              <GrilleTuiles
                elements={[
                  { libelle: T.clientNom, valeur: <LienTexte vers={`/utilisateurs/${r.client.id}`}>{`${r.client.prenom} ${r.client.nom}`}</LienTexte> },
                  { libelle: T.colPrestataire, valeur: r.prestataire.nomEntreprise },
                ]}
              />
            </Carte>
          </div>

          <div className="grid gap-4 lg:grid-cols-3">
            <Carte>
              <TitreCarte titre={T.paiement} />
              {r.paiement ? (
                <div className="mt-3.5 flex flex-col gap-2.5 text-sm">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-semibold">{formatMontant(r.paiement.montantAcompte)}</span>
                    <BadgeDuStatut statut={r.paiement.statut} />
                  </div>
                  {r.nombrePaiements > 1 && <p className="m-0 text-xs text-[#8a90a2]">{T.plusieursPaiements(r.nombrePaiements)}</p>}
                  <LienTexte vers={`/paiements/${r.paiement.id}`}>{T.voirPaiement}</LienTexte>
                </div>
              ) : (
                <p className={texteCarte}>{T.aucunPaiement}</p>
              )}
            </Carte>
            <Carte>
              <TitreCarte titre={T.avis} />
              {r.avis ? (
                <div className="mt-3.5 flex flex-col gap-2.5 text-sm">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-semibold">{T.avisTexte(r.avis.note)}</span>
                    <BadgeDuStatut statut={r.avis.statut} />
                  </div>
                  <LienTexte vers="/avis?vue=tous">{T.voirAvis}</LienTexte>
                </div>
              ) : (
                <p className={texteCarte}>{T.aucunAvis}</p>
              )}
            </Carte>
            <Carte>
              <TitreCarte titre={T.litige} />
              {r.litige ? (
                <div className="mt-3.5 flex flex-col items-start gap-2.5 text-sm">
                  <BadgeDuStatut statut={r.litige.statut} />
                  <LienTexte vers={`/litiges/${r.litige.id}`}>{T.voirLitige}</LienTexte>
                </div>
              ) : (
                <p className={texteCarte}>{T.aucunLitige}</p>
              )}
            </Carte>
          </div>

          <Carte>
            <TitreCarte titre={T.historique} />
            <GrilleTuiles
              elements={Object.entries(r.historique)
                .filter(([, valeur]) => valeur)
                .map(([cle, valeur]) => ({ libelle: T.jalons[cle] ?? cle, valeur: formatDateHeure(valeur as string) }))}
            />
          </Carte>
        </div>
      )}
    </PageDetailMq>
  )
}
