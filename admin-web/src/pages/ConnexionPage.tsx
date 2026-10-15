import { useId, useState } from "react";
import type { FormEvent, InputHTMLAttributes, ReactNode } from "react";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { ApiError, erreursParChamp } from "../api/client";
import { useAuth } from "../auth/AuthContext";
import Bouton from "../components/ui/Bouton";
import {
  IconeCadenas,
  IconeOeil,
  IconeTelephone,
} from "../components/ui/Icones";
import { L } from "../i18n/libelles";

type ChampConnexionProps = {
  label: string;
  icone: ReactNode;
  erreur?: string;
  aide?: string;
  droite?: ReactNode;
} & InputHTMLAttributes<HTMLInputElement>;

/** Champ de la page de connexion : icône à gauche, libellé relié au champ, erreur annoncée. */
function ChampConnexion({
  label,
  icone,
  erreur,
  aide,
  droite,
  ...reste
}: ChampConnexionProps) {
  const id = useId();
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-texte">
        {label}
      </label>
      <div className="relative">
        <span
          aria-hidden="true"
          className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-texte-discret"
        >
          {icone}
        </span>
        <input
          id={id}
          aria-invalid={erreur ? true : undefined}
          aria-describedby={
            erreur ? `${id}-erreur` : aide ? `${id}-aide` : undefined
          }
          className={`w-full rounded-moyen border bg-surface py-3 pr-11 pl-11 text-sm text-texte transition-colors placeholder:text-texte-discret hover:border-primaire-300 focus:border-primaire-500 ${
            erreur ? "border-erreur" : "border-bordure"
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
        <p
          id={`${id}-erreur`}
          role="alert"
          className="text-xs font-medium text-erreur-texte"
        >
          {erreur}
        </p>
      )}
    </div>
  );
}

/** Courbes décoratives du panneau de droite (aucune donnée, purement graphique). */
function Courbes() {
  const traits = Array.from({ length: 14 }, (_, i) => i);
  return (
    <svg
      viewBox="0 0 600 800"
      preserveAspectRatio="xMidYMid slice"
      className="absolute inset-0 size-full"
      fill="none"
    >
      {traits.map((i) => (
        <path
          key={i}
          d={`M-40 ${120 + i * 38} C 120 ${40 + i * 30}, 260 ${300 + i * 22}, 400 ${230 + i * 30} S 600 ${120 + i * 34}, 660 ${200 + i * 28}`}
          stroke="white"
          strokeOpacity={0.08 + (i % 5) * 0.05}
          strokeWidth="1.5"
        />
      ))}
    </svg>
  );
}

function Marque() {
  return (
    <span className="flex items-center gap-2.5">
      <span
        aria-hidden="true"
        className="grid size-8 place-items-center rounded-petit bg-primaire-700 text-sm font-bold text-white"
      >
        S
      </span>
      <span className="text-lg font-semibold text-primaire-800">
        {L.marque}
      </span>
    </span>
  );
}

export default function ConnexionPage() {
  const { utilisateur, avis, connexion } = useAuth();
  const navigate = useNavigate();
  const lieu = useLocation();
  const [telephone, setTelephone] = useState("");
  const [motDePasse, setMotDePasse] = useState("");
  const [visible, setVisible] = useState(false);
  const [enCours, setEnCours] = useState(false);
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null);
  const [erreursChamps, setErreursChamps] = useState<Record<string, string>>(
    {},
  );

  const destination = (lieu.state as { depuis?: string } | null)?.depuis ?? "/";
  if (utilisateur) return <Navigate to={destination} replace />;

  const soumettre = async (e: FormEvent) => {
    e.preventDefault();
    setErreurGenerale(null);
    setErreursChamps({});
    setEnCours(true);
    try {
      await connexion(telephone.trim(), motDePasse);
      navigate(destination, { replace: true });
    } catch (erreur) {
      if (erreur instanceof ApiError && erreur.status === 422) {
        setErreursChamps(erreursParChamp(erreur));
      } else if (erreur instanceof ApiError && erreur.status === 401) {
        setErreurGenerale(L.connexion.identifiantsInvalides);
      } else if (erreur instanceof ApiError) {
        setErreurGenerale(erreur.message);
      } else {
        setErreurGenerale(L.erreurs.inconnue);
      }
    } finally {
      setEnCours(false);
    }
  };

  return (
    <div className="animate-apparition grid min-h-screen bg-surface lg:grid-cols-2">
      <main className="flex flex-col p-8 sm:p-12">
        <Marque />
        <form
          onSubmit={soumettre}
          noValidate
          className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-5 py-8"
        >
          <div className="text-center">
            <h1 className="text-2xl font-bold text-primaire-800">
              {L.connexion.titre}
            </h1>
            <p className="mt-2 text-sm text-texte-doux">
              {L.connexion.sousTitre}
            </p>
          </div>

          {avis && (
            <p
              role="status"
              className="rounded-moyen bg-attention-fond px-4 py-3 text-sm font-medium text-attention-texte"
            >
              {avis}
            </p>
          )}
          {erreurGenerale && (
            <p
              role="alert"
              className="rounded-moyen bg-erreur-fond px-4 py-3 text-sm font-medium text-erreur-texte"
            >
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
            type={visible ? "text" : "password"}
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
                aria-label={
                  visible ? L.connexion.masquer : L.connexion.afficher
                }
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
        <p className="text-center text-xs text-texte-discret">
          {L.connexion.droits}
        </p>
      </main>

      <section
        aria-hidden="true"
        className="carte-sopi relative hidden flex-col justify-end overflow-hidden text-white lg:flex lg:rounded-l-[3rem]"
      >
        <Courbes />
        <div className="relative flex flex-col gap-3 p-14">
          <span className="grid size-14 place-items-center rounded-grand bg-white/15 text-2xl font-bold shadow-eleve backdrop-blur-sm">
            S
          </span>
          <h2 className="mt-2 text-3xl font-semibold">
            {L.connexion.accrocheTitre}
          </h2>
          <p className="max-w-md text-sm text-primaire-100">
            {L.connexion.accrocheTexte}
          </p>
        </div>
      </section>
    </div>
  );
}
