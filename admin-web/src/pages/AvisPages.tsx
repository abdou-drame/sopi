import { useState } from 'react'
import { Link } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { AvisAdmin } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import BanniereAlerte from '../components/ui/BanniereAlerte'
import Bouton from '../components/ui/Bouton'
import EnTetePage from '../components/ui/EnTetePage'
import FiltresListe from '../components/ui/FiltresListe'
import ListePaginee from '../components/ui/ListePaginee'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { formatDate, formatDateHeure } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.avisModeration
const MOTIF_MIN = 10
const MOTIF_MAX = 1000

type Vue = 'a_traiter' | 'signales' | 'masques' | 'tous'

const OPTIONS_VUE = [
  { valeur: 'a_traiter', libelle: T.aTraiter },
  { valeur: 'signales', libelle: T.signales },
  { valeur: 'masques', libelle: T.masques },
  { valeur: 'tous', libelle: T.tous },
]
const OPTIONS_NOTE = [{ valeur: '', libelle: L.commun.tous }, ...[5, 4, 3, 2, 1].map((n) => ({ valeur: String(n), libelle: T.sur5(n) }))]

/** La vue choisie (une seule clé dans l'adresse) se traduit en paramètres de l'API. */
function chargerAvis(params: Record<string, string | number>) {
  const { vue, ...reste } = params
  const filtres: Record<string, string | number> = { ...reste }
  if ((vue as Vue) === 'a_traiter') filtres.aTraiter = 'true'
  if ((vue as Vue) === 'signales') filtres.signale = 'true'
  if ((vue as Vue) === 'masques') filtres.masque = 'true'
  return api.avis(filtres)
}

type Mode = 'masquer' | 'conserver' | 'retablir'

/** Une action de modération avec sa confirmation. Masquer exige un motif, conserver le propose, rétablir n'en a pas. */
function ActionModeration({ avis, mode, onFait }: { avis: AvisAdmin; mode: Mode; onFait: (message: string) => void }) {
  const [ouverte, setOuverte] = useState(false)
  const nom = `${avis.client.prenom} ${avis.client.nom}`
  const action = useAction((motif: string) => {
    if (mode === 'masquer') return api.masquerAvis(avis.id, motif)
    if (mode === 'conserver') return api.conserverAvis(avis.id, motif || undefined)
    return api.retablirAvis(avis.id)
  })
  const fermer = () => {
    setOuverte(false)
    action.reset()
  }
  const textes = {
    masquer: { bouton: T.masquer, titre: T.masquerTitre, texte: T.masquerTexte, fait: T.masquerFait, libelle: T.masquerLibelle(nom) },
    conserver: { bouton: T.conserver, titre: T.conserverTitre, texte: T.conserverTexte, fait: T.conserverFait, libelle: T.conserverLibelle(nom) },
    retablir: { bouton: T.retablir, titre: T.retablirTitre, texte: T.retablirTexte, fait: T.retablirFait, libelle: T.retablirLibelle(nom) },
  }[mode]
  return (
    <>
      <Bouton variante={mode === 'masquer' ? 'danger' : 'secondaire'} taille="p" aria-label={textes.libelle} onClick={() => setOuverte(true)}>
        {textes.bouton}
      </Bouton>
      <ModaleConfirmation
        ouverte={ouverte}
        titre={textes.titre}
        description={textes.texte}
        libelleConfirmer={textes.bouton}
        libelleMotif={T.motif}
        danger={mode === 'masquer'}
        motifObligatoire={mode === 'masquer'}
        motifFacultatif={mode === 'conserver'}
        motifMin={MOTIF_MIN}
        motifMax={MOTIF_MAX}
        enCours={action.isPending}
        erreur={action.isError ? messageDErreur(action.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={(motif) =>
          action.mutate(motif, {
            onSuccess: () => {
              fermer()
              onFait(textes.fait)
            },
          })
        }
      />
    </>
  )
}

export default function AvisListePage() {
  const etat = useFiltresUrl(['vue', 'note', 'prestataire'], { vue: 'a_traiter' })
  const [message, setMessage] = useState<string | null>(null)
  const colonnes: Colonne<AvisAdmin>[] = [
    {
      cle: 'avis',
      titre: T.avis,
      rendu: (a) => (
        <div className="max-w-md">
          <p className="text-sm font-semibold text-texte">{T.sur5(a.note)}</p>
          <p className="mt-0.5 line-clamp-3 text-sm whitespace-pre-line text-texte">{a.commentaire ?? <span className="text-texte-doux">{T.sansCommentaire}</span>}</p>
          {a.signaleLe && (
            <p className="mt-1 text-xs text-texte-doux">
              {T.signaleLe(formatDateHeure(a.signaleLe))}
              {a.motifSignalement ? ` : « ${a.motifSignalement} »` : ''}
            </p>
          )}
          {a.modereLe && (
            <p className="mt-1 text-xs text-texte-doux">
              {T.modereLe(formatDateHeure(a.modereLe))}
              {a.motifModeration ? ` : « ${a.motifModeration} »` : ''}
            </p>
          )}
        </div>
      ),
    },
    { cle: 'client', titre: T.client, rendu: (a) => `${a.client.prenom} ${a.client.nom}` },
    { cle: 'prestataire', titre: T.prestataireCol, rendu: (a) => a.prestataire.nomEntreprise },
    { cle: 'date', titre: T.dateCol, rendu: (a) => formatDate(a.dateCreation) },
    { cle: 'statut', titre: L.commun.statut, rendu: (a) => <BadgeStatut statut={a.statut} /> },
    {
      cle: 'actions',
      titre: T.actionsColonne,
      alignement: 'droite',
      rendu: (a) => (
        <div className="flex flex-wrap justify-end gap-2">
          <Link to={`/reservations/${a.reservationId}`} className="self-center text-xs font-semibold text-primaire-700 hover:underline">
            {T.reservation}
          </Link>
          {a.signaleLe && !a.modereLe && <ActionModeration avis={a} mode="conserver" onFait={setMessage} />}
          {a.masque ? <ActionModeration avis={a} mode="retablir" onFait={setMessage} /> : <ActionModeration avis={a} mode="masquer" onFait={setMessage} />}
        </div>
      ),
    },
  ]
  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      {message && <BanniereAlerte gravite="info" titre={message} />}
      <FiltresListe
        legende={T.filtres}
        etat={etat}
        filtres={[
          { cle: 'vue', type: 'select', label: T.vue, options: OPTIONS_VUE },
          { cle: 'note', type: 'select', label: T.note, options: OPTIONS_NOTE },
          { cle: 'prestataire', type: 'texte', label: T.prestataire },
        ]}
      />
      <ListePaginee legende={T.legende} cle="avis" charger={chargerAvis} etat={etat} colonnes={colonnes} cleLigne={(a) => a.id} messageVide={T.vide} />
    </div>
  )
}
