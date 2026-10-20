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
        <Bouton type="submit" className="mt-7" enCours={action.isPending} succes={action.isSuccess} disabled={libelle.trim().length < LIBELLE_MIN}>
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
    <div className="flex flex-col gap-6">
      <EnTetePage titre={T.titre} sousTitre={T.sousTitre} />
      <FormulaireAjout onFait={retour.succes} />
      <Tableau
        legende={T.legende}
        colonnes={colonnes}
        lignes={requete.data?.data ?? []}
        cleLigne={(m) => m.id}
        chargement={requete.isPending}
        erreur={requete.isError ? messageDErreur(requete.error) : null}
        onReessayer={() => void requete.refetch()}
        messageVide={T.vide}
      />
    </div>
  )
}
