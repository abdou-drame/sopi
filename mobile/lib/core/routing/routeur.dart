import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/accueil/accueil_page.dart';
import '../../features/auth/compte_restreint_page.dart';
import '../../features/auth/connexion_page.dart';
import '../../features/auth/inscription_page.dart';
import '../../features/auth/mot_de_passe_oublie_page.dart';
import '../../features/auth/session.dart';
import '../../features/compte/aide_page.dart';
import '../../features/compte/changer_mot_de_passe_page.dart';
import '../../features/compte/litige_page.dart';
import '../../features/compte/modifier_profil_page.dart';
import '../../features/compte/notifications_page.dart';
import '../../features/compte/profil_client_page.dart';
import '../../features/compte/signalements_pages.dart';
import '../../features/inscription_prestataire/etape_activite_page.dart';
import '../../features/inscription_prestataire/etape_informations_page.dart';
import '../../features/inscription_prestataire/etape_justificatifs_page.dart';
import '../../features/inscription_prestataire/stockage_jeton_suivi.dart';
import '../../features/inscription_prestataire/suivi_dossier_page.dart';
import '../../features/reservation/accueil_client_page.dart';
import '../../features/reservation/avis_page.dart';
import '../../features/reservation/avis_prestataire_page.dart';
import '../../features/reservation/choix_creneau_page.dart';
import '../../features/reservation/demande_envoyee_page.dart';
import '../../features/reservation/detail_reservation_page.dart';
import '../../features/reservation/paiement_page.dart';
import '../../features/reservation/fiche_prestataire_page.dart';
import '../../features/reservation/modeles.dart';
import '../../features/reservation/liste_prestataires_page.dart';
import '../../features/reservation/mes_reservations_page.dart';
import '../../features/auth/utilisateur.dart';
import '../../features/prestataire/agenda_page.dart';
import '../../features/prestataire/aiguillage_reservation.dart';
import '../../features/prestataire/refus_motif_page.dart';
import '../../features/prestataire/reservations_prestataire_page.dart';
import '../../features/prestataire/tableau_page.dart';
import 'barre_onglets.dart';
import 'chemins.dart';
import 'ecrans_provisoires.dart';

/// Redirection selon la session. Renvoie null si l'utilisateur peut rester sur [chemin].
/// [jetonSuivi] : jeton d'un dossier prestataire déposé sur ce téléphone ; il permet d'envoyer un compte
/// `en_attente` ou `refuse` vers le suivi du dossier plutôt que vers la page de compte restreint.
String? redirectionSopi(
  EtatSession session,
  String chemin, {
  String? jetonSuivi,
}) {
  switch (session) {
    case SessionInconnue():
      return chemin == Chemins.demarrage ? null : Chemins.demarrage;
    case Restreint(:final statutCompte):
      final versSuivi =
          jetonSuivi != null &&
          (statutCompte == 'en_attente' || statutCompte == 'refuse');
      final cible = versSuivi ? Chemins.suiviDossier : Chemins.compteRestreint;
      return chemin == cible ? null : cible;
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
      if (chemin == Chemins.etatApi || chemin == Chemins.notifications) {
        // Les notifications sont communes aux deux espaces.
        return null;
      }
      final espacePrestataire =
          chemin == Chemins.racinePrestataire ||
          chemin.startsWith('${Chemins.racinePrestataire}/');
      return espacePrestataire == estPrestataire ? null : accueil;
  }
}

const _ongletsClient = [
  OngletSopi(libelle: 'Accueil', icone: Icons.home_rounded),
  OngletSopi(libelle: 'Rechercher', icone: Icons.search_rounded),
  OngletSopi(libelle: 'Réservations', icone: Icons.event_note_rounded),
  OngletSopi(libelle: 'Profil', icone: Icons.person_rounded),
];

const _ongletsPrestataire = [
  OngletSopi(libelle: 'Accueil', icone: Icons.home_rounded),
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
  String? Function()? jetonSuivi,
  required Listenable rafraichir,
  String? cheminInitial,
}) {
  return GoRouter(
    initialLocation: cheminInitial ?? Chemins.demarrage,
    refreshListenable: rafraichir,
    redirect: (_, etat) => redirectionSopi(
      session(),
      etat.uri.path,
      jetonSuivi: jetonSuivi?.call(),
    ),
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
      GoRoute(
        path: Chemins.inscriptionPrestataire,
        builder: (_, _) => const EtapeActivitePage(),
      ),
      GoRoute(
        path: Chemins.inscriptionInformations,
        builder: (_, _) => const EtapeInformationsPage(),
      ),
      GoRoute(
        path: Chemins.inscriptionJustificatifs,
        builder: (_, _) => const EtapeJustificatifsPage(),
      ),
      GoRoute(
        path: Chemins.suiviDossier,
        builder: (_, _) => const SuiviDossierPage(),
      ),
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
            Chemins.rechercher,
            () => const ListePrestatairesPage(avecRetour: false),
          ),
          _onglet(Chemins.reservations, () => const MesReservationsPage()),
          _onglet(Chemins.profil, () => const ProfilClientPage()),
        ],
      ),

      // — Espace client : parcours plein écran
      GoRoute(
        path: Chemins.notifications,
        builder: (context, state) => const NotificationsPage(),
      ),
      GoRoute(
        path: Chemins.prestataires,
        builder: (context, state) => ListePrestatairesPage(
          metier: state.uri.queryParameters['metier'] ?? '',
        ),
      ),
      GoRoute(
        path: '${Chemins.prestataires}/:id',
        builder: (context, state) =>
            FichePrestatairePage(prestataireId: state.pathParameters['id']!),
        routes: [
          GoRoute(
            path: 'avis',
            builder: (context, state) =>
                AvisPrestatairePage(prestataireId: state.pathParameters['id']!),
          ),
        ],
      ),
      GoRoute(
        path: '${Chemins.prestataires}/:id/creneau',
        builder: (context, state) => ChoixCreneauPage(
          prestataireId: state.pathParameters['id']!,
          prestationId: state.uri.queryParameters['prestation'] ?? '',
        ),
      ),
      GoRoute(
        path: '${Chemins.reservations}/:id/envoyee',
        // Sans la réponse de la création (lien ouvert directement), il n'y a rien à afficher.
        redirect: (_, state) =>
            state.extra is ReservationCreee ? null : Chemins.accueil,
        builder: (context, state) =>
            DemandeEnvoyeePage(reservation: state.extra as ReservationCreee),
      ),
      GoRoute(
        path: '${Chemins.reservations}/:id',
        builder: (context, state) =>
            DetailReservationPage(reservationId: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '${Chemins.reservations}/:id/paiement',
        builder: (context, state) =>
            PaiementPage(reservationId: state.pathParameters['id']!),
      ),
      _provisoire(
        '${Chemins.reservations}/:id/annulation',
        'Annulation',
        parametres: ['id'],
      ),
      GoRoute(
        path: '${Chemins.reservations}/:id/avis',
        builder: (context, state) =>
            AvisPage(reservationId: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '${Chemins.reservations}/:id/litige',
        builder: (context, state) =>
            LitigePage(reservationId: state.pathParameters['id']!),
      ),
      GoRoute(
        path: Chemins.signalements,
        builder: (context, state) => const MesSignalementsPage(),
      ),
      GoRoute(
        path: '/signalements/:id',
        builder: (context, state) =>
            DetailSignalementPage(litigeId: state.pathParameters['id']!),
      ),
      _provisoire(Chemins.retourPaiement, 'Retour de paiement'),
      GoRoute(
        path: Chemins.profilModifier,
        builder: (context, state) => const ModifierProfilPage(),
      ),
      GoRoute(
        path: Chemins.motDePasseChanger,
        builder: (context, state) => const ChangerMotDePassePage(),
      ),
      GoRoute(
        path: Chemins.aide,
        builder: (context, state) => const AidePage(),
      ),

      // — Espace prestataire : coque à 4 onglets
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => _coque(_ongletsPrestataire, shell),
        branches: [
          _onglet(Chemins.tableau, () => const TableauPage()),
          _onglet(Chemins.agenda, () => const AgendaPage()),
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
      GoRoute(
        path: Chemins.reservationsPrestataire,
        builder: (_, _) => const ReservationsPrestatairePage(),
      ),
      GoRoute(
        path: '${Chemins.racinePrestataire}/reservations/:id',
        builder: (_, state) =>
            AiguillageReservationPrestataire(id: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '${Chemins.racinePrestataire}/reservations/:id/refus',
        builder: (_, state) => RefusMotifPage(id: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '${Chemins.racinePrestataire}/reservations/:id/annulation',
        builder: (_, state) =>
            RefusMotifPage(id: state.pathParameters['id']!, annulation: true),
      ),
    ],
  );
}

/// Routeur de l'application : se réévalue à chaque changement de session.
final routeurProvider = Provider<GoRouter>((ref) {
  final changement = ValueNotifier<int>(0);
  ref.listen(sessionProvider, (_, _) => changement.value++);
  ref.listen(jetonSuiviProvider, (_, _) => changement.value++);
  final routeur = creerRouteur(
    session: () => ref.read(sessionProvider),
    jetonSuivi: () => ref.read(jetonSuiviProvider),
    rafraichir: changement,
  );
  ref.onDispose(() {
    routeur.dispose();
    changement.dispose();
  });
  return routeur;
});
