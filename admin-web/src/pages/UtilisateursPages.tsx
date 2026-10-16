import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { TypeSanction, UtilisateurDetail, UtilisateurListe } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import BanniereAlerte from '../components/ui/BanniereAlerte'
import Bouton from '../components/ui/Bouton'
import Carte from '../components/ui/Carte'
import { ChampDate, Selecteur } from '../components/ui/Champs'
import Definitions from '../components/ui/Definitions'
import EnTetePage from '../components/ui/EnTetePage'
import FiltresListe from '../components/ui/FiltresListe'
import LienLigne from '../components/ui/LienLigne'
import ListePaginee from '../components/ui/ListePaginee'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import PageDetail from '../components/ui/PageDetail'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { aujourdhui, formatDate, formatDateHeure, formatMontant } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'
import { BoutonLever, etatSanction } from './SanctionsPages'

const T = L.utilisateurs
const S = L.sanctions
const MOTIF_MIN = 10
const MOTIF_MAX = 1000

const OPTIONS_ROLE = [
  { valeur: '', libelle: L.commun.tous },
  { valeur: 'client', libelle: L.roles.client },
  { valeur: 'prestataire', libelle: L.roles.prestataire },
]
const OPTIONS_STATUT = [
  { valeur: '', libelle: L.commun.tous },
  ...(['en_attente', 'actif', 'refuse', 'suspendu', 'banni'] as const).map((valeur) => ({ valeur, libelle: L.statuts[valeur] })),
]

export function UtilisateursListePage() {
  const etat = useFiltresUrl(['role', 'statut', 'q'])
  const colonnes: Colonne<UtilisateurListe>[] = [
    {
      cle: 'nom',
      titre: T.utilisateur,
      rendu: (u) => (
        <div>
          <LienLigne vers={`/utilisateurs/${u.id}`}>{`${u.prenom} ${u.nom}`}</LienLigne>
          {u.prestataire && <p className="text-xs text-texte-doux">{u.prestataire.nomEntreprise}</p>}
        </div>
      ),
    },
    { cle: 'role', titre: T.role, rendu: (u) => L.roles[u.role] ?? u.role },
    { cle: 'telephone', titre: T.telephone, rendu: (u) => u.telephone },
    { cle: 'email', titre: T.email, rendu: (u) => u.email ?? '—' },
    { cle: 'cree', titre: T.creeLe, rendu: (u) => formatDate(u.dateCreation) },
    { cle: 'statut', titre: L.commun.statut, rendu: (u) => <BadgeStatut statut={u.statut} /> },
  ]
  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[
          { cle: 'role', type: 'select', label: T.role, options: OPTIONS_ROLE },
          { cle: 'statut', type: 'select', label: T.statut, options: OPTIONS_STATUT },
          { cle: 'q', type: 'texte', label: T.recherche, placeholder: T.recherchePlaceholder },
        ]}
      />
      <ListePaginee legende={T.legende} cle="utilisateurs" charger={api.utilisateurs} etat={etat} colonnes={colonnes} cleLigne={(u) => u.id} lien={(u) => ({ vers: `/utilisateurs/${u.id}`, nom: `${u.prenom} ${u.nom}` })} messageVide={T.vide} />
    </div>
  )
}

function ActionSanctionner({ utilisateur, onFait }: { utilisateur: UtilisateurDetail; onFait: (message: string) => void }) {
  const [ouverte, setOuverte] = useState(false)
  const [type, setType] = useState<TypeSanction | ''>('')
  const [dateFin, setDateFin] = useState('')
  const poser = useAction((corps: Parameters<typeof api.poserSanction>[1]) => api.poserSanction(utilisateur.id, corps))
  const suspension = type === 'suspension_temporaire'

  const fermer = () => {
    setOuverte(false)
    setType('')
    setDateFin('')
    poser.reset()
  }

  const champs = (
    <>
      <Selecteur
        label={T.typeChamp}
        value={type}
        onChange={(e) => setType(e.target.value as TypeSanction | '')}
        options={[{ valeur: '', libelle: T.choisirType }, ...Object.entries(S.types).map(([valeur, libelle]) => ({ valeur, libelle }))]}
      />
      {suspension && <ChampDate label={T.dateFin} value={dateFin} onChange={setDateFin} min={aujourdhui()} />}
    </>
  )

  return (
    <>
      <Bouton variante="danger" onClick={() => setOuverte(true)}>
        {T.poserSanction}
      </Bouton>
      <ModaleConfirmation
        ouverte={ouverte}
        titre={T.poserTitre}
        description={T.poserTexte}
        libelleConfirmer={T.poserSanction}
        libelleMotif={T.motifSanction}
        danger
        champs={champs}
        confirmerDesactive={type === '' || (suspension && dateFin === '')}
        motifObligatoire
        motifMin={MOTIF_MIN}
        motifMax={MOTIF_MAX}
        enCours={poser.isPending}
        erreur={poser.isError ? messageDErreur(poser.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={(motif) => {
          if (type === '') return
          poser.mutate(
            {
              type,
              motif,
              // Dakar est à UTC+0 : la suspension court jusqu'à la fin du jour choisi.
              ...(suspension ? { dateFin: `${dateFin}T23:59:59+00:00` } : {}),
            },
            {
              onSuccess: () => {
                fermer()
                onFait(T.sanctionPosee)
              },
            },
          )
        }}
      />
    </>
  )
}

function HistoriqueSanctions({ id, onFait }: { id: string; onFait: (message: string) => void }) {
  const requete = useQuery({ queryKey: ['sanctions-utilisateur', id], queryFn: () => api.sanctionsUtilisateur(id) })
  if (requete.isPending) return <p className="text-sm text-texte-doux">{L.commun.chargement}</p>
  if (requete.isError) return <p role="alert" className="text-sm text-erreur-texte">{messageDErreur(requete.error)}</p>
  const sanctions = requete.data.data
  if (sanctions.length === 0) return <p className="text-sm text-texte-doux">{T.aucuneSanction}</p>
  return (
    <ul className="flex flex-col divide-y divide-bordure">
      {sanctions.map((s) => {
        const e = etatSanction(s)
        return (
          <li key={s.id} className="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <div className="min-w-0 flex-1">
              <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-texte">
                {S.types[s.type] ?? s.type}
                <BadgeStatut ton={e.ton} libelle={e.libelle} />
              </p>
              <p className="mt-1 text-sm whitespace-pre-line text-texte">{s.motif}</p>
              <p className="mt-1 text-xs text-texte-doux">
                {S.poseeLe} {formatDateHeure(s.dateCreation)}
                {s.dateFin ? ` · ${S.fin.toLowerCase()} ${formatDateHeure(s.dateFin)}` : ''}
                {s.leveeLe ? ` · ${S.leveeLe(formatDateHeure(s.leveeLe))}` : ''}
                {s.litigeId ? ` · ${S.origineLitige}` : ''}
                {s.avisId ? ` · ${S.origineAvis}` : ''}
              </p>
            </div>
            {s.active && <BoutonLever id={s.id} nom={S.types[s.type] ?? s.type} onFait={onFait} />}
          </li>
        )
      })}
    </ul>
  )
}

export function UtilisateurDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['utilisateur', id], queryFn: () => api.utilisateur(id) })
  const [message, setMessage] = useState<string | null>(null)
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/utilisateurs', libelle: T.retour }}
      titre={(u) => `${u.prenom} ${u.nom}`}
      sousTitre={(u) => `${L.roles[u.role] ?? u.role} · ${T.creeLe.toLowerCase()} ${formatDate(u.dateCreation)}`}
      badge={(u) => <BadgeStatut statut={u.statut} />}
      actions={(u) => (u.sanctionActive ? null : <ActionSanctionner utilisateur={u} onFait={setMessage} />)}
    >
      {(u) => {
        const p = u.prestataire
        return (
          <>
            {message && <BanniereAlerte gravite="info" titre={message} />}
            <div className="grid gap-5 lg:grid-cols-2">
              <Carte titre={T.compte}>
                <Definitions
                  elements={[
                    { libelle: T.telephone, valeur: u.telephone },
                    { libelle: T.email, valeur: u.email },
                    ...(p ? [{ libelle: T.entreprise, valeur: p.nomEntreprise }] : []),
                  ]}
                />
              </Carte>
              <Carte titre={T.sanctionActive}>
                {u.sanctionActive ? (
                  <p className="text-sm text-texte">
                    {S.types[u.sanctionActive.type] ?? u.sanctionActive.type}
                    {u.sanctionActive.dateFin ? ` · ${S.fin.toLowerCase()} ${formatDateHeure(u.sanctionActive.dateFin)}` : ` · ${S.sansFin.toLowerCase()}`}
                  </p>
                ) : (
                  <p className="text-sm text-texte-doux">{T.aucuneSanctionActive}</p>
                )}
              </Carte>
            </div>

            {p && (
              <Carte titre={T.activite}>
                <Definitions
                  elements={[
                    { libelle: T.metier, valeur: p.metier },
                    { libelle: T.quartier, valeur: p.quartier },
                    { libelle: T.adresse, valeur: p.adresse },
                    { libelle: T.solde, valeur: formatMontant(p.solde) },
                    { libelle: T.prestationsActives, valeur: p.prestationsActives },
                    { libelle: T.note, valeur: p.noteMoyenne === null ? T.aucuneNote : `${p.noteMoyenne} / 5 · ${T.avis(p.nombreAvis)}` },
                    { libelle: T.litiges, valeur: T.litigesTexte(p.litiges.total, p.litiges.ouverts) },
                    {
                      libelle: T.reservationsParStatut,
                      large: true,
                      valeur: (
                        <ul className="flex flex-wrap gap-2">
                          {Object.entries(p.reservationsParStatut).map(([statut, nombre]) => (
                            <li key={statut} className="flex items-center gap-1.5 text-sm">
                              <BadgeStatut statut={statut} />
                              <span className="font-semibold">{nombre}</span>
                            </li>
                          ))}
                        </ul>
                      ),
                    },
                  ]}
                />
                <div className="mt-4 flex flex-wrap gap-4 text-sm">
                  <Link to={`/reservations?prestataire=${p.id}`} className="font-semibold text-primaire-700 hover:underline">
                    {T.voirReservations}
                  </Link>
                  <Link to={`/retraits?prestataire=${p.id}`} className="font-semibold text-primaire-700 hover:underline">
                    {T.voirRetraits} ({p.nombreRetraits})
                  </Link>
                </div>
              </Carte>
            )}

            <Carte titre={T.historique}>
              <HistoriqueSanctions id={u.id} onFait={setMessage} />
            </Carte>
          </>
        )
      }}
    </PageDetail>
  )
}
