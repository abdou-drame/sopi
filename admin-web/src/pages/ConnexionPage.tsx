import { useState } from 'react'
import type { FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { ApiError, erreursParChamp } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import Bouton from '../components/ui/Bouton'
import { Champ } from '../components/ui/Champs'
import { IconeOeil } from '../components/ui/Icones'
import { L } from '../i18n/libelles'

/** Carte décorative inspirée d'une carte bancaire : donne la couleur de marque à l'écran de connexion. */
function CarteDecorative() {
  return (
    <div aria-hidden="true" className="animate-apparition relative w-full max-w-sm rounded-grand bg-gradient-to-br from-primaire-700 via-primaire-600 to-primaire-400 p-6 text-white shadow-eleve">
      <p className="text-sm text-primaire-100">Chiffre d’affaires du mois</p>
      <p className="mt-1 text-3xl font-semibold">1 250 000 FCFA</p>
      <div className="mt-6 flex gap-3">
        {['Dossiers', 'Litiges', 'Retraits'].map((t) => (
          <span key={t} className="flex-1 rounded-moyen bg-white/15 py-2 text-center text-xs backdrop-blur-sm">
            {t}
          </span>
        ))}
      </div>
    </div>
  )
}

export default function ConnexionPage() {
  const { utilisateur, avis, connexion } = useAuth()
  const navigate = useNavigate()
  const lieu = useLocation()
  const [telephone, setTelephone] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [visible, setVisible] = useState(false)
  const [enCours, setEnCours] = useState(false)
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null)
  const [erreursChamps, setErreursChamps] = useState<Record<string, string>>({})

  const destination = (lieu.state as { depuis?: string } | null)?.depuis ?? '/'
  if (utilisateur) return <Navigate to={destination} replace />

  const soumettre = async (e: FormEvent) => {
    e.preventDefault()
    setErreurGenerale(null)
    setErreursChamps({})
    setEnCours(true)
    try {
      await connexion(telephone.trim(), motDePasse)
      navigate(destination, { replace: true })
    } catch (erreur) {
      if (erreur instanceof ApiError && erreur.status === 422) {
        setErreursChamps(erreursParChamp(erreur))
      } else if (erreur instanceof ApiError && erreur.status === 401) {
        setErreurGenerale(L.connexion.identifiantsInvalides)
      } else if (erreur instanceof ApiError) {
        setErreurGenerale(erreur.message)
      } else {
        setErreurGenerale(L.erreurs.inconnue)
      }
    } finally {
      setEnCours(false)
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <section className="hidden flex-col justify-between bg-gradient-to-br from-primaire-900 via-primaire-800 to-primaire-600 p-12 text-white lg:flex">
        <div className="flex items-center gap-3">
          <span aria-hidden="true" className="grid size-11 place-items-center rounded-moyen bg-white/15 text-xl font-bold">
            S
          </span>
          <span className="text-xl font-semibold">{L.marque}</span>
        </div>
        <div className="flex flex-col gap-8">
          <div>
            <h2 className="text-3xl font-semibold">Pilotez la plateforme en toute confiance.</h2>
            <p className="mt-2 max-w-md text-primaire-100">
              Réservations, paiements, litiges et modération des services de proximité de Keur Massar.
            </p>
          </div>
          <CarteDecorative />
        </div>
        <p className="text-xs text-primaire-200">{L.sousMarque}</p>
      </section>

      <main className="grid place-items-center p-6">
        <form onSubmit={soumettre} noValidate className="animate-apparition flex w-full max-w-sm flex-col gap-5">
          <div>
            <h1 className="text-2xl font-semibold text-primaire-800">{L.connexion.titre}</h1>
            <p className="mt-1 text-sm text-texte-doux">{L.connexion.sousTitre}</p>
          </div>

          {avis && (
            <p role="status" className="rounded-moyen bg-attention-fond px-4 py-3 text-sm font-medium text-attention-texte">
              {avis}
            </p>
          )}
          {erreurGenerale && (
            <p role="alert" className="rounded-moyen bg-erreur-fond px-4 py-3 text-sm font-medium text-erreur-texte">
              {erreurGenerale}
            </p>
          )}

          <Champ
            label={L.connexion.telephone}
            type="tel"
            name="telephone"
            autoComplete="username"
            inputMode="tel"
            value={telephone}
            onChange={(e) => setTelephone(e.target.value)}
            aide={L.connexion.telephoneAide}
            erreur={erreursChamps.telephone}
            required
          />
          <div className="relative">
            <Champ
              label={L.connexion.motDePasse}
              type={visible ? 'text' : 'password'}
              name="password"
              autoComplete="current-password"
              value={motDePasse}
              onChange={(e) => setMotDePasse(e.target.value)}
              erreur={erreursChamps.password}
              className="pr-11"
              required
            />
            <button
              type="button"
              onClick={() => setVisible((v) => !v)}
              aria-label={visible ? L.connexion.masquer : L.connexion.afficher}
              aria-pressed={visible}
              className="absolute top-[2.15rem] right-2 grid size-8 place-items-center rounded-petit text-texte-doux hover:text-primaire-700"
            >
              <IconeOeil width={18} height={18} />
            </button>
          </div>

          <Bouton type="submit" enCours={enCours} className="w-full">
            {enCours ? L.connexion.enCours : L.connexion.action}
          </Bouton>
        </form>
      </main>
    </div>
  )
}
