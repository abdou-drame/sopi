import { effacerJeton, lireJeton } from '../auth/jeton'
import { L } from '../i18n/libelles'

// Client HTTP unique du back-office : toute requête vers l'API passe par `requete`.
// En développement, l'URL est relative : le proxy de Vite (vite.config.ts) relaie /api vers l'API distante (pas de CORS).
// En production, VITE_API_BASE_URL est lue au build ; à défaut on garde l'API officielle pour ne pas casser le déploiement.
const API_PAR_DEFAUT = 'https://sopi-api.duckdns.org'
const BASE_URL = (import.meta.env.DEV ? '' : import.meta.env.VITE_API_BASE_URL || API_PAR_DEFAUT).replace(/\/+$/, '')

export type ErreursChamps = Record<string, string[]>

export class ApiError extends Error {
  status: number
  errors: ErreursChamps
  retryAfter: number | null

  constructor(message: string, status: number, errors: ErreursChamps = {}, retryAfter: number | null = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.retryAfter = retryAfter
  }
}

/** Réactions globales aux erreurs, branchées une fois par l'application (routeur, contexte d'authentification). */
interface Gestionnaires {
  surSessionExpiree: () => void
  surAccesRefuse: () => void
}

let gestionnaires: Gestionnaires = { surSessionExpiree: () => undefined, surAccesRefuse: () => undefined }

export function brancherGestionnaires(g: Gestionnaires): void {
  gestionnaires = g
}

interface Options {
  corps?: unknown
  params?: Record<string, string | number | undefined | null>
  /** Requête publique (connexion) : un 401 / 403 est une réponse métier et ne déclenche pas les réactions globales. */
  publique?: boolean
  signal?: AbortSignal
}

function construireUrl(chemin: string, params?: Options['params']): string {
  const url = `${BASE_URL}/api/v1${chemin}`
  if (!params) return url
  const requete = new URLSearchParams()
  for (const [cle, valeur] of Object.entries(params)) {
    if (valeur !== undefined && valeur !== null && valeur !== '') requete.set(cle, String(valeur))
  }
  const texte = requete.toString()
  return texte ? `${url}?${texte}` : url
}

async function lireCorps(reponse: Response): Promise<{ message?: string; errors?: ErreursChamps } & Record<string, unknown>> {
  try {
    return await reponse.json()
  } catch {
    return {}
  }
}

export async function requete<T>(methode: 'GET' | 'POST', chemin: string, options: Options = {}): Promise<T> {
  const entetes: Record<string, string> = { Accept: 'application/json' }
  const jeton = lireJeton()
  if (jeton && !options.publique) entetes.Authorization = `Bearer ${jeton}`
  if (options.corps !== undefined) entetes['Content-Type'] = 'application/json'

  let reponse: Response
  try {
    reponse = await fetch(construireUrl(chemin, options.params), {
      method: methode,
      headers: entetes,
      body: options.corps !== undefined ? JSON.stringify(options.corps) : undefined,
      signal: options.signal,
    })
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') throw e
    throw new ApiError(L.erreurs.reseau, 0)
  }

  if (reponse.ok) {
    return (await lireCorps(reponse)) as T
  }

  const corps = await lireCorps(reponse)
  const retryAfter = Number(reponse.headers.get('Retry-After')) || null
  const status = reponse.status

  if (status === 401 && !options.publique) {
    effacerJeton()
    gestionnaires.surSessionExpiree()
    throw new ApiError(L.connexion.sessionExpiree, 401)
  }
  if (status === 403 && !options.publique) {
    gestionnaires.surAccesRefuse()
    throw new ApiError(corps.message ?? L.erreurs.accesRefuseTexte, 403)
  }
  if (status === 422) {
    throw new ApiError(corps.message ?? L.erreurs.inconnue, 422, corps.errors ?? {})
  }
  if (status === 429) {
    const attente = retryAfter ? L.connexion.bloque(Math.ceil(retryAfter / 60)) : L.erreurs.attente
    throw new ApiError(corps.message ?? attente, 429, {}, retryAfter)
  }
  if (status >= 500) {
    throw new ApiError(L.erreurs.serveur, status)
  }
  throw new ApiError(corps.message ?? L.erreurs.inconnue, status, corps.errors ?? {})
}

/** Première erreur de chaque champ (422) : { telephone: "Le téléphone est obligatoire." }. */
export function erreursParChamp(erreur: unknown): Record<string, string> {
  if (!(erreur instanceof ApiError)) return {}
  return Object.fromEntries(Object.entries(erreur.errors).map(([champ, messages]) => [champ, messages[0]]))
}

export function messageDErreur(erreur: unknown): string {
  return erreur instanceof ApiError ? erreur.message : L.erreurs.inconnue
}
