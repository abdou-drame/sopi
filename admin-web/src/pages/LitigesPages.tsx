import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import type { ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { CibleSanction, DecisionLitige, LitigeDetail } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import FichierProtege, { cheminApi } from '../components/ui/FichierProtege'
import { ChampJour, ChampSelect } from '../design/champs'
import Fenetre from '../design/Fenetre'
import { Badge, Carte as CarteMq, DEGRADE_BLEU, EnteteCarte, EtapeChrono, LienRond, LigneLien, Onglets, PiedListe, TitreCarte, TitreDetail, TitrePage, type TonBadge } from '../design/composants'
import { IHorloge, IImage, IMessage } from '../design/icones'
import PageDetailMq from '../design/PageDetailMq'
import { EtatsListe, useListe } from '../design/useListe'
import { L, dureeMinutes } from '../i18n/libelles'
import { aujourdhui, formatDate, formatHeureDuJour, formatMontant, formatNombre } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.litiges
const MOTIF_MIN = 10
const MOTIF_MAX = 1000

const OPTIONS_STATUT = [
  { valeur: 'ouvert', libelle: T.ouverts },
  { valeur: 'cloture', libelle: T.clotures },
  { valeur: 'tous', libelle: T.tous },
]

const nomComplet = (p: { prenom: string; nom: string }) => `${p.prenom} ${p.nom}`

const TON_STATUT: Record<string, TonBadge> = { ouvert: 'danger', cloture: 'succes' }

/** « Clôturé · rejet » quand la clôture est un rejet de la plainte, « Clôturé » pour toute autre décision. */
const libelleStatut = (statut: string, decision: DecisionLitige | null) => (statut === 'cloture' && decision === 'rejet' ? T.clotureRejet : (L.statuts[statut] ?? statut))

export function LitigesListePage() {
  const etat = useFiltresUrl(['statut'], { statut: 'ouvert' })
  const liste = useListe('litiges', api.litiges, etat)
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const compteurs: Record<string, number | undefined> = {
    ouvert: tableau?.litiges.ouverts,
    cloture: tableau?.litiges.clos,
    tous: tableau?.litiges.total,
  }
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Litiges" suite="à trancher" sousTitre="Examinez chaque litige, puis tranchez : rejet, avertissement, suspension ou bannissement." />
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
        </div>
        <div className="overflow-x-auto">
          <table className="mq-table min-w-[860px]" aria-label={T.legende}>
            <thead>
              <tr>
                <th>Litige</th>
                <th>{T.client}</th>
                <th>{T.prestataire}</th>
                <th style={{ textAlign: 'right' }}>{T.acompte}</th>
                <th>{T.ouvertLe}</th>
                <th>{L.commun.statut}</th>
                <th className="relative">
                  <span className="sr-only">Action</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {liste.lignes.map((l) => {
                const acompte = l.paiements.length === 0 ? null : l.paiements.reduce((somme, p) => somme + p.montantAcompte, 0)
                return (
                  <LigneLien key={l.id} vers={`/litiges/${l.id}`} libelle={`Ouvrir le litige ${l.motif}`}>
                    <td>
                      <span className="block font-semibold">{l.reservation.prestation}</span>
                      <span className="mt-px block text-[11px] text-[#9aa0b2]">—</span>
                    </td>
                    <td>{nomComplet(l.client)}</td>
                    <td>{l.prestataire.nomEntreprise ?? nomComplet(l.prestataire)}</td>
                    <td style={{ textAlign: 'right' }}>
                      <span className="font-semibold tabular-nums">{acompte === null ? '—' : formatMontant(acompte)}</span>
                    </td>
                    <td>{formatDate(l.dateCreation)}</td>
                    <td>
                      <Badge ton={l.statut === 'cloture' && l.decision === 'rejet' ? 'neutre' : (TON_STATUT[l.statut] ?? 'neutre')}>{libelleStatut(l.statut, l.decision)}</Badge>
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

function ActionTrancher({ litige, onFait }: { litige: LitigeDetail; onFait: (message: string) => void }) {
  const [ouverte, setOuverte] = useState(false)
  const [decision, setDecision] = useState<DecisionLitige | ''>('')
  const [cible, setCible] = useState<CibleSanction | ''>('')
  const [dateFin, setDateFin] = useState('')
  const trancher = useAction((corps: Parameters<typeof api.trancherLitige>[1]) => api.trancherLitige(litige.id, corps))

  const suspension = decision === 'suspension_temporaire'
  const sanction = decision !== '' && decision !== 'rejet'

  const fermer = () => {
    setOuverte(false)
    setDecision('')
    setCible('')
    setDateFin('')
    trancher.reset()
  }

  const champs = (
    <>
      <ChampSelect
        label={T.decisionChamp}
        value={decision}
        onChange={(e) => setDecision(e.target.value as DecisionLitige | '')}
        options={[{ valeur: '', libelle: T.choisir }, ...Object.entries(T.decisions).map(([valeur, libelle]) => ({ valeur, libelle }))]}
      />
      {sanction && (
        <ChampSelect
          label={T.cible}
          value={cible}
          onChange={(e) => setCible(e.target.value as CibleSanction | '')}
          options={[
            { valeur: '', libelle: T.cibleDefaut },
            { valeur: 'prestataire', libelle: T.ciblePrestataire },
            { valeur: 'client', libelle: T.cibleClient },
          ]}
        />
      )}
      {suspension && <ChampJour label={T.dateFin} value={dateFin} onChange={(e) => setDateFin(e.target.value)} min={aujourdhui()} />}
    </>
  )

  return (
    <>
      <button
        type="button"
        onClick={() => setOuverte(true)}
        className="mq-btn-bleu h-12 cursor-pointer rounded-3xl border-none bg-[#1d4ed8] px-6 text-[13px] font-bold text-white shadow-[0_8px_18px_rgba(29,78,216,0.30)]"
      >
        {T.trancher}
      </button>
      <Fenetre
        ouverte={ouverte}
        titre={T.trancherTitre}
        description={T.trancherTexte}
        libelleConfirmer={T.trancher}
        libelleMotif={T.motifDecisionLibelle}
        danger={sanction}
        champs={champs}
        confirmerDesactive={decision === '' || (suspension && dateFin === '')}
        motifObligatoire
        motifMin={MOTIF_MIN}
        motifMax={MOTIF_MAX}
        enCours={trancher.isPending}
        erreur={trancher.isError ? messageDErreur(trancher.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={(motif) => {
          if (decision === '') return
          trancher.mutate(
            {
              decision,
              motif,
              ...(sanction && cible ? { cible } : {}),
              // Dakar est à UTC+0 : la suspension court jusqu'à la fin du jour choisi.
              ...(suspension ? { dateFin: `${dateFin}T23:59:59+00:00` } : {}),
            },
            {
              onSuccess: () => {
                fermer()
                onFait(T.tranche)
              },
            },
          )
        }}
      />
    </>
  )
}

const ABSENT = '—'
const heureDe = (iso: string) => formatHeureDuJour(iso)

/** Ligne « libellé / valeur » du bloc Réservation : une ligne sur deux grisée. */
function LigneInfo({ libelle, children, grise }: { libelle: string; children: ReactNode; grise: boolean }) {
  return (
    <div className={`flex justify-between gap-3 rounded-2xl px-4 py-3 ${grise ? 'bg-[#f3f4f7]' : ''}`}>
      <span className="font-medium text-[#6b7287]">{libelle}</span>
      <span className="text-right font-semibold">{children}</span>
    </div>
  )
}

function Personne({ nom, role, couleur, lien, libelleLien }: { nom: string; role: string; couleur: string; lien: string; libelleLien: string }) {
  const mots = nom.trim().split(/\s+/)
  const initiales = ((mots[0]?.[0] ?? '') + (mots.length > 1 ? (mots[mots.length - 1][0] ?? '') : (mots[0]?.[1] ?? ''))).toUpperCase()
  return (
    <div className="flex items-center gap-3 rounded-[22px] bg-[#f3f4f7] px-3.5 py-3">
      <span aria-hidden="true" className={`grid size-11 shrink-0 place-items-center rounded-full text-[13px] font-bold ${couleur}`}>
        {initiales}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-semibold">{nom}</span>
        <span className="mt-px block text-xs text-[#8a90a2]">{role}</span>
      </span>
      <LienRond vers={lien} libelle={libelleLien} blanc />
    </div>
  )
}

export function LitigeDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['litige', id], queryFn: () => api.litige(id) })
  const retour = useRetours()
  const [maintenant] = useState(() => Date.now())
  return (
    <PageDetailMq requete={requete} retour="/litiges" libelleRetour="Retour aux litiges">
      {(l) => {
        const paiement = l.paiements[0]
        const acompte = l.paiements.length === 0 ? null : l.paiements.reduce((somme, p) => somme + p.montantAcompte, 0)
        const depuis = Math.max(0, (maintenant - new Date(l.dateCreation).getTime()) / 60000)
        const ouvert = l.statut === 'ouvert'
        const reponse = l.reponsePrestataire
          ? `Réponse du prestataire${l.reponseLe ? ` le ${formatDate(l.reponseLe)}` : ''} : « ${l.reponsePrestataire} »`
          : 'Aucune réponse écrite du prestataire pour le moment'
        const cloture =
          [
            l.clotureLe ? formatDate(l.clotureLe) : null,
            l.decision ? (T.decisionsCourt[l.decision] ?? l.decision) : null,
            l.motifDecision ? `« ${l.motifDecision} »` : null,
          ]
            .filter(Boolean)
            .join(' · ') || ABSENT
        const nomPrestataire = l.prestataire.nomEntreprise ?? nomComplet(l.prestataire)
        return (
          <>
            <TitreDetail
              retour="/litiges"
              libelleRetour="Retour aux litiges"
              titre="Litige"
              suite={ABSENT}
              badge={
                <Badge ton={ouvert ? 'danger' : l.decision === 'rejet' ? 'neutre' : 'succes'} gras>
                  {libelleStatut(l.statut, l.decision)}
                </Badge>
              }
              infos={`Ouvert le ${formatDate(l.dateCreation)} à ${heureDe(l.dateCreation)}${ouvert ? ` · sans décision depuis ${dureeMinutes(depuis)}` : ''}`}
            >
              {ouvert && <ActionTrancher litige={l} onFait={retour.succes} />}
            </TitreDetail>
            <div className="flex flex-wrap items-start gap-3.5">
              <div className="flex min-w-0 flex-[2_1_560px] flex-col gap-3.5">
                <CarteMq>
                  <EnteteCarte icone={<IMessage />} titre="Motif du client" sousTitre={l.motif} />
                  <p className="mt-[18px] mb-0 rounded-[22px] bg-[#f3f4f7] px-5 py-[18px] text-sm leading-[22px] whitespace-pre-line text-[#3c4257]">« {l.description} »</p>
                </CarteMq>
                <CarteMq>
                  <EnteteCarte icone={<IHorloge />} titre="Chronologie" sousTitre="Du début de la réservation à aujourd'hui" />
                  <div className="mt-5 flex flex-col">
                    <EtapeChrono grand couleur="#1d4ed8" titre="Réservation confirmée" detail={paiement?.payeLe ? `${formatDate(paiement.payeLe)} · acompte payé` : ABSENT} />
                    <EtapeChrono grand couleur="#1d4ed8" titre="Prestation terminée par le prestataire" detail={ABSENT} />
                    <EtapeChrono grand couleur="#e5484d" titre="Litige ouvert par le client" detail={`${formatDate(l.dateCreation)} · ${heureDe(l.dateCreation)}`} />
                    {ouvert ? (
                      <EtapeChrono
                        grand
                        derniere
                        couleur="#cdd9fb"
                        titre="En attente d'une décision"
                        detail={reponse}
                      />
                    ) : (
                      <EtapeChrono grand derniere couleur="#1d4ed8" titre="Litige clôturé" detail={cloture} />
                    )}
                  </div>
                </CarteMq>
                <CarteMq>
                  <EnteteCarte icone={<IImage taille={18} trait={1.9} />} titre="Preuves jointes" sousTitre="Envoyées par le client" />
                  <div className="mt-[18px] grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3">
                    {l.preuves.length > 0
                      ? l.preuves.map((preuve, i) => (
                          <div key={preuve.id} className="flex h-[150px] items-center justify-center overflow-hidden rounded-[22px] bg-[#e7edff]">
                            <FichierProtege chemin={cheminApi(preuve.url)} alt={`Preuve ${i + 1} du client`} className="h-[150px] w-full rounded-[22px]!" />
                          </div>
                        ))
                      : [1, 2].map((n) => (
                          <div key={n} className="flex h-[150px] flex-col items-center justify-center gap-2 rounded-[22px] bg-[#e7edff] text-xs font-semibold text-[#6b84c9]">
                            <IImage taille={24} />
                            Photo {n} · {ABSENT}
                          </div>
                        ))}
                  </div>
                </CarteMq>
              </div>
              <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-3.5">
                <CarteMq>
                  <TitreCarte titre="Acompte concerné" sousTitre="Retenu en attendant la décision" />
                  <div className="mt-[18px] box-border rounded-[22px] p-5 text-white" style={{ background: DEGRADE_BLEU }}>
                    <div className="text-xs font-semibold opacity-80">Montant de l'acompte</div>
                    <div className="mt-1.5 flex items-baseline gap-2">
                      <span className="text-[38px] leading-[44px] font-bold tracking-[-1px] tabular-nums">{acompte === null ? ABSENT : formatNombre(acompte)}</span>
                      <span className="text-sm font-semibold opacity-85">FCFA</span>
                    </div>
                    <div className="mt-[18px] flex items-center justify-between text-xs">
                      <span className="opacity-80">Réservation {ABSENT}</span>
                      <span className="rounded-full bg-white/20 px-2.5 py-1 font-bold">{paiement ? (L.statuts[paiement.statut] ?? paiement.statut) : ABSENT}</span>
                    </div>
                  </div>
                </CarteMq>
                <CarteMq>
                  <TitreCarte titre="Réservation" />
                  <div className="mt-3.5 flex flex-col gap-1 text-[13px]">
                    <LigneInfo libelle="Service" grise>
                      {l.reservation.prestation}
                    </LigneInfo>
                    <LigneInfo libelle="Métier" grise={false}>
                      {ABSENT}
                    </LigneInfo>
                    <LigneInfo libelle="Date prévue" grise>{`${formatDate(l.reservation.dateDebut)} · ${heureDe(l.reservation.dateDebut)}`}</LigneInfo>
                    <LigneInfo libelle="Quartier" grise={false}>
                      {ABSENT}
                    </LigneInfo>
                  </div>
                </CarteMq>
                <CarteMq>
                  <TitreCarte titre="Personnes concernées" />
                  <div className="mt-3.5 flex flex-col gap-2.5">
                    <Personne nom={nomComplet(l.client)} role="Client" couleur="bg-[#dbeafe] text-[#1d4ed8]" lien={`/utilisateurs/${l.client.id}`} libelleLien="Voir le profil du client" />
                    <Personne nom={nomPrestataire} role={`Prestataire · note ${ABSENT}`} couleur="bg-[#fff0d4] text-[#983705]" lien={`/utilisateurs/${l.prestataire.id}`} libelleLien="Voir le profil du prestataire" />
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
