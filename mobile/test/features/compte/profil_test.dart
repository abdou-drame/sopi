import 'dart:convert';

import 'package:dio/dio.dart';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/auth/session.dart';
import 'package:sopi/features/compte/changer_mot_de_passe_page.dart';
import 'package:sopi/features/compte/modifier_profil_page.dart';
import 'package:sopi/features/compte/profil_client_page.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

Future<(AdaptateurSimule, ProviderContainer)> _ouvrir(
  WidgetTester tester,
  String chemin, {
  Map<String, Simulee> routes = const {},
  String supportEmail = '',
  List<Uri>? urls,
}) async {
  tester.view.physicalSize = const Size(390, 1500);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final serveur = AdaptateurSimule(
    routesClient({
      'POST /auth/deconnexion': const Simulee(200, {'message': 'ok'}),
      ...routes,
    }),
  );
  final conteneur = await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: chemin,
    supportEmail: supportEmail,
    urlsOuvertes: urls,
    surcharges: [versionAppProvider.overrideWith((ref) async => '1.2.3')],
  );
  await passerTransition(tester);
  return (serveur, conteneur);
}

Iterable<RequestOptions> _appels(
  AdaptateurSimule s,
  String methode,
  String p,
) => s.requetes.where((r) => r.method == methode && r.path == p);

Map<String, Object?> _profil({String prenom = 'Awa', String nom = 'Ndiaye'}) =>
    {
      'id': 'u-1',
      'nom': nom,
      'prenom': prenom,
      'email': 'aminata@exemple.sn',
      'telephone': '+221771234567',
      'role': 'client',
      'statutCompte': 'actif',
    };

void main() {
  setUpAll(initialiserFormats);

  group('profil', () {
    testWidgets('identité, lignes et version', (tester) async {
      await _ouvrir(tester, Chemins.profil);
      expect(find.text('A'), findsOneWidget);
      expect(find.text('Aminata Diop'), findsOneWidget);
      expect(find.textContaining('aminata@exemple.sn'), findsOneWidget);
      for (final l in [
        'Modifier mon profil',
        'Changer le mot de passe',
        'Notifications',
        'Mes signalements',
        'Aide et support',
        'Se déconnecter',
      ]) {
        expect(find.text(l), findsOneWidget);
      }
      expect(find.text("Version de l'application 1.2.3"), findsOneWidget);
    });

    testWidgets('chaque ligne ouvre son écran', (tester) async {
      final cas = {
        'Modifier mon profil': Chemins.profilModifier,
        'Changer le mot de passe': Chemins.motDePasseChanger,
        'Aide et support': Chemins.aide,
      };
      for (final e in cas.entries) {
        await _ouvrir(tester, Chemins.profil);
        await tester.tap(find.text(e.key));
        await passerTransition(tester);
        expect(cheminCourant(tester), e.value);
      }
    });

    testWidgets('déconnexion : confirmation, annulation sans effet', (
      tester,
    ) async {
      final (serveur, _) = await _ouvrir(tester, Chemins.profil);
      await tester.tap(find.text('Se déconnecter'));
      await passerTransition(tester);
      expect(find.text('Se déconnecter ?'), findsOneWidget);
      await tester.tap(find.text('Annuler'));
      await passerTransition(tester);
      expect(_appels(serveur, 'POST', '/auth/deconnexion'), isEmpty);
      expect(cheminCourant(tester), Chemins.profil);
    });

    testWidgets("déconnexion confirmée : retour à l'orientation", (
      tester,
    ) async {
      final (serveur, _) = await _ouvrir(tester, Chemins.profil);
      await tester.tap(find.text('Se déconnecter'));
      await passerTransition(tester);
      await tester.tap(find.text('Se déconnecter').last);
      await passerTransition(tester);
      expect(_appels(serveur, 'POST', '/auth/deconnexion'), hasLength(1));
      expect(cheminCourant(tester), Chemins.orientation);
    });
  });

  group('modifier le profil', () {
    testWidgets('champs préremplis et note sur l e-mail', (tester) async {
      await _ouvrir(tester, Chemins.profilModifier);
      expect(find.text('Modifier\nmon profil', findRichText: true), findsOne);
      expect(tester.widget<TextField>(champ(0)).controller!.text, 'Aminata');
      expect(tester.widget<TextField>(champ(1)).controller!.text, 'Diop');
      expect(
        find.textContaining("L'adresse e-mail ne peut pas être modifiée"),
        findsOneWidget,
      );
    });

    testWidgets('téléphone invalide : message local, aucun appel', (
      tester,
    ) async {
      final (serveur, _) = await _ouvrir(tester, Chemins.profilModifier);
      await saisir(tester, 2, '123');
      await toucher(tester, find.text('Enregistrer'));
      expect(find.text(messageProfilModifie), findsNothing);
      expect(_appels(serveur, 'PUT', '/me'), isEmpty);
      expect(find.textContaining('mobile sénégalais'), findsOneWidget);
    });

    testWidgets('succès : corps envoyé, message, nom mis à jour partout', (
      tester,
    ) async {
      final (serveur, conteneur) = await _ouvrir(
        tester,
        Chemins.profilModifier,
        routes: {'PUT /me': Simulee(200, _profil())},
      );
      await saisir(tester, 0, 'Awa');
      await saisir(tester, 1, 'Ndiaye');
      await saisir(tester, 2, '77 123 45 67');
      await toucher(tester, find.text('Enregistrer'));
      await passerTransition(tester);

      final envoi = _appels(serveur, 'PUT', '/me').single;
      expect(envoi.data, {
        'nom': 'Ndiaye',
        'prenom': 'Awa',
        'telephone': '+221771234567',
      });
      expect(find.text(messageProfilModifie), findsOneWidget);
      final etat = conteneur.read(sessionProvider) as Connecte;
      expect(etat.utilisateur.nomComplet, 'Awa Ndiaye');
    });

    testWidgets('422 : message sous le champ concerné', (tester) async {
      await _ouvrir(
        tester,
        Chemins.profilModifier,
        routes: {
          'PUT /me': const Simulee(422, {
            'message': 'Données invalides.',
            'errors': {
              'telephone': ['Ce numéro est déjà utilisé.'],
            },
          }),
        },
      );
      await toucher(tester, find.text('Enregistrer'));
      await passerTransition(tester);
      expect(find.text('Ce numéro est déjà utilisé.'), findsOneWidget);
      expect(find.text(messageProfilModifie), findsNothing);
    });

    testWidgets('429 : message du serveur affiché', (tester) async {
      await _ouvrir(
        tester,
        Chemins.profilModifier,
        routes: {
          'PUT /me': const Simulee(
            429,
            {'message': 'Trop de requêtes.'},
            {'retry-after': '30'},
          ),
        },
      );
      await toucher(tester, find.text('Enregistrer'));
      await passerTransition(tester);
      expect(find.textContaining('Trop de'), findsWidgets);
      expect(find.text(messageProfilModifie), findsNothing);
    });
  });

  group('changer le mot de passe', () {
    testWidgets('validation locale : aucun appel', (tester) async {
      final (serveur, _) = await _ouvrir(tester, Chemins.motDePasseChanger);
      await saisir(tester, 0, 'ancien-mdp');
      await saisir(tester, 1, 'court');
      await saisir(tester, 2, 'autre');
      await toucher(tester, find.text('Enregistrer'));
      expect(_appels(serveur, 'PUT', '/me/mot-de-passe'), isEmpty);
      expect(find.text(messageMotDePasseModifie), findsNothing);
    });

    testWidgets('succès : corps, message, toujours connecté', (tester) async {
      final (serveur, conteneur) = await _ouvrir(
        tester,
        Chemins.motDePasseChanger,
        routes: {
          'PUT /me/mot-de-passe': const Simulee(200, {
            'message': 'Votre mot de passe a été modifié.',
          }),
        },
      );
      await saisir(tester, 0, 'ancien-mdp');
      await saisir(tester, 1, 'nouveau-mdp-1');
      await saisir(tester, 2, 'nouveau-mdp-1');
      await toucher(tester, find.text('Enregistrer'));
      await passerTransition(tester);

      final envoi = _appels(serveur, 'PUT', '/me/mot-de-passe').single;
      expect(jsonDecode(jsonEncode(envoi.data)), {
        'mot_de_passe_actuel': 'ancien-mdp',
        'nouveau_mot_de_passe': 'nouveau-mdp-1',
        'confirmation': 'nouveau-mdp-1',
      });
      expect(find.text(messageMotDePasseModifie), findsOneWidget);
      expect(conteneur.read(sessionProvider), isA<Connecte>());
      expect(tester.widget<TextField>(champ(0)).controller!.text, isEmpty);
    });

    testWidgets('422 : mot de passe actuel incorrect sous le champ', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        Chemins.motDePasseChanger,
        routes: {
          'PUT /me/mot-de-passe': const Simulee(422, {
            'message': 'Données invalides.',
            'errors': {
              'mot_de_passe_actuel': ['Le mot de passe actuel est incorrect.'],
            },
          }),
        },
      );
      await saisir(tester, 0, 'mauvais');
      await saisir(tester, 1, 'nouveau-mdp-1');
      await saisir(tester, 2, 'nouveau-mdp-1');
      await toucher(tester, find.text('Enregistrer'));
      await passerTransition(tester);
      expect(
        find.text('Le mot de passe actuel est incorrect.'),
        findsOneWidget,
      );
    });
  });

  group('aide', () {
    testWidgets('questions statiques, pas de contact sans support', (
      tester,
    ) async {
      await _ouvrir(tester, Chemins.aide);
      expect(find.text('Comment fonctionne la réservation ?'), findsOneWidget);
      expect(find.textContaining('indiquée avant le paiement'), findsOneWidget);
      expect(find.text('Contacter le support'), findsNothing);
    });

    testWidgets('contact du support : ouvre la messagerie', (tester) async {
      final urls = <Uri>[];
      await _ouvrir(
        tester,
        Chemins.aide,
        supportEmail: 'aide@sopi.test',
        urls: urls,
      );
      await tester.tap(find.text('Contacter le support'));
      await passerTransition(tester);
      expect(urls.single.toString(), 'mailto:aide@sopi.test');
    });
  });
}
