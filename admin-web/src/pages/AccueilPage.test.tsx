import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AccueilPage from './AccueilPage'

function afficher() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <AccueilPage />
    </QueryClientProvider>,
  )
}

function simulerReponse(corps: unknown, status = 200) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(corps), { status })))
}

describe('AccueilPage', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('affiche un message de chargement', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})))
    afficher()
    expect(screen.getByText(/Chargement de l'état de l'API/)).toBeInTheDocument()
  })

  it("affiche l'état de l'API et de la base quand tout est ok", async () => {
    simulerReponse({
      status: 'ok',
      app: 'Sopi',
      version: '0.1.0',
      database: 'ok',
      time: '2026-10-08T10:00:00+00:00',
    })
    afficher()
    expect(await screen.findByText('Base de données')).toBeInTheDocument()
    expect(screen.getAllByText('OK')).toHaveLength(2)
    expect(screen.getByRole('contentinfo')).toHaveTextContent('Version 0.1.0')
  })

  it("signale une erreur quand l'API répond 503", async () => {
    simulerReponse({ status: 'ok', app: 'Sopi', version: '0.1.0', database: 'erreur', time: 'x' }, 503)
    afficher()
    expect(await screen.findByRole('alert')).toHaveTextContent(/indisponible/i)
    expect(screen.getByRole('contentinfo')).toHaveTextContent('Version indisponible')
  })

  it('affiche une erreur claire quand le serveur est injoignable', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('network')))
    afficher()
    const alerte = await screen.findByRole('alert')
    expect(alerte).toHaveTextContent("L'API est indisponible")
    expect(alerte).toHaveTextContent('Impossible de joindre le serveur')
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument()
  })
})
