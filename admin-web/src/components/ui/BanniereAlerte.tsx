import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { L } from '../../i18n/libelles'
import { IconeAttention, IconeCritique, IconeFleche, IconeInfo } from './Icones'

export type Gravite = 'info' | 'attention' | 'critique'

const styles: Record<Gravite, { boite: string; icone: ReactNode }> = {
  info: { boite: 'bg-info-fond text-info-texte border-info/30', icone: <IconeInfo /> },
  attention: { boite: 'bg-attention-fond text-attention-texte border-attention/40', icone: <IconeAttention /> },
  critique: { boite: 'bg-erreur-fond text-erreur-texte border-erreur/40', icone: <IconeCritique /> },
}

interface Props {
  gravite: Gravite
  titre: string
  detail?: string
  /** Lien vers la liste concernée. */
  vers?: string
  delai?: number
}

/** Bannière d'alerte à trois gravités. Les alertes critiques sont annoncées immédiatement (role="alert"), les autres poliment. */
export default function BanniereAlerte({ gravite, titre, detail, vers, delai = 0 }: Props) {
  const s = styles[gravite]
  return (
    <div
      role={gravite === 'critique' ? 'alert' : 'status'}
      className={`animate-glissement flex items-start gap-3 rounded-grand border px-5 py-3.5 ${s.boite}`}
      style={{ animationDelay: `${delai}ms` }}
    >
      <span className="mt-0.5 shrink-0">{s.icone}</span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-semibold">
          <span className="sr-only">{L.gravites[gravite]} : </span>
          {titre}
        </p>
        {detail && <p className="mt-0.5 text-xs opacity-90">{detail}</p>}
      </div>
      {vers && (
        <Link
          to={vers}
          className="inline-flex shrink-0 items-center gap-1 rounded-petit text-sm font-semibold underline-offset-2 hover:underline"
        >
          {L.commun.voirLaListe}
          <IconeFleche width={16} height={16} />
        </Link>
      )}
    </div>
  )
}
