import { useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/endpoints'
import { ApiError, brancherGestionnaires } from '../api/client'
import type { Utilisateur } from '../api/types'
import { L } from '../i18n/libelles'
import { ecrireJeton, effacerJeton, lireJeton } from './jeton'

interface Contexte {
  utilisateur: Utilisateur | null
  /** Vrai tant qu'on vérifie un jeton déjà présent au chargement de la page. */
  initialisation: boolean
  /** Message à afficher sur l'écran de connexion (ex. session expirée). */
  avis: string | null
  connexion: (telephone: string, motDePasse: string) => Promise<void>
  deconnexion: () => Promise<void>
}

const AuthContext = createContext<Contexte | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [utilisateur, setUtilisateur] = useState<Utilisateur | null>(null)
  const [initialisation, setInitialisation] = useState(() => lireJeton() !== null)
  const [avis, setAvis] = useState<string | null>(null)

  // Réactions globales de l'API : 401 → retour à la connexion, 403 → page « accès refusé ».
  useEffect(() => {
    brancherGestionnaires({
      surSessionExpiree: () => {
        setUtilisateur(null)
        setAvis(L.connexion.sessionExpiree)
        queryClient.clear()
        navigate('/connexion', { replace: true })
      },
      surAccesRefuse: () => navigate('/acces-refuse', { replace: true }),
    })
  }, [navigate, queryClient])

  // Au chargement : un jeton présent est vérifié auprès de l'API (et refusé s'il n'appartient pas à un administrateur).
  useEffect(() => {
    if (lireJeton() === null) return
    let annule = false
    api
      .moi()
      .then((u) => {
        if (annule) return
        if (u.role === 'administrateur') setUtilisateur(u)
        else effacerJeton()
      })
      .catch(() => undefined) // 401 déjà géré par le client ; une panne réseau laisse simplement l'écran de connexion.
      .finally(() => !annule && setInitialisation(false))
    return () => {
      annule = true
    }
  }, [])

  const connexion = useCallback(async (telephone: string, motDePasse: string) => {
    const reponse = await api.connexion(telephone, motDePasse)
    if (reponse.user.role !== 'administrateur') {
      // Compte non administrateur : on révoque aussitôt le jeton émis et on refuse l'accès.
      ecrireJeton(reponse.token)
      await api.deconnexion().catch(() => undefined)
      effacerJeton()
      throw new ApiError(L.connexion.reserve, 403)
    }
    ecrireJeton(reponse.token)
    setAvis(null)
    setUtilisateur(reponse.user)
  }, [])

  const deconnexion = useCallback(async () => {
    await api.deconnexion().catch(() => undefined)
    effacerJeton()
    setUtilisateur(null)
    setAvis(null)
    queryClient.clear()
    navigate('/connexion', { replace: true })
  }, [navigate, queryClient])

  const valeur = useMemo(
    () => ({ utilisateur, initialisation, avis, connexion, deconnexion }),
    [utilisateur, initialisation, avis, connexion, deconnexion],
  )
  return <AuthContext.Provider value={valeur}>{children}</AuthContext.Provider>
}

export function useAuth(): Contexte {
  const contexte = useContext(AuthContext)
  if (!contexte) throw new Error('useAuth doit être utilisé dans un AuthProvider.')
  return contexte
}
