import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/accueil/accueil_page.dart';
import '../../features/auth/compte_restreint_page.dart';
import '../../features/auth/connexion_page.dart';
import '../../features/auth/inscription_page.dart';
import '../../features/auth/mot_de_passe_oublie_page.dart';
import '../../features/auth/session.dart';
import '../../features/reservation/accueil_client_page.dart';
import '../../features/reservation/liste_prestataires_page.dart';
import '../../features/auth/utilisateur.dart';
import 'barre_onglets.dart';
import 'chemins.dart';
import 'ecrans_provisoires.dart';

/// Redirection selon la session. Renvoie null si l'utilisateur peut rester sur [chemin].
String? redirectionSopi(EtatSession session, String chemin) {
  switch (session) {
    case SessionInconnue():
      return chemin == Chemins.demarrage ? null : Chemins.demarrage;
    case Restreint():
      return chemin == Chemins.compteRestreint ? null : Chemins.compteRestreint;
    case NonConnecte():
      if (Chemins.publiques.contains(chemin)) {
        // Le démarrage n'est qu'un passage : une fois la session connue, on va à la connexion.
        return chemin == Chemins.demarrage ? Chemins.connexion : null;
      }
      return Chemins.connexion;
    case Connecte(:final utilisateur):
      final estPrestataire = utilisateur.role == RoleSopi.prestataire;
      final accueil = estPrestataire ? Chemins.tableau : Chemins.accueil;
      if (chemin == '/' || Chemins.authentification.contains(chemin)) {
        return accueil;
      }
      if (chemin == Chemins.etatApi) return null;
      final espacePrestataire =
          chemin == Chemins.racinePrestataire ||
          chemin.startsWith('${Chemins.racinePrestataire}/');
      return espacePrestataire == estPrestataire ? null : accueil;
  }
}

const _ongletsClient = [
  OngletSopi(libelle: 'Accueil', icone: Icons.home_rounded),
  OngletSopi(libelle: 'Réservations', icone: Icons.event_note_rounded),
  OngletSopi(libelle: 'Notifications', icone: Icons.notifications_rounded),
  OngletSopi(libelle: 'Profil', icone: Icons.person_rounded),
];

const _ongletsPrestataire = [
  OngletSopi(libelle: 'Tableau', icone: Icons.dashboard_rounded),
  OngletSopi(libelle: 'Agenda', icone: Icons.calendar_month_rounded),
  OngletSopi(
    libelle: 'Portefeuille',
    icone: Icons.account_balance_wallet_rounded,
  ),
  OngletSopi(libelle: 'Profil', icone: Icons.person_rounded),
];

/// Coque commune aux deux espaces : contenu de l'onglet + barre flottante.
Widget _coque(List<OngletSopi> onglets, StatefulNavigationShell shell) {
  return Scaffold(
    extendBody: true,
    body: shell,
    bottomNavigationBar: BarreOnglets(
      onglets: onglets,
      indexActif: shell.currentIndex,
      onSelection: (i) =>
          shell.goBranch(i, initialLocation: i == shell.currentIndex),
    ),
  );
}

StatefulShellBranch _onglet(String chemin, Widget Function() ecran) =>
    StatefulShellBranch(
      routes: [GoRoute(path: chemin, builder: (_, _) => ecran())],
    );

/// Route d'un écran futur, branchée sur un écran provisoire.
GoRoute _provisoire(
  String chemin,
  String nom, {
  List<String> parametres = const [],
}) => GoRoute(
  path: chemin,
  builder: (_, etat) => EcranProvisoire(
    nom: nom,
    parametres: {for (final p in parametres) p: etat.pathParameters[p] ?? ''},
  ),
);

GoRouter creerRouteur({
  required EtatSession Function() session,
  required Listenable rafraichir,
  String? cheminInitial,
}) {
  return GoRouter(
    initialLocation: cheminInitial ?? Chemins.demarrage,
    refreshListenable: rafraichir,
    redirect: (_, etat) => redirectionSopi(session(), etat.uri.path),
    routes: [
      GoRoute(
        path: '/',
        redirect: (_, _) => null,
        builder: (_, _) => const EcranProvisoire(nom: 'Sopi'),
      ),
      GoRoute(
        path: Chemins.demarrage,
        builder: (_, _) =>
            const Scaffold(body: Center(child: CircularProgressIndicator())),
      ),
      GoRoute(path: Chemins.etatApi, builder: (_, _) => const AccueilPage()),

      // — Authentification
      GoRoute(
        path: Chemins.connexion,
        builder: (_, _) => const ConnexionPage(),
      ),
      GoRoute(
        path: Chemins.inscription,
        builder: (_, _) => const InscriptionPage(),
      ),
      GoRoute(
        path: Chemins.motDePasseOublie,
        builder: (_, _) => const MotDePasseOubliePage(),
      ),
      _provisoire(Chemins.reinitialisation, 'Réinitialisation du mot de passe'),
      _provisoire(Chemins.inscriptionPrestataire, 'Inscription prestataire'),
      _provisoire(Chemins.suiviDossier, 'Suivi du dossier'),
      GoRoute(
        path: Chemins.compteRestreint,
        builder: (_, _) => const CompteRestreintPage(),
      ),

      // — Espace client : coque à 4 onglets
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => _coque(_ongletsClient, shell),
        branches: [
          _onglet(Chemins.accueil, () => const AccueilClientPage()),
          _onglet(
            Chemins.reservations,
            () => const EcranProvisoire(nom: 'Réservations'),
          ),
          _onglet(
            Chemins.notifications,
            () => const EcranProvisoire(nom: 'Notifications'),
          ),
          _onglet(Chemins.profil, () => const ProfilProvisoire()),
        ],
      ),

      // — Espace client : parcours plein écran
      GoRoute(
        path: Chemins.prestataires,
        builder: (context, state) => ListePrestatairesPage(
          metier: state.uri.queryParameters['metier'] ?? '',
        ),
      ),
      _provisoire(
        '${Chemins.prestataires}/:id',
        'Fiche prestataire',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.prestataires}/:id/creneau',
        'Choix du créneau',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.reservations}/:id',
        'Détail de la réservation',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.reservations}/:id/paiement',
        'Paiement',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.reservations}/:id/annulation',
        'Annulation',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.reservations}/:id/avis',
        'Avis',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.reservations}/:id/litige',
        'Litige',
        parametres: ['id'],
      ),
      _provisoire(Chemins.retourPaiement, 'Retour de paiement'),
      _provisoire(Chemins.profilModifier, 'Modifier le profil'),
      _provisoire(Chemins.motDePasseChanger, 'Changer le mot de passe'),

      // — Espace prestataire : coque à 4 onglets
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => _coque(_ongletsPrestataire, shell),
        branches: [
          _onglet(Chemins.tableau, () => const EcranProvisoire(nom: 'Tableau')),
          _onglet(Chemins.agenda, () => const EcranProvisoire(nom: 'Agenda')),
          _onglet(
            Chemins.portefeuille,
            () => const EcranProvisoire(nom: 'Portefeuille'),
          ),
          _onglet(Chemins.profilPrestataire, () => const ProfilProvisoire()),
        ],
      ),

      // — Espace prestataire : écrans plein écran
      _provisoire(Chemins.services, 'Mes services'),
      _provisoire(Chemins.disponibilites, 'Disponibilités'),
      _provisoire(Chemins.retrait, 'Retrait'),
      _provisoire(Chemins.historiqueRetraits, 'Historique des retraits'),
      _provisoire(
        '${Chemins.racinePrestataire}/demandes/:id',
        'Demande de réservation',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.racinePrestataire}/reservations/:id',
        'Réservation',
        parametres: ['id'],
      ),
      _provisoire(
        '${Chemins.racinePrestataire}/reservations/:id/demarrer',
        'Démarrer la prestation',
        parametres: ['id'],
      ),
    ],
  );
}

/// Routeur de l'application : se réévalue à chaque changement de session.
final routeurProvider = Provider<GoRouter>((ref) {
  final changement = ValueNotifier<int>(0);
  ref.listen(sessionProvider, (_, _) => changement.value++);
  final routeur = creerRouteur(
    session: () => ref.read(sessionProvider),
    rafraichir: changement,
  );
  ref.onDispose(() {
    routeur.dispose();
    changement.dispose();
  });
  return routeur;
});
