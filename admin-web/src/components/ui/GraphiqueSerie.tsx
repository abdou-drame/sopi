import { useEffect, useState } from "react";
import { domAnimation, LazyMotion, m, useReducedMotion } from "motion/react";
import { animationsActives } from "../../design/animation/usePresence";

export type ModeSerie = "colonnes" | "courbe";

interface Props {
  description: string;
  donnees: { libelle: string; valeur: number }[];
  mode: ModeSerie;
  formatValeur?: (v: number) => string;
}

const L = 360;
const H = 150;
const MARGE = { haut: 34, bas: 26, cote: 14 };
const BASE = H - MARGE.bas;
const DIAMETRE_POINT = 10;

/** Arrondit le maximum à une valeur « ronde » pour que la plus haute forme ne touche pas le haut. */
function maximumRond(max: number): number {
  if (max <= 0) return 1;
  const puissance = 10 ** Math.floor(Math.log10(max));
  const n = max / puissance;
  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * puissance;
}

/** Courbe lisse sans dépassement : entre deux points, les tangentes sont horizontales. */
function trace(points: [number, number][]): string {
  if (points.length === 0) return "";
  return points.reduce((d, [x, y], i) => {
    if (i === 0) return `M${x},${y}`;
    const [px, py] = points[i - 1];
    const milieu = (px + x) / 2;
    return `${d} C${milieu},${py} ${milieu},${y} ${x},${y}`;
  }, "");
}

/**
 * Série de valeurs par période, en colonnes ou en courbe. Chaque période est UNE seule forme SVG : une colonne arrondie
 * qui, en mode courbe, se resserre en point sur la courbe (transitions CSS sur x, y, largeur, hauteur et rayon),
 * pendant que la ligne se trace (Motion interpole le tracé `d`, ce que les transitions CSS ne font que sur Chrome). Le passage d'un mode à l'autre est donc un vrai morph, dans les deux sens.
 * Au premier affichage les formes poussent depuis la base ; sans animation possible (mouvement réduit), tout est posé d'emblée.
 */
export default function GraphiqueSerie({
  description,
  donnees,
  mode,
  formatValeur = (v) => String(v),
}: Props) {
  const anime = animationsActives();
  const reduit = useReducedMotion();
  // Le glissement des tracés est piloté par Motion (interpolation JS de `d`), fiable sur tous les navigateurs ; le reste reste en transitions CSS.
  const glissement = {
    duration: anime && !reduit ? 0.55 : 0,
    ease: [0.22, 1, 0.36, 1] as const,
  };
  const [pret, setPret] = useState(!anime);
  useEffect(() => {
    if (pret) return;
    const image = requestAnimationFrame(() => setPret(true));
    return () => cancelAnimationFrame(image);
  }, [pret]);

  const [survol, setSurvol] = useState<number | null>(null);
  const maxValeur = Math.max(0, ...donnees.map((d) => d.valeur));
  const max = maximumRond(maxValeur);
  const actif =
    survol ??
    Math.max(
      0,
      donnees.findIndex((d) => d.valeur === maxValeur),
    );
  const resume = donnees
    .map((d) => `${d.libelle} ${formatValeur(d.valeur)}`)
    .join(", ");

  const n = Math.max(donnees.length, 1);
  const pas = (L - 2 * MARGE.cote) / n;
  const largeurColonne = Math.min(pas * 0.55, 26);
  const formes = donnees.map((d, i) => {
    const cx = MARGE.cote + pas * (i + 0.5);
    const y = pret ? BASE - (d.valeur / max) * (BASE - MARGE.haut) : BASE;
    return { cx, y, valeur: d.valeur };
  });
  const courbe = mode === "courbe";
  const chemin = trace(formes.map((f) => [f.cx, f.y]));
  const aire =
    formes.length > 0
      ? `${chemin} L${formes[formes.length - 1].cx},${BASE} L${formes[0].cx},${BASE} Z`
      : "";
  const choisi = formes[actif];
  const texte = choisi ? formatValeur(choisi.valeur) : "";
  const largeurBulle = texte.length * 6.4 + 16;

  return (
    <div
      role="img"
      aria-label={`${description} : ${resume}`}
      data-mode={mode}
      data-anime={anime}
      data-survol={survol !== null}
      className="relative mt-1 h-[150px]"
    >
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 top-4 bottom-6"
        style={{
          background:
            "radial-gradient(ellipse at 50% 60%, rgba(45,106,79,0.16), transparent 70%)",
        }}
      />
      <LazyMotion features={domAnimation} strict>
        <svg
          viewBox={`0 0 ${L} ${H}`}
          className="relative h-full w-full"
          aria-hidden="true"
          onMouseLeave={() => setSurvol(null)}
        >
          <defs>
            <linearGradient id="serie-aire" x1="0" y1="0" x2="0" y2="1">
              <stop
                offset="0%"
                stopColor="var(--color-marque)"
                stopOpacity="0.22"
              />
              <stop
                offset="100%"
                stopColor="var(--color-marque)"
                stopOpacity="0"
              />
            </linearGradient>
          </defs>
          {choisi && (
            <rect
              className="serie-survol"
              x={choisi.cx - pas / 2 + 2}
              y={MARGE.haut - 14}
              width={pas - 4}
              height={BASE - MARGE.haut + 14}
              rx={14}
              fill="var(--color-marque)"
              style={{ opacity: survol !== null ? 0.08 : 0 }}
            />
          )}
          <m.path
            className="serie-aire"
            initial={false}
            animate={{ d: aire }}
            transition={glissement}
            fill="url(#serie-aire)"
            style={{ opacity: courbe && pret ? 1 : 0 }}
          />
          <m.path
            className="serie-ligne"
            initial={false}
            animate={{ d: chemin }}
            transition={glissement}
            pathLength={1}
            fill="none"
            stroke="var(--color-marque)"
            strokeWidth={2.5}
            strokeLinecap="round"
            strokeDasharray={1}
            strokeDashoffset={courbe && pret ? 0 : 1}
          />
          {formes.map((f, i) => {
            const estActif = i === actif;
            const grossi = survol === i;
            const diametre = grossi ? DIAMETRE_POINT + 5 : DIAMETRE_POINT;
            const largeur = largeurColonne + (grossi ? 5 : 0);
            const hauteurColonne =
              f.valeur > 0 ? Math.max(BASE - f.y, pret ? 4 : 0) : 0;
            const forme = courbe
              ? {
                  x: f.cx - diametre / 2,
                  y: f.y - diametre / 2,
                  width: diametre,
                  height: diametre,
                  rx: diametre / 2,
                }
              : {
                  x: f.cx - largeur / 2,
                  y: BASE - hauteurColonne,
                  width: largeur,
                  height: hauteurColonne,
                  rx: largeur / 2,
                };
            return (
              <rect
                key={i}
                className="serie-forme"
                data-forme={courbe ? "point" : "colonne"}
                {...forme}
                fill={
                  courbe ? "#fff" : estActif ? "var(--color-marque)" : "#a7d4bf"
                }
                stroke="var(--color-marque)"
                strokeWidth={courbe ? 2.5 : 0}
                style={{ opacity: courbe || f.valeur > 0 ? 1 : 0 }}
              />
            );
          })}
          {choisi && choisi.valeur > 0 && (
            <g
              className="serie-bulle"
              style={{
                transform: `translate(${choisi.cx}px, ${choisi.y - 12}px)`,
              }}
            >
              <rect
                x={-largeurBulle / 2}
                y={-17}
                width={largeurBulle}
                height={17}
                rx={8.5}
                fill="var(--color-marque)"
              />
              <text
                y={-5}
                textAnchor="middle"
                fontSize={10}
                fontWeight={700}
                fill="#fff"
              >
                {texte}
              </text>
            </g>
          )}
          {donnees.map((d, i) => (
            <text
              key={i}
              x={formes[i].cx}
              y={H - 8}
              textAnchor="middle"
              fontSize={11}
              fontWeight={500}
              fill="#9aa0b2"
            >
              {d.libelle}
            </text>
          ))}
          {formes.map((f, i) => (
            <rect
              key={`h-${i}`}
              x={f.cx - pas / 2}
              y={0}
              width={pas}
              height={H}
              fill="transparent"
              onMouseEnter={() => setSurvol(i)}
            />
          ))}
        </svg>
      </LazyMotion>
    </div>
  );
}
