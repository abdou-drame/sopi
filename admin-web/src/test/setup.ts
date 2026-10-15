import '@testing-library/jest-dom/vitest'
import { configure } from '@testing-library/react'

// Le premier rendu d'un fichier de tests charge toute l'application : sur machine chargée, 1 s ne suffit pas toujours.
configure({ asyncUtilTimeout: 5000 })
