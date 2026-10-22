import { useLayoutEffect, useRef, useState, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { IChevronBas, IFlecheHautDroite, IPlus, IRecherche, IRetour } from './icones'

/* Composants du Guide (admin-web/maquettes/html/Guide.html) et des écrans dessinés. Valeurs exactes des maquettes. */

/** Titre de page : « Litiges <à trancher> », sous-titre, actions à droite (rangée de titre des maquettes). */
export function TitrePage({ titre, suite, sousTitre, children, compact = false }: { titre: string; suite?: string; sousTitre?: string; children?: ReactNode; compact?: boolean }) {
  return (
    <div className={`flex flex-wrap items-center gap-4 px-2 ${compact ? '' : 'pt-2'}`}>
      <div className="min-w-0 flex-[1_1_360px]">
        <h1 className={`m-0 font-semibold text-[#12172b] ${compact ? 'text-[28px] leading-9 tracking-[-0.8px]' : 'text-[42px] leading-[50px] tracking-[-1.2px]'}`}>
          {titre}
          {suite && <> <span className="font-normal text-[#9aa0b2]">{suite}</span></>}
        </h1>
        {sousTitre && <p className="mt-1.5 mb-0 text-sm leading-[normal] text-[#8a90a2]">{sousTitre}</p>}
      </div>
      {children}
    </div>
  )
}

/** Titre d'une page de détail : retour rond, titre 36/44, pastille + ligne d'infos, boutons. */
export function TitreDetail({
  retour,
  libelleRetour,
  titre,
  suite,
  badge,
  infos,
  children,
}: {
  retour: string
  libelleRetour: string
  titre: string
  suite?: string
  badge?: ReactNode
  infos?: ReactNode
  children?: ReactNode
}) {
  return (
    <div className="flex flex-wrap items-center gap-4 px-2 pt-2">
      <Link
        to={retour}
        aria-label={libelleRetour}
        className="mq-btn-blanc grid size-12 shrink-0 place-items-center rounded-3xl text-[#12172b]"
      >
        <IRetour />
      </Link>
      <div className="min-w-0 flex-[1_1_320px]">
        <h1 className="m-0 text-4xl leading-[44px] font-semibold tracking-[-1px] text-[#12172b]">
          {titre}
          {suite && <> <span className="font-normal text-[#9aa0b2]">{suite}</span></>}
        </h1>
        <div className="mt-1.5 flex flex-wrap items-center gap-2.5 text-[13px] text-[#8a90a2]">
          {badge}
          {infos}
        </div>
      </div>
      {children}
    </div>
  )
}

/** Carte blanche : rayon 32, padding 18. */
export function Carte({ children, className = '', ...p }: { children: ReactNode; className?: string } & React.HTMLAttributes<HTMLElement>) {
  return (
    <section className={`box-border rounded-[32px] bg-white p-[18px] ${className}`} {...p}>
      {children}
    </section>
  )
}

/** En-tête de carte avec rond d'icône 44 px, titre 15/700 et sous-titre 12. */
export function EnteteCarte({ icone, titre, sousTitre, droite }: { icone: ReactNode; titre: string; sousTitre?: string; droite?: ReactNode }) {
  return (
    <div className="flex items-center gap-3.5">
      <span className="grid size-11 shrink-0 place-items-center rounded-[22px] bg-[#f3f4f7] text-[#12172b]">{icone}</span>
      <span className="min-w-0 flex-1">
        <span className="block text-[15px] font-bold">{titre}</span>
        {sousTitre && <span className="mt-0.5 block text-xs text-[#8a90a2]">{sousTitre}</span>}
      </span>
      {droite}
    </div>
  )
}

/** Titre simple de carte (15/700) avec sous-titre facultatif. */
export function TitreCarte({ titre, sousTitre }: { titre: string; sousTitre?: string }) {
  return (
    <>
      <div className="text-[15px] font-bold">{titre}</div>
      {sousTitre && <div className="mt-0.5 text-xs text-[#8a90a2]">{sousTitre}</div>}
    </>
  )
}

/** Fond plat de la couleur de marque (cartes « hero »). */
export const FOND_MARQUE = 'var(--color-marque)'

export type TonBadge = 'attention' | 'danger' | 'succes' | 'neutre' | 'info'
const TONS: Record<TonBadge, string> = {
  attention: 'bg-[#fff0d4] text-[#983705]',
  danger: 'bg-[#fde9e9] text-[#b0161b]',
  succes: 'bg-[#d7f7e4] text-[#0e6245]',
  neutre: 'bg-[#eef1f6] text-[#4f566b]',
  info: 'bg-neutre-claire text-neutre',
}

/** Pastille de statut : padding 5px 12px, pilule, 12 px (600 dans les tableaux, 700 ailleurs). */
export function Badge({ ton, children, gras = false }: { ton: TonBadge; children: ReactNode; gras?: boolean }) {
  return (
    <span className={`inline-block rounded-full px-3 py-[5px] text-xs transition-colors duration-200 ${gras ? 'font-bold' : 'font-semibold'} ${TONS[ton]}`}>{children}</span>
  )
}

const PALETTE = [
  'bg-marque-claire text-marque',
  'bg-[#fde7f3] text-[#a3206b]',
  'bg-[#d7f7e4] text-[#0e6245]',
  'bg-[#fff0d4] text-[#983705]',
  'bg-[#ede7ff] text-[#5b3fd0]',
  'bg-neutre-claire text-neutre',
]

/** Avatar d'initiales : paires pastel des maquettes, choisies de façon stable d'après le nom. */
export function Avatar({ nom, taille = 40 }: { nom: string; taille?: 30 | 36 | 40 | 44 }) {
  let h = 0
  for (const c of nom) h = (h * 31 + c.charCodeAt(0)) >>> 0
  const mots = nom.trim().split(/\s+/).filter(Boolean)
  const initiales = ((mots[0]?.[0] ?? '') + (mots.length > 1 ? (mots[mots.length - 1][0] ?? '') : (mots[0]?.[1] ?? ''))).toUpperCase()
  const dim = taille === 30 ? 'size-[30px] text-[11px]' : taille === 36 ? 'size-9 text-xs' : taille === 44 ? 'size-11 text-[13px]' : 'size-10 text-[13px]'
  return (
    <span aria-hidden="true" className={`grid shrink-0 place-items-center rounded-full font-bold ${dim} ${PALETTE[h % PALETTE.length]}`}>
      {initiales}
    </span>
  )
}

export interface OngletDef {
  cle: string
  libelle: string
  /** Compteur affiché dans l'onglet ; « — » quand la donnée n'existe pas. */
  compteur?: ReactNode
  actif: boolean
  onSelect?: () => void
}

/** Onglets : conteneur #f3f4f7 (padding 4), item de 36 px, actif bleu avec compteur en pastille blanche translucide. */
export function Onglets({ onglets, libelle, pastille = false }: { onglets: OngletDef[]; libelle: string; pastille?: boolean }) {
  const racine = useRef<HTMLDivElement>(null)
  const [cible, setCible] = useState<{ x: number; l: number } | null>(null)
  const [glisse, setGlisse] = useState(false)
  const indexActif = onglets.findIndex((o) => o.actif)
  const signature = onglets.map((o) => `${o.cle}:${o.compteur ?? ''}`).join('|')

  // La pastille se place sur l'onglet actif ; le premier placement est immédiat, les suivants glissent.
  useLayoutEffect(() => {
    const actif = racine.current?.querySelectorAll<HTMLElement>('[role="tab"]')[indexActif]
    setCible(actif ? { x: actif.offsetLeft, l: actif.offsetWidth } : null)
  }, [indexActif, signature])
  useLayoutEffect(() => {
    if (cible && !glisse) {
      const image = requestAnimationFrame(() => setGlisse(true))
      return () => cancelAnimationFrame(image)
    }
  }, [cible, glisse])

  return (
    <div ref={racine} role="tablist" aria-label={libelle} className={`relative flex max-w-full overflow-x-auto rounded-full bg-[#f3f4f7] p-1 ${pastille ? 'gap-1' : ''}`}>
      {cible && (
        <span
          aria-hidden="true"
          data-testid="pastille-onglet"
          data-glisse={glisse || undefined}
          className={`mq-onglet-pastille pointer-events-none absolute top-1 left-0 h-9 rounded-full ${pastille ? 'bg-white shadow-[0_2px_8px_rgba(18,23,43,0.12)]' : 'bg-marque'}`}
          style={{ width: cible.l, transform: `translateX(${cible.x}px)` }}
        />
      )}
      {onglets.map((o) => (
        <button
          key={o.cle}
          type="button"
          role="tab"
          aria-selected={o.actif}
          onClick={o.onSelect}
          className={`relative z-[1] flex h-9 cursor-pointer items-center gap-2 rounded-full border-none bg-transparent px-4 text-[13px] font-semibold whitespace-nowrap transition-colors duration-200 ${
            o.actif ? (pastille ? 'text-[#12172b]' : 'text-white') : 'text-[#6b7287]'
          }`}
        >
          {o.libelle}
          {o.compteur !== undefined &&
            (pastille ? (
              <span className={`grid h-[22px] min-w-[22px] place-items-center rounded-full px-1.5 text-[11px] font-bold ${o.actif ? 'bg-marque text-white' : 'bg-[#e6e8ee] text-[#6b7287]'}`}>{o.compteur}</span>
            ) : o.actif ? (
              <span className="rounded-[9px] bg-white/[0.22] px-[7px] text-[11px] leading-[18px]">{o.compteur}</span>
            ) : (
              <span className="text-[11px] text-[#9aa0b2]">{o.compteur}</span>
            ))}
        </button>
      ))}
    </div>
  )
}

/** Champ de recherche de 42 px avec loupe (largeur 280 px au plus). */
export function Recherche({
  valeur,
  onChange,
  placeholder,
  libelle,
}: {
  valeur: string
  onChange: (v: string) => void
  placeholder: string
  libelle: string
}) {
  return (
    <label className="relative block flex-[0_1_280px]">
      <span className="absolute top-[13px] left-4 flex text-[#9aa0b2]">
        <IRecherche taille={16} trait={2} />
      </span>
      <input
        type="search"
        value={valeur}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        aria-label={libelle}
        className="mq-champ box-border h-[42px] w-full rounded-[21px] border-none bg-[#f3f4f7] pr-4 pl-10 text-[13px] text-[#12172b] placeholder:text-[#9aa0b2]"
      />
    </label>
  )
}

/** Bouton de filtre de 42 px avec chevron (« Métier », « Statut »). */
export function BoutonFiltre({ children, ...p }: { children: ReactNode } & ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      type="button"
      className="mq-btn-gris flex h-[42px] cursor-pointer items-center gap-2 rounded-[21px] border-none px-4 text-[13px] font-semibold"
      {...p}
    >
      {children}
      <span className="text-[#8a90a2]">
        <IChevronBas />
      </span>
    </button>
  )
}

/** Bouton blanc « + Exporter » de la rangée de titre. */
export function BoutonExporter({ onClick }: { onClick?: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="mq-btn-blanc flex h-12 cursor-pointer items-center gap-2 rounded-3xl border-none pr-5 pl-4 text-[13px] font-semibold text-[#12172b]"
    >
      <IPlus />
      Exporter
    </button>
  )
}

/** Bouton principal de la rangée de titre (« + Nouvelle sanction »). */
export function BoutonPlus({ children, onClick }: { children: ReactNode; onClick?: () => void }) {
  return (
    <button type="button" onClick={onClick} className="mq-btn-bleu flex h-12 cursor-pointer items-center gap-2 rounded-3xl border-none pr-6 pl-[18px] text-[13px] font-bold">
      <IPlus />
      {children}
    </button>
  )
}

/** Pied de liste : « 1 à 6 sur 12 résultats » et boutons Précédent / Suivant (38 px). */
/** Une fenêtre de 5 numéros de page au plus, centrée sur la page courante. */
function numerosDePage(page: number, total: number): number[] {
  const debut = Math.max(1, Math.min(page - 2, total - 4))
  const fin = Math.min(total, debut + 4)
  return Array.from({ length: fin - debut + 1 }, (_, i) => debut + i)
}

/** Pied de liste à numéros de page ronds (précédent, 1 2 3…, suivant) avec le résumé « Affichage de … » à gauche. */
export function PaginationRonde({ resume, page, totalPages, onPage }: { resume: ReactNode; page: number; totalPages: number; onPage: (page: number) => void }) {
  const rond = 'grid size-8 place-items-center rounded-full border text-[13px] font-semibold transition-colors'
  const fleche = (d: string) => (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={d} />
    </svg>
  )
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#eceef2] pt-2.5 text-[13px] text-[#8a90a2]">
      <span>{resume}</span>
      <nav aria-label="Pagination" className="flex items-center gap-2">
        <button type="button" aria-label="Précédent" disabled={page <= 1} onClick={() => onPage(page - 1)} className={`${rond} border-[#e6e8ee] bg-white ${page > 1 ? 'mq-rond cursor-pointer text-[#12172b]' : 'cursor-default text-[#c3c7d3]'}`}>
          {fleche('M15 6l-6 6 6 6')}
        </button>
        {numerosDePage(page, totalPages).map((n) => (
          <button
            key={n}
            type="button"
            aria-label={`Page ${n}`}
            aria-current={n === page ? 'page' : undefined}
            onClick={() => onPage(n)}
            className={`${rond} cursor-pointer ${n === page ? 'border-marque bg-marque text-white' : 'mq-rond border-[#e6e8ee] bg-white text-[#12172b]'}`}
          >
            {n}
          </button>
        ))}
        <button type="button" aria-label="Suivant" disabled={page >= totalPages} onClick={() => onPage(page + 1)} className={`${rond} border-[#e6e8ee] bg-white ${page < totalPages ? 'mq-rond cursor-pointer text-[#12172b]' : 'cursor-default text-[#c3c7d3]'}`}>
          {fleche('M9 6l6 6-6 6')}
        </button>
      </nav>
    </div>
  )
}

export function PiedListe({
  resume,
  precedent,
  suivant,
  onPrecedent,
  onSuivant,
}: {
  resume: ReactNode
  precedent: boolean
  suivant: boolean
  onPrecedent: () => void
  onSuivant: () => void
}) {
  const base = 'h-[38px] rounded-[19px] border-none bg-[#f3f4f7] px-[18px] text-xs font-semibold'
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 text-[13px] text-[#8a90a2]">
      <span>{resume}</span>
      <span className="flex gap-2">
        <button type="button" disabled={!precedent} onClick={onPrecedent} className={`${base} ${precedent ? 'mq-rond cursor-pointer text-[#12172b]' : 'cursor-default text-[#b4b9c7]'}`}>
          Précédent
        </button>
        <button type="button" disabled={!suivant} onClick={onSuivant} className={`${base} ${suivant ? 'mq-rond cursor-pointer text-[#12172b]' : 'cursor-default text-[#b4b9c7]'}`}>
          Suivant
        </button>
      </span>
    </div>
  )
}

/** Lien rond ↗ de 36 px (colonne d'action des tableaux et listes de personnes). */
export function LienRond({ vers, libelle, blanc = false }: { vers: string; libelle: string; blanc?: boolean }) {
  return (
    <Link
      to={vers}
      aria-label={libelle}
      className={`mq-rond mq-lr inline-flex size-9 items-center justify-center rounded-full text-[#12172b] ${blanc ? 'bg-white' : 'bg-[#f3f4f7]'}`}
    >
      <IFlecheHautDroite />
    </Link>
  )
}

/**
 * Ligne de tableau dont toute la surface mène à la fiche : le lien rond ↗ étire une zone invisible sur la ligne
 * (clic, clic molette, Ctrl/Cmd+clic et clavier sont ceux d'un lien natif). Les autres liens et boutons de la ligne restent au-dessus.
 */
export function LigneLien({ vers, libelle, texte, discret = false, children }: { vers: string; libelle: string; texte?: string; discret?: boolean; children: ReactNode }) {
  const { search } = useLocation()
  return (
    <tr className="relative cursor-pointer focus-within:bg-[#f6f7fa] [&_a:not([data-voir])]:relative [&_a:not([data-voir])]:z-10 [&_button]:relative [&_button]:z-10">
      {children}
      <td style={texte ? { textAlign: 'right' } : undefined}>
        <Link
          to={vers}
          data-voir
          state={{ recherche: search }}
          aria-label={libelle}
          className={`mq-lr inline-flex items-center justify-center whitespace-nowrap ${discret ? 'gap-1 bg-marque-claire text-marque transition-colors duration-150 hover:bg-[#d5ecdf]' : 'mq-rond bg-[#f3f4f7] text-[#12172b]'} after:absolute after:inset-0 after:z-0 after:content-[''] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marque ${
            discret ? 'h-8 rounded-full pr-2.5 pl-3.5 text-xs font-bold' : texte ? 'h-[34px] rounded-[17px] px-3.5 text-xs font-bold' : 'size-9 rounded-full'
          }`}
        >
          {texte ?? <IFlecheHautDroite />}
          {discret && (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M9 6l6 6-6 6" />
            </svg>
          )}
        </Link>
      </td>
    </tr>
  )
}

/** Étape de chronologie : point 12 ou 14 px, trait vertical #e6e8ee, titre et date. */
export function EtapeChrono({
  couleur,
  titre,
  detail,
  derniere = false,
  grand = false,
}: {
  couleur: string
  titre: string
  detail: string
  derniere?: boolean
  grand?: boolean
}) {
  return (
    <div className={`flex ${grand ? 'gap-4' : 'gap-3.5'}`}>
      <div className="flex flex-col items-center">
        <span className={`shrink-0 rounded-full ${grand ? 'size-3.5' : 'size-3'}`} style={{ background: couleur }} />
        {!derniere && <span className="my-1 w-0.5 flex-1 bg-[#e6e8ee]" />}
      </div>
      <div className={derniere ? '' : grand ? 'pb-5' : 'pb-4'}>
        <div className={`${grand ? 'text-sm' : 'text-[13px]'} font-semibold`}>{titre}</div>
        <div className="mt-0.5 text-xs text-[#8a90a2]">{detail}</div>
      </div>
    </div>
  )
}

/** Tuile d'information : fond #f3f4f7, rayon 20, libellé 12 et valeur 14/600. */
export function Tuile({ libelle, children, className = '' }: { libelle: string; children: ReactNode; className?: string }) {
  return (
    <div className={`rounded-[20px] bg-[#f3f4f7] px-[18px] py-3.5 ${className}`}>
      <div className="text-xs leading-[normal] text-[#8a90a2]">{libelle}</div>
      <div className="mt-[3px] text-sm leading-[normal] font-semibold">{children}</div>
    </div>
  )
}

/** Statut en texte précédé d'un point de 7 px (colonne « Statut » des réservations). */
export function PointStatut({ couleur, children }: { couleur: string; children: ReactNode }) {
  return (
    <span className="inline-flex items-center gap-2 font-medium">
      <span aria-hidden="true" className="size-[7px] rounded-full" style={{ background: couleur }} />
      {children}
    </span>
  )
}

/** Grille de tuiles d'information (libellé + valeur) ; une valeur absente de l'API s'affiche « — ». */
export function GrilleTuiles({ elements, className = '' }: { elements: { libelle: string; valeur?: ReactNode; large?: boolean }[]; className?: string }) {
  return (
    <div className={`mt-3.5 grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-2.5 ${className}`}>
      {elements.map((e) => (
        <Tuile key={e.libelle} libelle={e.libelle} className={e.large ? 'col-span-full' : ''}>
          {e.valeur === undefined || e.valeur === null || e.valeur === '' ? <span className="text-[#9aa0b2]">—</span> : e.valeur}
        </Tuile>
      ))}
    </div>
  )
}

/** Lien dans le texte d'une tuile ou d'une carte. */
export function LienTexte({ vers, children }: { vers: string; children: ReactNode }) {
  return (
    <Link to={vers} className="font-semibold text-marque hover:underline">
      {children}
    </Link>
  )
}
