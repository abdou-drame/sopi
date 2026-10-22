import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import type { ReactNode } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { SanctionListe } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import Fenetre from '../design/Fenetre'
import { Avatar, BoutonPlus, Carte as CarteMq, Onglets, PaginationRonde, TitrePage } from '../design/composants'
import { IRecherche } from '../design/icones'
import { CarteBleue, CarteStat, IFiltre, Pastille, sansAccent } from '../design/synthese'
import { CartesSquelette } from '../design/etats'
import TableauDonnees from '../design/tableau'
import { BarreActionsGroupees } from '../design/ActionsGroupees'
import { useSelection } from '../design/useSelection'
import { EtatsListe, useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatDate, nombreDeJours } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.sanctions

const OPTIONS_ETAT = [
  { valeur: 'true', libelle: T.actives },
  { valeur: 'false', libelle: T.nonActives },
  { valeur: '', libelle: T.toutes },
]

/** État lisible d'une sanction : active, levée par un administrateur, ou arrivée à échéance. */
export function etatSanction(s: { active: boolean; leveeLe: string | null }): { ton: 'succes' | 'neutre'; libelle: string } {
  if (s.active) return { ton: 'succes', libelle: T.etatActive }
  return { ton: 'neutre', libelle: s.leveeLe ? T.etatLevee : T.etatTerminee }
}

/** Bouton « Lever » avec sa confirmation (action irréversible : la sanction ne peut pas être reposée telle quelle). */
export function BoutonLever({ id, nom, onFait }: { id: string; nom: string; onFait: (message: string) => void }) {
  const [ouverte, setOuverte] = useState(false)
  const lever = useAction(() => api.leverSanction(id))
  const fermer = () => {
    setOuverte(false)
    lever.reset()
  }
  return (
    <>
      <button
        type="button"
        aria-label={T.leverLibelle(nom)}
        onClick={() => setOuverte(true)}
        className="mq-btn-gris inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-full border border-[#e6e8ee] bg-white px-3.5 text-xs font-bold text-[#12172b]"
      >
        <IDeverrouille />
        {T.lever}
      </button>
      <Fenetre
        ouverte={ouverte}
        titre={T.leverTitre}
        description={T.leverTexte}
        libelleConfirmer={T.lever}
        enCours={lever.isPending}
        erreur={lever.isError ? messageDErreur(lever.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={() =>
          lever.mutate(undefined, {
            onSuccess: () => {
              fermer()
              onFait(T.levee)
            },
          })
        }
      />
    </>
  )
}

const ICO = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true } as const
const IBouclier = () => (
  <svg {...ICO}>
    <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z" />
  </svg>
)
const IAlerte = () => (
  <svg {...ICO}>
    <path d="M12 4L2.5 20h19L12 4zM12 10v4M12 17v.01" />
  </svg>
)
const IInterdit = () => (
  <svg {...ICO}>
    <circle cx="12" cy="12" r="9" />
    <path d="M5.7 5.7l12.6 12.6" />
  </svg>
)
const IPause = () => (
  <svg {...ICO}>
    <rect x="6" y="5" width="4" height="14" rx="1" />
    <rect x="14" y="5" width="4" height="14" rx="1" />
  </svg>
)
const IChevron = () => (
  <svg {...ICO} width={14} height={14}>
    <path d="M9 6l6 6-6 6" />
  </svg>
)
const IDeverrouille = () => (
  <svg {...ICO} width={14} height={14}>
    <rect x="5" y="11" width="14" height="9" rx="2" />
    <path d="M8 11V8a4 4 0 017.5-2" />
  </svg>
)

/** Pastille de statut du tableau : Active en rouge, Levée en vert, Terminée (arrivée à échéance) en neutre. */
const PASTILLE_ETAT = {
  active: { fond: 'bg-[#fde9e9] text-[#b0161b]', point: '#e5484d' },
  levee: { fond: 'bg-[#d7f7e4] text-[#0e6245]', point: '#16a34a' },
  terminee: { fond: 'bg-[#eef1f6] text-[#4f566b]', point: '#6b7287' },
}
function pastilleEtat(s: SanctionListe) {
  if (s.active) return PASTILLE_ETAT.active
  return s.leveeLe ? PASTILLE_ETAT.levee : PASTILLE_ETAT.terminee
}

const TYPE_STYLE: Record<string, { fond: string; icone: ReactNode }> = {
  avertissement: { fond: 'bg-[#fff0d4] text-[#983705]', icone: <IAlerte /> },
  suspension_temporaire: { fond: 'bg-neutre-claire text-neutre', icone: <IPause /> },
  bannissement_definitif: { fond: 'bg-[#fde9e9] text-[#b0161b]', icone: <IInterdit /> },
}

/** « Suspension 7 j » : la durée d'une suspension est calculée entre le début et la fin ; les autres types gardent leur libellé. */
function libelleType(s: SanctionListe): string {
  if (s.type === 'suspension_temporaire' && s.dateFin) {
    const jours = Math.max(1, nombreDeJours(s.dateCreation.slice(0, 10), s.dateFin.slice(0, 10)) - 1)
    return `Suspension ${jours} j`
  }
  return T.types[s.type] ?? s.type
}

function useCompteSanctions(params: Record<string, string>) {
  const { data } = useQuery({ queryKey: ['sanctions-compte', params], queryFn: () => api.sanctions(params), retry: false })
  return data?.meta.total
}

const OPTIONS_TYPE = Object.entries(T.types)

export default function SanctionsListePage() {
  const etat = useFiltresUrl(['actives', 'type', 'utilisateur', 'echeanceDansHeures'], { actives: 'true' })
  const liste = useListe('sanctions', api.sanctions, etat)
  const retour = useRetours()
  const navigate = useNavigate()
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const [recherche, setRecherche] = useState('')
  const actives = useCompteSanctions({ actives: 'true' })
  const terminees = useCompteSanctions({ actives: 'false' })
  const toutes = useCompteSanctions({ actives: '' })
  const avertissements = useCompteSanctions({ actives: 'true', type: 'avertissement' })
  const bannissements = useCompteSanctions({ actives: 'true', type: 'bannissement_definitif' })
  const comptes: Record<string, number | undefined> = { true: actives, false: terminees, '': toutes }
  const suspendus = tableau?.sanctions.suspensionsEnCours
  const detail =
    actives === undefined || avertissements === undefined || bannissements === undefined || suspendus === undefined
      ? '—'
      : actives === 0
        ? 'Aucune sanction en cours'
        : [
            bannissements > 0 && `${bannissements} bannissement${bannissements > 1 ? 's' : ''}`,
            suspendus > 0 && `${suspendus} suspension${suspendus > 1 ? 's' : ''}`,
            avertissements > 0 && `${avertissements} avertissement${avertissements > 1 ? 's' : ''}`,
          ]
            .filter(Boolean)
            .join(' · ')

  const lignes = liste.lignes.filter((s) => sansAccent(`${s.utilisateur.prenom} ${s.utilisateur.nom}`).includes(sansAccent(recherche.trim())))
  const nomSanctionne = (s: SanctionListe) => `${s.utilisateur.prenom} ${s.utilisateur.nom}`
  const selection = useSelection(lignes, (s) => s.id, {
    libelle: (s) => T.libelleLigne(nomSanctionne(s)),
    peutCocher: (s) => s.active,
    reinitialiserQuand: `${JSON.stringify(etat.parametresApi)}|${recherche.trim()}`,
  })

  return (
    <div className="flex flex-col gap-3">
      <TitrePage compact titre="Sanctions" suite="prestataires" sousTitre="Suivez les avertissements et suspensions, et levez-les quand c'est justifié.">
        <BoutonPlus onClick={() => navigate('/utilisateurs')}>Nouvelle sanction</BoutonPlus>
      </TitrePage>

      {liste.chargement && <CartesSquelette n={4} />}
      <div hidden={liste.chargement} className="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(190px,1fr))]">
        <CarteBleue icone={<IBouclier />} titre="Sanctions actives" valeur={actives} unite={toutes === undefined ? '' : `sur ${toutes}`} note={detail} />
        <CarteStat
          icone={<IAlerte />}
          teinte="bg-[#fff0d4] text-[#d97706]"
          badge="Actif"
          badgeTeinte="bg-[#fff0d4] text-[#983705]"
          titre="Avertissements"
          valeur={avertissements}
          note={avertissements === undefined ? undefined : avertissements === 0 ? 'Aucun avertissement actif' : `Prestataire${avertissements > 1 ? 's' : ''} toujours en ligne`}
        />
        <CarteStat
          icone={<IInterdit />}
          teinte="bg-[#fde9e9] text-[#e5484d]"
          badge="Définitif"
          badgeTeinte="bg-[#fde9e9] text-[#b0161b]"
          titre="Bannissements"
          valeur={bannissements}
          note={bannissements === undefined ? undefined : bannissements === 0 ? 'Aucun bannissement actif' : 'Compte fermé à vie'}
        />
        <CarteStat
          icone={<IPause />}
          teinte="bg-marque-claire text-marque"
          badge="Hors ligne"
          badgeTeinte="bg-[#eef1f6] text-[#4f566b]"
          titre="Prestataires suspendus"
          valeur={suspendus}
          note={terminees === undefined ? undefined : `${terminees} sanction${terminees > 1 ? 's' : ''} terminée${terminees > 1 ? 's' : ''} ou levée${terminees > 1 ? 's' : ''}`}
        />
      </div>

      <CarteMq className="flex flex-col gap-2 !p-3 !rounded-[24px]">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            pastille
            libelle={T.etat}
            onglets={OPTIONS_ETAT.map((o) => ({
              cle: o.valeur || 'toutes',
              libelle: o.libelle,
              compteur: comptes[o.valeur] ?? '—',
              actif: (etat.valeurs.actives ?? '') === o.valeur,
              onSelect: () => etat.definir({ actives: o.valeur }),
            }))}
          />
          <div className="ml-auto flex flex-wrap items-center gap-2.5">
            <label className="flex h-8 w-[260px] max-w-full items-center gap-2.5 rounded-[18px] border border-[#e6e8ee] bg-[#f6f7fa] px-4 text-[#9aa0b2] focus-within:border-marque">
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
            <label className="relative flex h-8 items-center gap-2 rounded-[18px] border border-[#e6e8ee] bg-white pr-4 pl-4 text-[13px] font-semibold text-[#12172b]">
              <IFiltre />
              <select value={etat.valeurs.type} onChange={(e) => etat.definir({ type: e.target.value })} aria-label="Filtrer par type" className="cursor-pointer appearance-none border-none bg-transparent pr-1 text-[13px] font-semibold outline-none">
                <option value="">{T.type}</option>
                {OPTIONS_TYPE.map(([code, libelle]) => (
                  <option key={code} value={code}>
                    {libelle}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>
        <TableauDonnees
          variante="compact"
          legende={T.legende}
          lignes={lignes}
          largeurMin={860}
          actionsColonne={T.actionsColonne}
          libelleActions="Actions"
          masque={liste.chargement}
          selection={selection}
          colonnes={[{ titre: 'Prestataire', tri: nomSanctionne }, { titre: T.type, tri: (s) => libelleType(s) }, { titre: T.motif }, { titre: 'Début', tri: (s) => s.dateCreation }, { titre: T.fin, tri: (s) => s.dateFin }, { titre: L.commun.statut, tri: (s) => etatSanction(s).libelle }]}
        >
              {(triees, ctx) => triees.map((s) => {
                const nom = `${s.utilisateur.prenom} ${s.utilisateur.nom}`
                const type = TYPE_STYLE[s.type] ?? TYPE_STYLE.avertissement
                const etatStyle = pastilleEtat(s)
                return (
                  <tr key={s.id} {...ctx.proprietes(s)}>
                    {ctx.caseACocher(s)}
                    <td>
                      <div className="flex items-center gap-2.5">
                        <Avatar nom={nom} taille={30} />
                        <span className="font-semibold whitespace-nowrap text-[#12172b]">{nom}</span>
                      </div>
                    </td>
                    <td>
                      <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${type.fond}`}>
                        <span className="[&>svg]:size-3.5">{type.icone}</span>
                        {libelleType(s)}
                      </span>
                    </td>
                    <td className="max-w-[280px] text-[#3c4257]">
                      <span className="line-clamp-1">{s.motif}</span>
                    </td>
                    <td className="whitespace-nowrap text-[#6b7287]">{formatDate(s.dateCreation)}</td>
                    <td className="whitespace-nowrap text-[#6b7287]">{s.dateFin ? formatDate(s.dateFin) : 'Aucune'}</td>
                    <td>
                      <Pastille fond={etatStyle.fond} point={etatStyle.point} libelle={etatSanction(s).libelle} />
                    </td>
                    <td>
                      <div className="flex items-center justify-end gap-2">
                        {s.active && <BoutonLever id={s.id} nom={nom} onFait={retour.succes} />}
                        <Link to={`/utilisateurs/${s.utilisateur.id}`} aria-label={`Voir le détail de ${nom}`} title="Voir le détail" className="mq-rond grid size-8 shrink-0 place-items-center rounded-full border border-[#e6e8ee] bg-white text-[#12172b]">
                          <IChevron />
                        </Link>
                      </div>
                    </td>
                  </tr>
                )
              })}
        </TableauDonnees>
        <BarreActionsGroupees
          selection={selection}
          actions={[
            {
              libelle: T.lever,
              elements: selection.coches.map((s) => ({ id: s.id, libelle: nomSanctionne(s) })),
              titre: T.leverLotTitre,
              description: T.leverTexte,
              libelleConfirmer: T.lever,
              executer: (id) => api.leverSanction(id),
              messageSucces: T.leverLotFait,
            },
          ]}
        />
        <EtatsListe
          chargement={liste.chargement}
          erreur={liste.erreur}
          vide={liste.donneesPresentes && lignes.length === 0}
          messageVide={T.vide}
          onReessayer={liste.recharger}
          colonnes={[{ titre: 'Prestataire' }, { titre: T.type }, { titre: T.motif }, { titre: 'Début' }, { titre: T.fin }, { titre: L.commun.statut }]}
          largeurMin={860}
          compact
          filtresActifs={recherche.trim() !== '' || etat.modifies}
          messageFiltres={recherche.trim() !== '' && liste.lignes.length > 0 ? 'Aucune sanction de cette page ne correspond à la recherche.' : undefined}
          onReinitialiser={() => {
            setRecherche('')
            etat.reinitialiser()
          }}
        />
        {liste.total > 0 && (
          <PaginationRonde
            resume={
              <>
                Affichage de <strong className="font-semibold text-[#12172b]">{liste.premier}–{liste.dernier}</strong> sur {liste.total} {liste.total > 1 ? 'sanctions' : 'sanction'}
              </>
            }
            page={liste.page}
            totalPages={liste.totalPages}
            onPage={etat.allerPage}
          />
        )}
      </CarteMq>
    </div>
  )
}
