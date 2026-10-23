import { useEffect, useState } from "react";
import { animationsActives } from "../../design/animation/usePresence";

export interface SerieGroupe {
  libelle: string;
  couleur: string;
}

export interface Groupe {
  libelle: string;
  /** Une valeur par série, dans l'ordre de `series`. */
  valeurs: number[];
}

interface Props {
  description: string;
  series: SerieGroupe[];
  groupes: Groupe[];
  formatValeur?: (v: number) => string;
}

const L = 460;
const H = 250;
const MARGE = { haut: 14, bas: 26, gauche: 36, droite: 8 };
const BASE = H - MARGE.bas;
const HAUTEUR_UTILE = BASE - MARGE.haut;
const LARGEUR_BARRE = 9;
const ECART = 3;
const COURBE = "cubic-bezier(0.22, 1, 0.36, 1)";

/** Arrondit le maximum à une valeur « ronde » pour que la plus haute barre ne touche pas le haut. */
function maximumRond(max: number): number {
  if (max <= 0) return 1;
  const puissance = 10 ** Math.floor(Math.log10(max));
  const n = max / puissance;
  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * puissance;
}

/** 12 500 → « 12,5 k » : les graduations restent courtes. */
function compact(v: number): string {
  return v >= 1000 ? `${(v / 1000).toLocaleString("fr-FR", { maximumFractionDigits: 1 })} k` : String(v);
}

/**
 * Colonnes groupées par période (une fine colonne par série, coiffée d'un trait de la couleur de la série), sur une grille
 * graduée, avec une infobulle qui détaille la période survolée (la dernière par défaut, comme sur la vidéo de référence).
 * Les colonnes poussent depuis la base en cascade de gauche à droite à l'arrivée du graphique, puis glissent vers leurs
 * nouvelles valeurs quand les données changent. Sans animation possible (mouvement réduit), tout est posé d'emblée.
 */
export default function GraphiqueGroupe({ description, series, groupes, formatValeur = (v) => String(v) }: Props) {
  const anime = animationsActives();
  const [pret, setPret] = useState(!anime);
  useEffect(() => {
    if (pret) return;
    const image = requestAnimationFrame(() => setPret(true));
    return () => cancelAnimationFrame(image);
  }, [pret]);

  // Le décalage entre colonnes ne sert qu'à l'arrivée du graphique ; ensuite elles glissent ensemble.
  const [entree, setEntree] = useState(anime);
  useEffect(() => {
    if (!entree) return;
    const minuteur = setTimeout(() => setEntree(false), 1400);
    return () => clearTimeout(minuteur);
  }, [entree]);

  const [survol, setSurvol] = useState<number | null>(null);
  const max = maximumRond(Math.max(0, ...groupes.flatMap((g) => g.valeurs)));
  const intervalles = max >= 5 ? 5 : max;
  const graduations = Array.from({ length: intervalles + 1 }, (_, i) => ({
    valeur: (max * i) / intervalles,
    y: BASE - (i / intervalles) * HAUTEUR_UTILE,
  }));

  const n = Math.max(groupes.length, 1);
  const pas = (L - MARGE.gauche - MARGE.droite) / n;
  const largeurGroupe = series.length * LARGEUR_BARRE + (series.length - 1) * ECART;
  const actif = survol ?? groupes.length - 1;
  const choisi = groupes[actif];
  const centre = (g: number) => MARGE.gauche + pas * (g + 0.5);
  const resume = groupes
    .map((g) => `${g.libelle} ${g.valeurs.map((v, i) => `${series[i].libelle} ${formatValeur(v)}`).join(", ")}`)
    .join(" ; ");
  const transition = (delai: number) =>
    anime ? `transform 900ms ${COURBE} ${delai}ms` : undefined;

  return (
    <div
      role="img"
      aria-label={`${description} : ${resume}`}
      data-anime={anime}
      data-survol={survol !== null}
      className="graphique-groupe relative"
    >
      <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-[11px] text-[#8a90a2]">
        {series.map((s) => (
          <span key={s.libelle} className="inline-flex items-center gap-1.5">
            <span className="size-1.5 rounded-full" style={{ background: s.couleur }} />
            {s.libelle}
          </span>
        ))}
      </div>
      <svg viewBox={`0 0 ${L} ${H}`} className="mt-1 block h-auto w-full" aria-hidden="true" onMouseLeave={() => setSurvol(null)}>
        <defs>
          <linearGradient id="groupe-colonne" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#f2f4f7" />
            <stop offset="100%" stopColor="#fafbfc" />
          </linearGradient>
        </defs>
        {graduations.map((g) => (
          <g key={g.y}>
            <line x1={MARGE.gauche} x2={L - MARGE.droite} y1={g.y} y2={g.y} stroke="#eef0f4" strokeWidth={1} />
            <text x={MARGE.gauche - 8} y={g.y + 3} textAnchor="end" fontSize={9} fill="#9aa0b2">
              {compact(g.valeur)}
            </text>
          </g>
        ))}
        {survol !== null && (
          <rect
            className="groupe-survol"
            x={centre(survol) - pas / 2 + 3}
            y={MARGE.haut - 6}
            width={pas - 6}
            height={HAUTEUR_UTILE + 6}
            rx={12}
            fill="var(--color-marque)"
            opacity={0.06}
          />
        )}
        {groupes.map((g, gi) =>
          g.valeurs.map((v, si) => {
            const x = centre(gi) - largeurGroupe / 2 + si * (LARGEUR_BARRE + ECART);
            const ratio = pret ? v / max : 0;
            const delai = entree ? (gi * series.length + si) * 40 : 0;
            return (
              <g key={`${gi}-${si}`} className="groupe-colonne" data-valeur={v} style={{ opacity: v > 0 ? 1 : 0.55 }}>
                <rect
                  x={x}
                  y={MARGE.haut}
                  width={LARGEUR_BARRE}
                  height={HAUTEUR_UTILE}
                  rx={1.5}
                  fill="url(#groupe-colonne)"
                  stroke="#e6e9ef"
                  strokeWidth={0.8}
                  style={{
                    transformOrigin: `${x + LARGEUR_BARRE / 2}px ${BASE}px`,
                    transform: `scaleY(${ratio})`,
                    transition: transition(delai),
                    opacity: v > 0 ? 1 : 0,
                  }}
                />
                <rect
                  x={x}
                  y={BASE - 2.5}
                  width={LARGEUR_BARRE}
                  height={2.5}
                  rx={1.2}
                  fill={series[si].couleur}
                  style={{ transform: `translateY(${-ratio * HAUTEUR_UTILE}px)`, transition: transition(delai) }}
                />
              </g>
            );
          }),
        )}
        {groupes.map((g, gi) => (
          <text key={`l-${gi}`} x={centre(gi)} y={H - 8} textAnchor="middle" fontSize={10} fontWeight={500} fill="#9aa0b2">
            {g.libelle}
          </text>
        ))}
        {groupes.map((_, gi) => (
          <rect key={`h-${gi}`} x={centre(gi) - pas / 2} y={0} width={pas} height={H} fill="transparent" onMouseEnter={() => setSurvol(gi)} />
        ))}
      </svg>
      {choisi && (
        <div
          aria-hidden="true"
          className="groupe-bulle pointer-events-none absolute top-6 z-10 min-w-[132px] rounded-xl bg-white px-3 py-2 text-[11px] shadow-[0_8px_24px_rgba(18,23,43,0.14)]"
          style={{
            left: `${Math.min(Math.max((centre(actif) / L) * 100, 17), 83)}%`,
            transform: "translateX(-50%)",
            transition: anime ? "left 300ms cubic-bezier(0.22, 1, 0.36, 1)" : undefined,
          }}
        >
          <div className="font-bold text-[#12172b]">{choisi.libelle}</div>
          {series.map((s, i) => (
            <div key={s.libelle} className="mt-1 flex items-center justify-between gap-3">
              <span className="inline-flex items-center gap-1.5 text-[#6b7287]">
                <span className="size-1.5 rounded-full" style={{ background: s.couleur }} />
                {s.libelle}
              </span>
              <span className="font-bold tabular-nums text-[#12172b]">{formatValeur(choisi.valeurs[i])}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
