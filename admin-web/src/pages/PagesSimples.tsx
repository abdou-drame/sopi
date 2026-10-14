import { useLocation } from 'react-router-dom'
import Carte from '../components/ui/Carte'
import EnTetePage from '../components/ui/EnTetePage'
import LienGelule from '../components/ui/LienGelule'
import { L } from '../i18n/libelles'
import { ENTREES_A_VENIR } from '../layout/menu'

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

/** Écrans du menu livrés à l'étape 2.2 : le titre vient du menu. */
export function BientotPage() {
  const { pathname } = useLocation()
  const entree = ENTREES_A_VENIR.find((e) => e.chemin === pathname)
  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre={entree?.libelle ?? L.bientot.titre} />
      <Carte>
        <div className="py-8 text-center">
          <span aria-hidden="true" className="mx-auto mb-3 grid size-14 place-items-center rounded-full bg-primaire-100 text-primaire-700">
            {entree?.icone}
          </span>
          <p className="text-lg font-semibold">{L.bientot.titre}</p>
          <p className="mt-1 text-sm text-texte-doux">{L.bientot.texte}</p>
        </div>
      </Carte>
    </div>
  )
}
