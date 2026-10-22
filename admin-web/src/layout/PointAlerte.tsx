/** Point rouge des alertes (comme sur la cloche) : posé en haut à droite d'un élément `relative`. */
export function PointAlerte({ className = '', bordure = 'border-white' }: { className?: string; bordure?: string }) {
  return <span aria-hidden="true" data-testid="point-alerte" className={`absolute box-content size-2 rounded-full border-2 bg-[#e5484d] ${bordure} ${className}`} />
}
