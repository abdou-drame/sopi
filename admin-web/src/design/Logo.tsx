/** Logo de la barre du haut, tel que dessiné dans les maquettes. */
export default function Logo({ taille = 34 }: { taille?: number }) {
  return (
    <svg width={taille} height={taille} viewBox="0 0 120 120" aria-hidden="true" focusable="false">
      <defs>
        <linearGradient id="mq-logo" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#3b66ea" />
          <stop offset="1" stopColor="#1d4ed8" />
        </linearGradient>
      </defs>
      <rect width="120" height="120" rx="30" fill="url(#mq-logo)" />
      <path
        d="M84 40C84 28 73 22 60 22C45 22 36 30 36 41C36 52 45 56 60 60C75 64 84 68 84 79C84 90 75 98 60 98C46 98 36 91 36 78"
        fill="none"
        stroke="#fff"
        strokeWidth="16"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx="93" cy="26" r="10" fill="#fbbf24" />
    </svg>
  )
}
