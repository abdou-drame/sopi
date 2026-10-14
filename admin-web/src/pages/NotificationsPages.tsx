import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { NotificationEchec } from '../api/typesAdmin'
import BadgeStatut from '../components/ui/BadgeStatut'
import Carte from '../components/ui/Carte'
import Definitions from '../components/ui/Definitions'
import EnTetePage from '../components/ui/EnTetePage'
import FiltresListe from '../components/ui/FiltresListe'
import LienLigne from '../components/ui/LienLigne'
import ListePaginee from '../components/ui/ListePaginee'
import PageDetail from '../components/ui/PageDetail'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { formatDateHeure } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.notificationsEchec

const OPTIONS_CANAL = [{ valeur: '', libelle: L.commun.tous }, ...Object.entries(T.canaux).map(([valeur, libelle]) => ({ valeur, libelle }))]
const OPTIONS_TYPE = [{ valeur: '', libelle: L.commun.tous }, ...Object.entries(T.types).map(([valeur, libelle]) => ({ valeur, libelle }))]

const libelleType = (type: string) => T.types[type] ?? type
const canaux = (n: NotificationEchec) => n.canauxEnEchec.map((c) => T.canaux[c] ?? c).join(', ')

export function NotificationsListePage() {
  const etat = useFiltresUrl(['canal', 'type', 'ancienneteMinutes'])
  const colonnes: Colonne<NotificationEchec>[] = [
    { cle: 'type', titre: T.colType, rendu: (n) => <LienLigne vers={`/notifications-en-echec/${n.id}`}>{libelleType(n.type)}</LienLigne> },
    { cle: 'destinataire', titre: T.colDestinataire, rendu: (n) => L.roles[n.destinataire.role] ?? n.destinataire.role },
    { cle: 'canaux', titre: T.colCanaux, rendu: canaux },
    { cle: 'tentatives', titre: T.colTentatives, alignement: 'droite', rendu: (n) => n.tentatives },
    { cle: 'erreur', titre: T.colErreur, rendu: (n) => n.classeErreur },
    { cle: 'date', titre: T.colDate, rendu: (n) => formatDateHeure(n.dateCreation) },
  ]
  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[
          { cle: 'canal', type: 'select', label: T.canal, options: OPTIONS_CANAL },
          { cle: 'type', type: 'select', label: T.type, options: OPTIONS_TYPE },
          { cle: 'ancienneteMinutes', type: 'nombre', label: T.anciennete },
        ]}
      />
      <ListePaginee legende={T.legende} cle="notifications-echec" charger={api.notificationsEnEchec} etat={etat} colonnes={colonnes} cleLigne={(n) => n.id} messageVide={T.vide} />
    </div>
  )
}

export function NotificationDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['notification-echec', id], queryFn: () => api.notificationEnEchec(id) })
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/notifications-en-echec', libelle: T.retour }}
      titre={(n) => libelleType(n.type)}
      sousTitre={(n) => formatDateHeure(n.dateCreation)}
      badge={(n) => <BadgeStatut ton="erreur" libelle={canaux(n)} />}
    >
      {(n) => (
        <Carte titre={T.detailTitre}>
          <Definitions
            elements={[
              { libelle: T.colType, valeur: libelleType(n.type) },
              { libelle: T.colCanaux, valeur: canaux(n) },
              { libelle: T.colTentatives, valeur: n.tentatives },
              { libelle: T.colErreur, valeur: n.classeErreur },
              { libelle: T.colDate, valeur: formatDateHeure(n.dateCreation) },
              {
                libelle: T.colDestinataire,
                valeur: (
                  <Link to={`/utilisateurs/${n.destinataire.id}`} className="font-semibold text-primaire-700 hover:underline">
                    {L.roles[n.destinataire.role] ?? n.destinataire.role} · {T.voirDestinataire}
                  </Link>
                ),
              },
            ]}
          />
        </Carte>
      )}
    </PageDetail>
  )
}
