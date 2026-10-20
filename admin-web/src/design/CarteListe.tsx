import type { ReactNode } from 'react'
import { L } from '../i18n/libelles'
import type { FiltresUrl } from '../lib/useFiltresUrl'
import { Carte, PiedListe } from './composants'
import Filtres, { type DefinitionFiltre } from './Filtres'
import { EtatsListe, type useListe } from './useListe'

export interface ColonneListe {
  titre: string
  droite?: boolean
}

/**
 * Carte d'une liste dessinée : rangée de filtres, tableau, états vide/chargement/erreur et pied de liste.
 * Les lignes (`children`) sont des `LigneLien` dont la dernière cellule est le lien de la maquette.
 */
export default function CarteListe({
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
  liste: ReturnType<typeof useListe>
  colonnes: ColonneListe[]
  largeurMin?: number
  messageVide: string
  avant?: ReactNode
  actionsColonne: string
  children: ReactNode
}) {
  return (
    <Carte className="flex flex-col gap-3.5">
      {(filtres.length > 0 || avant) && <Filtres filtres={filtres} etat={etat} avant={avant} />}
      <div className="overflow-x-auto">
        <table className="mq-table" style={{ minWidth: largeurMin }} aria-label={legende}>
          <thead>
            <tr>
              {colonnes.map((c) => (
                <th key={c.titre} style={c.droite ? { textAlign: 'right' } : undefined}>
                  {c.titre}
                </th>
              ))}
              <th className="relative">
                <span className="sr-only">{actionsColonne}</span>
              </th>
            </tr>
          </thead>
          <tbody>{children}</tbody>
        </table>
      </div>
      <EtatsListe chargement={liste.chargement} erreur={liste.erreur} vide={liste.donneesPresentes && liste.lignes.length === 0} messageVide={messageVide} onReessayer={liste.recharger} />
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
