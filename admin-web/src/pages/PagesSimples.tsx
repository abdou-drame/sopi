import { Link, useLocation } from 'react-router-dom'
import { L } from '../i18n/libelles'
import { ENTREES_A_VENIR } from '../layout/menu'

function PageMessage({ titre, texte, code }: { titre: string; texte: string; code?: string }) {
  return (
    <div className="animate-apparition mx-auto mt-16 flex max-w-md flex-col items-center gap-3 text-center">
      {code && <p aria-hidden="true" className="text-6xl font-bold text-primaire-200">{code}</p>}
      <h1 className="text-2xl font-semibold text-titre">{titre}</h1>
      <p className="text-sm text-sous-titre">{texte}</p>
      <Link
        to="/"
        className="mt-2 rounded-moyen bg-primaire-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primaire-700"
      >
        {L.erreurs.retourAccueil}
      </Link>
    </div>
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
    <div className="animate-apparition">
      <h1 className="text-2xl font-semibold text-titre">{entree?.libelle ?? L.bientot.titre}</h1>
      <div className="mt-6 rounded-grand bg-surface p-10 text-center shadow-carte">
        <p className="text-lg font-semibold">{L.bientot.titre}</p>
        <p className="mt-1 text-sm text-texte-doux">{L.bientot.texte}</p>
      </div>
    </div>
  )
}
