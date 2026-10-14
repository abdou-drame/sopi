import type { PaiementAdmin } from '../../api/types'
import { L } from '../../i18n/libelles'
import { formatDate, formatMontant } from '../../lib/format'
import BadgeStatut from './BadgeStatut'
import Bouton from './Bouton'
import { IconePaiement } from './Icones'
import Squelette from './Squelette'

interface Props {
  paiements: PaiementAdmin[]
  chargement?: boolean
  erreur?: string | null
  onReessayer?: () => void
}

const T = L.derniersPaiements

/** Date à afficher : paiement, sinon remboursement ; absente pour un paiement encore en attente. */
function dateDuPaiement(p: PaiementAdmin): string | null {
  return p.payeLe ?? p.rembourseLe ?? null
}

function heure(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Dakar' }).format(new Date(iso))
}

/**
 * Tableau « derniers paiements » : pastille ronde à icône, titre et sous-titre, date, heure, statut avec point coloré
 * et montant à droite. Ni code de réservation, ni référence DexPay, ni téléphone.
 */
export default function DerniersPaiements({ paiements, chargement, erreur, onReessayer }: Props) {
  if (erreur && !chargement)
    return (
      <div role="alert" className="flex flex-col items-center gap-3 px-4 py-8 text-center">
        <p className="text-sm font-medium text-erreur-texte">{erreur}</p>
        {onReessayer && (
          <Bouton variante="secondaire" taille="p" onClick={onReessayer}>
            {L.commun.reessayer}
          </Bouton>
        )}
      </div>
    )

  if (!chargement && paiements.length === 0) return <p className="px-4 py-8 text-center text-sm text-texte-doux">{T.vide}</p>

  const cles = chargement ? Array.from({ length: 5 }, (_, i) => `sq-${i}`) : paiements.map((p) => p.id)
  return (
    <div className="overflow-x-auto" aria-busy={chargement || undefined}>
      <table className="w-full border-collapse text-sm">
        <caption className="sr-only">{T.titre}</caption>
        <thead>
          <tr className="text-left text-micro text-texte-discret/80">
            <th scope="col" className="px-2 py-2 font-medium">{T.colonnePaiement}</th>
            <th scope="col" className="px-2 py-2 font-medium">{T.colonneDate}</th>
            <th scope="col" className="px-2 py-2 font-medium">{T.colonneHeure}</th>
            <th scope="col" className="px-2 py-2 font-medium">{T.colonneStatut}</th>
            <th scope="col" className="px-2 py-2 text-right font-medium">{T.colonneMontant}</th>
          </tr>
        </thead>
        <tbody>
          {cles.map((cle, i) => {
            const p = chargement ? null : paiements[i]
            const date = p ? dateDuPaiement(p) : null
            return (
              <tr key={cle} className="border-t border-bordure/60">
                <td className="px-2 py-3">
                  <div className="flex items-center gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-primaire-100 text-primaire-700">
                      <IconePaiement />
                    </span>
                    {p ? (
                      <div className="min-w-0">
                        <p className="font-semibold text-texte">{T.titreLigne}</p>
                        <p className="text-micro text-texte-doux">{T.reservation(p.reservationId.slice(0, 8))}</p>
                      </div>
                    ) : (
                      <Squelette className="h-4 w-32" />
                    )}
                  </div>
                </td>
                <td className="px-2 py-3 whitespace-nowrap text-texte-doux">
                  {p ? (date ? formatDate(date) : '—') : <Squelette className="h-4 w-20" />}
                </td>
                <td className="px-2 py-3 whitespace-nowrap text-texte-doux">
                  {p ? (date ? heure(date) : '—') : <Squelette className="h-4 w-12" />}
                </td>
                <td className="px-2 py-3">{p ? <BadgeStatut statut={p.statut} /> : <Squelette className="h-5 w-20" />}</td>
                <td className="px-2 py-3 text-right font-semibold whitespace-nowrap text-texte">
                  {p ? formatMontant(p.montantAcompte) : <Squelette className="ml-auto h-4 w-24" />}
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
      {chargement && (
        <p className="sr-only" role="status">
          {L.commun.chargement}
        </p>
      )}
    </div>
  )
}
