import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import type { ReactNode } from 'react'
import { erreursParChamp, messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { Metier } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import { ChampTexte } from '../design/champs'
import Fenetre from '../design/Fenetre'
import { Badge, BoutonPlus, Carte as CarteMq, TitrePage } from '../design/composants'
import { ICiseaux, IChemise, IMetiers } from '../design/icones'
import { EtatsListe } from '../design/useListe'
import { L } from '../i18n/libelles'
import { formatNombre } from '../lib/format'

const T = L.metiersAdmin
const LIBELLE_MIN = 2
const LIBELLE_MAX = 60

function FenetreAjout({ ouverte, onFermer, onFait }: { ouverte: boolean; onFermer: () => void; onFait: (message: string) => void }) {
  const [libelle, setLibelle] = useState('')
  const action = useAction((nom: string) => api.creerMetier(nom))
  const fermer = () => {
    setLibelle('')
    action.reset()
    onFermer()
  }
  return (
    <Fenetre
      ouverte={ouverte}
      titre={T.ajouterTitre}
      libelleConfirmer={T.ajouter}
      champs={<ChampTexte label={T.ajouterLibelle} aide={T.ajouterAide} value={libelle} maxLength={LIBELLE_MAX} onChange={(e) => setLibelle(e.target.value)} />}
      confirmerDesactive={libelle.trim().length < LIBELLE_MIN}
      enCours={action.isPending}
      erreur={action.isError ? (erreursParChamp(action.error).libelle ?? messageDErreur(action.error)) : undefined}
      onAnnuler={fermer}
      onConfirmer={() =>
        action.mutate(libelle, {
          onSuccess: () => {
            fermer()
            onFait(T.ajoute)
          },
        })
      }
    />
  )
}

const BOUTON_MQ = 'mq-btn-gris h-11 min-w-0 flex-1 cursor-pointer rounded-[22px] border-none bg-[#f3f4f7] px-3 text-[13px] font-bold text-[#12172b]'

function ActionRenommer({ metier, onFait }: { metier: Metier; onFait: (message: string) => void }) {
  const [ouverte, setOuverte] = useState(false)
  const [libelle, setLibelle] = useState(metier.libelle)
  const action = useAction((nom: string) => api.modifierMetier(metier.id, { libelle: nom }))
  const fermer = () => {
    setOuverte(false)
    action.reset()
  }
  const propre = libelle.trim()
  return (
    <>
      <button
        type="button"
        className={BOUTON_MQ}
        aria-label={T.renommerLibelle(metier.libelle)}
        onClick={() => {
          setLibelle(metier.libelle)
          setOuverte(true)
        }}
      >
        Modifier
      </button>
      <Fenetre
        ouverte={ouverte}
        titre={T.renommerTitre}
        libelleConfirmer={T.enregistrer}
        champs={<ChampTexte label={T.renommerChamp} value={libelle} maxLength={LIBELLE_MAX} onChange={(e) => setLibelle(e.target.value)} />}
        confirmerDesactive={propre.length < LIBELLE_MIN || propre === metier.libelle}
        enCours={action.isPending}
        erreur={action.isError ? (erreursParChamp(action.error).libelle ?? messageDErreur(action.error)) : undefined}
        onAnnuler={fermer}
        onConfirmer={() =>
          action.mutate(propre, {
            onSuccess: () => {
              fermer()
              onFait(T.renommerFait)
            },
          })
        }
      />
    </>
  )
}

function ActionActivation({ metier, onFait }: { metier: Metier; onFait: (message: string) => void }) {
  const [ouverte, setOuverte] = useState(false)
  const desactiver = metier.actif
  const action = useAction(() => api.modifierMetier(metier.id, { actif: !desactiver }))
  const fermer = () => {
    setOuverte(false)
    action.reset()
  }
  const textes = desactiver
    ? {
        bouton: T.desactiver,
        libelle: T.desactiverLibelle(metier.libelle),
        titre: T.desactiverTitre,
        texte: T.desactiverTexte,
        fait: T.desactiverFait,
      }
    : {
        bouton: T.reactiver,
        libelle: T.reactiverLibelle(metier.libelle),
        titre: T.reactiverTitre,
        texte: T.reactiverTexte,
        fait: T.reactiverFait,
      }
  return (
    <>
      <button type="button" className={BOUTON_MQ} aria-label={textes.libelle} onClick={() => setOuverte(true)}>
        {textes.bouton}
      </button>
      <Fenetre
        ouverte={ouverte}
        titre={textes.titre}
        description={textes.texte}
        libelleConfirmer={textes.bouton}
        danger={desactiver}
        enCours={action.isPending}
        erreur={action.isError ? messageDErreur(action.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={() =>
          action.mutate(undefined, {
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

/** Pastille d'icône des maquettes : clé (Mécanique), ciseaux (Coiffure), chemise (Pressing) ; autre métier : son initiale. */
const PASTILLES: Record<string, { fond: string; icone: ReactNode }> = {
  mecanique: {
    fond: 'bg-[#e7edff] text-[#1d4ed8]',
    icone: <IMetiers taille={24} trait={1.8} />,
  },
  coiffure: {
    fond: 'bg-[#fde7f3] text-[#a3206b]',
    icone: <ICiseaux taille={24} trait={1.8} />,
  },
  pressing: {
    fond: 'bg-[#d7f7e4] text-[#0e6245]',
    icone: <IChemise taille={24} trait={1.8} />,
  },
}

const cle = (s: string) =>
  s
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()

function TuileChiffre({ libelle, valeur }: { libelle: string; valeur: string }) {
  return (
    <div className="rounded-[20px] bg-[#f3f4f7] px-4 py-3.5">
      <div className="text-xs text-[#8a90a2]">{libelle}</div>
      <div className="mt-0.5 text-[22px] font-bold tabular-nums">{valeur}</div>
    </div>
  )
}

/** Carte d'un métier : pastille d'icône, nom, état, tuiles Prestataires / Réservations, actions. */
function CarteMetier({ m, onFait }: { m: Metier; onFait: (message: string) => void }) {
  const pastille = PASTILLES[cle(m.code)] ??
    PASTILLES[cle(m.libelle)] ?? {
      fond: 'bg-[#fff0d4] text-[#983705]',
      icone: <span className="text-xl font-bold">{m.libelle.charAt(0).toUpperCase()}</span>,
    }
  return (
    <CarteMq aria-label={m.libelle} className="flex flex-col gap-[18px]">
      <div className="flex items-center gap-3.5">
        <span aria-hidden="true" className={`flex size-14 shrink-0 items-center justify-center rounded-full ${pastille.fond}`}>
          {pastille.icone}
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-lg font-bold">{m.libelle}</span>
          <span className="mt-0.5 block text-xs text-[#8a90a2]">—</span>
        </span>
        <Badge ton={m.actif ? 'succes' : 'neutre'} gras>
          {m.actif ? T.actif : T.inactif}
        </Badge>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <TuileChiffre libelle={T.prestataires} valeur={formatNombre(m.nombrePrestataires)} />
        <TuileChiffre libelle="Réservations" valeur="—" />
      </div>
      <div className="flex gap-2">
        <ActionRenommer metier={m} onFait={onFait} />
        <ActionActivation metier={m} onFait={onFait} />
      </div>
    </CarteMq>
  )
}

export default function MetiersPage() {
  const requete = useQuery({ queryKey: ['metiers'], queryFn: api.metiers })
  const retour = useRetours()
  const [ajout, setAjout] = useState(false)
  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Métiers" suite="proposés" sousTitre="Gérez les catégories de services que les clients peuvent réserver.">
        <BoutonPlus onClick={() => setAjout(true)}>Ajouter un métier</BoutonPlus>
      </TitrePage>
      <FenetreAjout ouverte={ajout} onFermer={() => setAjout(false)} onFait={retour.succes} />
      <EtatsListe
        chargement={requete.isPending}
        erreur={requete.isError ? messageDErreur(requete.error) : null}
        vide={!!requete.data && requete.data.data.length === 0}
        messageVide={T.vide}
        onReessayer={() => void requete.refetch()}
      />
      <div className="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-3.5" aria-label={T.legende}>
        {(requete.data?.data ?? []).map((m) => (
          <CarteMetier key={m.id} m={m} onFait={retour.succes} />
        ))}
      </div>
    </div>
  )
}
