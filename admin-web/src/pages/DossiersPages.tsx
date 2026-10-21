import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { messageDErreur, telecharger } from '../api/client'
import { api } from '../api/endpoints'
import type { DemandeDetail } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useAlertes } from '../api/useAlertes'
import { useRetours } from '../components/ui/Alertes'
import FichierProtege from '../components/ui/FichierProtege'
import { BanniereDecision } from '../design/bannieres'
import Fenetre from '../design/Fenetre'
import {
  Avatar,
  Badge,
  Carte as CarteMq,
  DEGRADE_BLEU,
  EnteteCarte,
  EtapeChrono,
  LigneLien,
  Onglets,
  PaginationRonde,
  TitreCarte,
  TitreDetail,
  TitrePage,
  Tuile,
  type TonBadge,
} from '../design/composants'
import { IDocument, IHorloge, IRecherche, IUtilisateur } from '../design/icones'
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

const sansAccent = (t: string) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

const PASTILLE_STATUT: Record<string, { fond: string; point: string }> = {
  en_attente: { fond: 'bg-[#fff0d4] text-[#983705]', point: '#f59e0b' },
  actif: { fond: 'bg-[#d7f7e4] text-[#0e6245]', point: '#16a34a' },
  refuse: { fond: 'bg-[#fde9e9] text-[#b0161b]', point: '#e5484d' },
}

function PastilleStatut({ code, libelle, alerte }: { code: string; libelle: string; alerte: boolean }) {
  const style = alerte ? { fond: 'bg-[#fde9e9] text-[#b0161b]', point: '#e5484d' } : (PASTILLE_STATUT[code] ?? { fond: 'bg-[#eef1f6] text-[#4f566b]', point: '#9aa0b2' })
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-[5px] text-xs font-semibold ${style.fond}`}>
      <span aria-hidden="true" className="size-1.5 rounded-full" style={{ background: style.point }} />
      {libelle}
    </span>
  )
}

const ICO = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true } as const
const ICoche = () => (
  <svg {...ICO}>
    <path d="M5 12.5l4.5 4.5L19 7.5" />
  </svg>
)
const ICroix = () => (
  <svg {...ICO}>
    <path d="M6 6l12 12M18 6L6 18" />
  </svg>
)
const IFiltre = () => (
  <svg {...ICO} width={16} height={16}>
    <path d="M4 7h16M7 12h10M10 17h4" />
  </svg>
)

/** Carte de synthèse blanche : rond d'icône coloré, badge, grand chiffre et barre de progression fine (si un pourcentage existe). */
function CarteStat({
  icone,
  teinte,
  badge,
  badgeTeinte,
  titre,
  valeur,
  pourcentage,
  couleurBarre,
  note,
}: {
  icone: React.ReactNode
  teinte: string
  badge: string
  badgeTeinte: string
  titre: string
  valeur: number | undefined
  pourcentage?: number
  couleurBarre?: string
  note?: string
}) {
  return (
    <section aria-label={titre} className="box-border flex flex-col rounded-[32px] bg-white p-[22px]">
      <div className="flex items-center justify-between">
        <span className={`grid size-11 place-items-center rounded-[22px] ${teinte}`}>{icone}</span>
        <span className={`rounded-full px-3 py-1 text-xs font-bold ${badgeTeinte}`}>{badge}</span>
      </div>
      <div className="mt-5 text-[13px] font-medium text-[#8a90a2]">{titre}</div>
      <div className="mt-1 text-[44px] leading-[50px] font-bold tracking-[-1.2px] text-[#12172b] tabular-nums">{valeur === undefined ? '—' : valeur}</div>
      {pourcentage !== undefined ? (
        <div className="mt-3.5 h-1.5 overflow-hidden rounded-full bg-[#eceef2]" role="presentation">
          <div className="barre-metier h-full rounded-full" style={{ width: `${pourcentage}%`, background: couleurBarre }} />
        </div>
      ) : (
        <div className="mt-3.5 text-[13px] text-[#8a90a2]">{note}</div>
      )}
    </section>
  )
}

export function DossiersListePage() {
  const etat = useFiltresUrl(['statut'], { statut: 'en_attente' })
  const liste = useListe('demandes', api.demandes, etat)
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const { data: alertes } = useAlertes()
  const { data: metiers } = useQuery({ queryKey: ['metiers'], queryFn: api.metiers, retry: false })
  // Recherche et filtre de métier : l'API ne les propose pas, ils s'appliquent aux lignes de la page affichée.
  const [recherche, setRecherche] = useState('')
  const [metier, setMetier] = useState('')

  const prestataires = tableau?.comptes.prestataires
  const parStatut = prestataires?.parStatut
  const total = prestataires?.total
  const compteurs: Record<string, number | undefined> = {
    en_attente: parStatut?.en_attente,
    actif: parStatut?.actif,
    refuse: parStatut?.refuse,
    tous: total,
  }
  const part = (n: number | undefined) => (n === undefined || !total ? undefined : Math.round((n / total) * 100))
  const partAcceptes = part(parStatut?.actif)
  const partRefuses = part(parStatut?.refuse)
  const ageMax = alertes?.alertes.find((a) => a.type === 'dossiers_en_attente')?.ageMinutes
  const enAttente = parStatut?.en_attente

  const lignes = liste.lignes.filter(
    (d) => (!metier || d.metier === metier) && sansAccent(`${d.nomEntreprise} ${nomComplet(d)} ${d.quartier}`).includes(sansAccent(recherche.trim())),
  )
  const filtre = recherche.trim() !== '' || metier !== ''

  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Dossiers" suite="prestataires" sousTitre="Examinez les demandes d'inscription, puis validez ou refusez chaque dossier." />

      <div className="grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(230px,1fr))]">
        <section aria-label="En attente de décision" className="box-border flex flex-col rounded-[32px] p-[22px] text-white shadow-[0_18px_36px_-14px_rgba(30,64,216,0.55)]" style={{ background: 'linear-gradient(150deg, #2E5BFF 0%, #1E40D8 55%, #1A33A8 100%)' }}>
          <div className="flex items-center justify-between">
            <span className="grid size-11 place-items-center rounded-[22px] bg-white/20">
              <IHorloge />
            </span>
            {enAttente === 0 && (
              <span className="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-bold">
                <span className="[&>svg]:size-3.5">
                  <ICoche />
                </span>
                À jour
              </span>
            )}
          </div>
          <div className="mt-5 text-[13px] font-semibold opacity-90">En attente de décision</div>
          <div className="mt-1 flex items-baseline gap-2">
            <span className="text-[44px] leading-[50px] font-bold tracking-[-1.2px] tabular-nums">{enAttente === undefined ? '—' : enAttente}</span>
            <span className="text-sm font-semibold opacity-90">{enAttente === 1 ? 'dossier' : 'dossiers'}</span>
          </div>
          <div className="mt-3.5 text-[13px] opacity-85">Plus ancien : {ageMax == null ? 'aucun dossier en retard' : dureeMinutes(ageMax)}</div>
        </section>

        <CarteStat
          icone={<ICoche />}
          teinte="bg-[#d7f7e4] text-[#16a34a]"
          badge={partAcceptes === undefined ? '—' : `${partAcceptes} %`}
          badgeTeinte="bg-[#d7f7e4] text-[#0e6245]"
          titre="Acceptés"
          valeur={parStatut?.actif}
          pourcentage={partAcceptes ?? 0}
          couleurBarre="#16a34a"
        />
        <CarteStat
          icone={<ICroix />}
          teinte="bg-[#fde9e9] text-[#e5484d]"
          badge={partRefuses === undefined ? '—' : `${partRefuses} %`}
          badgeTeinte="bg-[#fde9e9] text-[#b0161b]"
          titre="Refusés"
          valeur={parStatut?.refuse}
          pourcentage={partRefuses ?? 0}
          couleurBarre="#e5484d"
        />
        <CarteStat
          icone={<IDocument />}
          teinte="bg-[#e7edff] text-[#1d4ed8]"
          badge="Total"
          badgeTeinte="bg-[#eef1f6] text-[#4f566b]"
          titre="Dossiers reçus"
          valeur={total}
          note="Depuis le lancement"
        />
      </div>

      <CarteMq className="flex flex-col gap-3.5 !p-5">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            pastille
            libelle={T.statut}
            onglets={OPTIONS_STATUT.map((o) => ({
              cle: o.valeur,
              libelle: o.libelle,
              compteur: compteurs[o.valeur] ?? '—',
              actif: etat.valeurs.statut === o.valeur,
              onSelect: () => etat.definir({ statut: o.valeur }),
            }))}
          />
          <div className="ml-auto flex flex-wrap items-center gap-2.5">
            <label className="flex h-11 w-[300px] max-w-full items-center gap-2.5 rounded-[22px] border border-[#e6e8ee] bg-[#f6f7fa] px-4 text-[#9aa0b2] focus-within:border-[#1d4ed8]">
              <IRecherche taille={16} />
              <input
                type="search"
                value={recherche}
                onChange={(e) => setRecherche(e.target.value)}
                placeholder="Rechercher un prestataire…"
                aria-label="Rechercher un prestataire"
                className="min-w-0 flex-1 border-none bg-transparent text-[13px] text-[#12172b] outline-none placeholder:text-[#9aa0b2]"
              />
            </label>
            <label className="relative flex h-11 items-center gap-2 rounded-[22px] border border-[#e6e8ee] bg-white pr-4 pl-4 text-[13px] font-semibold text-[#12172b]">
              <IFiltre />
              <select
                value={metier}
                onChange={(e) => setMetier(e.target.value)}
                aria-label="Filtrer par métier"
                className="cursor-pointer appearance-none border-none bg-transparent pr-1 text-[13px] font-semibold outline-none"
              >
                <option value="">{T.metier}</option>
                {(metiers?.data ?? []).map((m) => (
                  <option key={m.id} value={m.code}>
                    {m.libelle}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table
            className="w-full min-w-[820px] border-collapse text-[13px] [&_tbody_td]:border-b [&_tbody_td]:border-[#eceef2] [&_tbody_tr:last-child_td]:border-b-0 [&_td]:px-4 [&_td]:py-3.5 [&_td]:align-middle [&_td]:font-medium"
            aria-label={T.legende}
          >
            <thead>
              <tr className="text-left text-[11px] tracking-[0.06em] text-[#9aa0b2] uppercase [&_th]:border-b [&_th]:border-[#eceef2] [&_th]:px-4 [&_th]:py-3 [&_th]:font-semibold">
                <th>{T.prestataire}</th>
                <th>{T.metier}</th>
                <th>{T.quartier}</th>
                <th>Pièces</th>
                <th>Reçu le</th>
                <th>{L.commun.statut}</th>
                <th className="relative text-right">
                  <span className="sr-only">Action</span>
                  <span aria-hidden="true">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {lignes.map((d) => {
                const complet = d.nombreJustificatifs >= PIECES_ATTENDUES
                const manquante = d.statutCompte === 'en_attente' && !complet
                return (
                  <LigneLien key={d.id} vers={`/dossiers-prestataires/${d.id}`} libelle={`Examiner le dossier de ${nomComplet(d)}`} texte="Ouvrir le dossier" discret>
                    <td>
                      <div className="flex items-center gap-3">
                        <Avatar nom={d.nomEntreprise || nomComplet(d)} taille={36} />
                        <span className="min-w-0">
                          <span className="block font-semibold text-[#12172b]">{d.nomEntreprise || nomComplet(d)}</span>
                          <span className="mt-0.5 block text-xs font-normal text-[#9aa0b2]">{nomComplet(d)}</span>
                        </span>
                      </div>
                    </td>
                    <td>
                      <span className="inline-block rounded-full bg-[#e7edff] px-3 py-1 text-xs font-semibold text-[#1d4ed8]">{L.metiers[d.metier] ?? d.metier}</span>
                    </td>
                    <td className="text-[#6b7287]">{d.quartier}</td>
                    <td>
                      <span className={`inline-block rounded-full px-3 py-1 text-xs font-semibold ${complet ? 'bg-[#eef1f6] text-[#4f566b]' : 'bg-[#fff0d4] text-[#983705]'}`}>
                        {d.nombreJustificatifs}/{PIECES_ATTENDUES} pièces
                      </span>
                    </td>
                    <td className="text-[#6b7287]">{formatDate(d.dateSoumission)}</td>
                    <td>
                      <PastilleStatut code={d.statutCompte} alerte={manquante} libelle={manquante ? 'Pièce manquante' : (L.statuts[d.statutCompte] ?? d.statutCompte)} />
                    </td>
                  </LigneLien>
                )
              })}
            </tbody>
          </table>
        </div>
        <EtatsListe
          chargement={liste.chargement}
          erreur={liste.erreur}
          vide={liste.donneesPresentes && lignes.length === 0}
          messageVide={filtre && liste.lignes.length > 0 ? 'Aucun dossier de cette page ne correspond à la recherche.' : T.vide}
          onReessayer={liste.recharger}
        />
        <PaginationRonde
          resume={
            <>
              Affichage de <strong className="font-semibold text-[#12172b]">{liste.premier}–{liste.dernier}</strong> sur {liste.total} {liste.total > 1 ? 'dossiers' : 'dossier'}
            </>
          }
          page={liste.page}
          totalPages={liste.totalPages}
          onPage={etat.allerPage}
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
            {d.statutCompte === 'actif' && <BanniereDecision ton="succes">Ce dossier a déjà été accepté.</BanniereDecision>}
            {d.statutCompte === 'refuse' && <BanniereDecision ton="danger">Ce dossier a déjà été refusé.</BanniereDecision>}
            <div className="flex flex-wrap items-start gap-3.5">
              <div className="flex min-w-0 flex-[2_1_560px] flex-col gap-3.5">
                <CarteMq>
                  <EnteteCarte icone={<IUtilisateur />} titre="Informations du prestataire" sousTitre="Saisies lors de l'inscription" />
                  <div className="mt-[18px] grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-2">
                    <Tuile libelle="Nom complet">{nomComplet(d)}</Tuile>
                    <Tuile libelle="Téléphone">{d.telephone}</Tuile>
                    <Tuile libelle="Métier">{L.metiers[d.metier] ?? d.metier}</Tuile>
                    <Tuile libelle="Quartier">{d.quartier}</Tuile>
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
                    </div>
                  </div>
                </CarteMq>
                <CarteMq>
                  <TitreCarte titre="Historique du dossier" />
                  <div className="mt-4 flex flex-col">
                    <EtapeChrono couleur="#1d4ed8" titre="Dossier déposé" detail={formatDateHeure(d.dateSoumission)} />
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
