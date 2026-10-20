import type { ReactNode } from 'react'
import { L } from '../i18n/libelles'
import { BoutonAction } from './boutons'
import { IRecherche, ILitiges } from './icones'

/** Bloc centré commun au vide et à l'erreur (Etats.html) : rond de 64, titre 15 gras, texte 13. */
function BlocEtat({ ton, icone, titre, texte, role, children }: { ton: 'neutre' | 'erreur'; icone: ReactNode; titre: string; texte?: string | null; role?: 'alert' | 'status'; children?: ReactNode }) {
  return (
    <div role={role} className="px-3 py-10 text-center">
      <span className={`inline-flex size-16 items-center justify-center rounded-full ${ton === 'erreur' ? 'bg-[#fde9e9] text-[#b0161b]' : 'bg-[#f3f4f7] text-[#9aa0b2]'}`}>{icone}</span>
      <div className="mt-3.5 text-[15px] leading-[normal] font-bold">{titre}</div>
      {texte && <div className="mt-1 text-[13px] leading-[normal] text-[#8a90a2]">{texte}</div>}
      {children && <div className="mt-4">{children}</div>}
    </div>
  )
}

export function EtatVide({ texte }: { texte: string }) {
  return <BlocEtat ton="neutre" icone={<IRecherche width={26} height={26} />} titre={L.commun.aucunResultat} texte={texte} role="status" />
}

export function EtatErreur({ titre = L.commun.erreurListe, texte, onReessayer }: { titre?: string; texte?: string | null; onReessayer?: () => void }) {
  return (
    <BlocEtat ton="erreur" icone={<ILitiges width={26} height={26} />} titre={titre} texte={texte} role="alert">
      {onReessayer && <BoutonAction onClick={onReessayer}>{L.commun.reessayer}</BoutonAction>}
    </BlocEtat>
  )
}

/** Lignes animées : avatar rond, deux traits, un trait court à droite. Animation coupée avec prefers-reduced-motion. */
export function EtatChargement({ lignes = 4 }: { lignes?: number }) {
  return (
    <div role="status" aria-label={L.commun.chargement} aria-busy="true">
      {Array.from({ length: lignes }, (_, i) => (
        <div key={i} className="flex items-center gap-3 py-3" aria-hidden="true">
          <div className="mq-sk size-9 rounded-full" />
          <div className="flex flex-1 flex-col gap-2">
            <div className="mq-sk h-3.5 w-[55%] rounded-[7px]" />
            <div className="mq-sk h-2.5 w-[35%] rounded-[5px]" />
          </div>
          <div className="mq-sk h-3.5 w-[70px] rounded-[7px]" />
        </div>
      ))}
    </div>
  )
}
