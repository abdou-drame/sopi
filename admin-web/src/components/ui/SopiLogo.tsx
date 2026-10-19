interface Props {
  /** Côté du carré, en pixels. */
  taille?: number
  className?: string
  /** Vrai quand le mot « Sopi » est déjà écrit à côté : l'image est alors ignorée par les lecteurs d'écran. */
  decoratif?: boolean
}

/** Logo Sopi (« S » blanc et point orange sur fond bleu), fichier servi depuis public/logo. */
export default function SopiLogo({ taille = 40, className = '', decoratif = false }: Props) {
  return (
    <img
      src="/logo/sopi_icon_rounded.svg"
      alt={decoratif ? '' : 'Sopi'}
      width={taille}
      height={taille}
      className={`shrink-0 ${className}`}
      style={{ width: taille, height: taille }}
    />
  )
}
