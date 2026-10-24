/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// En développement, le serveur Vite relaie /api vers l'API distante : le navigateur ne voit qu'une seule origine,
// donc aucun problème de CORS (le backend n'est pas modifié). VITE_API_BASE_URL doit alors rester vide.
const CIBLE_API = process.env.VITE_DEV_API_TARGET ?? 'https://sopi-api.duckdns.org'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/api': { target: CIBLE_API, changeOrigin: true, secure: true },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    exclude: ['e2e/**', 'node_modules/**', 'dist/**'],
    setupFiles: ['./src/test/setup.ts'],
    // Poste chargé (processeur partagé avec d'autres programmes) : chaque fichier de tests lance son propre processus et son propre
    // jsdom, ce qui prend parfois plus de 10 s, et le premier rendu de l'application plus de 5 s. Les délais par défaut de Vitest
    // (5 s par test, 10 s pour fermer un processus) donnaient alors de faux échecs (« Test timed out », « Timeout terminating forks worker »).
    testTimeout: 30_000,
    hookTimeout: 30_000,
    teardownTimeout: 60_000,
    // Moitié des cœurs au plus : plus il y a de processus en même temps sur un petit processeur, plus chacun démarre lentement
    // (et le délai de démarrage de 90 s de Vitest, « Timeout starting forks runner », n'est pas réglable).
    maxWorkers: '50%',
  },
})
