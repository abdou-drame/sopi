import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { DemandeDetail, DemandeListe } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import BanniereAlerte from '../components/ui/BanniereAlerte'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import CarteStatutActuel from '../components/ui/CarteStatutActuel'
import { IconeDossier, IconeUtilisateurs } from '../components/ui/Icones'
import Carte from '../components/ui/Carte'
import Definitions from '../components/ui/Definitions'
import EnTetePage from '../components/ui/EnTetePage'
import FichierProtege from '../components/ui/FichierProtege'
import FiltresListe from '../components/ui/FiltresListe'
import LienLigne from '../components/ui/LienLigne'
import ListePaginee from '../components/ui/ListePaginee'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import PageDetail from '../components/ui/PageDetail'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { formatDateHeure } from '../lib/format'
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

export function DossiersListePage() {
  const etat = useFiltresUrl(['statut'], { statut: 'en_attente' })
  const colonnes: Colonne<DemandeListe>[] = [
    { cle: 'prestataire', titre: T.prestataire, rendu: (d) => <LienLigne vers={`/dossiers-prestataires/${d.id}`}>{nomComplet(d)}</LienLigne> },
    { cle: 'entreprise', titre: T.entreprise, rendu: (d) => d.nomEntreprise },
    { cle: 'metier', titre: T.metier, rendu: (d) => L.metiers[d.metier] ?? d.metier },
    { cle: 'quartier', titre: T.quartier, rendu: (d) => d.quartier },
    { cle: 'soumis', titre: T.soumisLe, rendu: (d) => formatDateHeure(d.dateSoumission) },
    { cle: 'justificatifs', titre: T.justificatifs, alignement: 'droite', rendu: (d) => d.nombreJustificatifs },
    { cle: 'statut', titre: L.commun.statut, rendu: (d) => <BadgeStatut statut={d.statutCompte} /> },
  ]
  return (
    <div className="flex flex-col gap-3.5">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[{ cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT, onglets: true }]}
      />
      <ListePaginee
        legende={T.legende}
        cle="demandes"
        charger={api.demandes}
        etat={etat}
        colonnes={colonnes}
        cleLigne={(d) => d.id}
        lien={(d) => ({ vers: `/dossiers-prestataires/${d.id}`, nom: d.nomEntreprise })}
        messageVide={T.vide}
      />
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
