// Stockage du jeton Sanctum : sessionStorage (propre à l'onglet, effacé à la fermeture), jamais localStorage.
// Chaque accès est protégé : le stockage peut être indisponible (navigation privée, données bloquées).
const CLE = 'sopi.admin.jeton'

export function lireJeton(): string | null {
  try {
    return sessionStorage.getItem(CLE)
  } catch {
    return null
  }
}

export function ecrireJeton(jeton: string): void {
  try {
    sessionStorage.setItem(CLE, jeton)
  } catch {
    // Sans stockage, la session ne survivra pas à un rechargement : rien d'autre à faire.
  }
}

export function effacerJeton(): void {
  try {
    sessionStorage.removeItem(CLE)
  } catch {
    // Rien à effacer.
  }
}
