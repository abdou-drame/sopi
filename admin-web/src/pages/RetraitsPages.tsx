import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { RetraitListe } from '../api/typesAdmin'
import BadgeStatut from '../components/ui/BadgeStatut'
import BanniereAlerte from '../components/ui/BanniereAlerte'
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

const T = L.retraits

const OPTIONS_STATUT = [
  { valeur: '', libelle: L.commun.tous },
  ...(['en_cours', 'reussi', 'echoue'] as const).map((valeur) => ({ valeur, libelle: L.statuts[valeur] })),
]
const OPTIONS_BLOQUES = [
  { valeur: '', libelle: T.tous },
  { valeur: 'true', libelle: T.bloquesSeuls },
  { valeur: 'false', libelle: T.nonBloques },
]

export function RetraitsListePage() {
  const etat = useFiltresUrl(['statut', 'prestataire', 'bloques'])
  const colonnes: Colonne<RetraitListe>[] = [
    {
      cle: 'prestataire',
      titre: T.colPrestataire,
      rendu: (r) => <LienLigne vers={`/retraits/${r.id}`}>{r.prestataire.nomEntreprise}</LienLigne>,
    },
    { cle: 'montant', titre: T.colMontant, alignement: 'droite', rendu: (r) => formatMontant(r.montant) },
    { cle: 'demande', titre: T.colDemande, rendu: (r) => formatDateHeure(r.dateDemande) },
    { cle: 'traite', titre: T.colTraite, rendu: (r) => (r.traiteLe ? formatDateHeure(r.traiteLe) : T.nonTraite) },
    // Le numéro arrive déjà masqué de l'API ; il n'est jamais reconstitué.
    { cle: 'versement', titre: T.colVersement, rendu: (r) => r.telephoneVersement },
    {
      cle: 'statut',
      titre: L.commun.statut,
      rendu: (r) => (
        <div className="flex flex-wrap gap-1.5">
          <BadgeStatut statut={r.statut} />
          {r.bloque && <BadgeStatut ton="erreur" libelle={T.bloque} />}
          {r.recredite && <BadgeStatut ton="attention" libelle={T.recredite} />}
        </div>
      ),
    },
  ]
  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[
          { cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT },
          { cle: 'bloques', type: 'select', label: T.bloques, options: OPTIONS_BLOQUES },
          { cle: 'prestataire', type: 'texte', label: T.prestataire },
        ]}
      />
      <ListePaginee legende={T.legende} cle="retraits" charger={api.retraits} etat={etat} colonnes={colonnes} cleLigne={(r) => r.id} messageVide={T.vide} />
    </div>
  )
}

export function RetraitDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['retrait', id], queryFn: () => api.retrait(id) })
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/retraits', libelle: T.retour }}
      titre={(r) => r.prestataire.nomEntreprise}
      sousTitre={(r) => formatMontant(r.montant)}
      badge={(r) => <BadgeStatut statut={r.statut} />}
    >
      {(r) => (
        <>
          {r.bloque && <BanniereAlerte gravite="attention" titre={T.blocageTexte} />}
          <Carte titre={T.detailTitre}>
            <Definitions
              elements={[
                { libelle: T.montant, valeur: formatMontant(r.montant) },
                { libelle: T.colDemande, valeur: formatDateHeure(r.dateDemande) },
                { libelle: T.traiteLe, valeur: r.traiteLe ? formatDateHeure(r.traiteLe) : T.nonTraite },
                { libelle: T.recrediteLe, valeur: r.recrediteLe ? formatDateHeure(r.recrediteLe) : null },
                { libelle: T.telephone, valeur: r.telephoneVersement },
                // La référence DexPay n'est montrée qu'ici, jamais en liste.
                { libelle: T.referenceDexpay, valeur: r.referenceDexpay },
                {
                  libelle: T.voirPrestataire,
                  valeur: (
                    <Link to={`/utilisateurs/${r.prestataire.userId}`} className="font-semibold text-primaire-700 hover:underline">
                      {T.voirPrestataire}
                    </Link>
                  ),
                },
              ]}
            />
          </Carte>
        </>
      )}
    </PageDetail>
  )
}
