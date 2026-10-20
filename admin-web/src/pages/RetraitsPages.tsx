import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { RetraitListe } from '../api/typesAdmin'
import CarteListe from '../design/CarteListe'
import PageDetailMq from '../design/PageDetailMq'
import { BadgeDuStatut } from '../design/Statut'
import { BanniereAlerteMq } from '../design/bannieres'
import { Avatar, Badge, Carte, GrilleTuiles, LienTexte, LigneLien, Onglets, TitreCarte, TitreDetail, TitrePage } from '../design/composants'
import { useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatDate, formatDateHeure, formatMontant } from '../lib/format'
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

/** Nombre de retraits d'un onglet, par une requête de total (la liste elle-même est paginée). */
function useCompteRetraits(cle: string, filtres: Record<string, string>) {
  const { data } = useQuery({ queryKey: ['retraits-compte', cle], queryFn: () => api.retraits(filtres), retry: false })
  return data?.meta.total
}

export function RetraitsListePage() {
  const etat = useFiltresUrl(['statut', 'prestataire', 'bloques'])
  const liste = useListe('retraits', api.retraits, etat)
  const { statut = '', bloques = '' } = etat.valeurs
  const comptes = { tous: useCompteRetraits('tous', {}), bloques: useCompteRetraits('bloques', { bloques: 'true' }), echoues: useCompteRetraits('echoues', { statut: 'echoue' }) }
  const onglets = [
    { cle: 'tous', libelle: T.tous, compteur: comptes.tous, actif: !statut && !bloques, valeurs: { statut: '', bloques: '' } },
    { cle: 'bloques', libelle: T.bloquesSeuls, compteur: comptes.bloques, actif: !statut && bloques === 'true', valeurs: { statut: '', bloques: 'true' } },
    { cle: 'echoues', libelle: L.statuts.echoue, compteur: comptes.echoues, actif: statut === 'echoue' && !bloques, valeurs: { statut: 'echoue', bloques: '' } },
  ]
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Retraits" suite="des prestataires" sousTitre={T.sousTitre} />
      <CarteListe
        legende={T.legende}
        etat={etat}
        liste={liste}
        messageVide={T.vide}
        actionsColonne={L.commun.voir}
        largeurMin={900}
        avant={<Onglets libelle={T.filtres} onglets={onglets.map((o) => ({ cle: o.cle, libelle: o.libelle, compteur: o.compteur ?? '—', actif: o.actif, onSelect: () => etat.definir(o.valeurs) }))} />}
        filtres={[
          { cle: 'prestataire', type: 'recherche', label: T.prestataire, placeholder: T.prestataire },
          { cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT },
          { cle: 'bloques', type: 'select', label: T.bloques, options: OPTIONS_BLOQUES },
        ]}
        colonnes={[{ titre: T.colPrestataire }, { titre: T.colDemande }, { titre: T.colTraite }, { titre: T.colMontant }, { titre: T.colVersement }, { titre: L.commun.statut }]}
      >
        {liste.lignes.map((r: RetraitListe) => (
          <LigneLien key={r.id} vers={`/retraits/${r.id}`} libelle={`Voir le détail du retrait de ${r.prestataire.nomEntreprise}`} texte={L.commun.voirCourt}>
            <td>
              <div className="flex items-center gap-3">
                <Avatar nom={r.prestataire.nomEntreprise} taille={36} />
                <span className="font-semibold">{r.prestataire.nomEntreprise}</span>
              </div>
            </td>
            <td>{formatDate(r.dateDemande)}</td>
            <td>{r.traiteLe ? formatDate(r.traiteLe) : <span className="text-[#9aa0b2]">{T.nonTraite}</span>}</td>
            <td>{formatMontant(r.montant)}</td>
            {/* Le numéro arrive déjà masqué de l'API ; il n'est jamais reconstitué. */}
            <td>{r.telephoneVersement}</td>
            <td>
              <div className="flex flex-wrap gap-1.5">
                <BadgeDuStatut statut={r.statut} />
                {r.bloque && <Badge ton="attention">{T.bloque}</Badge>}
                {r.recredite && <Badge ton="neutre">{T.recredite}</Badge>}
              </div>
            </td>
          </LigneLien>
        ))}
      </CarteListe>
    </div>
  )
}

export function RetraitDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['retrait', id], queryFn: () => api.retrait(id) })
  return (
    <PageDetailMq requete={requete} retour="/retraits" libelleRetour={T.retour}>
      {(r) => (
        <div className="flex flex-col gap-4">
          <TitreDetail retour="/retraits" libelleRetour={T.retour} titre={r.prestataire.nomEntreprise} badge={<BadgeDuStatut statut={r.statut} />} infos={formatMontant(r.montant)} />
          {r.bloque && <BanniereAlerteMq gravite="attention">{T.blocageTexte}</BanniereAlerteMq>}
          <Carte>
            <TitreCarte titre={T.detailTitre} />
            <GrilleTuiles
              elements={[
                { libelle: T.montant, valeur: formatMontant(r.montant) },
                { libelle: T.colDemande, valeur: formatDateHeure(r.dateDemande) },
                { libelle: T.traiteLe, valeur: r.traiteLe ? formatDateHeure(r.traiteLe) : T.nonTraite },
                { libelle: T.recrediteLe, valeur: r.recrediteLe ? formatDateHeure(r.recrediteLe) : null },
                { libelle: T.telephone, valeur: r.telephoneVersement },
                // La référence DexPay n'est montrée qu'ici, jamais en liste.
                { libelle: T.referenceDexpay, valeur: r.referenceDexpay },
                { libelle: T.voirPrestataire, valeur: <LienTexte vers={`/utilisateurs/${r.prestataire.userId}`}>{T.voirPrestataire}</LienTexte> },
              ]}
            />
          </Carte>
        </div>
      )}
    </PageDetailMq>
  )
}
