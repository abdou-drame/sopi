import { useId, useState } from 'react'
import type { FormEvent, InputHTMLAttributes, ReactNode } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import FondArrierePlan from '../components/ui/FondArrierePlan'
import { ApiError, erreursParChamp } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import Bouton from '../components/ui/Bouton'
import { IconeCadenas, IconeOeil, IconeTelephone } from '../components/ui/Icones'
import SopiLogo from '../components/ui/SopiLogo'
import { L } from '../i18n/libelles'

type ChampConnexionProps = {
  label: string
  icone: ReactNode
  erreur?: string
  aide?: string
  droite?: ReactNode
} & InputHTMLAttributes<HTMLInputElement>

/** Champ de la page de connexion : icône à gauche, libellé relié au champ, erreur annoncée. */
function ChampConnexion({ label, icone, erreur, aide, droite, ...reste }: ChampConnexionProps) {
  const id = useId()
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-texte">
        {label}
      </label>
      <div className="relative">
        <span aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-texte-discret">
          {icone}
        </span>
        <input
          id={id}
          aria-invalid={erreur ? true : undefined}
          aria-describedby={erreur ? `${id}-erreur` : aide ? `${id}-aide` : undefined}
          className={`w-full rounded-moyen border bg-surface py-3 pr-11 pl-11 text-sm text-texte transition-colors placeholder:text-texte-discret hover:border-primaire-300 focus:border-primaire-500 ${
            erreur ? 'border-erreur' : 'border-bordure'
          }`}
          {...reste}
        />
        {droite}
      </div>
      {aide && !erreur && (
        <p id={`${id}-aide`} className="text-xs text-texte-doux">
          {aide}
        </p>
      )}
      {erreur && (
        <p id={`${id}-erreur`} role="alert" className="text-xs font-medium text-erreur-texte">
          {erreur}
        </p>
      )}
    </div>
  )
}

// Aperçus décoratifs du panneau de droite : chiffres d'illustration, sans lien avec les données réelles.
// Couleurs fixes (indépendantes du thème) car posées sur un fond bleu.
const CARTE_APERCU = 'absolute rounded-grand border-2 border-white/50 bg-white p-4 text-slate-800 shadow-eleve'

function Anneau() {
  const r = 34
  const c = 2 * Math.PI * r
  const part = (valeur: number, decalage: number, couleur: string) => (
    <circle
      cx="44"
      cy="44"
      r={r}
      fill="none"
      stroke={couleur}
      strokeWidth="11"
      strokeDasharray={`${c * valeur} ${c}`}
      strokeDashoffset={-c * decalage}
      transform="rotate(-90 44 44)"
    />
  )
  return (
    <svg viewBox="0 0 88 88" className="size-20 shrink-0">
      <circle cx="44" cy="44" r={r} fill="none" stroke="#e2e8f0" strokeWidth="11" />
      {part(0.62, 0, 'var(--color-neutre)')}
      {part(0.2, 0.64, '#f5b82e')}
      {part(0.1, 0.86, '#ef6a5a')}
    </svg>
  )
}

function Progression({ libelle, valeur, pourcent, couleur }: { libelle: string; valeur: string; pourcent: number; couleur: string }) {
  return (
    <div className="mt-2.5">
      <div className="flex items-baseline justify-between text-[10px] text-slate-500">
        <span>{libelle}</span>
        <span className="font-semibold text-slate-700">{valeur}</span>
      </div>
      <div className="mt-1 h-1.5 overflow-hidden rounded-gelule bg-slate-200">
        <div className="h-full rounded-gelule" style={{ width: `${pourcent}%`, background: couleur }} />
      </div>
    </div>
  )
}

function Apercus() {
  const A = L.connexion.apercu
  return (
    <div className="relative h-[27rem] w-full max-w-[34rem]">
      <div className={`${CARTE_APERCU} top-0 left-8 w-64`}>
        <p className="text-[11px] font-semibold">{A.revenus}</p>
        <div className="mt-3 flex items-center gap-4">
          <Anneau />
          <div>
            <p className="text-xl font-bold">1 250 000</p>
            <p className="text-[10px] text-slate-500">{A.disponible} · FCFA</p>
            <ul className="mt-2 space-y-1 text-[10px] text-slate-500">
              <li className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-blue-600" />{A.acomptes}</li>
              <li className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-amber-400" />{A.commissions}</li>
              <li className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-red-400" />{A.retraits}</li>
            </ul>
          </div>
        </div>
        <div className="mt-3 rounded-petit bg-slate-700 py-1.5 text-center text-[10px] font-medium text-white">{A.voirTout}</div>
      </div>

      <div className={`${CARTE_APERCU} top-24 right-0 w-60`}>
        <p className="text-[11px] font-semibold">{A.reservations}</p>
        <p className="mt-2 text-lg font-bold">248</p>
        <Progression libelle={A.confirmees} valeur="132" pourcent={68} couleur="var(--color-neutre)" />
        <Progression libelle={A.terminees} valeur="90" pourcent={46} couleur="#10b981" />
        <Progression libelle={A.enAttente} valeur="26" pourcent={14} couleur="#f5b82e" />
      </div>

      <div className={`${CARTE_APERCU} bottom-0 left-0 w-64`}>
        <div className="flex items-baseline justify-between">
          <p className="text-[11px] font-semibold">{A.dossiers}</p>
          <p className="text-sm font-bold text-amber-500">3</p>
        </div>
        <ul className="mt-2 divide-y divide-slate-200 text-[11px]">
          {[A.dossier1, A.dossier2, A.dossier3].map((nom) => (
            <li key={nom} className="flex items-center justify-between py-1.5">
              <span>{nom}</span>
              <span className="rounded-gelule bg-amber-100 px-2 py-0.5 text-[9px] font-medium text-amber-700">…</span>
            </li>
          ))}
        </ul>
        <div className="mt-2 rounded-petit bg-slate-700 py-1.5 text-center text-[10px] font-medium text-white">{A.voirTout}</div>
      </div>
    </div>
  )
}

function Marque() {
  return (
    <span className="flex items-center gap-2.5">
      <SopiLogo taille={32} decoratif />
      <span className="text-lg font-semibold text-primaire-800">{L.marque}</span>
    </span>
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
    <div className="min-h-dvh">
      <FondArrierePlan />
      <div className="animate-apparition grid min-h-dvh w-full lg:grid-cols-2">
        <main className="flex flex-col bg-surface p-8 sm:p-10">
          <Marque />
          <form onSubmit={soumettre} noValidate className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-5 py-8">
            <div className="text-center">
              <h1 className="text-2xl font-bold text-primaire-800">{L.connexion.titre}</h1>
              <p className="mt-2 text-sm text-texte-doux">{L.connexion.sousTitre}</p>
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

            <ChampConnexion
              label={L.connexion.telephone}
              icone={<IconeTelephone width={18} height={18} />}
              type="tel"
              name="telephone"
              autoComplete="username"
              inputMode="tel"
              placeholder={L.connexion.telephonePlaceholder}
              value={telephone}
              onChange={(e) => setTelephone(e.target.value)}
              aide={L.connexion.telephoneAide}
              erreur={erreursChamps.telephone}
              required
            />
            <ChampConnexion
              label={L.connexion.motDePasse}
              icone={<IconeCadenas width={18} height={18} />}
              type={visible ? 'text' : 'password'}
              name="password"
              autoComplete="current-password"
              placeholder={L.connexion.motDePassePlaceholder}
              value={motDePasse}
              onChange={(e) => setMotDePasse(e.target.value)}
              erreur={erreursChamps.password}
              required
              droite={
                <button
                  type="button"
                  onClick={() => setVisible((v) => !v)}
                  aria-label={visible ? L.connexion.masquer : L.connexion.afficher}
                  aria-pressed={visible}
                  className="absolute top-1/2 right-2 grid size-8 -translate-y-1/2 place-items-center rounded-petit text-texte-doux hover:text-primaire-700"
                >
                  <IconeOeil width={18} height={18} />
                </button>
              }
            />

            <Bouton type="submit" enCours={enCours} className="w-full">
              {enCours ? L.connexion.enCours : L.connexion.action}
            </Bouton>
          </form>
          <p className="text-center text-xs text-texte-discret">{L.connexion.droits}</p>
        </main>

        <section aria-hidden="true" className="carte-sopi relative hidden flex-col overflow-hidden text-white lg:flex">
          <div className="absolute inset-0 opacity-40 [background-image:linear-gradient(rgb(255_255_255/0.08)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.08)_1px,transparent_1px)] [background-size:56px_56px]" />
          <div className="relative flex flex-1 items-center justify-center px-8 pt-8">
            <Apercus />
          </div>
          <div className="relative flex flex-col items-center gap-3 px-12 pb-8 text-center">
            <SopiLogo taille={56} className="shadow-eleve" decoratif />
            <h2 className="text-2xl font-semibold">{L.connexion.accrocheTitre}</h2>
            <p className="max-w-md text-sm text-primaire-100">{L.connexion.accrocheTexte}</p>
            <div className="mt-3 flex w-full gap-2">
              <span className="h-1 flex-[3] rounded-gelule bg-white" />
              <span className="h-1 flex-[3] rounded-gelule bg-white/45" />
              <span className="h-1 flex-[3] rounded-gelule bg-white/25" />
              <span className="h-1 flex-[3] rounded-gelule bg-white/25" />
            </div>
          </div>
        </section>
      </div>
    </div>
  )
}
