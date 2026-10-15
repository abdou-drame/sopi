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
  const colonnes: Colonne<NotificationEchec>[] = [
    { cle: 'type', titre: T.colType, rendu: (n) => <LienLigne vers={`/notifications-en-echec/${n.id}`}>{libelleType(n.type)}</LienLigne> },
    { cle: 'destinataire', titre: T.colDestinataire, rendu: libelleDestinataire },
    { cle: 'canaux', titre: T.colCanaux, rendu: (n) => <BadgeStatut ton="erreur" libelle={libelleCanaux(n)} /> },
    { cle: 'tentatives', titre: T.colTentatives, alignement: 'droite', rendu: (n) => n.tentatives },
    { cle: 'erreur', titre: T.colErreur, rendu: (n) => n.classeErreur ?? '—' },
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
          { cle: 'ancienneteMinutes', type: 'texte', label: T.anciennete },
        ]}
      />
      <ListePaginee
        legende={T.legende}
        cle="notifications-en-echec"
        charger={api.notificationsEnEchec}
        etat={etat}
        colonnes={colonnes}
        cleLigne={(n) => n.id}
        messageVide={T.vide}
      />
    </div>
  )
}

export function NotificationEchecDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['notification-en-echec', id], queryFn: () => api.notificationEnEchec(id) })
  return (
    <PageDetail
      requete={requete}
      retour={{ vers: '/notifications-en-echec', libelle: T.retour }}
      titre={(n) => libelleType(n.type)}
      sousTitre={(n) => `${libelleDestinataire(n)} · ${libelleCanaux(n)}`}
    >
      {(n) => (
        <Carte titre={T.detailTitre}>
          <p className="mb-3 text-sm text-texte-doux">{T.note}</p>
          <Definitions
            elements={[
              { libelle: T.colType, valeur: libelleType(n.type) },
              {
                libelle: T.destinataire,
                valeur: n.destinataire.role ? (
                  <Link to={`/utilisateurs/${n.destinataire.id}`} className="font-semibold text-primaire-700 hover:underline">
                    {T.voirDestinataire} ({libelleDestinataire(n)})
                  </Link>
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
      )}
    </PageDetail>
  )
}
