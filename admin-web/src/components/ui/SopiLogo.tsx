interface Props {
  /** Côté du carré, en pixels. */
  taille?: number
  className?: string
}

/** Logo Sopi (« S » blanc et point orange sur fond bleu), fichier servi depuis public/logo. */
export default function SopiLogo({ taille = 40, className = '' }: Props) {
  return (
    <img
      src="/logo/sopi_icon_rounded.svg"
      alt="Sopi"
      width={taille}
      height={taille}
      className={`shrink-0 ${className}`}
      style={{ width: taille, height: taille }}
    />
  )
}
