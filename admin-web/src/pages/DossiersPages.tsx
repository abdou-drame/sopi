import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { messageDErreur, telecharger } from '../api/client'
import { api } from '../api/endpoints'
import type { DemandeDetail } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import FichierProtege from '../components/ui/FichierProtege'
import Fenetre from '../design/Fenetre'
import {
  Avatar,
  Badge,
  BoutonExporter,
  BoutonFiltre,
  Carte as CarteMq,
  DEGRADE_BLEU,
  EnteteCarte,
  EtapeChrono,
  LigneLien,
  Onglets,
  PiedListe,
  Recherche,
  TitreCarte,
  TitreDetail,
  TitrePage,
  Tuile,
  type TonBadge,
} from '../design/composants'
import { IDocument, IUtilisateur } from '../design/icones'
import PageDetailMq from '../design/PageDetailMq'
import { EtatsListe, useListe } from '../design/useListe'
import { L, dureeMinutes } from '../i18n/libelles'
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
      <button type="button" onClick={() => setModale('refuser')} className="mq-btn-blanc h-12 cursor-pointer rounded-3xl border-none bg-white px-[22px] text-[13px] font-bold text-[#b0161b]">
        {T.refuser}
      </button>
      <button type="button" onClick={() => setModale('accepter')} className="mq-btn-bleu h-12 cursor-pointer rounded-3xl border-none bg-[#1d4ed8] px-6 text-[13px] font-bold text-white shadow-[0_8px_18px_rgba(29,78,216,0.30)]">
        Valider le dossier
      </button>
      <Fenetre
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
      <Fenetre
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
/** Ouvre une pièce protégée dans un nouvel onglet : le fichier est récupéré avec le jeton, puis montré via une adresse locale. */
function LienVoirPiece({ chemin, nom }: { chemin: string; nom: string }) {
  const [enCours, setEnCours] = useState(false)
  const ouvrir = async () => {
    setEnCours(true)
    try {
      const fichier = await telecharger(chemin)
      window.open(URL.createObjectURL(fichier), '_blank', 'noopener')
    } finally {
      setEnCours(false)
    }
  }
  return (
    <button
      type="button"
      aria-label={`Voir ${nom}`}
      disabled={enCours}
      onClick={() => void ouvrir()}
      className="cursor-pointer border-none bg-transparent p-0 text-xs font-bold text-[#1d4ed8]"
    >
      Voir
    </button>
  )
}

const ABSENT = '—'

export function DossierDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['demande', id], queryFn: () => api.demande(id) })
  const retour = useRetours()
  const [maintenant] = useState(() => Date.now())
  return (
    <PageDetailMq requete={requete} retour="/dossiers-prestataires" libelleRetour="Retour aux dossiers">
      {(d) => {
        const recu = Math.max(0, (maintenant - new Date(d.dateSoumission).getTime()) / 60000)
        const complet = d.justificatifs.length >= PIECES_ATTENDUES
        const statut = L.statuts[d.statutCompte] ?? d.statutCompte
        const pluriel = d.justificatifs.length > 1 ? 's' : ''
        return (
          <>
            <TitreDetail
              retour="/dossiers-prestataires"
              libelleRetour="Retour aux dossiers"
              titre={d.prenom}
              suite={d.nom}
              badge={
                <Badge ton={TON_STATUT[d.statutCompte] ?? 'neutre'} gras>
                  {statut}
                </Badge>
              }
              infos={`Dossier reçu le ${formatDate(d.dateSoumission)} · ${L.metiers[d.metier] ?? d.metier} · ${d.quartier}`}
            >
              {d.statutCompte === 'en_attente' && <ActionsDossier d={d} onFait={retour.succes} />}
            </TitreDetail>
            <div className="flex flex-wrap items-start gap-3.5">
              <div className="flex min-w-0 flex-[2_1_560px] flex-col gap-3.5">
                <CarteMq>
                  <EnteteCarte icone={<IUtilisateur />} titre="Informations du prestataire" sousTitre="Saisies lors de l'inscription" />
                  <div className="mt-[18px] grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-2">
                    <Tuile libelle="Nom complet">{nomComplet(d)}</Tuile>
                    <Tuile libelle="Téléphone">{d.telephone}</Tuile>
                    <Tuile libelle="Métier">{L.metiers[d.metier] ?? d.metier}</Tuile>
                    <Tuile libelle="Quartier">{d.quartier}</Tuile>
                    <div className="col-span-full rounded-[20px] bg-[#f3f4f7] px-[18px] py-3.5">
                      <div className="text-xs text-[#8a90a2]">Présentation de l'activité</div>
                      <div className="mt-[3px] text-sm leading-[22px] font-medium text-[#3c4257]">{ABSENT}</div>
                    </div>
                  </div>
                </CarteMq>
                <CarteMq>
                  <EnteteCarte
                    icone={<IDocument />}
                    titre="Pièces justificatives"
                    sousTitre={`${d.justificatifs.length} pièce${pluriel} reçue${pluriel} sur ${PIECES_ATTENDUES}`}
                    droite={
                      <Badge ton={complet ? 'succes' : 'attention'} gras>
                        {complet ? 'Complet' : 'Incomplet'}
                      </Badge>
                    }
                  />
                  <div className="mt-[18px] grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
                    {d.justificatifs.map((j) => {
                      const nom = T.documents[j.typeDocument] ?? j.typeDocument
                      return (
                        <div key={j.id} className="rounded-[22px] bg-[#f3f4f7] p-2.5">
                          <div className="flex h-[120px] items-center justify-center overflow-hidden rounded-2xl bg-[#e7edff]">
                            <FichierProtege chemin={`/admin/justificatifs/${j.id}/telecharger`} alt={nom} className="h-[120px] w-full rounded-2xl!" />
                          </div>
                          <div className="flex items-center justify-between px-1.5 pt-2.5 pb-1">
                            <span className="text-[13px] font-semibold">{nom}</span>
                            <LienVoirPiece chemin={`/admin/justificatifs/${j.id}/telecharger`} nom={nom} />
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </CarteMq>
              </div>
              <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-3.5">
                <CarteMq>
                  <TitreCarte titre="Décision" sousTitre="Le prestataire est prévenu par e-mail et notification" />
                  <div className="mt-[18px] box-border rounded-[22px] p-5 text-white" style={{ background: DEGRADE_BLEU }}>
                    <div className="text-xs font-semibold opacity-80">Statut actuel</div>
                    <div className="mt-1.5 text-[28px] leading-[34px] font-bold tracking-[-0.6px]">{statut}</div>
                    <div className="mt-[18px] flex items-center justify-between text-xs">
                      <span className="opacity-80">Reçu il y a {dureeMinutes(recu)}</span>
                      <span className="rounded-full bg-white/20 px-2.5 py-1 font-bold">{ABSENT}</span>
                    </div>
                  </div>
                </CarteMq>
                <CarteMq>
                  <TitreCarte titre="Historique du dossier" />
                  <div className="mt-4 flex flex-col">
                    <EtapeChrono couleur="#1d4ed8" titre="Dossier déposé" detail={formatDateHeure(d.dateSoumission)} />
                    <EtapeChrono couleur="#1d4ed8" titre="E-mail « Dossier bien reçu » envoyé" detail={ABSENT} />
                    {d.statutCompte === 'en_attente' ? (
                      <EtapeChrono couleur="#cdd9fb" titre="En attente d'une décision" detail="Aucune action pour le moment" derniere />
                    ) : (
                      <EtapeChrono couleur="#1d4ed8" titre={d.statutCompte === 'actif' ? 'Dossier accepté' : 'Dossier refusé'} detail={d.motifRefus ?? ABSENT} derniere />
                    )}
                  </div>
                </CarteMq>
              </div>
            </div>
          </>
        )
      }}
    </PageDetailMq>
  )
}
