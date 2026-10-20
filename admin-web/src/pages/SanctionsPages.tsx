import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { SanctionListe } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import { Avatar, Badge, BoutonPlus, Carte as CarteMq, DEGRADE_BLEU, Onglets, PiedListe, TitrePage, type TonBadge } from '../design/composants'
import { EtatsListe, useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatDate } from '../lib/format'
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
export function BoutonLever({ id, nom, onFait, mq = false }: { id: string; nom: string; onFait: (message: string) => void; mq?: boolean }) {
  const [ouverte, setOuverte] = useState(false)
  const lever = useAction(() => api.leverSanction(id))
  const fermer = () => {
    setOuverte(false)
    lever.reset()
  }
  return (
    <>
      {mq ? (
        <button
          type="button"
          aria-label={T.leverLibelle(nom)}
          onClick={() => setOuverte(true)}
          className="mq-btn-gris h-[34px] cursor-pointer rounded-[17px] border-none bg-[#f3f4f7] px-3.5 text-xs font-bold text-[#12172b]"
        >
          {T.lever}
        </button>
      ) : (
        <Bouton variante="secondaire" taille="p" aria-label={T.leverLibelle(nom)} onClick={() => setOuverte(true)}>
          {T.lever}
        </Bouton>
      )}
      <ModaleConfirmation
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

/** Statut du tableau : Active en rouge, Levée en vert, Terminée (arrivée à échéance) en neutre. */
function tonStatut(s: SanctionListe): TonBadge {
  if (s.active) return 'danger'
  return s.leveeLe ? 'succes' : 'neutre'
}

function useCompteSanctions(actives: string) {
  const { data } = useQuery({ queryKey: ['sanctions-compte', actives], queryFn: () => api.sanctions({ actives }), retry: false })
  return data?.meta.total
}

export default function SanctionsListePage() {
  const etat = useFiltresUrl(['actives', 'type', 'utilisateur', 'echeanceDansHeures'], { actives: 'true' })
  const liste = useListe('sanctions', api.sanctions, etat)
  const retour = useRetours()
  const navigate = useNavigate()
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const comptes: Record<string, number | undefined> = { true: useCompteSanctions('true'), false: useCompteSanctions('false'), '': useCompteSanctions('') }
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Sanctions" suite="prestataires" sousTitre="Suivez les avertissements et suspensions, et levez-les quand c'est justifié.">
        <BoutonPlus onClick={() => navigate('/utilisateurs')}>Nouvelle sanction</BoutonPlus>
      </TitrePage>
      <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-3.5">
        <CarteMq>
          <div className="text-[13px] leading-[normal] font-medium text-[#8a90a2]">Sanctions actives</div>
          <div className="mt-2 text-[40px] leading-[44px] font-bold tracking-[-1px]">{comptes.true ?? '—'}</div>
        </CarteMq>
        <CarteMq>
          <div className="text-[13px] leading-[normal] font-medium text-[#8a90a2]">Prestataires suspendus</div>
          <div className="mt-2 flex items-baseline gap-2.5">
            <span className="text-[40px] leading-[44px] font-bold tracking-[-1px]">{tableau?.sanctions.suspensionsEnCours ?? '—'}</span>
            <span className="rounded-full bg-[#fde9e9] px-2.5 py-1 text-[11px] leading-[normal] font-bold text-[#b0161b]">Hors ligne</span>
          </div>
        </CarteMq>
        <CarteMq className="text-white" style={{ background: DEGRADE_BLEU }}>
          <div className="text-[13px] leading-[normal] font-medium opacity-80">Levées ce mois-ci</div>
          <div className="mt-2 text-[40px] leading-[44px] font-bold tracking-[-1px]">—</div>
        </CarteMq>
      </div>
      <CarteMq className="flex flex-col gap-3.5">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            libelle={T.etat}
            onglets={OPTIONS_ETAT.map((o) => ({
              cle: o.valeur || 'toutes',
              libelle: o.libelle,
              compteur: comptes[o.valeur] ?? '—',
              actif: (etat.valeurs.actives ?? '') === o.valeur,
              onSelect: () => etat.definir({ actives: o.valeur }),
            }))}
          />
        </div>
        <div className="overflow-x-auto">
          <table className="mq-table min-w-[820px]" aria-label={T.legende}>
            <thead>
              <tr>
                <th>Prestataire</th>
                <th>{T.type}</th>
                <th>{T.motif}</th>
                <th>Début</th>
                <th>{T.fin}</th>
                <th>{L.commun.statut}</th>
                <th className="relative">
                  <span className="sr-only">{T.actionsColonne}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {liste.lignes.map((s) => {
                const nom = `${s.utilisateur.prenom} ${s.utilisateur.nom}`
                return (
                  <tr key={s.id}>
                    <td>
                      <div className="flex items-center gap-3">
                        <Avatar nom={nom} taille={36} />
                        <span className="font-semibold">{nom}</span>
                      </div>
                    </td>
                    <td>{T.types[s.type] ?? s.type}</td>
                    <td className="text-[#3c4257]">{s.motif}</td>
                    <td>{formatDate(s.dateCreation)}</td>
                    <td>{s.dateFin ? formatDate(s.dateFin) : <span className="text-[#9aa0b2]">—</span>}</td>
                    <td>
                      <Badge ton={tonStatut(s)}>{etatSanction(s).libelle}</Badge>
                    </td>
                    <td>
                      {s.active ? (
                        <BoutonLever mq id={s.id} nom={nom} onFait={retour.succes} />
                      ) : (
                        <span className="text-xs text-[#9aa0b2]">{T.etatTerminee}</span>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
        <EtatsListe chargement={liste.chargement} erreur={liste.erreur} vide={liste.donneesPresentes && liste.lignes.length === 0} messageVide={T.vide} onReessayer={liste.recharger} />
        {liste.totalPages > 1 && (
          <PiedListe
            resume={`${liste.premier} à ${liste.dernier} sur ${liste.total} ${liste.total > 1 ? 'résultats' : 'résultat'}`}
            precedent={liste.page > 1}
            suivant={liste.page < liste.totalPages}
            onPrecedent={() => etat.allerPage(liste.page - 1)}
            onSuivant={() => etat.allerPage(liste.page + 1)}
          />
        )}
      </CarteMq>
    </div>
  )
}
