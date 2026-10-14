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
    setupFiles: ['./src/test/setup.ts'],
  },
})
