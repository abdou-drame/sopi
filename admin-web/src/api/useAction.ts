import { useMutation, useQueryClient } from '@tanstack/react-query'

/**
 * Action d'écriture (accepter, refuser, trancher...). Après un succès, toutes les requêtes actives sont rechargées :
 * la liste, le détail et les compteurs d'alertes de la barre latérale restent à jour.
 */
export function useAction<V, R>(action: (variables: V) => Promise<R>) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: action,
    onSuccess: () => client.invalidateQueries(),
  })
}
