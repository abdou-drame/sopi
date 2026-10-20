import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { IChevronBas, IFlecheHautDroite, IPlus, IRecherche, IRetour } from './icones'

/* Composants du Guide (admin-web/maquettes/html/Guide.html) et des écrans dessinés. Valeurs exactes des maquettes. */

/** Titre de page : « Litiges <à trancher> », sous-titre, actions à droite (rangée de titre des maquettes). */
export function TitrePage({ titre, suite, sousTitre, children }: { titre: string; suite?: string; sousTitre?: string; children?: ReactNode }) {
  return (
    <div className="flex flex-wrap items-center gap-4 px-2 pt-2">
      <div className="min-w-0 flex-[1_1_360px]">
        <h1 className="m-0 text-[42px] leading-[50px] font-semibold tracking-[-1.2px] text-[#12172b]">
          {titre}
          {suite && <> <span className="font-normal text-[#9aa0b2]">{suite}</span></>}
        </h1>
        {sousTitre && <p className="mt-1.5 mb-0 text-sm text-[#8a90a2]">{sousTitre}</p>}
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

/** Bloc dégradé 150° des maquettes (#3563e8 → #1d4ed8 → #17338f). */
export const DEGRADE_BLEU = 'linear-gradient(150deg, #3563e8 0%, #1d4ed8 50%, #17338f 100%)'

export type TonBadge = 'attention' | 'danger' | 'succes' | 'neutre' | 'info'
const TONS: Record<TonBadge, string> = {
  attention: 'bg-[#fff0d4] text-[#983705]',
  danger: 'bg-[#fde9e9] text-[#b0161b]',
  succes: 'bg-[#d7f7e4] text-[#0e6245]',
  neutre: 'bg-[#eef1f6] text-[#4f566b]',
  info: 'bg-[#e7edff] text-[#1d4ed8]',
}

/** Pastille de statut : padding 5px 12px, pilule, 12 px (600 dans les tableaux, 700 ailleurs). */
export function Badge({ ton, children, gras = false }: { ton: TonBadge; children: ReactNode; gras?: boolean }) {
  return (
    <span className={`inline-block rounded-full px-3 py-[5px] text-xs ${gras ? 'font-bold' : 'font-semibold'} ${TONS[ton]}`}>{children}</span>
  )
}

const PALETTE = [
  'bg-[#e7edff] text-[#1d4ed8]',
  'bg-[#fde7f3] text-[#a3206b]',
  'bg-[#d7f7e4] text-[#0e6245]',
  'bg-[#fff0d4] text-[#983705]',
  'bg-[#ede7ff] text-[#5b3fd0]',
  'bg-[#dbeafe] text-[#1d4ed8]',
]

/** Avatar d'initiales : paires pastel des maquettes, choisies de façon stable d'après le nom. */
export function Avatar({ nom, taille = 40 }: { nom: string; taille?: 36 | 40 | 44 }) {
  let h = 0
  for (const c of nom) h = (h * 31 + c.charCodeAt(0)) >>> 0
  const mots = nom.trim().split(/\s+/).filter(Boolean)
  const initiales = ((mots[0]?.[0] ?? '') + (mots.length > 1 ? (mots[mots.length - 1][0] ?? '') : (mots[0]?.[1] ?? ''))).toUpperCase()
  const dim = taille === 36 ? 'size-9 text-xs' : taille === 44 ? 'size-11 text-[13px]' : 'size-10 text-[13px]'
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
export function Onglets({ onglets, libelle }: { onglets: OngletDef[]; libelle: string }) {
  return (
    <div role="tablist" aria-label={libelle} className="flex max-w-full overflow-x-auto rounded-full bg-[#f3f4f7] p-1">
      {onglets.map((o) => (
        <button
          key={o.cle}
          type="button"
          role="tab"
          aria-selected={o.actif}
          onClick={o.onSelect}
          className={`flex h-9 cursor-pointer items-center gap-2 rounded-full border-none px-4 text-[13px] font-semibold whitespace-nowrap ${
            o.actif ? 'bg-[#1d4ed8] text-white' : 'bg-transparent text-[#6b7287]'
          }`}
        >
          {o.libelle}
          {o.compteur !== undefined &&
            (o.actif ? (
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
export function LigneLien({ vers, libelle, children }: { vers: string; libelle: string; children: ReactNode }) {
  const { search } = useLocation()
  return (
    <tr className="relative cursor-pointer focus-within:bg-[#f6f7fa] [&_a:not([data-voir])]:relative [&_a:not([data-voir])]:z-10 [&_button]:relative [&_button]:z-10">
      {children}
      <td>
        <Link
          to={vers}
          data-voir
          state={{ recherche: search }}
          aria-label={libelle}
          className="mq-rond mq-lr inline-flex size-9 items-center justify-center rounded-full bg-[#f3f4f7] text-[#12172b] after:absolute after:inset-0 after:z-0 after:content-[''] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1d4ed8]"
        >
          <IFlecheHautDroite />
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
      <div className="text-xs text-[#8a90a2]">{libelle}</div>
      <div className="mt-[3px] text-sm font-semibold">{children}</div>
    </div>
  )
}
