import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { AvisAdmin } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import { Badge, Carte as CarteMq, Onglets, PiedListe, Recherche, TitrePage, type TonBadge } from '../design/composants'
import { IDocument } from '../design/icones'
import { EtatsListe, useListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatDate } from '../lib/format'
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
export const OPTIONS_NOTE = [{ valeur: '', libelle: L.commun.tous }, ...[5, 4, 3, 2, 1].map((n) => ({ valeur: String(n), libelle: T.sur5(n) }))]

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
    masquer: {
      bouton: T.masquer,
      titre: T.masquerTitre,
      texte: T.masquerTexte,
      fait: T.masquerFait,
      libelle: T.masquerLibelle(nom),
    },
    conserver: {
      bouton: T.conserver,
      titre: T.conserverTitre,
      texte: T.conserverTexte,
      fait: T.conserverFait,
      libelle: T.conserverLibelle(nom),
    },
    retablir: {
      bouton: T.retablir,
      titre: T.retablirTitre,
      texte: T.retablirTexte,
      fait: T.retablirFait,
      libelle: T.retablirLibelle(nom),
    },
  }[mode]
  return (
    <>
      <button
        type="button"
        aria-label={textes.libelle}
        onClick={() => setOuverte(true)}
        className={`h-11 min-w-0 flex-1 cursor-pointer rounded-[22px] border-none px-3 text-[13px] font-bold ${mode === 'masquer' ? 'mq-btn-rouge bg-[#fde9e9] text-[#b0161b]' : 'mq-btn-gris bg-[#eef1f6] text-[#4f566b]'}`}
      >
        {mode === 'masquer' ? "Masquer l'avis" : textes.bouton}
      </button>
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

function Etoiles({ note }: { note: number }) {
  return (
    <svg width="90" height="16" viewBox="0 0 90 16" role="img" aria-label={T.sur5(note)}>
      {[0, 1, 2, 3, 4].map((i) => (
        <path key={i} transform={`translate(${i * 18} 0)`} fill={i < note ? '#f59e0b' : '#d9dce5'} d="M8 1l2 4.3 4.7.6-3.4 3.2.9 4.6L8 11.4 3.8 13.7l.9-4.6L1.3 5.9 6 5.3z" />
      ))}
    </svg>
  )
}

const COULEURS_AVATAR = ['bg-[#dbeafe] text-[#1d4ed8]', 'bg-[#fde7f3] text-[#a3206b]', 'bg-[#fff0d4] text-[#983705]', 'bg-[#d7f7e4] text-[#0e6245]']
const TON_STATUT: Record<string, TonBadge> = {
  publie: 'succes',
  signale: 'attention',
  supprime: 'neutre',
}

function CarteAvis({ a, retour }: { a: AvisAdmin; retour: ReturnType<typeof useRetours> }) {
  const initiales = `${a.client.prenom.charAt(0)}${a.client.nom.charAt(0)}`.toUpperCase()
  const couleur = COULEURS_AVATAR[(a.client.nom.length + a.client.prenom.length) % COULEURS_AVATAR.length]
  return (
    <article className="box-border flex flex-col gap-3.5 rounded-[26px] bg-[#f3f4f7] p-5">
      <div className="flex items-center gap-3">
        <span aria-hidden="true" className={`grid size-11 shrink-0 place-items-center rounded-full text-[13px] font-bold ${couleur}`}>
          {initiales}
        </span>
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-semibold">
            {a.client.prenom} {a.client.nom}
          </span>
          <span className="mt-px block text-xs text-[#8a90a2]">
            sur {a.prestataire.nomEntreprise} · {formatDate(a.dateCreation)}
          </span>
        </span>
        <Badge ton={TON_STATUT[a.statut] ?? 'neutre'} gras>
          {L.statuts[a.statut] ?? a.statut}
        </Badge>
      </div>
      <Etoiles note={a.note} />
      <p className="m-0 text-sm leading-[22px] whitespace-pre-line text-[#3c4257]">{a.commentaire ? `« ${a.commentaire} »` : '—'}</p>
      <div className="flex items-center gap-2 text-xs text-[#6b7287]">
        <IDocument taille={14} trait={2} />
        Réservation — · —
      </div>
      <div className="mt-0.5 flex flex-wrap gap-2">
        {a.signaleLe && !a.modereLe && <ActionModeration avis={a} mode="conserver" onFait={retour.succes} />}
        {a.masque ? <ActionModeration avis={a} mode="retablir" onFait={retour.succes} /> : <ActionModeration avis={a} mode="masquer" onFait={retour.succes} />}
        <Link
          to={`/reservations/${a.reservationId}`}
          className="mq-btn-bleu inline-flex h-11 min-w-0 flex-1 items-center justify-center rounded-[22px] bg-[#1d4ed8] px-3 text-[13px] font-bold text-white"
        >
          Voir la réservation
        </Link>
      </div>
    </article>
  )
}

export default function AvisListePage() {
  const etat = useFiltresUrl(['vue', 'note', 'prestataire'], {
    vue: 'a_traiter',
  })
  const retour = useRetours()
  const liste = useListe('avis', chargerAvis, etat)
  const { data: tableau } = useQuery({
    queryKey: ['tableau-de-bord'],
    queryFn: api.tableauDeBord,
    retry: false,
  })
  const compteurs: Record<string, number | undefined> = {
    signales: tableau?.avis.signales,
    masques: tableau?.avis.masques,
    tous: tableau?.avis.total,
  }
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Avis" suite="des clients" sousTitre="Consultez les avis publiés et masquez ceux qui enfreignent les règles." />
      <CarteMq className="flex flex-col gap-3.5">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            libelle={T.vue}
            onglets={OPTIONS_VUE.map((o) => ({
              cle: o.valeur,
              libelle: o.libelle,
              compteur: compteurs[o.valeur] ?? '—',
              actif: etat.valeurs.vue === o.valeur,
              onSelect: () => etat.definir({ vue: o.valeur }),
            }))}
          />
          <div className="flex-1" />
          <Recherche valeur="" onChange={() => {}} placeholder="Rechercher dans les avis" libelle="Rechercher dans les avis" />
        </div>
        <div className="grid grid-cols-[repeat(auto-fit,minmax(340px,1fr))] gap-4" aria-label={T.legende}>
          {liste.lignes.map((a) => (
            <CarteAvis key={a.id} a={a} retour={retour} />
          ))}
        </div>
        <EtatsListe
          chargement={liste.chargement}
          erreur={liste.erreur}
          vide={liste.donneesPresentes && liste.lignes.length === 0}
          messageVide={T.vide}
          onReessayer={liste.recharger}
        />
        {liste.totalPages > 1 && (
          <PiedListe
            resume={`${liste.premier} à ${liste.dernier} sur ${liste.total} ${liste.total > 1 ? 'résultats' : 'résultat'}`}
            precedent={liste.page > 1}
            suivant={liste.page < liste.totalPages}
            onPrecedent={() => etat.allerPage(liste.page - 1)}
            onSuivant={() => etat.allerPage(liste.page + 1)}
          />
        )}
      </CarteMq>
    </div>
  )
}
