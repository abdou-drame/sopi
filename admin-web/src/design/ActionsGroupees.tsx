import { useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { messageDErreur } from '../api/client'
import { useRetours } from '../components/ui/Alertes'
import { L } from '../i18n/libelles'
import { usePresence } from './animation/usePresence'
import Fenetre from './Fenetre'
import type { Selection } from './useSelection'

export interface ElementLot {
  id: string
  libelle: string
}

export interface ResultatLot {
  reussis: string[]
  echecs: { id: string; libelle: string; message: string }[]
}

/** Exécute l'action élément par élément (un appel d'API chacun, sans s'arrêter à la première erreur). */
export async function executerEnLot(elements: ElementLot[], action: (id: string) => Promise<unknown>): Promise<ResultatLot> {
  const resultat: ResultatLot = { reussis: [], echecs: [] }
  for (const e of elements) {
    try {
      await action(e.id)
      resultat.reussis.push(e.id)
    } catch (erreur) {
      resultat.echecs.push({ id: e.id, libelle: e.libelle, message: messageDErreur(erreur) })
    }
  }
  return resultat
}

/** « 2 sur 3 traités. 1 en échec : Nom : message » */
export function resumeEchecs(total: number, resultat: ResultatLot): string {
  const detail = resultat.echecs.map((e) => `${e.libelle} : ${e.message}`).join(' ; ')
  return `${L.tableaux.traites(resultat.reussis.length, total)} ${L.tableaux.enEchec(resultat.echecs.length)} ${detail}`
}

export interface ActionGroupee {
  /** Libellé du bouton de la barre, sans le nombre. */
  libelle: string
  elements: ElementLot[]
  titre: string
  description: ReactNode
  libelleConfirmer: string
  danger?: boolean
  motifObligatoire?: boolean
  motifMin?: number
  motifMax?: number
  libelleMotif?: string
  /** Appel d'API pour un élément (le motif n'est transmis que si la fenêtre en demande un). */
  executer: (id: string, motif: string) => Promise<unknown>
  messageSucces: (n: number) => string
}

/** Barre fixe en bas d'écran, visible dès qu'au moins une ligne est cochée. */
export function BarreActionsGroupees<T>({ selection, actions }: { selection: Selection<T>; actions: ActionGroupee[] }) {
  const { monte, sortie } = usePresence(selection.nombre > 0)
  const [ouverte, setOuverte] = useState<number | null>(null)
  if (!monte && ouverte === null) return null
  const active = ouverte === null ? null : actions[ouverte]
  return (
    <>
      {monte && (
        <div
          role="toolbar"
          aria-label={L.tableaux.actionsGroupees}
          data-sortie={sortie || undefined}
          className="anim-barre fixed inset-x-0 bottom-5 z-40 mx-auto flex w-fit max-w-[calc(100vw-32px)] flex-wrap items-center justify-center gap-3 rounded-full bg-[#12172b] py-2 pr-2 pl-5 text-[13px] font-semibold text-white shadow-[0_12px_32px_rgba(18,23,43,0.3)] data-[sortie]:pointer-events-none"
        >
          <span aria-live="polite">{L.tableaux.selectionnes(selection.nombre)}</span>
          {actions.map((a, i) => (
            <button
              key={a.libelle}
              type="button"
              disabled={a.elements.length === 0}
              onClick={() => setOuverte(i)}
              className="h-9 cursor-pointer rounded-full border-none bg-white px-4 text-xs font-bold text-[#12172b] disabled:cursor-not-allowed disabled:opacity-40"
            >
              {`${a.libelle} (${a.elements.length})`}
            </button>
          ))}
          <button type="button" onClick={selection.vider} className="h-9 cursor-pointer rounded-full border-none bg-transparent px-3 text-xs font-bold text-white/80 hover:text-white">
            {L.tableaux.toutDeselectionner}
          </button>
        </div>
      )}
      {active && <ConfirmationGroupee action={active} selection={selection} onFerme={() => setOuverte(null)} />}
    </>
  )
}

/**
 * Confirmation d'une action groupée : rappelle le nombre d'éléments concernés, exécute le lot puis résume.
 * En cas d'échec partiel la fenêtre reste ouverte avec le détail, seules les lignes en échec restent cochées
 * et « Confirmer » relance l'action sur ces seules lignes.
 */
export function ConfirmationGroupee<T>({ action, selection, onFerme }: { action: ActionGroupee; selection: Selection<T>; onFerme: () => void }) {
  const client = useQueryClient()
  const retour = useRetours()
  const [enCours, setEnCours] = useState(false)
  const [erreur, setErreur] = useState<string>()
  const [restants, setRestants] = useState<ElementLot[]>(action.elements)
  const total = restants.length

  const confirmer = async (motif: string) => {
    setEnCours(true)
    setErreur(undefined)
    const resultat = await executerEnLot(restants, (id) => action.executer(id, motif))
    await client.invalidateQueries()
    setEnCours(false)
    if (resultat.echecs.length === 0) {
      selection.garder([])
      retour.succes(action.messageSucces(resultat.reussis.length))
      onFerme()
      return
    }
    const ids = resultat.echecs.map((e) => e.id)
    selection.garder(ids)
    setRestants(restants.filter((e) => ids.includes(e.id)))
    setErreur(resumeEchecs(total, resultat))
  }

  return (
    <Fenetre
      ouverte
      titre={action.titre}
      sousTitre={L.tableaux.concernes(total)}
      description={action.description}
      libelleConfirmer={erreur ? L.tableaux.reessayer(total) : action.libelleConfirmer}
      danger={action.danger}
      motifObligatoire={action.motifObligatoire}
      motifMin={action.motifMin}
      motifMax={action.motifMax}
      libelleMotif={action.libelleMotif}
      enCours={enCours}
      erreur={erreur}
      onConfirmer={confirmer}
      onAnnuler={onFerme}
    />
  )
}
