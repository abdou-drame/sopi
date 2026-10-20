const COULEURS = ['bg-[#dbeafe] text-[#1d4ed8]', 'bg-[#fde7f3] text-[#a3206b]', 'bg-[#fff0d4] text-[#983705]', 'bg-[#d7f7e4] text-[#0e6245]']

/** Pastille ronde avec les initiales ; la couleur dépend du nom (stable d'un affichage à l'autre). */
export default function AvatarInitiales({ prenom, nom }: { prenom: string; nom: string }) {
  const initiales = `${prenom.charAt(0)}${nom.charAt(0)}`.toUpperCase()
  const couleur = COULEURS[(prenom.length + nom.length) % COULEURS.length]
  return (
    <span aria-hidden="true" className={`flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold ${couleur}`}>
      {initiales}
    </span>
  )
}
