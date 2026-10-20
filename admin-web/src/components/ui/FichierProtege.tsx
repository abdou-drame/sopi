import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { messageDErreur, telecharger } from '../../api/client'
import { L } from '../../i18n/libelles'
import Bouton from './Bouton'
import Squelette from './Squelette'
import VisionneuseImage from './VisionneuseImage'

interface Props {
  /** Chemin de l'API (sans /api/v1) du fichier protégé. */
  chemin: string
  /** Texte alternatif (image) ou nom du lien (PDF). */
  alt: string
  /** Type MIME connu à l'avance ; à défaut, celui du fichier reçu. */
  typeMime?: string
  /** Dimensions de l'aperçu (défaut : une vignette de 112 px). */
  className?: string
}

/** Chemin d'une preuve renvoyé par l'API (« /api/v1/admin/... ») ramené au chemin attendu par le client HTTP. */
export function cheminApi(url: string): string {
  return url.replace(/^\/api\/v1/, '')
}

/**
 * Affiche un fichier protégé : il est récupéré avec l'en-tête Authorization, converti en adresse locale (blob:)
 * puis montré dans la visionneuse (image) ou ouvert dans un nouvel onglet (PDF). L'adresse locale est libérée au démontage.
 */
export default function FichierProtege({ chemin, alt, typeMime, className = 'size-28' }: Props) {
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['fichier', chemin],
    queryFn: ({ signal }) => telecharger(chemin, signal),
    staleTime: Infinity,
    gcTime: 60_000,
  })
  const [adresse, setAdresse] = useState<string | null>(null)

  useEffect(() => {
    if (!data) return
    const url = URL.createObjectURL(data)
    // L'adresse locale n'existe qu'après le rendu (et se libère au démontage) : elle ne peut pas être calculée pendant le rendu.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setAdresse(url)
    return () => {
      URL.revokeObjectURL(url)
      setAdresse(null)
    }
  }, [data])

  if (isPending || (data && !adresse)) {
    return (
      <div role="status" aria-label={L.commun.chargement}>
        <Squelette className={`${className} rounded-moyen`} />
      </div>
    )
  }
  if (isError) {
    return (
      <div role="alert" className={`flex ${className} flex-col items-center justify-center gap-1 rounded-moyen bg-fond p-2 text-center`}>
        <p className="text-micro text-erreur-texte">{messageDErreur(error)}</p>
        <Bouton variante="secondaire" taille="p" onClick={() => void refetch()}>
          {L.commun.reessayer}
        </Bouton>
      </div>
    )
  }
  const type = typeMime ?? data?.type ?? ''
  if (type.startsWith('image/')) {
    return <VisionneuseImage src={adresse!} alt={alt} className={className} />
  }
  return (
    <a
      href={adresse!}
      target="_blank"
      rel="noopener noreferrer"
      className={`inline-flex ${className} flex-col items-center justify-center gap-1 rounded-moyen border border-bordure bg-fond p-2 text-center text-xs font-medium text-primaire-700 hover:bg-primaire-50`}
    >
      <span aria-hidden="true" className="text-lg font-bold">PDF</span>
      {L.fichiers.ouvrir}
      <span className="sr-only"> : {alt}</span>
    </a>
  )
}
