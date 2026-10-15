/** Bloc gris pulsant : remplace le contenu pendant le chargement. Invisible aux lecteurs d'écran (le conteneur annonce le chargement). */
export default function Squelette({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`animate-pulsation rounded-petit bg-primaire-100/70 ${className}`} />
}
