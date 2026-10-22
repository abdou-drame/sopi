import type { ReactNode } from 'react'
import { L } from '../i18n/libelles'
import type { FiltresUrl } from '../lib/useFiltresUrl'
import { Carte, PiedListe } from './composants'
import Filtres, { type DefinitionFiltre } from './Filtres'
import TableauDonnees, { type ColonneDonnees } from './tableau'
import { EtatsListe, type useListe } from './useListe'

export type ColonneListe<T> = ColonneDonnees<T>

/**
 * Carte d'une liste dessinée : rangée de filtres, tableau, états vide/chargement/erreur et pied de liste.
 * Les lignes (`children`) sont des `LigneLien` dont la dernière cellule est le lien de la maquette.
 */
export default function CarteListe<T>({
  legende,
  filtres,
  etat,
  liste,
  colonnes,
  largeurMin = 860,
  messageVide,
  avant,
  actionsColonne,
  children,
}: {
  legende: string
  filtres: DefinitionFiltre[]
  etat: FiltresUrl
  liste: ReturnType<typeof useListe<T>>
  colonnes: ColonneListe<T>[]
  largeurMin?: number
  messageVide: string
  avant?: ReactNode
  actionsColonne: string
  /** Reçoit les lignes de la page, déjà triées selon l'en-tête cliqué. */
  children: (lignes: T[]) => ReactNode
}) {
  return (
    <Carte className="flex flex-col gap-3.5">
      {(filtres.length > 0 || avant) && <Filtres filtres={filtres} etat={etat} avant={avant} />}
      <TableauDonnees legende={legende} colonnes={colonnes} lignes={liste.lignes} largeurMin={largeurMin} actionsColonne={actionsColonne} masque={liste.chargement}>
        {(lignes) => children(lignes)}
      </TableauDonnees>
      <EtatsListe
        chargement={liste.chargement}
        erreur={liste.erreur}
        vide={liste.donneesPresentes && liste.lignes.length === 0}
        messageVide={messageVide}
        onReessayer={liste.recharger}
        colonnes={colonnes}
        largeurMin={largeurMin}
        filtresActifs={etat.modifies}
        onReinitialiser={etat.reinitialiser}
      />
      {liste.total > 0 && (
        <PiedListe
          resume={`${liste.premier} à ${liste.dernier} sur ${L.commun.resultats(liste.total)}`}
          precedent={liste.page > 1}
          suivant={liste.page < liste.totalPages}
          onPrecedent={() => etat.allerPage(liste.page - 1)}
          onSuivant={() => etat.allerPage(liste.page + 1)}
        />
      )}
    </Carte>
  )
}
