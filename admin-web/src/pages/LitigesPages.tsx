import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { CibleSanction, DecisionLitige, Litige, LitigeDetail, PartieLitige } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import Carte from '../components/ui/Carte'
import { ChampDate, Selecteur } from '../components/ui/Champs'
import Definitions from '../components/ui/Definitions'
import EnTetePage from '../components/ui/EnTetePage'
import FichierProtege, { cheminApi } from '../components/ui/FichierProtege'
import FiltresListe from '../components/ui/FiltresListe'
import LienLigne from '../components/ui/LienLigne'
import ListePaginee from '../components/ui/ListePaginee'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import PageDetail from '../components/ui/PageDetail'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { aujourdhui, formatDateHeure, formatMontant } from '../lib/format'
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

export function LitigesListePage() {
  const etat = useFiltresUrl(['statut'], { statut: 'ouvert' })
  const colonnes: Colonne<Litige>[] = [
    { cle: 'motif', titre: T.motif, rendu: (l) => <LienLigne vers={`/litiges/${l.id}`}>{l.motif}</LienLigne> },
    { cle: 'client', titre: T.client, rendu: (l) => nomComplet(l.client) },
    { cle: 'prestataire', titre: T.prestataire, rendu: (l) => l.prestataire.nomEntreprise ?? nomComplet(l.prestataire) },
    { cle: 'prestation', titre: T.prestation, rendu: (l) => l.reservation.prestation },
    { cle: 'date', titre: T.ouvertLe, rendu: (l) => formatDateHeure(l.dateCreation) },
    { cle: 'preuves', titre: T.preuves, alignement: 'droite', rendu: (l) => l.nombrePreuves },
    { cle: 'statut', titre: L.commun.statut, rendu: (l) => <BadgeStatut statut={l.statut} /> },
  ]
  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe legende={T.filtres} etat={etat} filtres={[{ cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT }]} />
      <ListePaginee legende={T.legende} cle="litiges" charger={api.litiges} etat={etat} colonnes={colonnes} cleLigne={(l) => l.id} lien={(l) => ({ vers: `/litiges/${l.id}`, nom: l.motif })} messageVide={T.vide} />
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
  return (
    <Carte titre={titre}>
      <Definitions
        elements={[
          { libelle: L.dossiers.nom, valeur: nomComplet(partie) },
          ...(entreprise ? [{ libelle: L.dossiers.entreprise, valeur: partie.nomEntreprise }] : []),
          { libelle: L.dossiers.telephone, valeur: partie.telephone },
          { libelle: L.dossiers.email, valeur: partie.email },
        ]}
      />
    </Carte>
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
        <>
          <div className="grid gap-5 lg:grid-cols-2">
            <Carte titre={T.description}>
              <p className="text-sm whitespace-pre-line text-texte">{l.description}</p>
            </Carte>
            <Carte titre={T.reponse}>
              {l.reponsePrestataire ? (
                <>
                  <p className="text-sm whitespace-pre-line text-texte">{l.reponsePrestataire}</p>
                  {l.reponseLe && <p className="mt-2 text-xs text-texte-doux">{T.reponduLe(formatDateHeure(l.reponseLe))}</p>}
                </>
              ) : (
                <p className="text-sm text-texte-doux">{T.aucuneReponse}</p>
              )}
            </Carte>
          </div>

          <Carte titre={T.preuves}>
            {l.preuves.length === 0 ? (
              <p className="text-sm text-texte-doux">{T.aucunePreuve}</p>
            ) : (
              <ul className="flex flex-wrap gap-4">
                {l.preuves.map((p, i) => (
                  <li key={p.id}>
                    <FichierProtege chemin={cheminApi(p.url)} alt={T.preuve(i + 1)} />
                  </li>
                ))}
              </ul>
            )}
          </Carte>

          <div className="grid gap-5 lg:grid-cols-2">
            <Partie titre={T.client} partie={l.client} />
            <Partie titre={T.prestataire} partie={l.prestataire} entreprise />
          </div>

          <div className="grid gap-5 lg:grid-cols-2">
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
            <Carte titre={T.paiements}>
              {l.paiements.length === 0 ? (
                <p className="text-sm text-texte-doux">{T.aucunPaiement}</p>
              ) : (
                <ul className="flex flex-col gap-3">
                  {l.paiements.map((p) => (
                    <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                      <span>
                        {T.acompte} {formatMontant(p.montantAcompte)} · {T.commission.toLowerCase()} {formatMontant(p.commission)}
                      </span>
                      <BadgeStatut statut={p.statut} />
                    </li>
                  ))}
                </ul>
              )}
            </Carte>
          </div>

          {l.decision && (
            <Carte titre={T.decision}>
              <Definitions
                elements={[
                  { libelle: T.decisionDe, valeur: T.decisionsCourt[l.decision] ?? l.decision },
                  { libelle: T.clotureLe, valeur: l.clotureLe ? formatDateHeure(l.clotureLe) : null },
                  { libelle: T.motifDecision, valeur: l.motifDecision, large: true },
                ]}
              />
            </Carte>
          )}
        </>
      )}
    </PageDetail>
  )
}
