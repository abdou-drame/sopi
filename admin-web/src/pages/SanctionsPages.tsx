import { useState } from 'react'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { SanctionListe } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import EnTetePage from '../components/ui/EnTetePage'
import FiltresListe from '../components/ui/FiltresListe'
import LienLigne from '../components/ui/LienLigne'
import ListePaginee from '../components/ui/ListePaginee'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { formatDateHeure } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.sanctions

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
      <Bouton variante="secondaire" taille="p" aria-label={T.leverLibelle(nom)} onClick={() => setOuverte(true)}>
        {T.lever}
      </Bouton>
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

export default function SanctionsListePage() {
  const etat = useFiltresUrl(['actives', 'type', 'utilisateur', 'echeanceDansHeures'], { actives: 'true' })
  const retour = useRetours()
  const colonnes: Colonne<SanctionListe>[] = [
    {
      cle: 'utilisateur',
      titre: T.utilisateur,
      rendu: (s) => (
        <div>
          <LienLigne vers={`/utilisateurs/${s.utilisateur.id}`}>{`${s.utilisateur.prenom} ${s.utilisateur.nom}`}</LienLigne>
          <p className="text-xs text-texte-doux">{L.roles[s.utilisateur.role] ?? s.utilisateur.role}</p>
        </div>
      ),
    },
    { cle: 'type', titre: T.type, rendu: (s) => T.types[s.type] ?? s.type },
    { cle: 'motif', titre: T.motif, rendu: (s) => <span className="line-clamp-2 max-w-xs">{s.motif}</span> },
    { cle: 'pose', titre: T.poseeLe, rendu: (s) => formatDateHeure(s.dateCreation) },
    { cle: 'fin', titre: T.fin, rendu: (s) => (s.dateFin ? formatDateHeure(s.dateFin) : T.sansFin) },
    {
      cle: 'etat',
      titre: T.etat,
      rendu: (s) => {
        const e = etatSanction(s)
        return <BadgeStatut ton={e.ton} libelle={e.libelle} />
      },
    },
    {
      cle: 'action',
      titre: T.actionsColonne,
      alignement: 'droite',
      rendu: (s) => (s.active ? <BoutonLever id={s.id} nom={`${s.utilisateur.prenom} ${s.utilisateur.nom}`} onFait={retour.succes} /> : null),
    },
  ]
  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[
          {
            cle: 'actives',
            type: 'select',
            label: T.etat,
            options: [
              { valeur: 'true', libelle: T.actives },
              { valeur: 'false', libelle: T.nonActives },
              { valeur: '', libelle: T.toutes },
            ],
          },
          {
            cle: 'type',
            type: 'select',
            label: T.type,
            options: [{ valeur: '', libelle: L.commun.tous }, ...Object.entries(T.types).map(([valeur, libelle]) => ({ valeur, libelle }))],
          },
          { cle: 'echeanceDansHeures', type: 'nombre', label: T.echeance },
          { cle: 'utilisateur', type: 'texte', label: T.utilisateurFiltre },
        ]}
      />
      <ListePaginee legende={T.legende} cle="sanctions" charger={api.sanctions} etat={etat} colonnes={colonnes} cleLigne={(s) => s.id} lien={(s) => ({ vers: `/utilisateurs/${s.utilisateur.id}`, nom: `${s.utilisateur.prenom} ${s.utilisateur.nom}` })} messageVide={T.vide} />
    </div>
  )
}
