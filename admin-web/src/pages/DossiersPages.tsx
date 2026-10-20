import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { DemandeDetail } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import BanniereAlerte from '../components/ui/BanniereAlerte'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import CarteStatutActuel from '../components/ui/CarteStatutActuel'
import { IconeDossier, IconeUtilisateurs } from '../components/ui/Icones'
import Carte from '../components/ui/Carte'
import { Avatar, Badge, BoutonExporter, BoutonFiltre, Carte as CarteMq, LigneLien, Onglets, PiedListe, Recherche, TitrePage, type TonBadge } from '../design/composants'
import { EtatsListe, useListe } from '../design/useListe'
import Definitions from '../components/ui/Definitions'
import FichierProtege from '../components/ui/FichierProtege'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import PageDetail from '../components/ui/PageDetail'
import { L } from '../i18n/libelles'
import { formatDate, formatDateHeure } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.dossiers
const MOTIF_MIN = 10
const MOTIF_MAX = 500

const OPTIONS_STATUT = [
  { valeur: 'en_attente', libelle: T.enAttente },
  { valeur: 'actif', libelle: T.acceptes },
  { valeur: 'refuse', libelle: T.refuses },
  { valeur: 'tous', libelle: T.tous },
]

const nomComplet = (d: { prenom: string; nom: string }) => `${d.prenom} ${d.nom}`
/** Nombre de pièces justificatives attendues : une par type de document connu (carte d'identité, diplôme, attestation). */
const PIECES_ATTENDUES = Object.keys(T.documents).length
const TON_STATUT: Record<string, TonBadge> = { en_attente: 'attention', actif: 'succes', refuse: 'danger' }

export function DossiersListePage() {
  const etat = useFiltresUrl(['statut'], { statut: 'en_attente' })
  const liste = useListe('demandes', api.demandes, etat)
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const parStatut = tableau?.comptes.prestataires.parStatut
  const compteurs: Record<string, number | undefined> = {
    en_attente: parStatut?.en_attente,
    actif: parStatut?.actif,
    refuse: parStatut?.refuse,
    tous: tableau?.comptes.prestataires.total,
  }
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Dossiers" suite="prestataires" sousTitre="Examinez les demandes d'inscription, puis validez ou refusez chaque dossier.">
        <BoutonExporter />
      </TitrePage>
      <CarteMq className="flex flex-col gap-3.5">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            libelle={T.statut}
            onglets={OPTIONS_STATUT.map((o) => ({
              cle: o.valeur,
              libelle: o.libelle,
              compteur: compteurs[o.valeur] ?? '—',
              actif: etat.valeurs.statut === o.valeur,
              onSelect: () => etat.definir({ statut: o.valeur }),
            }))}
          />
          <div className="flex-1" />
          <Recherche valeur="" onChange={() => {}} placeholder="Rechercher un prestataire" libelle="Rechercher un prestataire" />
          <BoutonFiltre>{T.metier}</BoutonFiltre>
          <BoutonFiltre>{T.quartier}</BoutonFiltre>
        </div>
        <div className="overflow-x-auto">
          <table className="mq-table min-w-[820px]" aria-label={T.legende}>
            <thead>
              <tr>
                <th>{T.prestataire}</th>
                <th>{T.metier}</th>
                <th>{T.quartier}</th>
                <th>Pièces</th>
                <th>Reçu le</th>
                <th>{L.commun.statut}</th>
                <th className="relative">
                  <span className="sr-only">Action</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {liste.lignes.map((d) => {
                const complet = d.nombreJustificatifs >= PIECES_ATTENDUES
                const manquante = d.statutCompte === 'en_attente' && !complet
                return (
                  <LigneLien key={d.id} vers={`/dossiers-prestataires/${d.id}`} libelle={`Examiner le dossier de ${nomComplet(d)}`}>
                    <td>
                      <div className="flex items-center gap-3">
                        <Avatar nom={nomComplet(d)} taille={40} />
                        <span>
                          <span className="block font-semibold">{nomComplet(d)}</span>
                          <span className="mt-px block text-[11px] text-[#9aa0b2]">—</span>
                        </span>
                      </div>
                    </td>
                    <td>{L.metiers[d.metier] ?? d.metier}</td>
                    <td>{d.quartier}</td>
                    <td>
                      <span className="inline-flex items-center gap-1.5">
                        <span className="size-1.5 rounded-full" style={{ background: complet ? '#16a34a' : '#e5484d' }} />
                        {d.nombreJustificatifs} / {PIECES_ATTENDUES}
                      </span>
                    </td>
                    <td>{formatDate(d.dateSoumission)}</td>
                    <td>
                      <Badge ton={manquante ? 'danger' : (TON_STATUT[d.statutCompte] ?? 'neutre')}>
                        {manquante ? 'Pièce manquante' : (L.statuts[d.statutCompte] ?? d.statutCompte)}
                      </Badge>
                    </td>
                  </LigneLien>
                )
              })}
            </tbody>
          </table>
        </div>
        <EtatsListe chargement={liste.chargement} erreur={liste.erreur} vide={liste.donneesPresentes && liste.lignes.length === 0} messageVide={T.vide} onReessayer={liste.recharger} />
        <PiedListe
          resume={`${liste.premier} à ${liste.dernier} sur ${liste.total} ${liste.total > 1 ? 'résultats' : 'résultat'}`}
          precedent={liste.page > 1}
          suivant={liste.page < liste.totalPages}
          onPrecedent={() => etat.allerPage(liste.page - 1)}
          onSuivant={() => etat.allerPage(liste.page + 1)}
        />
      </CarteMq>
    </div>
  )
}

function ActionsDossier({ d, onFait }: { d: DemandeDetail; onFait: (message: string) => void }) {
  const [modale, setModale] = useState<'accepter' | 'refuser' | null>(null)
  const accepter = useAction(() => api.accepterDemande(d.id))
  const refuser = useAction((motif: string) => api.refuserDemande(d.id, motif))
  const fermer = () => {
    setModale(null)
    accepter.reset()
    refuser.reset()
  }
  return (
    <>
      <Bouton variante="primaire" onClick={() => setModale('accepter')}>
        {T.accepter}
      </Bouton>
      <Bouton variante="danger" onClick={() => setModale('refuser')}>
        {T.refuser}
      </Bouton>
      <ModaleConfirmation
        ouverte={modale === 'accepter'}
        titre={T.accepterTitre}
        description={T.accepterTexte}
        libelleConfirmer={T.accepter}
        enCours={accepter.isPending}
        erreur={accepter.isError ? messageDErreur(accepter.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={() =>
          accepter.mutate(undefined, {
            onSuccess: () => {
              fermer()
              onFait(T.accepteMessage)
            },
          })
        }
      />
      <ModaleConfirmation
        ouverte={modale === 'refuser'}
        titre={T.refuserTitre}
        description={T.refuserTexte}
        libelleConfirmer={T.refuser}
        libelleMotif={T.motifRefusLibelle}
        danger
        motifObligatoire
        motifMin={MOTIF_MIN}
        motifMax={MOTIF_MAX}
        enCours={refuser.isPending}
        erreur={refuser.isError ? messageDErreur(refuser.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={(motif) =>
          refuser.mutate(motif, {
            onSuccess: () => {
              fermer()
              onFait(T.refuseMessage)
            },
          })
        }
      />
    </>
  )
}

export function DossierDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['demande', id], queryFn: () => api.demande(id) })
  const retour = useRetours()
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/dossiers-prestataires', libelle: T.retour }}
      titre={(d) => d.nomEntreprise}
      sousTitre={(d) => `${nomComplet(d)} · ${L.metiers[d.metier] ?? d.metier} · ${d.quartier}`}
      badge={(d) => <BadgeStatut statut={d.statutCompte} />}
      actions={(d) => (d.statutCompte === 'en_attente' ? <ActionsDossier d={d} onFait={retour.succes} /> : null)}
    >
      {(d) => (
        <>
          {d.statutCompte === 'refuse' && <BanniereAlerte gravite="attention" titre={T.dejaRefuse} detail={d.motifRefus ?? undefined} />}
          {d.statutCompte === 'actif' && <BanniereAlerte gravite="info" titre={T.dejaAccepte} />}
          <div className="flex flex-wrap items-start gap-3.5">
            <div className="flex min-w-0 flex-[2_1_560px] flex-col gap-3.5">
              <Carte titre={T.identite} icone={<IconeUtilisateurs width={18} height={18} />}>
                <Definitions
                  elements={[
                    { libelle: T.nom, valeur: nomComplet(d) },
                    { libelle: T.entreprise, valeur: d.nomEntreprise },
                    { libelle: T.telephone, valeur: d.telephone },
                    { libelle: T.email, valeur: d.email },
                    { libelle: T.metier, valeur: L.metiers[d.metier] ?? d.metier },
                    { libelle: T.quartier, valeur: d.quartier },
                    { libelle: T.adresse, valeur: d.adresse, large: true },
                    { libelle: T.soumisLe, valeur: formatDateHeure(d.dateSoumission) },
                  ]}
                />
              </Carte>
              <Carte titre={T.justificatifs} icone={<IconeDossier width={18} height={18} />}>
                {d.justificatifs.length === 0 ? (
                  <p className="text-sm text-texte-doux">{T.aucunJustificatif}</p>
                ) : (
                  <ul className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
                    {d.justificatifs.map((j) => {
                      const nom = T.documents[j.typeDocument] ?? j.typeDocument
                      return (
                        <li key={j.id} className="flex flex-col items-center gap-2 rounded-[22px] bg-fond p-2.5 text-center">
                          <FichierProtege chemin={`/admin/justificatifs/${j.id}/telecharger`} alt={`${nom} (${j.nomOriginal})`} typeMime={j.typeMime} />
                          <div className="flex w-full items-center justify-between gap-2 px-1.5 pt-1 pb-1">
                            <p className="text-[13px] font-semibold">{nom}</p>
                            <BadgeStatut statut={j.statut} />
                          </div>
                        </li>
                      )
                    })}
                  </ul>
                )}
              </Carte>
            </div>
            <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-3.5">
              <CarteStatutActuel
                titre={T.decision}
                libelleStatut={L.commun.statut}
                statut={L.statuts[d.statutCompte] ?? d.statutCompte}
                note={formatDateHeure(d.dateSoumission)}
              />
            </div>
          </div>
        </>
      )}
    </PageDetail>
  )
}
