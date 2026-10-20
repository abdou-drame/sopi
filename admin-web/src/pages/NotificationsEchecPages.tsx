import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { NotificationEchec } from '../api/typesAdmin'
import CarteListe from '../design/CarteListe'
import PageDetailMq from '../design/PageDetailMq'
import { Badge, Carte, GrilleTuiles, LienTexte, LigneLien, TitreCarte, TitreDetail, TitrePage } from '../design/composants'
import { useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatDateHeure } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.notificationsEchec

const OPTIONS_CANAL = [
  { valeur: '', libelle: L.commun.tous },
  ...Object.entries(T.canaux).map(([valeur, libelle]) => ({ valeur, libelle })),
]
const OPTIONS_TYPE = [
  { valeur: '', libelle: L.commun.tous },
  ...Object.entries(T.types).map(([valeur, libelle]) => ({ valeur, libelle })),
]

const libelleType = (type: string) => T.types[type] ?? type
const libelleCanaux = (n: NotificationEchec) => n.canauxEnEchec.map((c) => T.canaux[c] ?? c).join(', ')
const libelleDestinataire = (n: NotificationEchec) => (n.destinataire.role ? (T.roles[n.destinataire.role] ?? n.destinataire.role) : T.inconnue)

export function NotificationsEchecListePage() {
  const etat = useFiltresUrl(['canal', 'type', 'ancienneteMinutes'])
  const liste = useListe('notifications-en-echec', api.notificationsEnEchec, etat)
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Notifications" suite="en échec" sousTitre={T.sousTitre} />
      <CarteListe
        legende={T.legende}
        etat={etat}
        liste={liste}
        messageVide={T.vide}
        actionsColonne={L.commun.voir}
        largeurMin={900}
        filtres={[
          { cle: 'canal', type: 'select', label: T.canal, options: OPTIONS_CANAL },
          { cle: 'type', type: 'select', label: T.type, options: OPTIONS_TYPE },
          { cle: 'ancienneteMinutes', type: 'nombre', label: T.anciennete },
        ]}
        colonnes={[{ titre: T.colDestinataire }, { titre: T.colType }, { titre: T.colCanaux }, { titre: T.colTentatives, droite: true }, { titre: T.colDate }, { titre: T.colErreur }]}
      >
        {liste.lignes.map((n: NotificationEchec) => (
          <LigneLien key={n.id} vers={`/notifications-en-echec/${n.id}`} libelle={`Voir le détail de la notification ${libelleType(n.type)}`}>
            <td className="font-semibold">{libelleDestinataire(n)}</td>
            <td>{libelleType(n.type)}</td>
            <td>
              <Badge ton="danger">{libelleCanaux(n)}</Badge>
            </td>
            <td style={{ textAlign: 'right' }}>{n.tentatives}</td>
            <td>{formatDateHeure(n.dateCreation)}</td>
            <td>{n.classeErreur ?? <span className="text-[#9aa0b2]">—</span>}</td>
          </LigneLien>
        ))}
      </CarteListe>
    </div>
  )
}

export function NotificationEchecDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['notification-en-echec', id], queryFn: () => api.notificationEnEchec(id) })
  return (
    <PageDetailMq requete={requete} retour="/notifications-en-echec" libelleRetour={T.retour}>
      {(n) => (
        <div className="flex flex-col gap-4">
          <TitreDetail retour="/notifications-en-echec" libelleRetour={T.retour} titre={libelleType(n.type)} infos={`${libelleDestinataire(n)} · ${libelleCanaux(n)}`} />
          <Carte>
            <TitreCarte titre={T.detailTitre} sousTitre={T.note} />
            <GrilleTuiles
              elements={[
                { libelle: T.colType, valeur: libelleType(n.type) },
                {
                  libelle: T.destinataire,
                  valeur: n.destinataire.role ? (
                    <LienTexte vers={`/utilisateurs/${n.destinataire.id}`}>
                      {T.voirDestinataire} ({libelleDestinataire(n)})
                    </LienTexte>
                  ) : (
                    T.inconnue
                  ),
                },
                { libelle: T.colCanaux, valeur: libelleCanaux(n) },
                { libelle: T.colTentatives, valeur: n.tentatives },
                { libelle: T.colErreur, valeur: n.classeErreur },
                { libelle: T.messageErreur, valeur: n.messageErreur ?? null, large: true },
                { libelle: T.colDate, valeur: formatDateHeure(n.dateCreation) },
              ]}
            />
          </Carte>
        </div>
      )}
    </PageDetailMq>
  )
}
