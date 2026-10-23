import { useEffect, useRef, useState } from "react";
import { animationsActives } from "../../design/animation/usePresence";

interface Props {
  libelle: string;
  /** Part du métier dans les revenus, entre 0 et 1 ; absent tant que les données ne sont pas arrivées. */
  part: number | undefined;
  /** Texte sous le nom du métier (montant des commissions). */
  detail: string;
}

const TRAITS = 61;
const L = 120;
const H = 66;
const CENTRE = { x: L / 2, y: H - 4 };
const RAYON_EXT = 56;
const RAYON_INT = 40;
const DUREE_MS = 1300;

/** Vert de la marque au début de l'arc, olive à son extrémité (comme sur la vidéo de référence). */
function couleurTrait(r: number): string {
  const de = [58, 139, 96];
  const vers = [181, 168, 58];
  return `rgb(${de.map((c, i) => Math.round(c + (vers[i] - c) * r ** 1.2)).join(",")})`;
}

/** Valeur qui glisse de sa valeur courante vers `cible` (courbe douce) ; sans animation possible, elle y saute. */
function useProgression(cible: number): number {
  const anime = animationsActives();
  const [valeur, setValeur] = useState(0);
  const courante = useRef(0);
  useEffect(() => {
    if (!anime) return;
    const de = courante.current;
    let debut: number | null = null;
    let image = 0;
    const pas = (horloge: number) => {
      // L'horloge de requestAnimationFrame peut précéder le premier appel : on part de la première image reçue.
      debut ??= horloge;
      const t = Math.max(0, Math.min(1, (horloge - debut) / DUREE_MS));
      courante.current = de + (cible - de) * (1 - Math.pow(1 - t, 3));
      setValeur(courante.current);
      if (t < 1) image = requestAnimationFrame(pas);
    };
    image = requestAnimationFrame(pas);
    return () => cancelAnimationFrame(image);
  }, [cible, anime]);
  return anime ? valeur : cible;
}

/**
 * Jauge en demi-cercle de traits fins : les traits s'allument de gauche à droite pendant que le pourcentage monte,
 * puis la jauge rejoint sa nouvelle valeur quand les données changent.
 */
export default function JaugeMetier({ libelle, part, detail }: Props) {
  const progression = useProgression(part ?? 0);
  const pourcentage = (progression * 100).toLocaleString("fr-FR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  const final = ((part ?? 0) * 100).toLocaleString("fr-FR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  return (
    <div
      role="img"
      aria-label={part === undefined ? libelle : `${libelle} : ${final} % des commissions, ${detail}`}
      className="jauge-metier min-w-0 flex-1 text-center"
    >
      <div className="relative">
        <svg viewBox={`0 0 ${L} ${H}`} className="block h-auto w-full" aria-hidden="true">
          {Array.from({ length: TRAITS }, (_, i) => {
            const r = i / (TRAITS - 1);
            const angle = Math.PI * (1 - r);
            const cos = Math.cos(angle);
            const sin = Math.sin(angle);
            const allume = progression > 0 && r <= progression + 1e-9;
            return (
              <line
                key={i}
                x1={CENTRE.x + RAYON_INT * cos}
                y1={CENTRE.y - RAYON_INT * sin}
                x2={CENTRE.x + RAYON_EXT * cos}
                y2={CENTRE.y - RAYON_EXT * sin}
                stroke={allume ? couleurTrait(r) : "#e4e6ec"}
                strokeWidth={1.3}
                strokeLinecap="round"
              />
            );
          })}
        </svg>
        <div className="absolute inset-x-0 bottom-0 text-[15px] leading-none font-bold tracking-[-0.3px] tabular-nums text-[#12172b]">
          {part === undefined ? "—" : `${pourcentage} %`}
        </div>
      </div>
      <div className="mt-2 truncate text-xs font-semibold text-[#12172b]">{libelle}</div>
      <div className="mt-0.5 truncate text-[11px] text-[#8a90a2] tabular-nums">{detail}</div>
    </div>
  );
}
