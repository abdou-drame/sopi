import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { lazy, Suspense } from 'react'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import AccueilPage from './pages/AccueilPage'

const queryClient = new QueryClient()

// Page de démonstration du système de design : chargée uniquement en développement (le code est retiré du build de production).
const DemoPage = import.meta.env.DEV ? lazy(() => import('./pages/DemoPage')) : null

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Suspense fallback={null}>
          <Routes>
            {DemoPage && <Route path="/dev/design-system" element={<DemoPage />} />}
            <Route path="*" element={<AccueilPage />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
