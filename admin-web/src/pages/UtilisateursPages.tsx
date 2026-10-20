import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { TypeSanction, UtilisateurDetail } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import { BoutonAction } from '../design/boutons'
import CarteListe from '../design/CarteListe'
import { ChampJour, ChampSelect } from '../design/champs'
import { Avatar, Badge, Carte, EnteteCarte, GrilleTuiles, LienTexte, LigneLien, Onglets, TitreDetail, TitrePage, type TonBadge } from '../design/composants'
import { EtatErreur, EtatVide } from '../design/etats'
import Fenetre from '../design/Fenetre'
import { EtoilePleine, IUtilisateur } from '../design/icones'
import PageDetailMq from '../design/PageDetailMq'
import { BadgeDuStatut } from '../design/Statut'
import { useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { aujourdhui, formatDate, formatDateHeure, formatMontant } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'
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

const OPTIONS_STATUT = [
  { valeur: '', libelle: L.commun.tous },
  ...(['actif', 'suspendu', 'banni', 'refuse', 'en_attente'] as const).map((valeur) => ({ valeur, libelle: L.statuts[valeur] })),
]

export function UtilisateursListePage() {
  const etat = useFiltresUrl(['role', 'statut', 'q'], { role: 'prestataire' })
  const liste = useListe('utilisateurs', api.utilisateurs, etat)
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const compteurs: Record<string, number | undefined> = { prestataire: tableau?.comptes.prestataires.total, client: tableau?.comptes.clients.total }
  const prestataires = etat.valeurs.role !== 'client'
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Utilisateurs" suite="de Sopi" sousTitre="Prestataires et clients inscrits sur la plateforme." />
      <CarteListe
        legende={T.legende}
        etat={etat}
        liste={liste}
        messageVide={T.vide}
        actionsColonne="Action"
        largeurMin={860}
        avant={
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
        }
        filtres={[
          { cle: 'q', type: 'recherche', label: 'Rechercher un utilisateur', placeholder: 'Rechercher par nom ou téléphone' },
          { cle: 'statut', type: 'select', label: 'Statut', options: OPTIONS_STATUT },
        ]}
        colonnes={[{ titre: prestataires ? 'Prestataire' : 'Client' }, { titre: 'Métier' }, { titre: 'Quartier' }, { titre: 'Note' }, { titre: 'Réservations', droite: true }, { titre: L.commun.statut }]}
      >
        {liste.lignes.map((u) => {
          const nom = u.prestataire?.nomEntreprise ?? `${u.prenom} ${u.nom}`
          return (
            <LigneLien key={u.id} vers={`/utilisateurs/${u.id}`} libelle={`Voir le profil de ${nom}`} texte={L.commun.voirCourt}>
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
      </CarteListe>
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
      <ChampSelect
        label={T.typeChamp}
        value={type}
        onChange={(e) => setType(e.target.value as TypeSanction | '')}
        options={[{ valeur: '', libelle: T.choisirType }, ...Object.entries(S.types).map(([valeur, libelle]) => ({ valeur, libelle }))]}
      />
      {suspension && <ChampJour label={T.dateFin} value={dateFin} onChange={(e) => setDateFin(e.target.value)} min={aujourdhui()} />}
    </>
  )

  return (
    <>
      <BoutonAction variante="rouge" onClick={() => setOuverte(true)}>
        {T.poserSanction}
      </BoutonAction>
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

/** Historique des sanctions du compte : tableau Type, Motif, Début, Fin, État, avec « Lever » sur la sanction active. */
function HistoriqueSanctions({ id, onFait }: { id: string; onFait: (message: string) => void }) {
  const requete = useQuery({ queryKey: ['sanctions-utilisateur', id], queryFn: () => api.sanctionsUtilisateur(id) })
  if (requete.isPending) return <p className="m-0 text-sm text-[#8a90a2]">{L.commun.chargement}</p>
  if (requete.isError) return <EtatErreur texte={messageDErreur(requete.error)} onReessayer={() => void requete.refetch()} />
  const sanctions = requete.data.data
  if (sanctions.length === 0) return <EtatVide texte={T.aucuneSanction} />
  return (
    <div className="overflow-x-auto">
      <table className="mq-table" style={{ minWidth: 720 }} aria-label={T.historique}>
        <thead>
          <tr>
            <th>{S.type}</th>
            <th>{S.motif}</th>
            <th>{S.poseeLe}</th>
            <th>{S.fin}</th>
            <th>{L.commun.statut}</th>
            <th className="relative">
              <span className="sr-only">{S.actionsColonne}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {sanctions.map((s) => {
            const e = etatSanction(s)
            const nom = S.types[s.type] ?? s.type
            return (
              <tr key={s.id}>
                <td className="font-semibold">{nom}</td>
                <td className="text-[#3c4257]">
                  <span className="whitespace-pre-line">{s.motif}</span>
                  {(s.litigeId || s.avisId) && <span className="mt-px block text-[11px] text-[#9aa0b2]">{s.litigeId ? S.origineLitige : S.origineAvis}</span>}
                </td>
                <td>{formatDate(s.dateCreation)}</td>
                <td>{s.dateFin ? formatDate(s.dateFin) : <span className="text-[#9aa0b2]">—</span>}</td>
                <td>
                  <Badge ton={e.ton === 'succes' ? 'danger' : s.leveeLe ? 'succes' : 'neutre'}>{e.libelle}</Badge>
                </td>
                <td style={{ textAlign: 'right' }}>{s.active && <BoutonLever id={s.id} nom={nom} onFait={onFait} />}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

export function UtilisateurDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['utilisateur', id], queryFn: () => api.utilisateur(id) })
  const retour = useRetours()
  return (
    <PageDetailMq requete={requete} retour="/utilisateurs" libelleRetour={T.retour}>
      {(u) => {
        const p = u.prestataire
        return (
          <div className="flex flex-col gap-4">
            <TitreDetail
              retour="/utilisateurs"
              libelleRetour={T.retour}
              titre={`${u.prenom} ${u.nom}`}
              badge={<BadgeDuStatut statut={u.statut} />}
              infos={`${L.roles[u.role] ?? u.role} · ${T.creeLe.toLowerCase()} ${formatDate(u.dateCreation)}`}
            >
              {!u.sanctionActive && <ActionSanctionner utilisateur={u} onFait={retour.succes} />}
            </TitreDetail>

            <Carte>
              <EnteteCarte icone={<IUtilisateur />} titre={T.compte} />
              <GrilleTuiles
                elements={[
                  { libelle: T.telephone, valeur: u.telephone },
                  { libelle: T.email, valeur: u.email },
                  ...(p ? [{ libelle: T.entreprise, valeur: p.nomEntreprise }] : []),
                  ...(u.sanctionActive
                    ? [
                        {
                          libelle: T.sanctionActive,
                          valeur: `${S.types[u.sanctionActive.type] ?? u.sanctionActive.type}${u.sanctionActive.dateFin ? ` · ${S.fin.toLowerCase()} ${formatDateHeure(u.sanctionActive.dateFin)}` : ` · ${S.sansFin.toLowerCase()}`}`,
                        },
                      ]
                    : []),
                ]}
              />
            </Carte>

            {p && (
              <Carte>
                <EnteteCarte icone={<IUtilisateur />} titre={T.activite} />
                <GrilleTuiles
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
                        <ul className="m-0 flex list-none flex-wrap gap-3 p-0">
                          {Object.entries(p.reservationsParStatut).map(([statut, nombre]) => (
                            <li key={statut} className="flex items-center gap-1.5 text-sm">
                              <BadgeDuStatut statut={statut} />
                              <span className="font-semibold">{nombre}</span>
                            </li>
                          ))}
                        </ul>
                      ),
                    },
                    { libelle: T.voirReservations, valeur: <LienTexte vers={`/reservations?prestataire=${p.id}`}>{T.voirReservations}</LienTexte> },
                    { libelle: T.voirRetraits, valeur: <LienTexte vers={`/retraits?prestataire=${p.id}`}>{`${T.voirRetraits} (${p.nombreRetraits})`}</LienTexte> },
                  ]}
                />
              </Carte>
            )}

            <Carte>
              <EnteteCarte icone={<IUtilisateur />} titre={T.historique} sousTitre="Historique des sanctions de ce compte" />
              <div className="mt-3.5">
                <HistoriqueSanctions id={u.id} onFait={retour.succes} />
              </div>
            </Carte>
          </div>
        )
      }}
    </PageDetailMq>
  )
}
