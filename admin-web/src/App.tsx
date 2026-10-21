import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useState } from 'react'
import { BrowserRouter } from 'react-router-dom'
import { AlertesProvider } from './components/ui/Alertes'
import { AuthProvider } from './auth/AuthContext'
import EchelleEcran from './lib/echelle'
import Routage from './Routage'
import { ThemeProvider } from './theme/ThemeContext'

export function creerClientRequetes() {
  return new QueryClient({ defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } } })
}

export default function App() {
  const [client] = useState(creerClientRequetes)
  return (
    <ThemeProvider>
      <QueryClientProvider client={client}>
        <BrowserRouter>
          <EchelleEcran />
          <AlertesProvider>
<AuthProvider>
            <Routage />
          </AuthProvider>
</AlertesProvider>
        </BrowserRouter>
      </QueryClientProvider>
    </ThemeProvider>
  )
}
