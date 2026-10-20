/** Bloc gris animé : remplace le contenu pendant le chargement. Invisible aux lecteurs d'écran (le conteneur annonce le chargement). */
export default function Squelette({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`mq-sk rounded-xl ${className}`} />
}
