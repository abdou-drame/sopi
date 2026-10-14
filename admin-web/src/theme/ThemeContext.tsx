import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

export type Theme = 'clair' | 'sombre'

const CLE = 'sopi.admin.theme'

/** Lecture du choix mémorisé ; le stockage peut être indisponible (navigation privée, blocage) : on ignore alors l'erreur. */
function lireChoix(): Theme | null {
  try {
    const v = window.localStorage.getItem(CLE)
    return v === 'clair' || v === 'sombre' ? v : null
  } catch {
    return null
  }
}

function themeDuSysteme(): Theme {
  try {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'sombre' : 'clair'
  } catch {
    return 'clair'
  }
}

interface Valeur {
  theme: Theme
  basculer: () => void
}

const ContexteTheme = createContext<Valeur | null>(null)

/** Thème clair/sombre : choix mémorisé dans le navigateur, sinon réglage du système (suivi tant qu'aucun choix n'est fait). */
export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>(() => lireChoix() ?? themeDuSysteme())

  useEffect(() => {
    document.documentElement.dataset.theme = theme === 'sombre' ? 'dark' : 'light'
  }, [theme])

  useEffect(() => {
    if (lireChoix() || typeof window.matchMedia !== 'function') return
    const requete = window.matchMedia('(prefers-color-scheme: dark)')
    const suivre = (e: MediaQueryListEvent) => {
      if (!lireChoix()) setTheme(e.matches ? 'sombre' : 'clair')
    }
    requete.addEventListener('change', suivre)
    return () => requete.removeEventListener('change', suivre)
  }, [])

  const basculer = useCallback(() => {
    setTheme((actuel) => {
      const suivant: Theme = actuel === 'sombre' ? 'clair' : 'sombre'
      try {
        window.localStorage.setItem(CLE, suivant)
      } catch {
        /* stockage indisponible : le choix vaut pour la session seulement */
      }
      return suivant
    })
  }, [])

  const valeur = useMemo(() => ({ theme, basculer }), [theme, basculer])
  return <ContexteTheme.Provider value={valeur}>{children}</ContexteTheme.Provider>
}

export function useTheme(): Valeur {
  const v = useContext(ContexteTheme)
  if (!v) throw new Error('useTheme doit être utilisé dans un ThemeProvider')
  return v
}
