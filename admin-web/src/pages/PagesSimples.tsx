import { Link } from 'react-router-dom'
import { Carte } from '../design/composants'
import { L } from '../i18n/libelles'

function PageMessage({ titre, texte, code }: { titre: string; texte: string; code?: string }) {
  return (
    <Carte className="mx-auto mt-10 max-w-md">
      <div className="flex flex-col items-center gap-3 py-6 text-center">
        {code && (
          <p aria-hidden="true" className="m-0 text-6xl leading-[normal] font-bold text-[#a7d4bf]">
            {code}
          </p>
        )}
        <h1 className="m-0 text-2xl leading-[normal] font-bold text-[#12172b]">{titre}</h1>
        <p className="m-0 text-sm leading-[normal] text-[#8a90a2]">{texte}</p>
        <Link to="/" className="mq-btn-bleu mt-2 inline-flex h-12 items-center rounded-3xl bg-marque px-6 text-[13px] font-bold text-white no-underline shadow-[0_8px_18px_rgba(45,106,79,0.30)]">
          {L.erreurs.retourAccueil}
        </Link>
      </div>
    </Carte>
  )
}

export function AccesRefusePage() {
  return <PageMessage code="403" titre={L.erreurs.accesRefuse} texte={L.erreurs.accesRefuseTexte} />
}

export function NonTrouvePage() {
  return <PageMessage code="404" titre={L.erreurs.introuvable} texte={L.erreurs.introuvableTexte} />
}
