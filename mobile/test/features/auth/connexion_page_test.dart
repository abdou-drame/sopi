import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/widgets/champ_formulaire.dart';

import '../../aide_api.dart';
import 'aide_acces.dart';

const _utilisateurClient = {
  'id': '1',
  'role': 'client',
  'nom': 'Diallo',
  'prenom': 'Aminata',
};

Future<void> _remplir(WidgetTester tester) async {
  await saisir(tester, 0, 'aminata@exemple.sn');
  await saisir(tester, 1, 'motdepasse');
}

Finder _bouton(String libelle) => find.widgetWithText(FilledButton, libelle);

void main() {
  group('Connexion', () {
    testWidgets('affiche les éléments de la maquette', (tester) async {
      await lancerAcces(tester, AdaptateurSimule({}));
      expect(find.textContaining('Heureux de'), findsOneWidget);
      expect(find.textContaining('vous revoir !'), findsOneWidget);
      expect(
        find.text(
          'Réservez un mécanicien, un coiffeur ou un pressing dans votre quartier.',
        ),
        findsOneWidget,
      );
      expect(find.text('E-mail ou téléphone'), findsOneWidget);
      expect(find.text('Mot de passe'), findsOneWidget);
      expect(find.text('Mot de passe oublié ?'), findsOneWidget);
      expect(_bouton('Se connecter'), findsOneWidget);
      expect(find.text('Pas encore de compte ?'), findsOneWidget);
      expect(find.text('Créer un compte'), findsOneWidget);
      expect(find.text('Vous êtes professionnel ?'), findsOneWidget);
      expect(find.text('Devenir prestataire Sopi'), findsOneWidget);
    });

    testWidgets('champs vides : erreurs locales, aucune requête', (
      tester,
    ) async {
      final serveur = AdaptateurSimule({});
      await lancerAcces(tester, serveur);
      await toucher(tester, _bouton('Se connecter'));
      expect(
        find.text('Saisissez votre e-mail ou votre numéro de téléphone.'),
        findsOneWidget,
      );
      expect(find.text('Saisissez votre mot de passe.'), findsOneWidget);
      expect(
        serveur.requetes.where((r) => r.path == '/auth/connexion'),
        isEmpty,
      );

      // Taper efface l'erreur du champ.
      await saisir(tester, 0, 'a');
      expect(
        find.text('Saisissez votre e-mail ou votre numéro de téléphone.'),
        findsNothing,
      );
    });

    testWidgets('chargement : indicateur puis réponse', (tester) async {
      final serveur = AdaptateurLent({
        'POST /auth/connexion': const Simulee(401, {'message': 'x'}),
      });
      await lancerAcces(tester, serveur);
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      serveur.liberer();
      await avancer(tester, 200);
      expect(find.byType(CircularProgressIndicator), findsNothing);
    });

    testWidgets('envoie {identifiant, password} tels que saisis', (
      tester,
    ) async {
      final serveur = AdaptateurSimule({
        'POST /auth/connexion': const Simulee(401, {'message': 'x'}),
      });
      await lancerAcces(tester, serveur);
      await saisir(tester, 0, '  aminata@exemple.sn ');
      await saisir(tester, 1, 'motdepasse');
      await toucher(tester, _bouton('Se connecter'));
      final requete = serveur.requetes.firstWhere(
        (r) => r.path == '/auth/connexion',
      );
      expect(requete.data, {
        'identifiant': 'aminata@exemple.sn',
        'password': 'motdepasse',
      });
    });

    testWidgets('401 : message sous le formulaire', (tester) async {
      await lancerAcces(
        tester,
        AdaptateurSimule({
          'POST /auth/connexion': const Simulee(401, {'message': 'Autre'}),
        }),
      );
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));
      expect(
        find.text('Identifiant ou mot de passe incorrect.'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.connexion);
    });

    testWidgets('422 : message sous le champ concerné', (tester) async {
      await lancerAcces(
        tester,
        AdaptateurSimule({
          'POST /auth/connexion': const Simulee(422, {
            'message': 'Données invalides.',
            'errors': {
              'identifiant': ['Identifiant invalide.'],
            },
          }),
        }),
      );
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));
      expect(find.text('Identifiant invalide.'), findsOneWidget);
    });

    testWidgets('429 : bandeau, décompte, bouton bloqué puis libéré', (
      tester,
    ) async {
      await lancerAcces(
        tester,
        AdaptateurSimule({
          'POST /auth/connexion': const Simulee(
            429,
            {'message': 'Trop de tentatives.'},
            {'retry-after': '3'},
          ),
        }),
      );
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));

      expect(
        find.text(
          'Trop de tentatives. Votre accès est bloqué 15 minutes. Réessayez dans 00:03.',
        ),
        findsOneWidget,
      );
      expect(
        tester.widget<FilledButton>(_bouton('Se connecter')).onPressed,
        isNull,
      );
      expect(
        tester
            .widgetList<ChampFormulaire>(find.byType(ChampFormulaire))
            .last
            .erreurSansTexte,
        isTrue,
      );

      await tester.pump(const Duration(seconds: 1));
      expect(find.textContaining('Réessayez dans 00:02.'), findsOneWidget);

      await tester.pump(const Duration(seconds: 2));
      await tester.pump();
      expect(find.textContaining('Réessayez dans'), findsNothing);
      expect(
        tester.widget<FilledButton>(_bouton('Se connecter')).onPressed,
        isNotNull,
      );
    });

    testWidgets('réseau coupé : message de connexion', (tester) async {
      final serveur = AdaptateurSimule({})..reseauCoupe = true;
      await lancerAcces(tester, serveur);
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));
      expect(
        find.text('Pas de connexion. Vérifiez votre réseau.'),
        findsOneWidget,
      );
    });

    testWidgets('client : arrive sur l\'accueil client', (tester) async {
      await lancerAcces(
        tester,
        AdaptateurSimule({
          'POST /auth/connexion': const Simulee(200, {
            'token': 't',
            'user': _utilisateurClient,
          }),
        }),
      );
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));
      await tester.pump();
      await tester.pump();
      expect(cheminCourant(tester), Chemins.accueil);
    });

    testWidgets('prestataire : arrive sur le tableau de bord', (tester) async {
      await lancerAcces(
        tester,
        AdaptateurSimule({
          'POST /auth/connexion': const Simulee(200, {
            'token': 't',
            'user': {'id': '2', 'role': 'prestataire'},
          }),
        }),
      );
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));
      await tester.pump();
      await tester.pump();
      expect(cheminCourant(tester), Chemins.tableau);
    });

    testWidgets('403 : écran « compte restreint »', (tester) async {
      await lancerAcces(
        tester,
        AdaptateurSimule({
          'POST /auth/connexion': const Simulee(403, {
            'message': 'Votre compte est suspendu.',
            'motif': 'Retards répétés',
          }),
        }),
      );
      await _remplir(tester);
      await toucher(tester, _bouton('Se connecter'));
      await tester.pump();
      await tester.pump();
      expect(cheminCourant(tester), Chemins.compteRestreint);
    });

    testWidgets('lien « Créer un compte »', (tester) async {
      await lancerAcces(tester, AdaptateurSimule({}));
      await toucher(tester, find.text('Créer un compte'));
      expect(cheminCourant(tester), Chemins.inscription);
    });

    testWidgets('lien « Mot de passe oublié ? »', (tester) async {
      await lancerAcces(tester, AdaptateurSimule({}));
      await toucher(tester, find.text('Mot de passe oublié ?'));
      expect(cheminCourant(tester), Chemins.motDePasseOublie);
    });

    testWidgets('bouton « Devenir prestataire »', (tester) async {
      await lancerAcces(tester, AdaptateurSimule({}));
      await toucher(tester, find.bySemanticsLabel('Devenir prestataire'));
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
    });

    testWidgets('avec animations : l\'en-tête descend, puis tout est visible', (
      tester,
    ) async {
      await lancerAcces(tester, AdaptateurSimule({}), sansAnimation: false);
      await avancer(tester, 1200);
      expect(find.text('Se connecter'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('petit écran avec clavier : défile sans débordement', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 480);
      tester.view.devicePixelRatio = 1;
      tester.view.viewInsets = const FakeViewPadding(bottom: 200);
      addTearDown(tester.view.reset);
      await lancerAcces(tester, AdaptateurSimule({}));
      expect(tester.takeException(), isNull);
      await saisir(tester, 1, 'abc');
      expect(tester.takeException(), isNull);
    });
  });
}
