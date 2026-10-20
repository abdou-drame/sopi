import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { TypeSanction, UtilisateurDetail } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import Carte from '../components/ui/Carte'
import { ChampDate, Selecteur } from '../components/ui/Champs'
import Definitions from '../components/ui/Definitions'
import Fenetre from '../design/Fenetre'
import PageDetail from '../components/ui/PageDetail'
import { L } from '../i18n/libelles'
import { aujourdhui, formatDate, formatDateHeure, formatMontant } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'
import { Avatar, Badge, BoutonExporter, BoutonFiltre, Carte as CarteMq, LigneLien, Onglets, PiedListe, Recherche, TitrePage, type TonBadge } from '../design/composants'
import { EtoilePleine } from '../design/icones'
import { EtatsListe, useListe } from '../design/useListe'
import { BoutonLever, etatSanction } from './SanctionsPages'

const T = L.utilisateurs
const S = L.sanctions
const MOTIF_MIN = 10
const MOTIF_MAX = 1000

const TON_STATUT: Record<string, TonBadge> = { actif: 'succes', suspendu: 'danger', banni: 'danger', refuse: 'danger', en_attente: 'attention' }

const OPTIONS_ROLE = [
  { valeur: 'prestataire', libelle: 'Prestataires' },
  { valeur: 'client', libelle: 'Clients' },
]

export function UtilisateursListePage() {
  const etat = useFiltresUrl(['role', 'statut', 'q'], { role: 'prestataire' })
  const liste = useListe('utilisateurs', api.utilisateurs, etat)
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const compteurs: Record<string, number | undefined> = { prestataire: tableau?.comptes.prestataires.total, client: tableau?.comptes.clients.total }
  const prestataires = etat.valeurs.role !== 'client'
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Utilisateurs" suite="de Sopi" sousTitre="Prestataires et clients inscrits sur la plateforme.">
        <BoutonExporter />
      </TitrePage>
      <CarteMq className="flex flex-col gap-3.5">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            libelle={T.role}
            onglets={OPTIONS_ROLE.map((o) => ({
              cle: o.valeur,
              libelle: o.libelle,
              compteur: compteurs[o.valeur] ?? '—',
              actif: (etat.valeurs.role || 'prestataire') === o.valeur,
              onSelect: () => etat.definir({ role: o.valeur }),
            }))}
          />
          <div className="flex-1" />
          <Recherche valeur={etat.valeurs.q ?? ''} onChange={(v) => etat.definir({ q: v })} placeholder="Rechercher par nom ou téléphone" libelle="Rechercher un utilisateur" />
          <BoutonFiltre>Métier</BoutonFiltre>
          <BoutonFiltre>Statut</BoutonFiltre>
        </div>
        <div className="overflow-x-auto">
          <table className="mq-table min-w-[860px]" aria-label={T.legende}>
            <thead>
              <tr>
                <th>{prestataires ? 'Prestataire' : 'Client'}</th>
                <th>Métier</th>
                <th>Quartier</th>
                <th>Note</th>
                <th style={{ textAlign: 'right' }}>Réservations</th>
                <th>{L.commun.statut}</th>
                <th className="relative">
                  <span className="sr-only">Action</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {liste.lignes.map((u) => {
                const nom = u.prestataire?.nomEntreprise ?? `${u.prenom} ${u.nom}`
                return (
                  <LigneLien key={u.id} vers={`/utilisateurs/${u.id}`} libelle={`Voir le profil de ${nom}`}>
                    <td>
                      <div className="flex items-center gap-3">
                        <Avatar nom={nom} taille={40} />
                        <span>
                          <span className="block font-semibold">{nom}</span>
                          <span className="mt-px block text-[11px] text-[#9aa0b2]">{u.telephone}</span>
                        </span>
                      </div>
                    </td>
                    <td>{u.prestataire ? (L.metiers[u.prestataire.metier] ?? u.prestataire.metier) : '—'}</td>
                    <td>{u.prestataire?.quartier ?? '—'}</td>
                    <td>
                      <span className="inline-flex items-center gap-1 font-semibold">
                        <EtoilePleine />—
                      </span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <span className="font-semibold tabular-nums">—</span>
                    </td>
                    <td>
                      <Badge ton={TON_STATUT[u.statut] ?? 'neutre'}>{L.statuts[u.statut] ?? u.statut}</Badge>
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
      <Fenetre
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
  const retour = useRetours()
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/utilisateurs', libelle: T.retour }}
      titre={(u) => `${u.prenom} ${u.nom}`}
      sousTitre={(u) => `${L.roles[u.role] ?? u.role} · ${T.creeLe.toLowerCase()} ${formatDate(u.dateCreation)}`}
      badge={(u) => <BadgeStatut statut={u.statut} />}
      actions={(u) => (u.sanctionActive ? null : <ActionSanctionner utilisateur={u} onFait={retour.succes} />)}
    >
      {(u) => {
        const p = u.prestataire
        return (
          <>
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
              <HistoriqueSanctions id={u.id} onFait={retour.succes} />
            </Carte>
          </>
        )
      }}
    </PageDetail>
  )
}
