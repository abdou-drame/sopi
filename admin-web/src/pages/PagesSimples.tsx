import Carte from '../components/ui/Carte'
import LienGelule from '../components/ui/LienGelule'
import { L } from '../i18n/libelles'

function PageMessage({ titre, texte, code }: { titre: string; texte: string; code?: string }) {
  return (
    <Carte className="mx-auto mt-10 max-w-md">
      <div className="flex flex-col items-center gap-3 py-6 text-center">
        {code && <p aria-hidden="true" className="text-6xl font-bold text-primaire-200">{code}</p>}
        <h1 className="text-2xl font-bold text-texte">{titre}</h1>
        <p className="text-sm text-texte-doux">{texte}</p>
        <div className="mt-2">
          <LienGelule vers="/">{L.erreurs.retourAccueil}</LienGelule>
        </div>
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
