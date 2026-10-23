import { useEffect, useRef, useState } from "react";
import { domAnimation, LazyMotion, m, useReducedMotion } from "motion/react";
import { animationsActives } from "../../design/animation/usePresence";
import { maximumRond, trace } from "./GraphiqueSerie";

interface Point {
  libelle: string;
  valeur: number;
  /** Valeur du même jour de la période précédente. */
  precedente: number;
}

interface Props {
  description: string;
  points: Point[];
  libelleSerie: string;
  libellePrecedente: string;
  formatValeur: (v: number) => string;
}

const L = 360;
const H = 176;
const MARGE = { haut: 12, bas: 24, gauche: 36, droite: 8 };
const BASE = H - MARGE.bas;
const LARGEUR_UTILE = L - MARGE.gauche - MARGE.droite;

/** 2 500 → « 2,5 k » : les graduations restent courtes. */
function compact(v: number): string {
  return v >= 1000 ? `${(v / 1000).toLocaleString("fr-FR", { maximumFractionDigits: 1 })} k` : String(v);
}

/**
 * Courbe des revenus jour par jour, avec la période précédente en pointillés.
 * Au premier affichage (et à chaque changement de données) les deux courbes montent depuis la base ; au survol, un guide
 * vertical, un point sur la courbe et une infobulle donnent les deux valeurs du jour.
 */
export default function CourbeRevenus({ description, points, libelleSerie, libellePrecedente, formatValeur }: Props) {
  const anime = animationsActives();
  const reduit = useReducedMotion();
  const montee = { duration: anime && !reduit ? 0.9 : 0, ease: [0.22, 1, 0.36, 1] as const };
  const [pret, setPret] = useState(!anime);
  useEffect(() => {
    if (pret) return;
    const image = requestAnimationFrame(() => setPret(true));
    return () => cancelAnimationFrame(image);
  }, [pret]);

  const [survol, setSurvol] = useState<number | null>(null);
  const racine = useRef<HTMLDivElement>(null);

  const max = maximumRond(Math.max(0, ...points.flatMap((p) => [p.valeur, p.precedente])));
  const n = points.length;
  const x = (i: number) => MARGE.gauche + (n > 1 ? (LARGEUR_UTILE * i) / (n - 1) : LARGEUR_UTILE / 2);
  const y = (v: number) => (pret ? BASE - (v / max) * (BASE - MARGE.haut) : BASE);
  const courbe = trace(points.map((p, i) => [x(i), y(p.valeur)]));
  const pointillee = trace(points.map((p, i) => [x(i), y(p.precedente)]));
  const aire = n > 0 ? `${courbe} L${x(n - 1)},${BASE} L${x(0)},${BASE} Z` : "";
  const graduations = [0, 0.5, 1].map((r) => ({ valeur: max * r, y: BASE - r * (BASE - MARGE.haut) }));
  const resume = points.map((p) => `${p.libelle} ${formatValeur(p.valeur)}`).join(", ");
  const choisi = survol !== null ? points[survol] : undefined;
  const positionX = survol !== null ? (x(survol) / L) * 100 : 0;

  const survoler = (e: React.MouseEvent) => {
    const boite = racine.current?.getBoundingClientRect();
    if (!boite || n === 0) return;
    const xSvg = ((e.clientX - boite.left) / boite.width) * L;
    const i = Math.round(((xSvg - MARGE.gauche) / LARGEUR_UTILE) * (n - 1));
    setSurvol(Math.max(0, Math.min(n - 1, i)));
  };

  const transition = anime ? "opacity 180ms ease, transform 180ms cubic-bezier(0.22, 1, 0.36, 1)" : undefined;
  const indices = n > 1 ? [0, Math.floor((n - 1) / 2), n - 1] : [0];

  return (
    <div className="mt-2">
      <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-[11px] text-[#8a90a2]">
        <span className="inline-flex items-center gap-1.5">
          <span className="size-1.5 rounded-full bg-marque" />
          {libelleSerie}
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="w-3.5 border-t border-dashed border-[#9aa0b2]" />
          {libellePrecedente}
        </span>
      </div>
      <div
        ref={racine}
        role="img"
        aria-label={`${description} : ${resume}`}
        data-anime={anime}
        data-survol={survol !== null}
        className="courbe-revenus relative mt-1"
        onMouseMove={survoler}
        onMouseLeave={() => setSurvol(null)}
      >
        <LazyMotion features={domAnimation} strict>
          <svg viewBox={`0 0 ${L} ${H}`} className="block h-auto w-full" aria-hidden="true">
            <defs>
              <linearGradient id="courbe-revenus-aire" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="var(--color-marque)" stopOpacity="0.28" />
                <stop offset="100%" stopColor="var(--color-marque)" stopOpacity="0" />
              </linearGradient>
            </defs>
            {graduations.map((g) => (
              <g key={g.y}>
                <line x1={MARGE.gauche} x2={L - MARGE.droite} y1={g.y} y2={g.y} stroke="#eceef3" strokeWidth={1} />
                <text x={MARGE.gauche - 6} y={g.y + 3} textAnchor="end" fontSize={9} fill="#9aa0b2">
                  {compact(g.valeur)}
                </text>
              </g>
            ))}
            <m.path
              className="courbe-precedente"
              initial={false}
              animate={{ d: pointillee }}
              transition={{ ...montee, delay: anime && !reduit ? 0.12 : 0 }}
              fill="none"
              stroke="#9aa0b2"
              strokeWidth={1.4}
              strokeDasharray="4 4"
              strokeLinecap="round"
            />
            <m.path className="courbe-aire" initial={false} animate={{ d: aire }} transition={montee} fill="url(#courbe-revenus-aire)" />
            <m.path
              className="courbe-ligne"
              initial={false}
              animate={{ d: courbe }}
              transition={montee}
              fill="none"
              stroke="var(--color-marque)"
              strokeWidth={2.2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
            {choisi && (
              <line
                className="courbe-guide"
                x1={0}
                x2={0}
                y1={MARGE.haut - 4}
                y2={BASE}
                stroke="var(--color-marque)"
                strokeWidth={1}
                strokeDasharray="3 4"
                style={{ transform: `translateX(${x(survol!)}px)`, opacity: 0.5 }}
              />
            )}
            {choisi && (
              <circle
                className="courbe-point"
                cx={x(survol!)}
                cy={y(choisi.valeur)}
                r={4}
                fill="#fff"
                stroke="var(--color-marque)"
                strokeWidth={2.2}
              />
            )}
            {indices.map((i) => (
              <text
                key={i}
                x={x(i)}
                y={H - 8}
                textAnchor={i === 0 && n > 1 ? "start" : i === n - 1 && n > 1 ? "end" : "middle"}
                fontSize={9}
                fill="#9aa0b2"
              >
                {points[i]?.libelle}
              </text>
            ))}
          </svg>
        </LazyMotion>
        <div
          aria-hidden="true"
          className="courbe-bulle pointer-events-none absolute top-1 z-10 min-w-[128px] rounded-xl bg-white px-3 py-2 text-[11px] shadow-[0_8px_24px_rgba(18,23,43,0.14)]"
          style={{
            left: `${Math.min(Math.max(positionX, 22), 78)}%`,
            transform: `translateX(-50%) translateY(${choisi ? 0 : 6}px)`,
            opacity: choisi ? 1 : 0,
            transition,
          }}
        >
          <div className="font-bold text-[#12172b]">{choisi?.libelle}</div>
          <div className="mt-1 flex items-center justify-between gap-3">
            <span className="inline-flex items-center gap-1.5 text-[#6b7287]">
              <span className="size-1.5 rounded-full bg-marque" />
              {libelleSerie}
            </span>
            <span className="font-bold tabular-nums text-[#12172b]">{choisi ? formatValeur(choisi.valeur) : ""}</span>
          </div>
          <div className="mt-0.5 flex items-center justify-between gap-3">
            <span className="inline-flex items-center gap-1.5 text-[#6b7287]">
              <span className="size-1.5 rounded-full bg-[#9aa0b2]" />
              {libellePrecedente}
            </span>
            <span className="font-bold tabular-nums text-[#12172b]">{choisi ? formatValeur(choisi.precedente) : ""}</span>
          </div>
        </div>
      </div>
    </div>
  );
}
