import { useQuery } from '@tanstack/react-query'
import { getHealth } from '../lib/api'

function Pastille({ ok }: { ok: boolean }) {
  return (
    <span
      className={`inline-block rounded-full px-3 py-1 text-sm font-medium ${
        ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
      }`}
    >
      {ok ? 'OK' : 'Erreur'}
    </span>
  )
}

export default function AccueilPage() {
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['health'],
    queryFn: getHealth,
    retry: false,
  })

  return (
    <main className="mx-auto max-w-xl p-6">
      <h1 className="text-2xl font-bold">Sopi — Back-office</h1>

      <section className="mt-6 rounded-lg border border-gray-200 p-4" aria-live="polite">
        <h2 className="mb-3 text-lg font-semibold">État de l'API</h2>

        {isPending && <p>Chargement de l'état de l'API…</p>}

        {isError && (
          <div role="alert" className="text-red-700">
            <p>L'API est indisponible. {error.message}</p>
            <button
              type="button"
              onClick={() => refetch()}
              className="mt-3 rounded bg-gray-900 px-3 py-1 text-white"
            >
              Réessayer
            </button>
          </div>
        )}

        {data && (
          <dl className="space-y-2">
            <div className="flex items-center justify-between">
              <dt>API</dt>
              <dd>
                <Pastille ok={data.status === 'ok'} />
              </dd>
            </div>
            <div className="flex items-center justify-between">
              <dt>Base de données</dt>
              <dd>
                <Pastille ok={data.database === 'ok'} />
              </dd>
            </div>
            <div className="flex items-center justify-between text-sm text-gray-600">
              <dt>Version</dt>
              <dd>{data.version}</dd>
            </div>
          </dl>
        )}
      </section>
    </main>
  )
}
