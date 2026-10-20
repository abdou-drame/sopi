import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { CibleSanction, DecisionLitige, LitigeDetail, PartieLitige } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import CarteStatutActuel from '../components/ui/CarteStatutActuel'
import { IconeAvis, IconeDossier, IconeLitige, IconeSanction } from '../components/ui/Icones'
import Carte from '../components/ui/Carte'
import { ChampDate, Selecteur } from '../components/ui/Champs'
import Definitions from '../components/ui/Definitions'
import FichierProtege, { cheminApi } from '../components/ui/FichierProtege'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import PageDetail from '../components/ui/PageDetail'
import { Badge, BoutonExporter, Carte as CarteMq, LigneLien, Onglets, PiedListe, Recherche, TitrePage, type TonBadge } from '../design/composants'
import { EtatsListe, useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { aujourdhui, formatDate, formatDateHeure, formatMontant } from '../lib/format'
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
      <TitrePage titre="Litiges" suite="à trancher" sousTitre="Examinez chaque litige, puis remboursez l'acompte ou rejetez la demande.">
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
          <Recherche valeur="" onChange={() => {}} placeholder="Rechercher un litige" libelle="Rechercher un litige" />
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
                      <Badge ton={l.statut === 'cloture' && l.decision === 'rejet' ? 'neutre' : (TON_STATUT[l.statut] ?? 'neutre')}>{L.statuts[l.statut] ?? l.statut}</Badge>
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
      <Selecteur
        label={T.decisionChamp}
        value={decision}
        onChange={(e) => setDecision(e.target.value as DecisionLitige | '')}
        options={[{ valeur: '', libelle: T.choisir }, ...Object.entries(T.decisions).map(([valeur, libelle]) => ({ valeur, libelle }))]}
      />
      {sanction && (
        <Selecteur
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
      {suspension && <ChampDate label={T.dateFin} value={dateFin} onChange={setDateFin} min={aujourdhui()} />}
    </>
  )

  return (
    <>
      <Bouton onClick={() => setOuverte(true)}>{T.trancher}</Bouton>
      <ModaleConfirmation
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

function Partie({ titre, partie, entreprise }: { titre: string; partie: PartieLitige; entreprise?: boolean }) {
  const nom = entreprise && partie.nomEntreprise ? partie.nomEntreprise : nomComplet(partie)
  const initiales = `${partie.prenom.charAt(0)}${partie.nom.charAt(0)}`.toUpperCase()
  return (
    <li className="flex items-center gap-3 rounded-[22px] bg-fond px-3.5 py-3">
      <span
        aria-hidden="true"
        className={`grid size-11 shrink-0 place-items-center rounded-full text-[13px] font-bold ${entreprise ? 'bg-[#fff0d4] text-[#983705]' : 'bg-[#dbeafe] text-[#1d4ed8]'}`}
      >
        {initiales}
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold">{nom}</p>
        <p className="mt-px text-xs text-texte-discret">{titre}</p>
        <p className="flex flex-wrap gap-x-2 text-xs break-words text-texte-doux">
          <span>{partie.telephone}</span>
          <span>{partie.email}</span>
        </p>
      </div>
    </li>
  )
}

export function LitigeDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['litige', id], queryFn: () => api.litige(id) })
  const retour = useRetours()
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/litiges', libelle: T.retour }}
      titre={(l) => l.motif}
      sousTitre={(l) => `${l.reservation.prestation} · ${T.ouvertLe.toLowerCase()} ${formatDateHeure(l.dateCreation)}`}
      badge={(l) => <BadgeStatut statut={l.statut} />}
      actions={(l) => (l.statut === 'ouvert' ? <ActionTrancher litige={l} onFait={retour.succes} /> : null)}
    >
      {(l) => (
        <div className="flex flex-wrap items-start gap-3.5">
          <div className="flex min-w-0 flex-[2_1_560px] flex-col gap-3.5">
            <Carte titre={T.description} icone={<IconeLitige width={18} height={18} />}>
              <p className="rounded-[22px] bg-fond px-5 py-[18px] text-sm leading-[22px] whitespace-pre-line text-texte">{l.description}</p>
            </Carte>
            <Carte titre={T.reponse} icone={<IconeAvis width={18} height={18} />}>
              {l.reponsePrestataire ? (
                <>
                  <p className="rounded-[22px] bg-fond px-5 py-[18px] text-sm leading-[22px] whitespace-pre-line text-texte">{l.reponsePrestataire}</p>
                  {l.reponseLe && <p className="mt-2 text-xs text-texte-doux">{T.reponduLe(formatDateHeure(l.reponseLe))}</p>}
                </>
              ) : (
                <p className="text-sm text-texte-doux">{T.aucuneReponse}</p>
              )}
            </Carte>
            <Carte titre={T.preuves} icone={<IconeDossier width={18} height={18} />}>
              {l.preuves.length === 0 ? (
                <p className="text-sm text-texte-doux">{T.aucunePreuve}</p>
              ) : (
                <ul className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3">
                  {l.preuves.map((p, i) => (
                    <li key={p.id} className="grid place-items-center rounded-[22px] bg-primaire-100 p-3">
                      <FichierProtege chemin={cheminApi(p.url)} alt={T.preuve(i + 1)} />
                    </li>
                  ))}
                </ul>
              )}
            </Carte>
            {l.decision && (
              <Carte titre={T.decision} icone={<IconeSanction width={18} height={18} />}>
                <Definitions
                  elements={[
                    { libelle: T.decisionDe, valeur: T.decisionsCourt[l.decision] ?? l.decision },
                    { libelle: T.clotureLe, valeur: l.clotureLe ? formatDateHeure(l.clotureLe) : null },
                    { libelle: T.motifDecision, valeur: l.motifDecision, large: true },
                  ]}
                />
              </Carte>
            )}
          </div>

          <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-3.5">
            <CarteStatutActuel
              titre={T.paiements}
              libelleStatut={T.acompte}
              statut={l.paiements.length === 0 ? '—' : formatMontant(l.paiements.reduce((somme, p) => somme + p.montantAcompte, 0))}
              note={
                l.paiements.length === 0 ? (
                  T.aucunPaiement
                ) : (
                  <ul className="flex flex-col gap-1.5">
                    {l.paiements.map((p) => (
                      <li key={p.id} className="flex flex-wrap items-center justify-between gap-2">
                        <span>
                          {T.acompte} {formatMontant(p.montantAcompte)} · {T.commission.toLowerCase()} {formatMontant(p.commission)}
                        </span>
                        <span className="rounded-full bg-white/20 px-2.5 py-1 font-bold">{L.statuts[p.statut] ?? p.statut}</span>
                      </li>
                    ))}
                  </ul>
                )
              }
            />
            <Carte titre={T.reservation}>
              <Definitions
                elements={[
                  { libelle: T.prestation, valeur: l.reservation.prestation },
                  { libelle: L.commun.statut, valeur: <BadgeStatut statut={l.reservation.statut} /> },
                  { libelle: T.montantReservation, valeur: formatMontant(l.prixApplique) },
                  {
                    libelle: T.reservation,
                    valeur: (
                      <Link to={`/reservations/${l.reservation.id}`} className="font-semibold text-primaire-700 hover:underline">
                        {T.voirReservation}
                      </Link>
                    ),
                  },
                ]}
              />
            </Carte>
            <Carte titre={T.parties}>
              <ul className="flex flex-col gap-2.5">
                <Partie titre={T.client} partie={l.client} />
                <Partie titre={T.prestataire} partie={l.prestataire} entreprise />
              </ul>
            </Carte>
          </div>
        </div>
      )}
    </PageDetail>
  )
}
