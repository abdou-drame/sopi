# Maquettes du back-office Sopi (référence visuelle)

- images/*.png : capture de chaque écran à 1440 px de large. C'est LA référence à reproduire.
- html/*.html : le même écran en HTML statique, ouvrable dans n'importe quel navigateur. Le code (tailles, couleurs, rayons, espacements) sert de mesure exacte.
- Guide.png / Guide.html : couleurs, typographie, boutons, badges, champs, alertes, confirmation.
- Main = connexion : NE PAS la modifier (garder la page actuelle).

Valeurs, noms, dates, numéros, compteurs et variations en % sont des EXEMPLES. Les statuts réels viennent du backend.
Si une donnée n'existe pas dans l'API, on ne l'invente pas : on retire seulement cet élément, le reste de la mise en page reste identique.

## Écrans ajoutés (v3)
Utilisateur, NouvelleSanction, Trancher, MetierFormulaires, Revenus, Reservations, Paiements, Retraits, NotificationsEchec, Plus (menu), BannieresDossier, AlertesTableau, Etats.
Pour ces écrans, les COLONNES, CHAMPS et LIBELLÉS exacts sont ceux déjà présents dans l'application : la maquette fixe le style et la mise en page, pas le contenu. Un champ ou une colonne de la maquette qui n'existe pas dans l'application est retiré ; un champ existant absent de la maquette est conservé (même style).
Le menu de navigation a un nouvel item « Plus » (liste déroulante : Revenus, Réservations, Paiements, Retraits, Notifications en échec).
