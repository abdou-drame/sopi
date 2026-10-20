import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { erreursParChamp, messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { Metier } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import BadgeStatut from '../components/ui/BadgeStatut'
import { useRetours } from '../components/ui/Alertes'
import Bouton from '../components/ui/Bouton'
import Carte from '../components/ui/Carte'
import { Champ } from '../components/ui/Champs'
import EnTetePage from '../components/ui/EnTetePage'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import Tableau from '../components/ui/Tableau'
import type { Colonne } from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { formatNombre } from '../lib/format'

const T = L.metiersAdmin
const LIBELLE_MIN = 2
const LIBELLE_MAX = 60

function FormulaireAjout({ onFait }: { onFait: (message: string) => void }) {
  const [libelle, setLibelle] = useState('')
  const action = useAction((nom: string) => api.creerMetier(nom))
  const soumettre = (e: FormEvent) => {
    e.preventDefault()
    action.mutate(libelle, {
      onSuccess: () => {
        setLibelle('')
        onFait(T.ajoute)
      },
    })
  }
  const erreur = action.isError ? (erreursParChamp(action.error).libelle ?? messageDErreur(action.error)) : undefined
  return (
    <Carte titre={T.ajouterTitre}>
      <form onSubmit={soumettre} noValidate className="flex flex-wrap items-start gap-3">
        <div className="min-w-64 flex-1">
          <Champ label={T.ajouterLibelle} aide={T.ajouterAide} value={libelle} maxLength={LIBELLE_MAX} erreur={erreur} onChange={(e) => setLibelle(e.target.value)} />
        </div>
        <Bouton type="submit" className="mt-7" enCours={action.isPending} disabled={libelle.trim().length < LIBELLE_MIN}>
          {T.ajouter}
        </Bouton>
      </form>
    </Carte>
  )
}

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
      <Bouton
        variante="secondaire"
        taille="p"
        aria-label={T.renommerLibelle(metier.libelle)}
        onClick={() => {
          setLibelle(metier.libelle)
          setOuverte(true)
        }}
      >
        {T.renommer}
      </Bouton>
      <ModaleConfirmation
        ouverte={ouverte}
        titre={T.renommerTitre}
        libelleConfirmer={T.enregistrer}
        champs={<Champ label={T.renommerChamp} value={libelle} maxLength={LIBELLE_MAX} onChange={(e) => setLibelle(e.target.value)} />}
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
    ? { bouton: T.desactiver, libelle: T.desactiverLibelle(metier.libelle), titre: T.desactiverTitre, texte: T.desactiverTexte, fait: T.desactiverFait }
    : { bouton: T.reactiver, libelle: T.reactiverLibelle(metier.libelle), titre: T.reactiverTitre, texte: T.reactiverTexte, fait: T.reactiverFait }
  return (
    <>
      <Bouton variante={desactiver ? 'danger' : 'secondaire'} taille="p" aria-label={textes.libelle} onClick={() => setOuverte(true)}>
        {textes.bouton}
      </Bouton>
      <ModaleConfirmation
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

const COULEURS_PASTILLE = ['bg-[#e7edff] text-[#1d4ed8]', 'bg-[#fde7f3] text-[#a3206b]', 'bg-[#d7f7e4] text-[#0e6245]', 'bg-[#fff0d4] text-[#983705]']

/** Carte d'un métier : pastille à l'initiale, état, nombre de prestataires et actions. */
function CarteMetier({ m, onFait }: { m: Metier; onFait: (message: string) => void }) {
  const couleur = COULEURS_PASTILLE[m.libelle.length % COULEURS_PASTILLE.length]
  return (
    <section aria-label={m.libelle} className="flex flex-col gap-[18px] rounded-[26px] bg-fond p-5">
      <div className="flex items-center gap-3.5">
        <span aria-hidden="true" className={`flex size-14 shrink-0 items-center justify-center rounded-full text-xl font-bold ${couleur}`}>
          {m.libelle.charAt(0).toUpperCase()}
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-lg font-bold text-texte">{m.libelle}</p>
          <p className="truncate text-xs text-texte-discret">{m.code}</p>
        </div>
        <BadgeStatut ton={m.actif ? 'succes' : 'neutre'} libelle={m.actif ? T.actif : T.inactif} />
      </div>
      <div className="rounded-moyen bg-surface px-4 py-3.5">
        <p className="text-xs text-texte-discret">{T.prestataires}</p>
        <p className="mt-0.5 text-[22px] font-bold tabular-nums text-texte">{formatNombre(m.nombrePrestataires)}</p>
      </div>
      <div className="flex flex-wrap gap-2">
        <ActionRenommer metier={m} onFait={onFait} />
        <ActionActivation metier={m} onFait={onFait} />
      </div>
    </section>
  )
}

export default function MetiersPage() {
  const requete = useQuery({ queryKey: ['metiers'], queryFn: api.metiers })
  const retour = useRetours()
  const colonnes: Colonne<Metier>[] = [
    { cle: 'nom', titre: T.nom, rendu: (m) => <span className="font-semibold text-texte">{m.libelle}</span> },
    { cle: 'code', titre: T.code, rendu: (m) => <span className="text-texte-doux">{m.code}</span> },
    { cle: 'prestataires', titre: T.prestataires, rendu: (m) => formatNombre(m.nombrePrestataires) },
    { cle: 'etat', titre: T.etat, rendu: (m) => <BadgeStatut ton={m.actif ? 'succes' : 'neutre'} libelle={m.actif ? T.actif : T.inactif} /> },
    {
      cle: 'actions',
      titre: T.actions,
      alignement: 'droite',
      rendu: (m) => (
        <div className="flex flex-wrap justify-end gap-2">
          <ActionRenommer metier={m} onFait={retour.succes} />
          <ActionActivation metier={m} onFait={retour.succes} />
        </div>
      ),
    },
  ]
  return (
    <div className="flex flex-col gap-3.5">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FormulaireAjout onFait={retour.succes} />
      <Tableau
        legende={T.legende}
        colonnes={colonnes}
        lignes={requete.data?.data ?? []}
        cleLigne={(m) => m.id}
        carte={(m) => <CarteMetier m={m} onFait={retour.succes} />}
        chargement={requete.isPending}
        erreur={requete.isError ? messageDErreur(requete.error) : null}
        onReessayer={() => void requete.refetch()}
        messageVide={T.vide}
      />
    </div>
  )
}
