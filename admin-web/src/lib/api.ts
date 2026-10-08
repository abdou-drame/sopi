// Client HTTP unique du back-office. Toute requête vers l'API passe par ici.
const BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, '')

export class ApiError extends Error {
  status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

export async function apiGet<T>(chemin: string): Promise<T> {
  let reponse: Response
  try {
    reponse = await fetch(`${BASE_URL}/api/v1${chemin}`, {
      headers: { Accept: 'application/json' },
    })
  } catch {
    throw new ApiError('Impossible de joindre le serveur.', 0)
  }

  if (!reponse.ok) {
    throw new ApiError(`Le serveur a répondu avec une erreur (${reponse.status}).`, reponse.status)
  }
  return (await reponse.json()) as T
}

export interface EtatApi {
  status: string
  app: string
  version: string
  database: 'ok' | 'erreur'
  time: string
}

export const getHealth = () => apiGet<EtatApi>('/health')
