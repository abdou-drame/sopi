import type { ReactNode } from 'react'

interface Props {
  titre: string
  sousTitre?: string
  libelleStatut: string
  statut: string
  /** Ligne sous le statut (date, motif...). */
  note?: ReactNode
  children?: ReactNode
}

/** Carte « Décision » des détails : statut actuel sur fond dégradé bleu, comme la maquette. */
export default function CarteStatutActuel({ titre, sousTitre, libelleStatut, statut, note, children }: Props) {
  return (
    <section className="animate-apparition rounded-carte bg-surface p-[18px]">
      <h3 className="text-[15px] font-bold text-texte">{titre}</h3>
      {sousTitre && <p className="mt-0.5 text-xs text-texte-discret">{sousTitre}</p>}
      <div
        className="mt-[18px] rounded-[22px] p-5 text-white"
        style={{ backgroundImage: 'linear-gradient(150deg, #3563e8 0%, #1d4ed8 50%, #17338f 100%)' }}
      >
        <p className="text-xs font-semibold opacity-80">{libelleStatut}</p>
        <p className="mt-1.5 text-[28px] leading-[34px] font-bold tracking-tight" data-statut={statut}>
          {statut}
        </p>
        {note && <div className="mt-[18px] text-xs opacity-90">{note}</div>}
      </div>
      {children && <div className="mt-4 flex flex-col gap-2">{children}</div>}
    </section>
  )
}
