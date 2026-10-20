import { useId, useState } from 'react'

const L_VUE = 640
const H_VUE = 280
const MARGE = { haut: 40, droite: 16, bas: 36, gauche: 56 }

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
function Pastille({ x, y, texte }: { x: number; y: number; texte: string }) {
  const largeur = Math.max(44, texte.length * 6.4 + 18)
  const gauche = Math.max(2, Math.min(L_VUE - largeur - 2, x - largeur / 2))
  return (
    <g className="animate-infobulle" style={{ pointerEvents: 'none' }}>
      <rect x={gauche} y={y - 30} width={largeur} height={22} rx={11} fill="#12172b" />
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
export function GraphiqueCourbe({ description, libelles, series, formatValeur = (v) => String(v) }: PropsCourbe) {
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
            <line x1={MARGE.gauche} x2={L_VUE - MARGE.droite} y1={y(g)} y2={y(g)} stroke="#e6e8ee" strokeDasharray="2 6" />
            <text x={MARGE.gauche - 10} y={y(g) + 4} textAnchor="end" fontSize="10.5" fill="#9aa0b2">
              {abrege(g)}
            </text>
          </g>
        ))}
        {libelles.map((l, i) =>
          i % pas === 0 ? (
            <text key={`${l}-${i}`} x={x(i)} y={H_VUE - 12} textAnchor="middle" fontSize="10.5" fill="#9aa0b2">
              {l}
            </text>
          ) : null,
        )}
        {n > 0 && (
          <line x1={x(actif)} x2={x(actif)} y1={MARGE.haut} y2={y(0)} stroke="#b8c8f5" strokeDasharray="3 4" />
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
                <circle cx={x(actif)} cy={y(s.valeurs[actif] ?? 0)} r="5" fill="#ffffff" stroke={s.couleur} strokeWidth="2.5" />
              )}
            </g>
          )
        })}
        {n > 0 && series[0] && (
          <Pastille
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
      <ul className="mt-2 flex flex-wrap gap-4 text-xs text-[#8a90a2]">
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

/**
 * Barres verticales en capsule rayée d'après la maquette : échelle à gauche, grille en pointillés,
 * barre mise en avant (la plus haute, puis celle survolée ou ayant le focus) avec point blanc et bulle de valeur.
 * Hauteur fixe de 230 px, largeur fluide.
 */
export function GraphiqueBarres({ description, donnees, formatValeur = (v) => String(v) }: PropsBarres) {
  const max = maximumRond(Math.max(0, ...donnees.map((d) => d.valeur)))
  const maxValeur = Math.max(0, ...donnees.map((d) => d.valeur))
  const parDefaut = donnees.findIndex((d) => d.valeur === maxValeur)
  const [choix, setChoix] = useState<number | null>(null)
  const actif = choix ?? parDefaut
  const resume = donnees.map((d) => `${d.libelle} ${formatValeur(d.valeur)}`).join(', ')
  const echelle = [...graduations(max)].reverse()
  const rayures = 'repeating-linear-gradient(135deg, rgba(255,255,255,0.45) 0 1.5px, transparent 1.5px 5px)'

  return (
    <div role="img" aria-label={`${description} : ${resume}`} className="mt-1 flex h-[230px] gap-3">
      <div aria-hidden="true" className="flex flex-col justify-between pb-[26px] text-right text-[11px] text-[#9aa0b2] tabular-nums">
        {echelle.map((g) => (
          <span key={g}>{abrege(g)}</span>
        ))}
      </div>
      <div className="relative min-w-0 flex-1">
        <div aria-hidden="true" className="absolute inset-x-0 top-1.5 bottom-8 flex flex-col justify-between">
          {echelle.map((g) => (
            <div key={g} className="border-t border-dashed border-[#e6e8ee]" />
          ))}
        </div>
        <div className="relative box-border flex h-full justify-around gap-[clamp(8px,2.5vw,36px)] px-2 pt-11">
          {donnees.map((d, i) => {
            const estActif = i === actif
            const hauteur = d.valeur > 0 ? Math.max((d.valeur / max) * 100, 4) : 0
            return (
              <div
                key={d.libelle}
                tabIndex={0}
                onMouseEnter={() => setChoix(i)}
                onMouseLeave={() => setChoix(null)}
                onFocus={() => setChoix(i)}
                onBlur={() => setChoix(null)}
                className="flex h-full max-w-[84px] min-w-0 flex-1 flex-col items-center outline-none"
              >
                <div className="flex w-full flex-1 items-end">
                  <div
                    className="relative w-full origin-bottom rounded-full transition-colors duration-200"
                    style={{
                      height: `${hauteur}%`,
                      backgroundColor: estActif ? '#1d4ed8' : '#a9bdf6',
                      backgroundImage: rayures,
                      animation: `croissance 500ms cubic-bezier(0.22, 1, 0.36, 1) ${i * 80}ms both`,
                    }}
                  >
                    {estActif && d.valeur > 0 && (
                      <>
                        <span className="absolute -top-[38px] left-1/2 -translate-x-1/2 rounded-full bg-[#1d4ed8] px-[9px] py-[3px] text-[10px] font-bold whitespace-nowrap text-white">
                          {formatValeur(d.valeur)}
                        </span>
                        <span className="absolute -top-2.5 left-1/2 box-border size-3.5 -translate-x-1/2 rounded-full border-[3px] border-[#1d4ed8] bg-white" />
                      </>
                    )}
                  </div>
                </div>
                <div className="flex h-[26px] items-end text-[11px] font-medium text-[#9aa0b2]">{d.libelle}</div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
