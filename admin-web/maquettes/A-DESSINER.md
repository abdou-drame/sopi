# Éléments à dessiner

Éléments qui existent dans l'API et dans le code mais que les maquettes ne dessinent pas.
Ils sont fonctionnels (modale, appel, tests) mais **retirés de la page** tant que la maquette n'existe pas.

## Avis (carte d'un avis)

- **Conserver l'avis** : visible sur un avis signalé non encore modéré. Ouvre une modale avec motif facultatif
  (`POST /admin/avis/{id}/conserver`).
- **Rétablir l'avis** : visible sur un avis masqué. Ouvre une modale sans motif (`POST /admin/avis/{id}/retablir`).

Code : `ActionModeration` (modes `conserver` et `retablir`) dans `src/pages/AvisPages.tsx`, testé isolément dans `src/pages/avis.test.tsx`.
Pour les remettre, il suffit de les afficher dans `CarteAvis` une fois dessinés (en reprenant les conditions d'origine :
`signaleLe && !modereLe` pour Conserver, `masque` pour Rétablir à la place de Masquer).
