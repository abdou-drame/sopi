import type { ReactNode, SVGProps } from 'react'

// Petit jeu d'icônes en SVG (trait), sans dépendance. Décoratives : masquées aux lecteurs d'écran.
function Icone({ children, ...props }: SVGProps<SVGSVGElement> & { children: ReactNode }) {
  return (
    <svg
      viewBox="0 0 24 24"
      width="20"
      height="20"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      {...props}
    >
      {children}
    </svg>
  )
}

type P = SVGProps<SVGSVGElement>

export const IconeAccueil = (p: P) => (
  <Icone {...p}>
    <path d="M3 11l9-8 9 8" />
    <path d="M5 10v10h14V10" />
  </Icone>
)
export const IconeRevenus = (p: P) => (
  <Icone {...p}>
    <path d="M3 17l6-6 4 4 8-8" />
    <path d="M15 7h6v6" />
  </Icone>
)
export const IconeDossier = (p: P) => (
  <Icone {...p}>
    <path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z" />
    <path d="M14 3v5h5M9 13h6M9 17h4" />
  </Icone>
)
export const IconeLitige = (p: P) => (
  <Icone {...p}>
    <path d="M12 3v18M5 7h14M5 7l-3 7a3 3 0 006 0zM19 7l-3 7a3 3 0 006 0z" />
  </Icone>
)
export const IconeSanction = (p: P) => (
  <Icone {...p}>
    <circle cx="12" cy="12" r="9" />
    <path d="M5.6 5.6l12.8 12.8" />
  </Icone>
)
export const IconeAvis = (p: P) => (
  <Icone {...p}>
    <path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z" />
  </Icone>
)
export const IconeMetier = (p: P) => (
  <Icone {...p}>
    <rect x="3" y="7" width="18" height="13" rx="2" />
    <path d="M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2" />
    <path d="M3 13h18" />
  </Icone>
)
export const IconeUtilisateurs = (p: P) => (
  <Icone {...p}>
    <circle cx="9" cy="8" r="3.5" />
    <path d="M2.5 20a6.5 6.5 0 0113 0M16 4.5a3.5 3.5 0 010 7M18 14.5a6.5 6.5 0 013.5 5.5" />
  </Icone>
)
export const IconeReservation = (p: P) => (
  <Icone {...p}>
    <rect x="3" y="5" width="18" height="16" rx="2" />
    <path d="M3 10h18M8 3v4M16 3v4" />
  </Icone>
)
export const IconePaiement = (p: P) => (
  <Icone {...p}>
    <rect x="2.5" y="5" width="19" height="14" rx="2" />
    <path d="M2.5 10h19M6 15h4" />
  </Icone>
)
export const IconeRetrait = (p: P) => (
  <Icone {...p}>
    <path d="M12 3v12M7 10l5 5 5-5M4 20h16" />
  </Icone>
)
export const IconeCloche = (p: P) => (
  <Icone {...p}>
    <path d="M6 9a6 6 0 0112 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9zM10 20a2 2 0 004 0" />
  </Icone>
)
export const IconeDeconnexion = (p: P) => (
  <Icone {...p}>
    <path d="M9 4H5a2 2 0 00-2 2v12a2 2 0 002 2h4M16 8l4 4-4 4M20 12H9" />
  </Icone>
)
export const IconeChevron = (p: P) => (
  <Icone {...p}>
    <path d="M15 6l-6 6 6 6" />
  </Icone>
)
export const IconeFleche = (p: P) => (
  <Icone {...p}>
    <path d="M5 12h14M13 6l6 6-6 6" />
  </Icone>
)
export const IconeInfo = (p: P) => (
  <Icone {...p}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 11v5M12 8h.01" />
  </Icone>
)
export const IconeAttention = (p: P) => (
  <Icone {...p}>
    <path d="M12 3l10 18H2z" />
    <path d="M12 10v5M12 18h.01" />
  </Icone>
)
export const IconeCritique = (p: P) => (
  <Icone {...p}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7v6M12 16.5h.01" />
  </Icone>
)
export const IconeFermer = (p: P) => (
  <Icone {...p}>
    <path d="M6 6l12 12M18 6L6 18" />
  </Icone>
)
export const IconeTri = (p: P) => (
  <Icone {...p} width="14" height="14">
    <path d="M8 9l4-4 4 4M8 15l4 4 4-4" />
  </Icone>
)
export const IconeMenu = (p: P) => (
  <Icone {...p}>
    <path d="M4 6h16M4 12h16M4 18h16" />
  </Icone>
)
export const IconeOeil = (p: P) => (
  <Icone {...p}>
    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" />
    <circle cx="12" cy="12" r="3" />
  </Icone>
)

export const IconeSoleil = (p: P) => (
  <Icone {...p}>
    <circle cx="12" cy="12" r="4" />
    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
  </Icone>
)
export const IconeLune = (p: P) => (
  <Icone {...p}>
    <path d="M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z" />
  </Icone>
)

export const IconeChevronBas = (p: P) => (
  <Icone {...p}>
    <path d="M6 9l6 6 6-6" />
  </Icone>
)
export const IconeChevronDroite = (p: P) => (
  <Icone {...p}>
    <path d="M9 6l6 6-6 6" />
  </Icone>
)
export const IconeCalendrier = (p: P) => (
  <Icone {...p}>
    <rect x="3.5" y="5" width="17" height="15.5" rx="3" />
    <path d="M3.5 10h17M8 3v4M16 3v4" />
  </Icone>
)
export const IconeRetour = (p: P) => (
  <Icone {...p}>
    <path d="M19 12H5M11 6l-6 6 6 6" />
  </Icone>
)
export const IconeRecherche = (p: P) => (
  <Icone {...p}>
    <circle cx="11" cy="11" r="6.5" />
    <path d="M16 16l4.5 4.5" />
  </Icone>
)

export const IconeActualiser = (p: P) => (
  <Icone {...p}>
    <path d="M20 11a8 8 0 0 0-14.5-4.5L4 8" />
    <path d="M4 4v4h4" />
    <path d="M4 13a8 8 0 0 0 14.5 4.5L20 16" />
    <path d="M20 20v-4h-4" />
  </Icone>
)

export const IconeGraphique = (p: P) => (
  <Icone {...p}>
    <rect x="3" y="3" width="18" height="18" rx="5" />
    <path d="M8 16v-4" />
    <path d="M12 16V8" />
    <path d="M16 16v-6" />
  </Icone>
)

export const IconeTelephone = (p: P) => (
  <Icone {...p}>
    <path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z" />
  </Icone>
)

export const IconeCadenas = (p: P) => (
  <Icone {...p}>
    <rect x="5" y="11" width="14" height="9" rx="2" />
    <path d="M8 11V8a4 4 0 018 0v3" />
  </Icone>
)
