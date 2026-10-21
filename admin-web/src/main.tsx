import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import { appliquerEchelle, calculerEchelle } from './lib/echelle'
import './index.css'

// Échelle posée avant le premier rendu (pas de flash à 100 %) ; la connexion reste à 100 %.
appliquerEchelle(window.location.pathname === '/connexion' ? 1 : calculerEchelle(window.innerWidth))

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
