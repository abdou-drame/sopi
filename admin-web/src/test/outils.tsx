import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { vi } from 'vitest'
import { creerClientRequetes } from '../App'
import { AlertesProvider } from '../components/ui/Alertes'
import { AuthProvider } from '../auth/AuthContext'
import Routage from '../Routage'
import { ThemeProvider } from '../theme/ThemeContext'

export interface ReponseSimulee {
  status?: number
  corps?: unknown
  entetes?: Record<string, string>
  /** Fichier binaire (justificatif, preuve) : renvoyé tel quel à la place du JSON. */
  binaire?: { type: string; contenu?: string }
}
type Gestionnaire = ReponseSimulee | ((requete: { corps: unknown; entetes: Headers; url: URL }) => ReponseSimulee)

export interface AppelApi {
  methode: string
  chemin: string
  corps: unknown
  entetes: Headers
  /** Paramètres de requête (filtres, page). */
  recherche: URLSearchParams
}

/**
 * Remplace fetch par une API simulée. Les clés sont « METHODE /chemin » (sans /api/v1, sans paramètres de requête).
 * Une route non prévue répond 404 et fait échouer le test qui la déclenche par erreur.
 */
export function simulerApi(routes: Record<string, Gestionnaire>) {
  const appels: AppelApi[] = []
  const fetchSimule = vi.fn(async (entree: RequestInfo | URL, init?: RequestInit) => {
    const url = new URL(String(entree), 'http://localhost')
    const methode = (init?.method ?? 'GET').toUpperCase()
    const chemin = url.pathname.replace(/^\/api\/v1/, '')
    const entetes = new Headers(init?.headers)
    const corps = init?.body ? JSON.parse(String(init.body)) : undefined
    appels.push({ methode, chemin, corps, entetes, recherche: url.searchParams })
    const gestionnaire = routes[`${methode} ${chemin}`]
    if (!gestionnaire) return new Response(JSON.stringify({ message: `Route non simulée : ${methode} ${chemin}` }), { status: 404 })
    const r = typeof gestionnaire === 'function' ? gestionnaire({ corps, entetes, url }) : gestionnaire
    if (r.binaire) {
      return new Response(new Blob([r.binaire.contenu ?? 'x'], { type: r.binaire.type }), {
        status: r.status ?? 200,
        headers: { 'Content-Type': r.binaire.type },
      })
    }
    return new Response(JSON.stringify(r.corps ?? {}), {
      status: r.status ?? 200,
      headers: { 'Content-Type': 'application/json', ...r.entetes },
    })
  })
  vi.stubGlobal('fetch', fetchSimule)
  return { appels, fetchSimule }
}

function AfficheLieu() {
  const lieu = useLocation()
  return <p data-testid="lieu">{lieu.pathname + lieu.search}</p>
}

/** Rend l'application complète (routes, authentification, requêtes) à une adresse donnée. */
export function afficherApplication(adresse = '/') {
  const client = creerClientRequetes()
  return render(
    <ThemeProvider>
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[adresse]}>
        <AlertesProvider>
<AuthProvider>
          <Routage />
          <AfficheLieu />
        </AuthProvider>
</AlertesProvider>
      </MemoryRouter>
    </QueryClientProvider>
    </ThemeProvider>,
  )
}

export const ADMIN = {
  id: '0198a1b2-0000-7000-8000-000000000001',
  nom: 'Diop',
  prenom: 'Fatou',
  telephone: '+221700000001',
  email: null,
  role: 'administrateur' as const,
  statut: 'actif',
  created_at: '2030-01-01T00:00:00+00:00',
}

export const ALERTES_VIDES = { dateGeneration: '2030-01-20T10:00:00+00:00', total: 0, parGravite: { critique: 0, attention: 0, info: 0 }, alertes: [] }
