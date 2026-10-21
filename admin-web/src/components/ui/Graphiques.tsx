import { useId, useState } from 'react'

/** Dimensions du dessin : la variante compacte (tableau de bord) est étroite, sans axe vertical chiffré. */
function dimensions(compact: boolean) {
  return compact
    ? { L: 360, H: 190, marge: { haut: 36, droite: 10, bas: 28, gauche: 10 } }
    : { L: 640, H: 280, marge: { haut: 40, droite: 16, bas: 36, gauche: 56 } }
}

export interface SerieCourbe {
  nom: string
  couleur: string
  valeurs: number[]
}

interface PropsCourbe {
  description: string
  libelles: string[]
  series: SerieCourbe[]
  formatValeur?: (v: number) => string
  compact?: boolean
}

/** Arrondit le maximum à une valeur « ronde » pour graduer l'axe vertical. */
function maximumRond(max: number): number {
  if (max <= 0) return 1
  const puissance = 10 ** Math.floor(Math.log10(max))
  const n = max / puissance
  const pas = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10
  return pas * puissance
}

function graduations(max: number): number[] {
  return [0, 0.25, 0.5, 0.75, 1].map((f) => max * f)
}

/** Libellé d'axe raccourci pour les grandes valeurs (1 500 000 → « 1,5 M »). */
function abrege(v: number): string {
  if (v >= 1_000_000) return `${(v / 1_000_000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} M`
  if (v >= 1_000) return `${(v / 1_000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} k`
  return v.toLocaleString('fr-FR')
}

/** Trace lissé (spline de Catmull-Rom convertie en courbes de Bézier), sans dépassement au-dessus ou au-dessous des points voisins. */
function traceLisse(points: [number, number][]): string {
  if (points.length === 0) return ''
  if (points.length === 1) return `M${points[0][0]},${points[0][1]}`
  let d = `M${points[0][0]},${points[0][1]}`
  for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[i - 1] ?? points[i]
    const p1 = points[i]
    const p2 = points[i + 1]
    const p3 = points[i + 2] ?? p2
    const haut = Math.min(p1[1], p2[1])
    const bas = Math.max(p1[1], p2[1])
    const limite = (y: number) => Math.max(haut, Math.min(bas, y))
    const c1: [number, number] = [p1[0] + (p2[0] - p0[0]) / 6, limite(p1[1] + (p2[1] - p0[1]) / 6)]
    const c2: [number, number] = [p2[0] - (p3[0] - p1[0]) / 6, limite(p2[1] - (p3[1] - p1[1]) / 6)]
    d += ` C${c1[0]},${c1[1]} ${c2[0]},${c2[1]} ${p2[0]},${p2[1]}`
  }
  return d
}

/** Pastille sombre d'infobulle (texte blanc) centrée sur x, posée au-dessus de y. */
function Pastille({ x, y, texte, vue }: { x: number; y: number; texte: string; vue: number }) {
  const largeur = Math.max(44, texte.length * 6.4 + 18)
  const gauche = Math.max(2, Math.min(vue - largeur - 2, x - largeur / 2))
  return (
    <g className="animate-infobulle" style={{ pointerEvents: 'none' }}>
      <rect x={gauche} y={y - 30} width={largeur} height={22} rx={11} fill="var(--color-primaire-900)" />
      <text x={gauche + largeur / 2} y={y - 15} textAnchor="middle" fontSize="11" fontWeight="600" fill="#fff">
        {texte}
      </text>
    </g>
  )
}

/**
 * Courbe(s) lissée(s) en SVG, sans dépendance : tracé animé à l'apparition, dégradé sous la première série,
 * point et pastille d'infobulle qui suivent le survol (par défaut sur la valeur la plus haute).
 */
export function GraphiqueCourbe({ description, libelles, series, formatValeur = (v) => String(v), compact = false }: PropsCourbe) {
  const { L: L_VUE, H: H_VUE, marge: MARGE } = dimensions(compact)
  const id = useId().replace(/:/g, '')
  const n = libelles.length
  const max = maximumRond(Math.max(0, ...series.flatMap((s) => s.valeurs)))
  const largeur = L_VUE - MARGE.gauche - MARGE.droite
  const hauteur = H_VUE - MARGE.haut - MARGE.bas
  const x = (i: number) => MARGE.gauche + (n <= 1 ? largeur / 2 : (i / (n - 1)) * largeur)
  const y = (v: number) => MARGE.haut + hauteur - (v / max) * hauteur
  const pas = Math.max(1, Math.ceil(n / 7))
  const premiere = series[0]?.valeurs ?? []
  const parDefaut = premiere.length ? premiere.indexOf(Math.max(...premiere)) : 0
  const [survol, setSurvol] = useState<number | null>(null)
  const actif = Math.min(survol ?? parDefaut, Math.max(0, n - 1))
  const colonne = n > 1 ? largeur / (n - 1) : largeur

  return (
    <div>
      <svg viewBox={`0 0 ${L_VUE} ${H_VUE}`} role="img" aria-label={description} className="h-auto w-full">
        <defs>
          {series.map((s, k) => (
            <linearGradient key={s.nom} id={`${id}-d${k}`} x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor={s.couleur} stopOpacity="0.28" />
              <stop offset="100%" stopColor={s.couleur} stopOpacity="0" />
            </linearGradient>
          ))}
        </defs>
        {graduations(max).map((g) => (
          <g key={g}>
            <line x1={MARGE.gauche} x2={L_VUE - MARGE.droite} y1={y(g)} y2={y(g)} stroke="var(--color-bordure)" strokeDasharray="2 6" />
            {!compact && (
              <text x={MARGE.gauche - 10} y={y(g) + 4} textAnchor="end" fontSize="10.5" fill="var(--color-texte-discret)">
                {abrege(g)}
              </text>
            )}
          </g>
        ))}
        {libelles.map((l, i) =>
          i % pas === 0 ? (
            <text key={`${l}-${i}`} x={x(i)} y={H_VUE - 12} textAnchor="middle" fontSize="10.5" fill="var(--color-texte-discret)">
              {l}
            </text>
          ) : null,
        )}
        {n > 0 && (
          <line x1={x(actif)} x2={x(actif)} y1={MARGE.haut} y2={y(0)} stroke="var(--color-primaire-200)" strokeDasharray="3 4" />
        )}
        {series.map((s, k) => {
          const points = s.valeurs.map((v, i) => [x(i), y(v)] as [number, number])
          const ligne = traceLisse(points)
          const zone = `${ligne} L${x(n - 1)},${y(0)} L${x(0)},${y(0)} Z`
          return (
            <g key={s.nom}>
              {k === 0 && n > 1 && <path d={zone} fill={`url(#${id}-d${k})`} className="animate-apparition" />}
              {n > 1 && (
                <path
                  d={ligne}
                  fill="none"
                  stroke={s.couleur}
                  strokeWidth="2.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  pathLength={1}
                  strokeDasharray={1}
                  className="animate-trace"
                  style={{ ['--longueur' as string]: 1 }}
                />
              )}
              {n > 0 && (
                <circle cx={x(actif)} cy={y(s.valeurs[actif] ?? 0)} r="5" fill="var(--color-surface)" stroke={s.couleur} strokeWidth="2.5" />
              )}
            </g>
          )
        })}
        {n > 0 && series[0] && (
          <Pastille
            vue={L_VUE}
            key={actif}
            x={x(actif)}
            y={y(series[0].valeurs[actif] ?? 0) - 4}
            texte={`${libelles[actif]} · ${formatValeur(series[0].valeurs[actif] ?? 0)}`}
          />
        )}
        {libelles.map((l, i) => (
          <rect
            key={`h-${l}-${i}`}
            x={x(i) - colonne / 2}
            y={MARGE.haut - 10}
            width={colonne}
            height={hauteur + 10}
            fill="transparent"
            onMouseEnter={() => setSurvol(i)}
            onMouseLeave={() => setSurvol(null)}
          />
        ))}
      </svg>
      <ul className="mt-2 flex flex-wrap gap-4 text-xs text-texte-doux">
        {series.map((s) => (
          <li key={s.nom} className="flex items-center gap-1.5">
            <span aria-hidden="true" className="size-2.5 rounded-full" style={{ background: s.couleur }} />
            {s.nom}
          </li>
        ))}
      </ul>
    </div>
  )
}

interface PropsBarres {
  description: string
  donnees: { libelle: string; valeur: number }[]
  couleur?: string
  formatValeur?: (v: number) => string
  compact?: boolean
}

/**
 * Barres verticales en gélule : barres inactives hachurées et claires, barre active pleine et foncée
 * (la plus haute par défaut, puis celle qui est survolée ou a le focus) surmontée d'une pastille de valeur.
 */
export function GraphiqueBarres({
  description,
  donnees,
  couleur = 'var(--color-primaire-500)',
  formatValeur = (v) => String(v),
  compact = false,
}: PropsBarres) {
  const { L: L_VUE, H: H_VUE, marge: MARGE } = dimensions(compact)
  const id = useId().replace(/:/g, '')
  const n = donnees.length
  const max = maximumRond(Math.max(0, ...donnees.map((d) => d.valeur)))
  const largeur = L_VUE - MARGE.gauche - MARGE.droite
  const hauteur = H_VUE - MARGE.haut - MARGE.bas
  const pasX = largeur / Math.max(1, n)
  const l = Math.min(compact ? 26 : 44, pasX * (compact ? 0.5 : 0.55))
  const parDefaut = n ? donnees.findIndex((d) => d.valeur === Math.max(...donnees.map((e) => e.valeur))) : 0
  const [choix, setChoix] = useState<number | null>(null)
  const actif = choix ?? parDefaut
  const resume = donnees.map((d) => `${d.libelle} ${formatValeur(d.valeur)}`).join(', ')

  return (
    <svg viewBox={`0 0 ${L_VUE} ${H_VUE}`} role="img" aria-label={`${description} : ${resume}`} className="h-auto w-full">
      <defs>
        <pattern id={`${id}-h`} width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
          <rect width="7" height="7" fill={couleur} fillOpacity="0.22" />
          <rect width="3" height="7" fill={couleur} fillOpacity="0.55" />
        </pattern>
        <radialGradient id={`${id}-halo`} cx="50%" cy="60%" r="55%">
          <stop offset="0%" stopColor={couleur} stopOpacity="0.22" />
          <stop offset="100%" stopColor={couleur} stopOpacity="0" />
        </radialGradient>
      </defs>
      {compact && <ellipse cx={L_VUE / 2} cy={MARGE.haut + hauteur * 0.6} rx={L_VUE * 0.48} ry={hauteur * 0.62} fill={`url(#${id}-halo)`} className="animate-apparition" />}
      {graduations(max).map((g) => {
        const yy = MARGE.haut + hauteur - (g / max) * hauteur
        return (
          <g key={g}>
            <line x1={MARGE.gauche} x2={L_VUE - MARGE.droite} y1={yy} y2={yy} stroke="var(--color-bordure)" strokeDasharray="2 6" />
            {!compact && (
              <text x={MARGE.gauche - 10} y={yy + 4} textAnchor="end" fontSize="10.5" fill="var(--color-texte-discret)">
                {abrege(g)}
              </text>
            )}
          </g>
        )
      })}
      {donnees.map((d, i) => {
        const h = Math.max((d.valeur / max) * hauteur, d.valeur > 0 ? l : 0)
        const cx = MARGE.gauche + pasX * i + pasX / 2
        const yy = MARGE.haut + hauteur - h
        const estActif = i === actif
        return (
          <g
            key={d.libelle}
            onMouseEnter={() => setChoix(i)}
            onMouseLeave={() => setChoix(null)}
            onFocus={() => setChoix(i)}
            onBlur={() => setChoix(null)}
            tabIndex={0}
            style={{ outline: 'none' }}
          >
            <rect x={cx - pasX / 2} y={MARGE.haut - 10} width={pasX} height={hauteur + 10} fill="transparent" />
            <rect
              x={cx - l / 2}
              y={yy}
              width={l}
              height={h}
              rx={l / 2}
              fill={estActif ? (compact ? couleur : 'var(--color-primaire-800)') : `url(#${id}-h)`}
              style={{
                transformBox: 'fill-box',
                transformOrigin: 'bottom',
                transition: 'fill var(--duree-normale) var(--ease-standard), filter var(--duree-normale) var(--ease-standard)',
                filter: estActif && compact ? `drop-shadow(0 6px 10px color-mix(in srgb, ${couleur} 45%, transparent))` : undefined,
                animation: `croissance var(--duree-lente) var(--ease-sortie) ${i * 80}ms both`,
              }}
            />
            <text x={cx} y={H_VUE - 12} textAnchor="middle" fontSize="10.5" fill="var(--color-texte-discret)">
              {d.libelle}
            </text>
            {estActif && <Pastille vue={L_VUE} key={`${i}-${choix}`} x={cx} y={yy - 2} texte={formatValeur(d.valeur)} />}
          </g>
        )
      })}
    </svg>
  )
}
