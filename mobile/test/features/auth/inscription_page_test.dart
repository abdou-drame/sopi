import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/widgets/champ_formulaire.dart';
import 'package:sopi/features/auth/validateurs.dart';

import '../../aide_api.dart';
import 'aide_acces.dart';

const _utilisateurClient = {
  'id': '1',
  'role': 'client',
  'nom': 'Diallo',
  'prenom': 'Aminata',
};

Finder _bouton() => find.widgetWithText(FilledButton, 'Créer mon compte');

Future<void> _remplir(
  WidgetTester tester, {
  String telephone = '77 123 45 67',
  String email = '',
  String confirmation = 'motdepasse',
}) async {
  await saisir(tester, 0, 'Aminata');
  await saisir(tester, 1, 'Diallo');
  await saisir(tester, 2, telephone);
  await saisir(tester, 3, email);
  await saisir(tester, 4, 'motdepasse');
  await saisir(tester, 5, confirmation);
}

Future<AdaptateurSimule> _lancer(
  WidgetTester tester, [
  Map<String, Simulee> routes = const {},
  AdaptateurSimule? serveur,
]) async {
  final s = serveur ?? AdaptateurSimule(routes);
  await lancerAcces(tester, s, chemin: Chemins.inscription);
  return s;
}

void main() {
  group('Inscription client', () {
    testWidgets('affiche les éléments de la maquette', (tester) async {
      await _lancer(tester);
      expect(find.text('Étape 1 sur 1'), findsOneWidget);
      expect(find.textContaining('Créer votre'), findsOneWidget);
      expect(find.textContaining('compte client'), findsOneWidget);
      for (final libelle in [
        'Prénom',
        'Nom',
        'Téléphone',
        'E-mail (facultatif)',
        'Mot de passe',
        'Confirmer le mot de passe',
      ]) {
        expect(find.text(libelle), findsOneWidget);
      }
      expect(_bouton(), findsOneWidget);
      expect(find.text('Déjà un compte ?'), findsOneWidget);
      expect(find.text('Se connecter'), findsOneWidget);
    });

    testWidgets('champs vides : erreurs locales, aucune requête', (
      tester,
    ) async {
      final serveur = await _lancer(tester);
      await toucher(tester, _bouton());
      expect(find.text('Le prénom est obligatoire.'), findsOneWidget);
      expect(find.text('Le nom est obligatoire.'), findsOneWidget);
      expect(
        find.text('Le numéro de téléphone est obligatoire.'),
        findsOneWidget,
      );
      expect(find.text('Le mot de passe est obligatoire.'), findsOneWidget);
      expect(find.text('Confirmez votre mot de passe.'), findsOneWidget);
      expect(
        serveur.requetes.where((r) => r.path == '/auth/inscription'),
        isEmpty,
      );
      await saisir(tester, 0, 'A');
      expect(find.text('Le prénom est obligatoire.'), findsNothing);
    });

    testWidgets('téléphone invalide : message de la maquette', (tester) async {
      final serveur = await _lancer(tester);
      await _remplir(tester, telephone: '71 123 45 67');
      await toucher(tester, _bouton());
      expect(find.text(ValidateursAcces.messageTelephone), findsOneWidget);
      expect(serveur.requetes.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('e-mail invalide et confirmation différente', (tester) async {
      await _lancer(tester);
      await _remplir(tester, email: 'pas-un-mail', confirmation: 'autre');
      await toucher(tester, _bouton());
      expect(find.text("L'adresse e-mail n'est pas valide."), findsOneWidget);
      expect(
        find.text('Les deux mots de passe ne sont pas identiques.'),
        findsOneWidget,
      );
    });

    testWidgets('mot de passe trop court', (tester) async {
      await _lancer(tester);
      await saisir(tester, 4, 'court');
      await toucher(tester, _bouton());
      expect(
        find.text('Le mot de passe doit contenir au moins 8 caractères.'),
        findsOneWidget,
      );
    });

    testWidgets('chargement : indicateur puis réponse', (tester) async {
      final serveur = AdaptateurLent({
        'POST /auth/inscription': const Simulee(422, {
          'message': 'Données invalides.',
          'errors': {
            'email': ['Cet e-mail est déjà utilisé.'],
          },
        }),
      });
      await _lancer(tester, const {}, serveur);
      await _remplir(tester, email: 'a@exemple.sn');
      await toucher(tester, _bouton());
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      serveur.liberer();
      await avancer(tester, 200);
      expect(find.byType(CircularProgressIndicator), findsNothing);
    });

    testWidgets('corps envoyé : sans e-mail vide, sans confirmation', (
      tester,
    ) async {
      final serveur = await _lancer(tester, {
        'POST /auth/inscription': const Simulee(422, {'message': 'x'}),
      });
      await _remplir(tester, telephone: '+221 77 123 45 67');
      await toucher(tester, _bouton());
      final requete = serveur.requetes.firstWhere(
        (r) => r.path == '/auth/inscription',
      );
      expect(requete.data, {
        'nom': 'Diallo',
        'prenom': 'Aminata',
        'telephone': '+221771234567',
        'password': 'motdepasse',
      });
    });

    testWidgets('e-mail renseigné : il est envoyé', (tester) async {
      final serveur = await _lancer(tester, {
        'POST /auth/inscription': const Simulee(422, {'message': 'x'}),
      });
      await _remplir(tester, email: ' aminata@exemple.sn ');
      await toucher(tester, _bouton());
      final requete = serveur.requetes.firstWhere(
        (r) => r.path == '/auth/inscription',
      );
      expect((requete.data as Map)['email'], 'aminata@exemple.sn');
    });

    testWidgets('422 : messages sous les champs concernés', (tester) async {
      await _lancer(tester, {
        'POST /auth/inscription': const Simulee(422, {
          'message': 'Données invalides.',
          'errors': {
            'telephone': ['Ce numéro est déjà utilisé.'],
            'email': ['Cet e-mail est déjà utilisé.'],
          },
        }),
      });
      await _remplir(tester, email: 'a@exemple.sn');
      await toucher(tester, _bouton());
      expect(find.text('Ce numéro est déjà utilisé.'), findsOneWidget);
      expect(find.text('Cet e-mail est déjà utilisé.'), findsOneWidget);
    });

    testWidgets('429 : bandeau, décompte, bouton bloqué puis libéré', (
      tester,
    ) async {
      await _lancer(tester, {
        'POST /auth/inscription': const Simulee(
          429,
          {'message': 'Trop de tentatives.'},
          {'retry-after': '3'},
        ),
      });
      await _remplir(tester);
      await toucher(tester, _bouton());
      expect(
        find.text('Trop de tentatives. Réessayez dans 00:03.'),
        findsOneWidget,
      );
      expect(tester.widget<FilledButton>(_bouton()).onPressed, isNull);
      await tester.pump(const Duration(seconds: 1));
      expect(find.textContaining('Réessayez dans 00:02.'), findsOneWidget);
      await tester.pump(const Duration(seconds: 2));
      await tester.pump();
      expect(find.textContaining('Réessayez dans'), findsNothing);
      expect(tester.widget<FilledButton>(_bouton()).onPressed, isNotNull);
    });

    testWidgets('réseau coupé : message de connexion', (tester) async {
      final serveur = AdaptateurSimule({})..reseauCoupe = true;
      await _lancer(tester, const {}, serveur);
      await _remplir(tester);
      await toucher(tester, _bouton());
      expect(
        find.text('Pas de connexion. Vérifiez votre réseau.'),
        findsOneWidget,
      );
    });

    testWidgets('201 : session ouverte, arrivée sur l\'accueil client', (
      tester,
    ) async {
      await _lancer(tester, {
        'POST /auth/inscription': const Simulee(201, {
          'token': 't',
          'user': _utilisateurClient,
        }),
      });
      await _remplir(tester);
      await toucher(tester, _bouton());
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.accueil);
    });

    testWidgets('« Se connecter » ramène à la connexion', (tester) async {
      await _lancer(tester);
      await toucher(tester, find.text('Se connecter'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.connexion);
    });

    testWidgets('champs sensibles masqués par défaut', (tester) async {
      await _lancer(tester);
      final champs = tester.widgetList<ChampFormulaire>(
        find.byType(ChampFormulaire),
      );
      expect(champs.where((c) => c.motDePasse), hasLength(2));
    });

    testWidgets('petit écran avec clavier : défile sans débordement', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 480);
      tester.view.devicePixelRatio = 1;
      tester.view.viewInsets = const FakeViewPadding(bottom: 200);
      addTearDown(tester.view.reset);
      await _lancer(tester);
      expect(tester.takeException(), isNull);
      await saisir(tester, 5, 'abc');
      expect(tester.takeException(), isNull);
    });
  });
}
