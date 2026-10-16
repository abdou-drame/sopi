import { defineConfig } from '@playwright/test'

// Parcours de bout en bout du back-office contre une API SIMULÉE (voir e2e/apiSimulee.ts) : le serveur de développement
// est volontairement relayé vers une adresse locale morte, de sorte qu'aucune requête ne puisse atteindre la production.
const PORT = 5199

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  reporter: 'list',
  use: { baseURL: `http://localhost:${PORT}`, locale: 'fr-FR', viewport: { width: 1366, height: 768 } },
  webServer: {
    command: `npm run dev -- --port ${PORT} --strictPort`,
    url: `http://localhost:${PORT}`,
    reuseExistingServer: false,
    env: { VITE_DEV_API_TARGET: 'http://127.0.0.1:9', VITE_API_BASE_URL: '' },
    timeout: 120_000,
  },
})
