import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useState } from 'react'
import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import Routage from './Routage'

export function creerClientRequetes() {
  return new QueryClient({ defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } } })
}

export default function App() {
  const [client] = useState(creerClientRequetes)
  return (
    <QueryClientProvider client={client}>
      <BrowserRouter>
        <AuthProvider>
          <Routage />
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
