import type { ReactNode, SVGProps } from 'react'

/** Icônes exactes des maquettes (admin-web/maquettes) : viewBox 24, trait arrondi. */
function Ico({ taille = 20, trait = 1.8, children, ...p }: { taille?: number; trait?: number; children: ReactNode } & Omit<SVGProps<SVGSVGElement>, 'children'>) {
  return (
    <svg
      width={taille}
      height={taille}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={trait}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      {...p}
    >
      {children}
    </svg>
  )
}

type P = { taille?: number; trait?: number } & Omit<SVGProps<SVGSVGElement>, 'children'>

export const ITableau = (p: P) => (
  <Ico {...p}>
    <rect x="3" y="3" width="7" height="7" rx="2" />
    <rect x="14" y="3" width="7" height="7" rx="2" />
    <rect x="3" y="14" width="7" height="7" rx="2" />
    <rect x="14" y="14" width="7" height="7" rx="2" />
  </Ico>
)
export const IDossiers = (p: P) => (
  <Ico {...p}>
    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
    <path d="M14 3v5h5" />
    <path d="m9 15 2 2 4-4" />
  </Ico>
)
export const ILitiges = (p: P) => (
  <Ico trait={1.9} {...p}>
    <path d="M12 3 2 20h20z" />
    <path d="M12 10v4M12 17h.01" />
  </Ico>
)
export const IAvis = (p: P) => (
  <Ico {...p}>
    <path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z" />
  </Ico>
)
export const ISanctions = (p: P) => (
  <Ico {...p}>
    <path d="M12 3 4 6v6c0 5 3.4 8 8 9 4.6-1 8-4 8-9V6z" />
  </Ico>
)
export const IUtilisateurs = (p: P) => (
  <Ico {...p}>
    <circle cx="9" cy="8" r="3.5" />
    <path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6" />
    <path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.6 3.5 2.6 3.5 5" />
  </Ico>
)
export const IMetiers = (p: P) => (
  <Ico {...p}>
    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
  </Ico>
)
export const IActualiser = (p: P) => (
  <Ico {...p}>
    <path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5" />
  </Ico>
)
export const IDeconnexion = (p: P) => (
  <Ico {...p}>
    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
  </Ico>
)
export const IRecherche = (p: P) => (
  <Ico taille={18} trait={1.9} {...p}>
    <circle cx="11" cy="11" r="7" />
    <path d="m20 20-3.5-3.5" />
  </Ico>
)
export const ICloche = (p: P) => (
  <Ico taille={18} trait={1.9} {...p}>
    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8" />
    <path d="M10.3 20a2 2 0 0 0 3.4 0" />
  </Ico>
)
export const IPlus = (p: P) => (
  <Ico taille={16} trait={2.2} {...p}>
    <path d="M12 5v14M5 12h14" />
  </Ico>
)
export const IRetour = (p: P) => (
  <Ico taille={18} trait={2} {...p}>
    <path d="M19 12H5M11 6l-6 6 6 6" />
  </Ico>
)
export const IFlecheHautDroite = (p: P) => (
  <Ico taille={14} trait={2.2} {...p}>
    <path d="M7 17 17 7M8 7h9v9" />
  </Ico>
)
export const IChevronBas = (p: P) => (
  <Ico taille={12} trait={2.4} {...p}>
    <path d="m6 9 6 6 6-6" />
  </Ico>
)
export const IUtilisateur = (p: P) => (
  <Ico taille={18} trait={1.9} {...p}>
    <circle cx="12" cy="8" r="4" />
    <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" />
  </Ico>
)
export const IDocument = (p: P) => (
  <Ico taille={18} trait={1.9} {...p}>
    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
    <path d="M14 3v5h5" />
  </Ico>
)
export const IImage = (p: P) => (
  <Ico taille={26} trait={1.7} {...p}>
    <rect x="3" y="4" width="18" height="16" rx="3" />
    <circle cx="9" cy="10" r="2" />
    <path d="m21 16-5-5-9 9" />
  </Ico>
)
export const IMessage = (p: P) => (
  <Ico taille={18} trait={1.9} {...p}>
    <path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
  </Ico>
)
export const IHorloge = (p: P) => (
  <Ico taille={18} trait={1.9} {...p}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7v5l3 2" />
  </Ico>
)
export const ICiseaux = (p: P) => (
  <Ico taille={24} {...p}>
    <circle cx="6" cy="6" r="3" />
    <circle cx="6" cy="18" r="3" />
    <path d="M20 4 8.1 15.9M14.5 14.5 20 20M8.1 8.1 12 12" />
  </Ico>
)
export const IChemise = (p: P) => (
  <Ico taille={24} {...p}>
    <path d="M8 3 3 6l2 4 2-1v12h10V9l2 1 2-4-5-3a4 4 0 0 1-8 0z" />
  </Ico>
)

/** Étoile pleine des notes (viewBox 16). */
export function EtoilePleine({ taille = 14, couleur = '#f59e0b' }: { taille?: number; couleur?: string }) {
  return (
    <svg width={taille} height={taille} viewBox="0 0 16 16" aria-hidden="true" focusable="false">
      <path d="M8 1l2 4.3 4.7.6-3.4 3.2.9 4.6L8 11.4 3.8 13.7l.9-4.6L1.3 5.9 6 5.3z" fill={couleur} />
    </svg>
  )
}

/** Cinq étoiles (maquette Avis) : `note` étoiles orange, le reste gris. */
export function Etoiles({ note }: { note: number }) {
  const d = 'M8 1l2 4.3 4.7.6-3.4 3.2.9 4.6L8 11.4 3.8 13.7l.9-4.6L1.3 5.9 6 5.3z'
  return (
    <svg width="90" height="16" viewBox="0 0 90 16" role="img" aria-label={`Note : ${note} sur 5`}>
      {[0, 1, 2, 3, 4].map((i) => (
        <path key={i} transform={`translate(${i * 18} 0)`} d={d} fill={i < note ? '#f59e0b' : '#d9dce5'} />
      ))}
    </svg>
  )
}
export const ICalendrier = (p: P) => (
  <Ico taille={16} trait={1.9} {...p}>
    <rect x="3" y="5" width="18" height="16" rx="3" />
    <path d="M3 10h18M8 3v4M16 3v4" />
  </Ico>
)
export const IGraphique = (p: P) => (
  <Ico taille={18} trait={1.9} {...p}>
    <path d="M4 20V10M10 20V4M16 20v-8M22 20H2" />
  </Ico>
)
export const IPaiement = (p: P) => (
  <Ico taille={18} trait={1.8} {...p}>
    <rect x="2" y="5" width="20" height="14" rx="3" />
    <path d="M2 10h20" />
  </Ico>
)
export const IRetrait = (p: P) => (
  <Ico taille={18} trait={1.8} {...p}>
    <path d="M12 3v12M7 10l5 5 5-5M5 21h14" />
  </Ico>
)
