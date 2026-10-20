/** Bloc gris pulsant : remplace le contenu pendant le chargement. Invisible aux lecteurs d'écran (le conteneur annonce le chargement). */
export default function Squelette({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`animate-balayage rounded-petit bg-primaire-100/70 bg-[linear-gradient(90deg,transparent_0%,color-mix(in_srgb,var(--color-primaire-50)_80%,transparent)_50%,transparent_100%)] bg-[length:200%_100%] ${className}`} />
}
