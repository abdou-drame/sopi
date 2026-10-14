import { useId } from 'react'

const L_VUE = 640
const H_VUE = 280
const MARGE = { haut: 16, droite: 16, bas: 36, gauche: 64 }

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

/** Courbe(s) en SVG, sans dépendance : tracé animé à l'apparition, zone dégradée sous la première série, info-bulle native au survol. */
export function GraphiqueCourbe({ description, libelles, series, formatValeur = (v) => String(v) }: PropsCourbe) {
  const id = useId().replace(/:/g, '')
  const n = libelles.length
  const max = maximumRond(Math.max(0, ...series.flatMap((s) => s.valeurs)))
  const largeur = L_VUE - MARGE.gauche - MARGE.droite
  const hauteur = H_VUE - MARGE.haut - MARGE.bas
  const x = (i: number) => MARGE.gauche + (n <= 1 ? largeur / 2 : (i / (n - 1)) * largeur)
  const y = (v: number) => MARGE.haut + hauteur - (v / max) * hauteur
  const pas = Math.max(1, Math.ceil(n / 7))

  return (
    <div>
      <svg viewBox={`0 0 ${L_VUE} ${H_VUE}`} role="img" aria-label={description} className="h-auto w-full">
        <defs>
          {series.map((s, k) => (
            <linearGradient key={s.nom} id={`${id}-d${k}`} x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor={s.couleur} stopOpacity="0.25" />
              <stop offset="100%" stopColor={s.couleur} stopOpacity="0" />
            </linearGradient>
          ))}
        </defs>
        {graduations(max).map((g) => (
          <g key={g}>
            <line x1={MARGE.gauche} x2={L_VUE - MARGE.droite} y1={y(g)} y2={y(g)} stroke="var(--color-bordure)" strokeDasharray="3 4" />
            <text x={MARGE.gauche - 8} y={y(g) + 4} textAnchor="end" fontSize="11" fill="var(--color-texte-doux)">
              {abrege(g)}
            </text>
          </g>
        ))}
        {libelles.map((l, i) =>
          i % pas === 0 ? (
            <text key={`${l}-${i}`} x={x(i)} y={H_VUE - 12} textAnchor="middle" fontSize="11" fill="var(--color-texte-doux)">
              {l}
            </text>
          ) : null,
        )}
        {series.map((s, k) => {
          const points = s.valeurs.map((v, i) => `${x(i)},${y(v)}`)
          const ligne = `M${points.join(' L')}`
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
              {s.valeurs.map((v, i) => (
                <circle key={i} cx={x(i)} cy={y(v)} r={n > 31 ? 2 : 3.5} fill="var(--color-surface)" stroke={s.couleur} strokeWidth="2">
                  <title>{`${libelles[i]} — ${s.nom} : ${formatValeur(v)}`}</title>
                </circle>
              ))}
            </g>
          )
        })}
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
}

/** Barres verticales en SVG avec croissance animée et valeur affichée au-dessus de chaque barre. */
export function GraphiqueBarres({
  description,
  donnees,
  couleur = 'var(--color-primaire-500)',
  formatValeur = (v) => String(v),
}: PropsBarres) {
  const n = donnees.length
  const max = maximumRond(Math.max(0, ...donnees.map((d) => d.valeur)))
  const largeur = L_VUE - MARGE.gauche - MARGE.droite
  const hauteur = H_VUE - MARGE.haut - MARGE.bas - 14
  const pasX = largeur / Math.max(1, n)
  const l = Math.min(56, pasX * 0.6)

  return (
    <svg viewBox={`0 0 ${L_VUE} ${H_VUE}`} role="img" aria-label={description} className="h-auto w-full">
      {graduations(max).map((g) => {
        const yy = MARGE.haut + 14 + hauteur - (g / max) * hauteur
        return (
          <g key={g}>
            <line x1={MARGE.gauche} x2={L_VUE - MARGE.droite} y1={yy} y2={yy} stroke="var(--color-bordure)" strokeDasharray="3 4" />
            <text x={MARGE.gauche - 8} y={yy + 4} textAnchor="end" fontSize="11" fill="var(--color-texte-doux)">
              {abrege(g)}
            </text>
          </g>
        )
      })}
      {donnees.map((d, i) => {
        const h = (d.valeur / max) * hauteur
        const cx = MARGE.gauche + pasX * i + pasX / 2
        const yy = MARGE.haut + 14 + hauteur - h
        return (
          <g key={d.libelle}>
            <rect
              x={cx - l / 2}
              y={yy}
              width={l}
              height={Math.max(h, 0)}
              rx="6"
              fill={couleur}
              style={{
                transformBox: 'fill-box',
                transformOrigin: 'bottom',
                animation: `croissance var(--duree-lente) var(--ease-sortie) ${i * 70}ms both`,
              }}
            >
              <title>{`${d.libelle} : ${formatValeur(d.valeur)}`}</title>
            </rect>
            <text x={cx} y={yy - 6} textAnchor="middle" fontSize="11" fontWeight="600" fill="var(--color-texte)">
              {formatValeur(d.valeur)}
            </text>
            <text x={cx} y={H_VUE - 12} textAnchor="middle" fontSize="11" fill="var(--color-texte-doux)">
              {d.libelle}
            </text>
          </g>
        )
      })}
    </svg>
  )
}
