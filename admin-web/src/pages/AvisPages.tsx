import { useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { AvisAdmin } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import Fenetre from '../design/Fenetre'
import { Avatar, Carte as CarteMq, Onglets, PaginationRonde, TitrePage } from '../design/composants'
import { IRecherche } from '../design/icones'
import { CarteBleue, CarteStat, Pastille } from '../design/synthese'
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

/**
 * Une action de modération avec sa confirmation. Masquer exige un motif, conserver le propose, rétablir n'en a pas.
 * « Conserver » et « Rétablir » restent fonctionnels mais ne sont pas affichés : la maquette ne les dessine pas encore
 * (voir maquettes/A-DESSINER.md).
 */
export function ActionModeration({ avis, mode, onFait }: { avis: AvisAdmin; mode: Mode; onFait: (message: string) => void }) {
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
        className={`inline-flex h-8 shrink-0 cursor-pointer items-center gap-1.5 rounded-full px-3.5 text-xs font-bold whitespace-nowrap ${mode === 'masquer' ? 'mq-btn-rouge border border-[#f6c9cb] bg-[#fde9e9] text-[#b0161b]' : 'mq-btn-gris border border-[#e6e8ee] bg-white text-[#12172b]'}`}
      >
        {mode === 'masquer' ? 'Masquer' : mode === 'retablir' ? 'Republier' : textes.bouton}
      </button>
      <Fenetre
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
    <span className="inline-flex items-center gap-2 whitespace-nowrap">
      <svg width="80" height="14" viewBox="0 0 90 16" role="img" aria-label={T.sur5(note)}>
        {[0, 1, 2, 3, 4].map((i) => (
          <path key={i} transform={`translate(${i * 18} 0)`} fill={i < note ? '#f59e0b' : '#d9dce5'} d="M8 1l2 4.3 4.7.6-3.4 3.2.9 4.6L8 11.4 3.8 13.7l.9-4.6L1.3 5.9 6 5.3z" />
        ))}
      </svg>
      <span className="text-[13px] font-semibold text-[#12172b] tabular-nums">{note.toFixed(1).replace('.', ',')}</span>
    </span>
  )
}

const PASTILLE_AVIS: Record<string, { fond: string; point: string }> = {
  publie: { fond: 'bg-[#d7f7e4] text-[#0e6245]', point: '#16a34a' },
  signale: { fond: 'bg-[#fff0d4] text-[#983705]', point: '#f59e0b' },
  supprime: { fond: 'bg-[#eef1f6] text-[#4f566b]', point: '#9aa0b2' },
}

const ICO = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true } as const
const IBulle = () => (
  <svg {...ICO}>
    <path d="M5 5h14a2 2 0 012 2v8a2 2 0 01-2 2h-7l-5 4v-4H5a2 2 0 01-2-2V7a2 2 0 012-2z" />
  </svg>
)
const IOeil = () => (
  <svg {...ICO}>
    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
    <circle cx="12" cy="12" r="3" />
  </svg>
)
const IOeilBarre = () => (
  <svg {...ICO}>
    <path d="M3 3l18 18M10.6 6.1A9.8 9.8 0 0112 6c6.5 0 10 6 10 6a17 17 0 01-3.2 3.9M6.6 7.6A17 17 0 002 12s3.5 6 10 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 004.2 4.2" />
  </svg>
)
const IPavillon = () => (
  <svg {...ICO}>
    <path d="M5 21V4M5 4h11l-2 4 2 4H5" />
  </svg>
)
const IFlecheHautDroite = () => (
  <svg {...ICO} width={14} height={14}>
    <path d="M7 17L17 7M8 7h9v9" />
  </svg>
)
const IEtoileFiltre = () => (
  <svg {...ICO} width={14} height={14} fill="#f59e0b" stroke="#f59e0b">
    <path d="M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6L12 16.7 6.6 19.6l1.1-6L3.2 9.4l6.1-.8z" />
  </svg>
)
const IReinit = () => (
  <svg {...ICO} width={16} height={16}>
    <path d="M20 12a8 8 0 11-2.3-5.7M20 4v5h-5" />
  </svg>
)

/** Champ de recherche par prestataire : s'applique à Entrée, en quittant le champ ou 450 ms après la dernière frappe. */
function ChampPrestataire({ valeur, onValider }: { valeur: string; onValider: (v: string) => void }) {
  const [saisie, setSaisie] = useState(valeur)
  const [origine, setOrigine] = useState(valeur)
  if (origine !== valeur) {
    setOrigine(valeur)
    setSaisie(valeur)
  }
  const valider = () => saisie.trim() !== valeur && onValider(saisie.trim())
  const valideur = useRef(valider)
  valideur.current = valider
  useEffect(() => {
    if (saisie.trim() === valeur) return
    const minuteur = window.setTimeout(() => valideur.current(), 450)
    return () => window.clearTimeout(minuteur)
  }, [saisie, valeur])
  return (
    <label className="flex h-8 w-[260px] max-w-full items-center gap-2.5 rounded-[18px] border border-[#e6e8ee] bg-[#f6f7fa] px-4 text-[#9aa0b2] focus-within:border-[#1d4ed8]">
      <IRecherche taille={16} />
      <input
        type="search"
        value={saisie}
        onChange={(e) => setSaisie(e.target.value)}
        onBlur={valider}
        onKeyDown={(e) => e.key === 'Enter' && valider()}
        placeholder="Prestataire…"
        aria-label={T.prestataire}
        className="min-w-0 flex-1 border-none bg-transparent text-[13px] text-[#12172b] outline-none placeholder:text-[#9aa0b2]"
      />
    </label>
  )
}

function LigneAvis({ a, retour }: { a: AvisAdmin; retour: ReturnType<typeof useRetours> }) {
  const nom = `${a.client.prenom} ${a.client.nom}`
  const style = PASTILLE_AVIS[a.statut] ?? PASTILLE_AVIS.supprime
  const libelleStatut = a.masque ? L.statuts.supprime : (L.statuts[a.statut] ?? a.statut)
  return (
    <tr>
      <td>
        <div className="flex items-center gap-2.5">
          <Avatar nom={nom} taille={30} />
          <span className="font-semibold whitespace-nowrap text-[#12172b]">{nom}</span>
        </div>
      </td>
      <td className="whitespace-nowrap text-[#6b7287]">{a.prestataire.nomEntreprise}</td>
      <td>
        <Etoiles note={a.note} />
      </td>
      <td className="max-w-[300px] text-[#3c4257]">
        <span className="line-clamp-1">{a.commentaire ? `« ${a.commentaire} »` : '—'}</span>
      </td>
      <td className="whitespace-nowrap text-[#6b7287]">{formatDate(a.dateCreation)}</td>
      <td>
        <Pastille fond={style.fond} point={style.point} libelle={libelleStatut} />
      </td>
      <td>
        <div className="flex items-center justify-end gap-2">
          <ActionModeration avis={a} mode={a.masque ? 'retablir' : 'masquer'} onFait={retour.succes} />
          <Link to={`/reservations/${a.reservationId}`} aria-label="Voir la réservation" title="Voir la réservation" className="mq-rond grid size-8 shrink-0 place-items-center rounded-full border border-[#e6e8ee] bg-white text-[#12172b]">
            <IFlecheHautDroite />
          </Link>
        </div>
      </td>
    </tr>
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
  const stats = tableau?.avis
  const compteurs: Record<string, number | undefined> = {
    signales: stats?.signales,
    masques: stats?.masques,
    tous: stats?.total,
  }
  // L'API ne donne pas de compteur « publiés » : on le déduit (total moins masqués moins signalés).
  const publies = stats ? Math.max(0, stats.total - stats.masques - stats.signales) : undefined
  const pct = (n: number | undefined) => (n === undefined || !stats || stats.total === 0 ? undefined : Math.round((n / stats.total) * 100))
  const partPublies = pct(publies)
  const partMasques = pct(stats?.masques)
  const filtresActifs = etat.valeurs.note !== '' || etat.valeurs.prestataire !== ''
  return (
    <div className="flex flex-col gap-3">
      <TitrePage compact titre="Avis" suite="des clients" sousTitre="Consultez les avis publiés et masquez ceux qui enfreignent les règles." />

      <div className="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(190px,1fr))]">
        <CarteBleue icone={<IBulle />} titre="Avis reçus" valeur={stats?.total} unite={stats?.total === 1 ? 'avis' : 'avis'} note={stats ? `${publies} publié${publies === 1 ? '' : 's'} · ${stats.masques} masqué${stats.masques > 1 ? 's' : ''}` : '—'} />
        <CarteStat
          icone={<IOeil />}
          teinte="bg-[#d7f7e4] text-[#16a34a]"
          badge={partPublies === undefined ? '—' : `${partPublies} %`}
          badgeTeinte="bg-[#d7f7e4] text-[#0e6245]"
          titre="Publiés"
          valeur={publies}
          pourcentage={partPublies ?? 0}
          couleurBarre="#16a34a"
        />
        <CarteStat
          icone={<IOeilBarre />}
          teinte="bg-[#eef1f6] text-[#4f566b]"
          badge={partMasques === undefined ? '—' : `${partMasques} %`}
          badgeTeinte="bg-[#eef1f6] text-[#4f566b]"
          titre="Masqués"
          valeur={stats?.masques}
          pourcentage={partMasques ?? 0}
          couleurBarre="#4f566b"
        />
        <CarteStat
          icone={<IPavillon />}
          teinte="bg-[#fde9e9] text-[#e5484d]"
          badge={stats?.signales === 0 ? 'Rien à modérer' : 'À modérer'}
          badgeTeinte={stats?.signales === 0 ? 'bg-[#d7f7e4] text-[#0e6245]' : 'bg-[#fde9e9] text-[#b0161b]'}
          titre="Signalés"
          valeur={stats?.signales}
          note={stats?.signales === 0 ? 'Aucun signalement en attente' : 'En attente de modération'}
        />
      </div>

      <CarteMq className="flex flex-col gap-2 !p-3 !rounded-[24px]">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            pastille
            libelle={T.vue}
            onglets={OPTIONS_VUE.map((o) => ({
              cle: o.valeur,
              libelle: o.libelle,
              compteur: compteurs[o.valeur] ?? '—',
              actif: etat.valeurs.vue === o.valeur,
              onSelect: () => etat.definir({ vue: o.valeur }),
            }))}
          />
          <div className="ml-auto flex flex-wrap items-center gap-2.5">
            <ChampPrestataire valeur={etat.valeurs.prestataire} onValider={(v) => etat.definir({ prestataire: v })} />
            <label className="relative flex h-8 items-center gap-2 rounded-[18px] border border-[#e6e8ee] bg-white pr-4 pl-4 text-[13px] font-semibold text-[#12172b]">
              <IEtoileFiltre />
              <select value={etat.valeurs.note} onChange={(e) => etat.definir({ note: e.target.value })} aria-label={T.note} className="cursor-pointer appearance-none border-none bg-transparent pr-1 text-[13px] font-semibold outline-none">
                <option value="">{T.note}</option>
                {OPTIONS_NOTE.filter((o) => o.valeur !== '').map((o) => (
                  <option key={o.valeur} value={o.valeur}>
                    {o.libelle}
                  </option>
                ))}
              </select>
            </label>
            {filtresActifs && (
              <button type="button" onClick={() => etat.definir({ note: '', prestataire: '' })} className="inline-flex h-8 cursor-pointer items-center gap-1.5 border-none bg-transparent px-2 text-[13px] font-semibold text-[#6b7287]">
                <IReinit />
                Réinitialiser
              </button>
            )}
          </div>
        </div>
        <div className="overflow-x-auto">
          <table
            className="w-full min-w-[980px] border-collapse text-[13px] [&_tbody_td]:border-b [&_tbody_td]:border-[#eceef2] [&_tbody_tr:last-child_td]:border-b-0 [&_td]:px-4 [&_td]:py-1.5 [&_td]:align-middle [&_td]:font-medium"
            aria-label={T.legende}
          >
            <thead>
              <tr className="text-left text-[11px] tracking-[0.06em] text-[#9aa0b2] uppercase [&_th]:border-b [&_th]:border-[#eceef2] [&_th]:px-4 [&_th]:py-1.5 [&_th]:font-semibold">
                <th>Client</th>
                <th>Prestataire</th>
                <th>{T.note}</th>
                <th>Commentaire</th>
                <th>Date</th>
                <th>{L.commun.statut}</th>
                <th className="text-right">
                  <span className="sr-only">Actions</span>
                  <span aria-hidden="true">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {liste.lignes.map((a) => (
                <LigneAvis key={a.id} a={a} retour={retour} />
              ))}
            </tbody>
          </table>
        </div>
        <EtatsListe
          chargement={liste.chargement}
          erreur={liste.erreur}
          vide={liste.donneesPresentes && liste.lignes.length === 0}
          messageVide={T.vide}
          onReessayer={liste.recharger}
        />
        {liste.total > 0 && (
          <PaginationRonde
            resume={
              <>
                Affichage de <strong className="font-semibold text-[#12172b]">{liste.premier}–{liste.dernier}</strong> sur {liste.total} {liste.total > 1 ? 'avis' : 'avis'}
              </>
            }
            page={liste.page}
            totalPages={liste.totalPages}
            onPage={etat.allerPage}
          />
        )}
      </CarteMq>
    </div>
  )
}
