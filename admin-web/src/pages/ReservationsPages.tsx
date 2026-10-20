import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { ReservationListe } from '../api/typesAdmin'
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

const T = L.reservations

const OPTIONS_STATUT = [
  { valeur: '', libelle: L.commun.tous },
  ...(['en_attente', 'acceptee', 'confirmee', 'en_cours', 'terminee', 'refusee', 'annulee'] as const).map((valeur) => ({ valeur, libelle: L.statuts[valeur] })),
]

const lien = 'font-semibold text-primaire-700 hover:underline'

export function ReservationsListePage() {
  const etat = useFiltresUrl(['statut', 'prestataire', 'client', 'q'])
  const colonnes: Colonne<ReservationListe>[] = [
    {
      cle: 'prestation',
      titre: T.colPrestation,
      rendu: (r) => <LienLigne vers={`/reservations/${r.id}`}>{r.prestation.nom}</LienLigne>,
    },
    // Liste : prénom et initiale seulement ; le nom complet est réservé au détail.
    { cle: 'client', titre: T.colClient, rendu: (r) => `${r.client.prenom} ${r.client.initialeNom}` },
    { cle: 'prestataire', titre: T.colPrestataire, rendu: (r) => r.prestataire.nomEntreprise },
    { cle: 'creneau', titre: T.colCreneau, rendu: (r) => formatDateHeure(r.creneau.debut) },
    { cle: 'prix', titre: T.colPrix, alignement: 'droite', rendu: (r) => formatMontant(r.prixApplique) },
    { cle: 'paiement', titre: T.colPaiement, rendu: (r) => (r.statutPaiement ? <BadgeStatut statut={r.statutPaiement} /> : <span className="text-texte-doux">{T.sansPaiement}</span>) },
    { cle: 'statut', titre: L.commun.statut, rendu: (r) => <BadgeStatut statut={r.statut} /> },
  ]
  return (
    <div className="flex flex-col gap-3.5">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[
          { cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT },
          { cle: 'prestataire', type: 'texte', label: T.prestataire },
          { cle: 'client', type: 'texte', label: T.client },
          { cle: 'q', type: 'texte', label: T.recherche, placeholder: T.recherchePlaceholder },
        ]}
      />
      <ListePaginee legende={T.legende} cle="reservations" charger={api.reservations} etat={etat} colonnes={colonnes} cleLigne={(r) => r.id} lien={(r) => ({ vers: `/reservations/${r.id}`, nom: r.prestation.nom })} messageVide={T.vide} />
    </div>
  )
}

export function ReservationDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['reservation', id], queryFn: () => api.reservation(id) })
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/reservations', libelle: T.retour }}
      titre={(r) => r.prestation.nom}
      sousTitre={(r) => `${r.prestataire.nomEntreprise} · ${formatDateHeure(r.creneau.debut)}`}
      badge={(r) => <BadgeStatut statut={r.statut} />}
    >
      {(r) => (
        <>
          <div className="grid gap-5 lg:grid-cols-2">
            <Carte titre={T.infos}>
              <Definitions
                elements={[
                  { libelle: T.prestation, valeur: r.prestation.nom },
                  { libelle: T.duree, valeur: T.dureeMinutes(r.prestation.dureeMinutes) },
                  { libelle: T.creneau, valeur: `${formatDateHeure(r.creneau.debut)} → ${formatDateHeure(r.creneau.fin)}` },
                  { libelle: T.prix, valeur: formatMontant(r.prixApplique) },
                  ...(r.motifRefus ? [{ libelle: T.motifRefus, valeur: r.motifRefus, large: true }] : []),
                ]}
              />
            </Carte>
            <Carte titre={T.partiesTitre}>
              <Definitions
                elements={[
                  {
                    libelle: T.clientNom,
                    valeur: (
                      <Link to={`/utilisateurs/${r.client.id}`} className={lien}>
                        {r.client.prenom} {r.client.nom}
                      </Link>
                    ),
                  },
                  { libelle: T.colPrestataire, valeur: r.prestataire.nomEntreprise },
                ]}
              />
            </Carte>
          </div>

          <div className="grid gap-5 lg:grid-cols-3">
            <Carte titre={T.paiement}>
              {r.paiement ? (
                <div className="flex flex-col gap-2 text-sm">
                  <div className="flex items-center justify-between gap-2">
                    <span>{formatMontant(r.paiement.montantAcompte)}</span>
                    <BadgeStatut statut={r.paiement.statut} />
                  </div>
                  {r.nombrePaiements > 1 && <p className="text-xs text-texte-doux">{T.plusieursPaiements(r.nombrePaiements)}</p>}
                  <Link to={`/paiements/${r.paiement.id}`} className={lien}>
                    {T.voirPaiement}
                  </Link>
                </div>
              ) : (
                <p className="text-sm text-texte-doux">{T.aucunPaiement}</p>
              )}
            </Carte>
            <Carte titre={T.avis}>
              {r.avis ? (
                <div className="flex flex-col gap-2 text-sm">
                  <div className="flex items-center justify-between gap-2">
                    <span>{T.avisTexte(r.avis.note)}</span>
                    <BadgeStatut statut={r.avis.statut} />
                  </div>
                  <Link to="/avis?vue=tous" className={lien}>
                    {T.voirAvis}
                  </Link>
                </div>
              ) : (
                <p className="text-sm text-texte-doux">{T.aucunAvis}</p>
              )}
            </Carte>
            <Carte titre={T.litige}>
              {r.litige ? (
                <div className="flex flex-col gap-2 text-sm">
                  <BadgeStatut statut={r.litige.statut} />
                  <Link to={`/litiges/${r.litige.id}`} className={lien}>
                    {T.voirLitige}
                  </Link>
                </div>
              ) : (
                <p className="text-sm text-texte-doux">{T.aucunLitige}</p>
              )}
            </Carte>
          </div>

          <Carte titre={T.historique}>
            <Definitions
              elements={Object.entries(r.historique)
                .filter(([, valeur]) => valeur)
                .map(([cle, valeur]) => ({ libelle: T.jalons[cle] ?? cle, valeur: formatDateHeure(valeur as string) }))}
            />
          </Carte>
        </>
      )}
    </PageDetail>
  )
}
