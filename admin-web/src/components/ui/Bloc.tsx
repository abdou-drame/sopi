import type { ReactNode } from 'react'
import { L } from '../../i18n/libelles'
import Bouton from './Bouton'
import Squelette from './Squelette'

interface Props {
  titre: string
  chargement?: boolean
  erreur?: string | null
  onReessayer?: () => void
  /** Bloc sans donnée à montrer (état vide). */
  vide?: boolean
  messageVide?: string
  children: ReactNode
  /** Hauteur du squelette de chargement. */
  hauteur?: string
}

/** Section d'écran avec ses propres états de chargement, d'erreur (avec « Réessayer ») et vide. */
export default function Bloc({ titre, chargement, erreur, onReessayer, vide, messageVide, children, hauteur = 'h-40' }: Props) {
  return (
    <section aria-label={titre} className="flex flex-col gap-3">
      <h2 className="text-lg font-bold text-titre">{titre}</h2>
      {chargement ? (
        <div role="status" aria-label={`${L.commun.chargement} ${titre}`}>
          <Squelette className={`${hauteur} w-full rounded-carte`} />
        </div>
      ) : erreur ? (
        <div role="alert" className="flex flex-col items-start gap-3 rounded-carte bg-erreur-fond p-6 text-erreur-texte">
          <p className="text-sm font-medium">{erreur}</p>
          {onReessayer && (
            <Bouton variante="secondaire" taille="p" onClick={onReessayer}>
              {L.commun.reessayer}
            </Bouton>
          )}
        </div>
      ) : vide ? (
        <p className="rounded-carte bg-surface p-6 text-center text-sm text-texte-doux shadow-carte">
          {messageVide ?? L.commun.aucuneDonnee}
        </p>
      ) : (
        children
      )}
    </section>
  )
}
